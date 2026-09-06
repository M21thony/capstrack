<?php

namespace Database\Seeders;

use App\Models\StudentRole;
use Illuminate\Database\Seeder;

class StudentRoleSeeder extends Seeder
{
    public function run(): void
    {
        StudentRole::create(['role_name' => 'Project Head/Network Engineer']);
        StudentRole::create(['role_name' => 'UI Designer']);
        StudentRole::create(['role_name' => 'Document Writer/QA Tester']);
    }
}
