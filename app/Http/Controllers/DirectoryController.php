<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who is already in CapsTrack, for the boxes where somebody is added one at
 * a time.
 *
 * Typing a full address and hoping is how the wrong person gets added. This
 * lets the box behave like Gmail's: type a few letters, see who matches, pick
 * one. It searches this system's own records - a directory of every account at
 * the college would need the school's Google admin to hand it over, which is
 * an institutional decision rather than a coding one.
 *
 * Not offered to the roster upload: a spreadsheet already carries the name,
 * number, program and section of every row, so there is nothing left to find.
 */
class DirectoryController extends Controller
{
    /** Enough to find someone, tedious to enumerate a college with. */
    private const MIN_QUERY = 3;

    private const LIMIT = 8;

    public function people(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < self::MIN_QUERY) {
            return response()->json(['people' => []]);
        }

        // LIKE treats these as wildcards, so a search for "a_b" would quietly
        // match far more than it looks like it should.
        $like = '%'.addcslashes($q, '%_\\').'%';

        return response()->json([
            'people' => $request->query('kind') === 'student'
                ? $this->students($like)
                : $this->experts($like),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function experts(string $like): array
    {
        return CapstoneExpert::with('roles')
            ->where(fn ($w) => $w
                ->where('email', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like))
            ->orderBy('last_name')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (CapstoneExpert $expert) {
                $roles = $expert->roles->map(fn ($r) => Role::labelFor($r->role_name))->all();

                return [
                    'email' => $expert->email,
                    'name' => trim($expert->first_name.' '.$expert->last_name),
                    // What they already are, so adding someone who is already
                    // an adviser is visibly a no-op rather than a surprise.
                    'detail' => $roles
                        ? implode(', ', $roles)
                        : ($expert->approved_status ? 'No role yet' : 'Awaiting approval'),
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function students(string $like): array
    {
        return Student::where(fn ($w) => $w
            ->where('email', 'like', $like)
            ->orWhere('f_name', 'like', $like)
            ->orWhere('l_name', 'like', $like)
            ->orWhere('student_number', 'like', $like))
            ->orderBy('l_name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Student $student) => [
                'email' => $student->email,
                'name' => trim($student->f_name.' '.$student->l_name),
                // Where they are now, so pulling someone out of a colleague's
                // section is a visible decision rather than an accident.
                'detail' => trim(implode(' · ', array_filter([
                    $student->student_number,
                    $student->course,
                    $student->year_section,
                ]))) ?: 'Not in a section',
            ])
            ->all();
    }
}
