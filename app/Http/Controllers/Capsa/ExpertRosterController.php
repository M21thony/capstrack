<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\ExpertExpertise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// CAPSA uploads one .xlsx of the faculty who will work on capstone, and the
// file says what each of them does. Every row creates an ALREADY-APPROVED
// account (CAPSA vetted them by including them) matching their real CSPC
// email - the existing AuthController login flow just starts working for
// that email once it is on the list.
//
// The file carries the role, because CAPSA is the one who decides it and
// asked to set it where she sets everything else about a person. A faculty
// member never picks their own role (see auth/request-account.blade.php);
// Manage Roles on Expert Management remains for changing one afterwards.
//
// Two shapes are read. The template writes one sheet with Name, Email
// Address, Role and Expertise. The older shape - three sheets named Adviser,
// Consultant and Grammarian - is still read, with the sheet name standing in
// for the Role column, so a file CAPSA already keeps does not stop working.
//
// Expertise is optional. Left blank, whatever the expert has recorded on
// their own profile is left alone; filled in, it replaces it.
//
// The upload itself is a modal on the Expert Management page, not its own
// page - this controller only handles what that modal submits to.
class ExpertRosterController extends Controller
{
    /** The older three-sheet shape, where the sheet name is the role. */
    private const SHEETS = ['Adviser', 'Consultant', 'Grammarian'];

    /** The headings the template writes, in the order it writes them. */
    private const TEMPLATE_HEADINGS = [
        'Name', 'Email Address', 'Role', 'Expertise', 'Accepted Programs', 'Team Slots',
    ];

    /**
     * The file, read into the people it names. Nothing is written.
     *
     * Both the preview and the upload go through here, so what the preview
     * shows and what the upload does cannot drift apart - they are the same
     * reading of the same file.
     *
     * @return array{people: array<string, array>, errors: string[], skipped: int}
     */
    private function read(string $path): array
    {
        $spreadsheet = IOFactory::load($path);

        $errors = [];
        $skipped = 0;

        // Gathered across the whole file before anything is written, because
        // one person can be listed on more than one sheet and their roles are
        // then the union of those rows - applied once, not fought over.
        $people = [];

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $sheetRole = collect(self::SHEETS)->first(fn ($s) => strcasecmp($s, $sheetName) === 0);

            $rows = $spreadsheet->getSheetByName($sheetName)->toArray();
            $header = array_shift($rows) ?? [];

            // Match columns by their header text rather than a fixed
            // position - a sheet with "Name" before "Email Address" (or any
            // other column order) used to get read backwards, silently
            // treating a name as an email and failing every row.
            $columnIndex = [];
            foreach ($header as $i => $label) {
                $columnIndex[strtolower(trim((string) $label))] = $i;
            }

            $emailCol = $columnIndex['email address'] ?? $columnIndex['email'] ?? null;
            $nameCol = $columnIndex['name'] ?? null;
            $roleCol = $columnIndex['role'] ?? $columnIndex['roles'] ?? null;
            $expertiseCol = $columnIndex['expertise'] ?? $columnIndex['areas of expertise'] ?? null;
            // Both optional, and both something the expert can set for
            // themselves - a CaPSA filling them in is a head start, not the
            // only way they get there.
            $programsCol = $columnIndex['accepted programs'] ?? $columnIndex['programs'] ?? null;
            $slotsCol = $columnIndex['team slots'] ?? $columnIndex['slots'] ?? $columnIndex['max teams'] ?? null;

            if ($emailCol === null || $nameCol === null) {
                // A sheet named after a role was meant to be read, so say it
                // was not. Any other sheet is somebody's notes tab.
                if ($sheetRole !== null) {
                    $errors[] = "{$sheetRole} sheet needs a 'Name' and an 'Email Address' column header - it was skipped entirely.";
                }

                continue;
            }

            foreach ($rows as $i => $row) {
                $rowNum = $i + 2; // 1-based + header
                $where = "{$sheetName} row {$rowNum}";

                $email = trim((string) ($row[$emailCol] ?? ''));
                $name = trim((string) ($row[$nameCol] ?? ''));

                if ($email === '' && $name === '') {
                    continue; // a blank line at the end of the sheet
                }

                if ($email === '' || $name === '') {
                    $skipped++;
                    $errors[] = "{$where}: missing name or email - skipped.";

                    continue;
                }

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    $errors[] = "{$where}: '{$email}' isn't a valid email - skipped.";

                    continue;
                }

                // This tool onboards Adviser/Consultant/Grammarian experts,
                // not Admin/CaPSA staff - changing one of those accounts
                // (even just its name) is Admin's call, not a side effect of
                // a bulk file upload that happens to share their email
                // (which a real roster can do by accident).
                if ($this->isStaffAccount($email)) {
                    $skipped++;
                    $errors[] = "{$where}: \"{$email}\" belongs to an Admin/CaPSA account - roster uploads don't touch those.";

                    continue;
                }

                // The same email appearing twice with two different names is
                // almost always a mistake - two people sharing one address,
                // or a copied row whose email never got changed - not a
                // rename, so the first name seen wins.
                if (isset($people[$email]) && $people[$email]['name'] !== $name) {
                    $skipped++;
                    $errors[] = "{$where}: \"{$email}\" was already used for \"{$people[$email]['name']}\" earlier in this file - this row's name (\"{$name}\") was ignored. Give this person their own email address if they're someone else.";

                    continue;
                }

                $people[$email] ??= [
                    'name' => $name, 'roles' => [], 'expertise' => null,
                    'programs' => null, 'slots' => null,
                ];

                [$roles, $unknown] = $this->readRoles(
                    $roleCol !== null ? (string) ($row[$roleCol] ?? '') : '',
                    $sheetRole
                );

                if ($unknown !== []) {
                    $errors[] = "{$where}: '".implode("', '", $unknown)."' is not a role - use "
                        .implode(', ', CapstoneExpert::STUDENT_FACING_ROLES).'. The account was still created.';
                }

                $people[$email]['roles'] = array_values(array_unique([...$people[$email]['roles'], ...$roles]));

                if ($expertiseCol !== null) {
                    $expertise = $this->readExpertise((string) ($row[$expertiseCol] ?? ''));

                    // Blank means "not saying", not "clear it" - the expert
                    // fills this in from their own profile too, and a list
                    // that leaves the column empty must not wipe that.
                    if ($expertise !== []) {
                        $people[$email]['expertise'] = array_values(array_unique(
                            [...($people[$email]['expertise'] ?? []), ...$expertise]
                        ));
                    }
                }

                // Same rule for both of these: an empty cell says nothing and
                // leaves whatever the expert has already set alone.
                if ($programsCol !== null) {
                    $programs = $this->readPrograms((string) ($row[$programsCol] ?? ''), $where, $errors);

                    if ($programs !== []) {
                        $people[$email]['programs'] = $programs;
                    }
                }

                if ($slotsCol !== null) {
                    $slots = trim((string) ($row[$slotsCol] ?? ''));

                    if ($slots !== '') {
                        if (! ctype_digit($slots) || (int) $slots < 1) {
                            $errors[] = "{$where}: 'Team Slots' has to be a whole number of teams. The account was still created.";
                        } else {
                            $people[$email]['slots'] = (int) $slots;
                        }
                    }
                }
            }
        }

        return ['people' => $people, 'errors' => $errors, 'skipped' => $skipped];
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $read = $this->read($request->file('file')->getRealPath());

        $errors = $read['errors'];

        $roleIds = DB::table('roles')
            ->whereIn('role_name', CapstoneExpert::STUDENT_FACING_ROLES)
            ->pluck('role_id', 'role_name');

        $inserted = 0;
        $updated = 0;

        foreach ($read['people'] as $email => $person) {
            $nameParts = preg_split('/\s+/', $person['name'], 2);

            $existing = DB::table('capstone_experts')->where('email', $email)->first(['expert_id']);

            if ($existing) {
                DB::table('capstone_experts')->where('email', $email)->update([
                    'first_name' => $nameParts[0],
                    'last_name' => $nameParts[1] ?? $nameParts[0],
                    'approved_status' => true,
                ]);

                $expertId = (int) $existing->expert_id;
                $updated++;
            } else {
                $expertId = (int) DB::table('capstone_experts')->insertGetId([
                    'email' => $email,
                    'first_name' => $nameParts[0],
                    'last_name' => $nameParts[1] ?? $nameParts[0],
                    'approved_status' => true,
                    'reject_attempts' => 0,
                    'created_at' => now(),
                ]);

                $inserted++;
            }

            $ids = [];

            foreach ($person['roles'] as $role) {
                // A role with no row is a broken install, not a bad file -
                // worth saying so rather than dying on it, and worth saying
                // rather than quietly onboarding somebody with no role.
                if ($roleIds->has($role)) {
                    $ids[] = $roleIds[$role];
                } else {
                    $errors[] = "The '{$role}' role is not set up on this system, so it could not be given to {$email}.";
                }
            }

            // Scoped to the student-facing roles, so an upload can neither
            // grant nor revoke CaPSA - that stays its own deliberate action.
            if ($ids !== []) {
                CapstoneExpert::syncRoles($expertId, $ids, $roleIds->values());
            }

            if ($person['expertise'] !== null) {
                ExpertExpertise::where('expert_id', $expertId)->delete();

                foreach ($person['expertise'] as $field) {
                    ExpertExpertise::create(['expert_id' => $expertId, 'expertise_field' => $field]);
                }
            }

            // program_slots is the same "1 - BSIT, 5 - BSIS" free text the
            // request form writes, so one shape is read everywhere. Without
            // a per-programme count, the slot number is spread across the
            // programmes named; without programmes, only the overall cap is
            // set and the expert says which programmes themselves.
            if ($person['programs'] !== null || $person['slots'] !== null) {
                $account = CapstoneExpert::withTrashed()->find($expertId);

                if ($account) {
                    $each = $person['slots'] ?? 1;

                    if ($person['programs'] !== null) {
                        $account->program_slots = collect($person['programs'])
                            ->map(fn ($program) => $each.' - '.$program)
                            ->implode(', ');
                    }

                    if ($person['slots'] !== null) {
                        $account->max_teams = $person['programs'] !== null
                            ? $each * count($person['programs'])
                            : $person['slots'];
                    }

                    $account->save();
                }
            }
        }

        return response()->json([
            'success' => true,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $read['skipped'],
            'errors' => $errors,
        ]);
    }

    /**
     * What the file would do, without doing any of it.
     *
     * The list is how faculty get accounts and what decides their roles, so
     * an upload approves people and grants them work in one go, and nothing
     * undoes it. This runs the same reading and the same comparison store()
     * runs and reports the outcome per person instead of writing it.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $read = $this->read($request->file('file')->getRealPath());

        $rows = [];
        $create = 0;
        $update = 0;
        $unchanged = 0;

        foreach ($read['people'] as $email => $person) {
            $account = DB::table('capstone_experts')->where('email', $email)->first(['expert_id', 'first_name', 'last_name', 'approved_status']);

            if (! $account) {
                $action = 'create';
                $changes = [];
                $create++;
            } elseif (($changes = $this->changesFor($person, $account)) !== []) {
                $action = 'update';
                $update++;
            } else {
                $action = 'unchanged';
                $unchanged++;
            }

            $rows[] = [
                'name' => $person['name'],
                'email' => $email,
                'roles' => $person['roles'],
                'expertise' => $person['expertise'] ?? [],
                // Shown in the preview so a CaPSA sees what the sheet is
                // about to set before it sets it.
                'programs' => $person['programs'] ?? [],
                'slots' => $person['slots'],
                'action' => $action,
                // Named, not counted: "roles" says what an upload is about to
                // change about somebody who already has an account.
                'changes' => $changes,
            ];
        }

        return response()->json([
            'success' => true,
            'rows' => $rows,
            'create' => $create,
            'update' => $update,
            'unchanged' => $unchanged,
            'skipped' => $read['skipped'],
            'errors' => $read['errors'],
        ]);
    }

    /**
     * What this row would change about an account that already exists.
     *
     * Reported by the preview and written by the upload, from this one place,
     * so the preview cannot promise something the upload does not do.
     *
     * @return string[]
     */
    private function changesFor(array $person, object $account): array
    {
        $changes = [];

        $nameParts = preg_split('/\s+/', $person['name'], 2);

        if ($account->first_name !== $nameParts[0] || $account->last_name !== ($nameParts[1] ?? $nameParts[0])) {
            $changes[] = 'name';
        }

        if (! $account->approved_status) {
            $changes[] = 'approval';
        }

        // Scoped to the student-facing roles, because CaPSA is the only role
        // an upload never touches and comparing it in would report a change
        // that could not happen.
        $held = DB::table('expert_roles')
            ->join('roles', 'roles.role_id', '=', 'expert_roles.role_id')
            ->where('expert_roles.expert_id', $account->expert_id)
            ->whereIn('roles.role_name', CapstoneExpert::STUDENT_FACING_ROLES)
            ->pluck('roles.role_name')
            ->sort()
            ->values()
            ->all();

        $wanted = collect($person['roles'])->sort()->values()->all();

        if ($person['roles'] !== [] && $held !== $wanted) {
            $changes[] = 'roles';
        }

        // Blank expertise means "not saying", so it is not a change.
        if ($person['expertise'] !== null) {
            $recorded = ExpertExpertise::where('expert_id', $account->expert_id)
                ->pluck('expertise_field')
                ->sort()
                ->values()
                ->all();

            if ($recorded !== collect($person['expertise'])->sort()->values()->all()) {
                $changes[] = 'expertise';
            }
        }

        return $changes;
    }

    /**
     * The blank list, with the columns this reads and one filled-in row.
     *
     * CAPSA asked for the template to carry everything an account needs,
     * role included - so a list can be filled in once and be finished,
     * rather than uploaded and then walked through Manage Roles per person.
     */
    public function template(): BinaryFileResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Experts');

        $sheet->fromArray([self::TEMPLATE_HEADINGS], null, 'A1');
        $sheet->fromArray([
            ['Juan Dela Cruz', 'juan.delacruz@gmail.com', 'Adviser', 'Research Methodology, Data Analytics', 'BSIT, BSIS', 2],
            // The last two are optional: leave them blank and the expert
            // sets their own programmes and capacity.
            ['Maria Santos', 'maria.santos@gmail.com', 'Consultant, Grammarian', '', '', ''],
        ], null, 'A2');

        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'experts').'.xlsx';
        (new Xlsx($book))->save($path);

        return response()->download($path, 'Expert List Template.xlsx')->deleteFileAfterSend();
    }

    /**
     * The programme codes named in one cell.
     *
     * "BSIT, BSIS" or "BSIT/BSIS" - anything that is not one of the four is
     * reported and dropped rather than written, since a programme nobody
     * offers would quietly stop every student matching against it.
     *
     * @param  array<int, string>  $errors
     * @return array<int, string>
     */
    private function readPrograms(string $cell, string $where, array &$errors): array
    {
        $named = collect(preg_split('/[,;\/|]+/', $cell))
            ->map(fn ($part) => strtoupper(trim((string) $part)))
            ->filter()
            ->unique();

        $unknown = $named->reject(fn ($program) => in_array($program, CapstoneExpert::PROGRAMS, true));

        if ($unknown->isNotEmpty()) {
            $errors[] = "{$where}: '".$unknown->implode("', '")."' is not a program - use "
                .implode(', ', CapstoneExpert::PROGRAMS).'. The account was still created.';
        }

        return $named->filter(fn ($program) => in_array($program, CapstoneExpert::PROGRAMS, true))
            ->values()
            ->all();
    }

    /**
     * The roles named on a row, and anything on it that is not a role.
     *
     * One person can hold more than one, so "Consultant, Grammarian" in a
     * single cell is read as both. A sheet named after a role stands in when
     * there is no Role column, which is what makes the older three-sheet
     * file still work.
     *
     * @return array{0: string[], 1: string[]} known roles, unrecognised words
     */
    private function readRoles(string $cell, ?string $sheetRole): array
    {
        $written = array_filter(array_map('trim', preg_split('/[,\/;]+/', $cell)));

        if ($written === []) {
            return [$sheetRole !== null ? [strtolower($sheetRole)] : [], []];
        }

        $roles = [];
        $unknown = [];

        foreach ($written as $word) {
            $role = strtolower($word);

            if (in_array($role, CapstoneExpert::STUDENT_FACING_ROLES, true)) {
                $roles[] = $role;
            } else {
                $unknown[] = $word;
            }
        }

        return [$roles, $unknown];
    }

    /** The areas of expertise named on a row, if it names any. */
    private function readExpertise(string $cell): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $cell))));
    }

    /**
     * Whether this address belongs to an Admin or a CaPSA.
     *
     * Being a CaPSA means holding the capstone_adviser role (see
     * CapstoneExpert::CAPSA_ROLE) - read from expert_roles, not from the
     * retired is_capsa column.
     */
    private function isStaffAccount(string $email): bool
    {
        $account = DB::table('capstone_experts')->where('email', $email)->first(['expert_id', 'is_admin']);

        if (! $account) {
            return false;
        }

        return (bool) $account->is_admin || DB::table('expert_roles')
            ->join('roles', 'roles.role_id', '=', 'expert_roles.role_id')
            ->where('expert_roles.expert_id', $account->expert_id)
            ->where('roles.role_name', CapstoneExpert::CAPSA_ROLE)
            ->exists();
    }
}
