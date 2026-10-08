<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\ExpertEvaluation;
use App\Models\ExpertExpertise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * The expert's profile page: what they may change on the left, and on
     * the right how students see them while they choose an expert.
     */
    public function index(): View
    {
        $expert = auth('expert')->user();

        // One research area list per role they hold, and one for panels.
        $heldRoles = $expert->roles()->pluck('role_name')->all();
        $sides = collect(ExpertExpertise::SIDES)
            ->filter(fn (string $label, string $side) => $side === 'panel' || in_array($side, $heldRoles, true));

        // What they last saved per program. A flat number with no split
        // ("ALL") ticks nothing, so they choose the programs themselves.
        $programSlots = [];
        foreach ($expert->parsedCapacity() as [$program, $max]) {
            if ($program !== 'ALL') {
                $programSlots[$program] = (int) $max;
            }
        }

        $teamsHandled = $expert->teamAssignments()->where('status', 'approved')->count();

        $rating = ExpertEvaluation::where('expert_id', $expert->expert_id)
            ->selectRaw('AVG(rating) as average, COUNT(*) as total')
            ->first();

        return view('instructor.profile', [
            'currentPage' => 'profile',
            'expert' => $expert,
            'researchSides' => $sides->all(),
            'researchAreas' => $sides->map(fn (string $label, string $side) => $expert->expertiseFor($side)->all())->all(),
            'programSlots' => $programSlots,
            'teamsHandled' => $teamsHandled,
            'slotSuggestion' => $expert->slotSuggestion($teamsHandled),
            'ratingAverage' => (int) ($rating?->total ?? 0) > 0 ? round((float) $rating->average, 1) : null,
            'ratingCount' => (int) ($rating?->total ?? 0),
        ]);
    }

    /**
     * The only two things an expert may change about their own account:
     * which programs they accept teams from (and how many of each), and
     * their areas of expertise. Everything else - name, email, roles, the
     * program they belong to - stays under CaPSA/Admin control.
     */
    public function update(Request $request): JsonResponse
    {
        // This is a web route, not under api/*, so $request->validate()'s
        // failure path would try to redirect back instead of returning
        // JSON (see bootstrap/app.php's shouldRenderJsonWhen()) - the JS
        // here expects JSON either way, so validate manually.
        $validator = Validator::make($request->all(), [
            'program_slots' => ['required', 'array'],
            'program_slots.*' => ['integer', 'min:0', 'max:20'],
            // One list per side - adviser, consultant, grammarian, panel -
            // or, from an older page, one list for all of them.
            'expertise_by_side' => ['sometimes', 'array'],
            'expertise_by_side.*' => ['array'],
            'expertise_by_side.*.*' => ['nullable', 'string', 'max:100'],
            'expertise' => ['sometimes', 'array'],
            'expertise.*' => ['string', 'max:100'],
            'highest_degree' => ['nullable', 'in:'.implode(',', array_keys(CapstoneExpert::DEGREES))],
            'degree' => ['nullable', 'in:'.implode(',', CapstoneExpert::degreeTitles())],
        ]);

        $validator->after(function ($v) use ($request) {
            // Sent empty is a choice (none); not sent at all is a broken page.
            if (! $request->exists('expertise_by_side') && ! $request->exists('expertise')) {
                $v->errors()->add('expertise', 'Send your research areas with the profile.');

                return;
            }

            foreach (array_keys((array) $request->input('expertise_by_side', [])) as $side) {
                if (! array_key_exists($side, ExpertExpertise::SIDES)) {
                    $v->errors()->add('expertise_by_side', 'Research areas are kept for adviser, consultant, grammarian and panel only.');

                    return;
                }
            }

            $slots = $request->input('program_slots', []);

            foreach (array_keys($slots) as $program) {
                if (! in_array($program, CapstoneExpert::PROGRAMS, true)) {
                    $v->errors()->add('program_slots', "\"{$program}\" isn't a program you can accept teams from.");

                    return;
                }
            }

            // An expert with zero slots everywhere can never be assigned, so
            // it's almost certainly a mis-save rather than an intent - reject
            // it here rather than silently making them unassignable.
            if (array_sum($slots) < 1) {
                $v->errors()->add('program_slots', 'Accept at least one team in at least one program.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();
        $expert = auth('expert')->user();

        // Keep only programs they actually take teams from, in the canonical
        // PROGRAMS order so the saved text is stable between saves.
        $accepted = [];
        foreach (CapstoneExpert::PROGRAMS as $program) {
            $count = (int) ($validated['program_slots'][$program] ?? 0);
            if ($count > 0) {
                $accepted[$program] = $count;
            }
        }

        // program_slots is what CapstoneExpert::parsedCapacity() reads for
        // the per-program Capacity column, and it already understands this
        // "<n> - <PROGRAM>" shape (it's the format the roster upload's own
        // free-text cells used). max_teams stays the overall total so the
        // existing full/slots-left checks keep working unchanged.
        $raw = implode(', ', array_map(
            fn ($program, $count) => $count.' - '.$program,
            array_keys($accepted),
            $accepted
        ));

        $clean = fn (array $fields) => collect($fields)->map(fn ($e) => trim($e))->filter()->unique(fn ($e) => mb_strtolower($e))->values();

        // side => fields. A null side is one list that stands for every side.
        $bySide = $request->exists('expertise_by_side')
            ? collect($validated['expertise_by_side'] ?? [])->map(fn ($fields) => $clean((array) $fields))
            : collect(['' => $clean($validated['expertise'] ?? [])]);
        $expertise = $bySide->flatten()->unique(fn ($e) => mb_strtolower($e))->values();

        // Only when the panel sent it, so an older page that does not know
        // about degrees cannot wipe one out by saving.
        $degree = $request->has('degree')
            ? CapstoneExpert::degreeFromTitle($validated['degree'] ?? null)
            : ($request->has('highest_degree') ? ['highest_degree' => $validated['highest_degree'] ?? null] : []);

        DB::transaction(function () use ($expert, $accepted, $raw, $bySide, $degree) {
            $expert->update([
                'program_slots' => $raw,
                'max_teams' => array_sum($accepted),
            ] + $degree);

            // Replace rather than merge - the panel sends the full list it
            // is showing, so anything missing from it was removed there.
            DB::table('expert_expertise')->where('expert_id', $expert->expert_id)->delete();
            foreach ($bySide as $side => $fields) {
                foreach ($fields as $field) {
                    DB::table('expert_expertise')->insert([
                        'expert_id' => $expert->expert_id,
                        'expertise_field' => $field,
                        'side' => $side === '' ? null : $side,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'program_slots' => $accepted,
            'max_teams' => $expert->fresh()->max_teams,
            'expertise' => $expertise,
            'expertise_by_side' => $bySide->except('')->map->all()->all(),
        ]);
    }
}
