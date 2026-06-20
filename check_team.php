<?php

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== student_s_y columns ===\n";
foreach (DB::select('DESCRIBE student_s_y') as $c) {
    echo $c->Field.' '.$c->Type."\n";
}

echo "\n=== Student 3 check ===\n";
$student = Student::find(3);
echo 'Found student: '.($student ? $student->student_id.' | '.$student->email : 'NULL')."\n";

echo "\n=== student_s_y rows for student_id=3 ===\n";
$rows = StudentSchoolYear::where('student_id', 3)->get();
echo 'Count: '.$rows->count()."\n";
foreach ($rows as $r) {
    echo '  s_y_id='.$r->s_y_id.' team_id='.($r->team_id ?? 'NULL').' program='.$r->program."\n";
}

echo "\n=== Teams ===\n";
foreach (Team::all() as $t) {
    echo '  team_id='.$t->team_id.' name='.$t->team_name."\n";
}
