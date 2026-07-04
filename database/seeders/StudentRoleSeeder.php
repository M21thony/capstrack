<?php

namespace Database\Seeders;

use App\Models\StudentRole;
use Illuminate\Database\Seeder;

class StudentRoleSeeder extends Seeder
{
    public function run(): void
    {
        StudentRole::create(['role_name' => 'Leader']);
        StudentRole::create(['role_name' => 'Member']);
    }
}
