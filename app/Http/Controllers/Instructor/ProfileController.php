<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function index()
    {
        return view('instructor.profile', [
            'currentPage' => 'profile',
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
            'expertise' => ['present', 'array'],
            'expertise.*' => ['string', 'max:100'],
            'highest_degree' => ['nullable', 'in:'.implode(',', array_keys(CapstoneExpert::DEGREES))],
        ]);

        $validator->after(function ($v) use ($request) {
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

        $expertise = collect($validated['expertise'])
            ->map(fn ($e) => trim($e))
            ->filter()
            ->unique()
            ->values();

        // Only when the panel sent it, so an older page that does not know
        // about degrees cannot wipe one out by saving.
        $degree = $request->has('highest_degree') ? ['highest_degree' => $validated['highest_degree'] ?? null] : [];

        DB::transaction(function () use ($expert, $accepted, $raw, $expertise, $degree) {
            $expert->update([
                'program_slots' => $raw,
                'max_teams' => array_sum($accepted),
            ] + $degree);

            // Replace rather than merge - the panel sends the full list it
            // is showing, so anything missing from it was removed there.
            DB::table('expert_expertise')->where('expert_id', $expert->expert_id)->delete();
            foreach ($expertise as $field) {
                DB::table('expert_expertise')->insert([
                    'expert_id' => $expert->expert_id,
                    'expertise_field' => $field,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'program_slots' => $accepted,
            'max_teams' => $expert->fresh()->max_teams,
            'expertise' => $expertise,
        ]);
    }
}
