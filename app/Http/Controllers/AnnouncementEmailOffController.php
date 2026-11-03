<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\Student;
use Illuminate\Http\Request;

/**
 * The "Turn off announcement emails" link at the foot of every announcement
 * email, signed so it needs no sign-in.
 *
 * Opening the link only asks; the button (or Gmail's own Unsubscribe, which
 * POSTs here through the List-Unsubscribe header) turns it off. A GET that
 * changed anything would be clicked by every mail scanner that opens links.
 */
class AnnouncementEmailOffController extends Controller
{
    public function __invoke(Request $request, string $who, int $id)
    {
        $person = match ($who) {
            'student' => Student::find($id),
            'expert' => CapstoneExpert::find($id),
            default => null,
        };

        abort_unless($person, 404);

        if ($request->isMethod('post')) {
            $person->notification_prefs = [...($person->notification_prefs ?? []), 'email.announcement' => false];
            $person->save();
        }

        return view('announcement-emails-off', ['done' => $request->isMethod('post')]);
    }
}
