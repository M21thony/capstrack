<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

// CAPSA uploads one .xlsx with 3 sheets - Adviser, Consultant, Grammarian -
// matching the real roster format. This ONLY onboards/approves accounts by
// name + email; it does not assign a role. Which sheet a person is listed
// under is just how CaPSA organizes the file - it has no effect on the
// account created. Role assignment is a separate, deliberate action CaPSA
// takes afterward via "Manage Roles" on Expert Management, for every
// account this creates (previously the sheet name auto-set the role, but
// that silently granted a role with no explicit CaPSA decision behind it).
// Every row creates an ALREADY-APPROVED expert account (CAPSA vetted them
// by including them), matching their real CSPC email - the existing
// AuthController login flow just starts working for that email once it's
// on the domain allow-list. The upload itself is a modal on
// capsa/experts.blade.php, not its own page - this controller only
// handles the store() the modal submits to.
//
// Each sheet only needs a "Name" and "Email Address" column - CaPSA isn't
// the one who knows an expert's accepted programs, areas of expertise, or
// team capacity, so the roster doesn't collect those either. The expert
// fills those in themselves afterward, from their own profile (see
// instructor/partials/expert-profile.blade.php and
// Instructor\ProfileController).
class ExpertRosterController extends Controller
{
    private const SHEETS = ['Adviser', 'Consultant', 'Grammarian'];

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $sheetNames = $spreadsheet->getSheetNames();

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        // The same email can legitimately appear more than once in this
        // file (listed under more than one sheet) - that's fine as long as
        // the name matches every time. If a later row shows a DIFFERENT
        // name for an email already seen, that's almost always a mistake
        // (two different people accidentally sharing one email, or a
        // copy-pasted row that never got its email updated), not an
        // intentional rename - so the first name seen wins and the
        // conflicting row is ignored outright.
        $namesSeenByEmail = [];

        foreach (self::SHEETS as $sheetLabel) {
            $matchedSheetName = collect($sheetNames)->first(
                fn ($name) => strcasecmp($name, $sheetLabel) === 0
            );

            if (! $matchedSheetName) {
                continue;
            }

            $rows = $spreadsheet->getSheetByName($matchedSheetName)->toArray();
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

            if ($emailCol === null || $nameCol === null) {
                $errors[] = "{$sheetLabel} sheet needs a 'Name' and an 'Email Address' column header - it was skipped entirely.";

                continue;
            }

            foreach ($rows as $i => $row) {
                $rowNum = $i + 2; // 1-based + header

                $email = trim((string) ($row[$emailCol] ?? ''));
                $name = trim((string) ($row[$nameCol] ?? ''));

                if ($email === '' || $name === '') {
                    $skipped++;
                    $errors[] = "{$sheetLabel} row {$rowNum}: missing name or email - skipped.";

                    continue;
                }

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    $errors[] = "{$sheetLabel} row {$rowNum}: '{$email}' isn't a valid email - skipped.";

                    continue;
                }

                // This tool onboards Adviser/Consultant/Grammarian
                // experts, not Admin/CaPSA staff - changing one of those
                // accounts (even just its name) is Admin's call, not a
                // side effect of a bulk file upload that happens to share
                // their email (which a real roster can do by accident).
                // Being a CaPSA means holding the capstone_adviser role (see
                // CapstoneExpert::CAPSA_ROLE) - read it from expert_roles, not
                // from the retired is_capsa column.
                $existingAccount = DB::table('capstone_experts')->where('email', $email)->first(['expert_id', 'is_admin']);
                $existingIsCapsa = $existingAccount && DB::table('expert_roles')
                    ->join('roles', 'roles.role_id', '=', 'expert_roles.role_id')
                    ->where('expert_roles.expert_id', $existingAccount->expert_id)
                    ->where('roles.role_name', CapstoneExpert::CAPSA_ROLE)
                    ->exists();

                if ($existingAccount && ($existingAccount->is_admin || $existingIsCapsa)) {
                    $skipped++;
                    $errors[] = "{$sheetLabel} row {$rowNum}: \"{$email}\" belongs to an Admin/CaPSA account - roster uploads don't touch those.";

                    continue;
                }

                if (isset($namesSeenByEmail[$email]) && $namesSeenByEmail[$email] !== $name) {
                    $skipped++;
                    $errors[] = "{$sheetLabel} row {$rowNum}: \"{$email}\" was already used for \"{$namesSeenByEmail[$email]}\" earlier in this file - this row's name (\"{$name}\") was ignored. Give this person their own email address if they're someone else.";

                    continue;
                }
                $namesSeenByEmail[$email] = $name;

                $nameParts = preg_split('/\s+/', $name, 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? $nameParts[0];

                $wasExisting = DB::table('capstone_experts')->where('email', $email)->exists();

                DB::transaction(function () use ($email, $firstName, $lastName, $wasExisting) {
                    if ($wasExisting) {
                        DB::table('capstone_experts')->where('email', $email)->update([
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'approved_status' => true,
                        ]);
                    } else {
                        DB::table('capstone_experts')->insertGetId([
                            'email' => $email,
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'approved_status' => true,
                            'reject_attempts' => 0,
                            'created_at' => now(),
                        ]);
                    }
                });

                $wasExisting ? $updated++ : $inserted++;
            }
        }

        return response()->json([
            'success' => true,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }
}
