<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A CaPSA/TSA, three of their students and one outside their sections, for
 * trying who an announcement reaches - and for tests/e2e/announcements.spec.js,
 * which signs in as these accounts by address.
 *
 * The CaPSA/TSA covers BSIT-3A and BSIT-3B. Ana (3A) leads the team "PW Bug
 * Busters", Ben is her classmate in 3A, Cara is in 3B and Dan in 3C, which
 * the CaPSA/TSA does not cover.
 *
 *   php artisan db:seed --class=AnnouncementsDemoSeeder
 *
 * Running it again starts the accounts over; to take them out:
 *
 *   php artisan tinker --execute "Database\Seeders\AnnouncementsDemoSeeder::clean();"
 */
class AnnouncementsDemoSeeder extends Seeder
{
    public const CAPSA = 'pw-ann-capsa@cspc.edu.ph';

    public const TEAM = 'PW Bug Busters';

    /** @var array<string, array{0: string, 1: string}> email => [first name, section letter] */
    public const STUDENTS = [
        'pw-ann-ana@my.cspc.edu.ph' => ['Ana', 'A'],
        'pw-ann-ben@my.cspc.edu.ph' => ['Ben', 'A'],
        'pw-ann-cara@my.cspc.edu.ph' => ['Cara', 'B'],
        'pw-ann-dan@my.cspc.edu.ph' => ['Dan', 'C'],
    ];

    public function run(): void
    {
        self::clean();

        $capsa = CapstoneExpert::create([
            'email' => self::CAPSA,
            'first_name' => 'Pat',
            'last_name' => 'Wright',
            'approved_status' => true,
            'is_admin' => false,
            'sections' => ['BSIT-3A', 'BSIT-3B'],
        ]);
        $capsa->forceFill(['tour_completed_at' => now()])->save();

        $roleId = DB::table('roles')->where('role_name', CapstoneExpert::CAPSA_ROLE)->value('role_id')
            ?? DB::table('roles')->insertGetId(['role_name' => CapstoneExpert::CAPSA_ROLE, 'description' => 'Capstone Subject Adviser']);
        DB::table('expert_roles')->insertOrIgnore(['expert_id' => $capsa->expert_id, 'role_id' => $roleId]);

        // Tours done, so the portal tour does not cover the pages under test.
        $students = collect(self::STUDENTS)->map(fn (array $who, string $email) => tap(Student::create([
            'email' => $email,
            'f_name' => $who[0],
            'l_name' => 'Wright',
            'course' => 'BSIT',
            'year_section' => '3rd · '.$who[1],
        ]), fn (Student $student) => $student->forceFill(['tutorial_completed_at' => now()])->save()));

        $ana = $students->get('pw-ann-ana@my.cspc.edu.ph');
        $team = Team::create([
            'team_name' => self::TEAM,
            'leader_id' => $ana->student_id,
            'team_status' => 'Active',
            'is_confirmed' => true,
        ]);
        StudentSchoolYear::create(['student_id' => $ana->student_id, 'team_id' => $team->team_id]);
    }

    public static function clean(): void
    {
        $studentIds = Student::whereIn('email', array_keys(self::STUDENTS))->pluck('student_id');
        $capsaId = CapstoneExpert::withTrashed()->where('email', self::CAPSA)->value('expert_id');

        Notification::whereIn('student_id', $studentIds)->delete();
        StudentSchoolYear::whereIn('student_id', $studentIds)->delete();
        Team::where('team_name', self::TEAM)->delete();
        Student::whereIn('student_id', $studentIds)->delete();

        if ($capsaId) {
            Announcement::where('admin_id', $capsaId)->delete();
            DB::table('expert_roles')->where('expert_id', $capsaId)->delete();
            CapstoneExpert::withTrashed()->where('expert_id', $capsaId)->forceDelete();
        }
    }
}
