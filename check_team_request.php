<?php

use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simulate authenticating as student 3
$user = Student::find(3);
$guard = auth()->guard('web');
auth()->login($user);

$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$request = Request::create('/team', 'POST', [
    'team_name' => 'API Test Team',
    'team_status' => 'Active',
], [], [], [
    'HTTP_AUTHORIZATION' => '',
    'HTTP_ACCEPT' => 'application/json',
]);

try {
    $response = $kernel->handle($request);
    echo 'Status: '.$response->getStatusCode()."\n";
    echo 'Body: '.$response->getContent()."\n";

    echo "\n=== After creation ===\n";
    $rows = StudentSchoolYear::where('student_id', 3)->get();
    foreach ($rows as $r) {
        echo '  team_id='.($r->team_id ?? 'NULL')."\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}
