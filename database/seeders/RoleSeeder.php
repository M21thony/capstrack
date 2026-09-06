<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::updateOrCreate(['role_name' => 'adviser'], ['description' => 'Provides academic guidance and supervision for capstone projects.']);
        Role::updateOrCreate(['role_name' => 'consultant'], ['description' => 'Provides subject-matter expertise and technical advice.']);
        Role::updateOrCreate(['role_name' => 'grammarian'], ['description' => 'Reviews and corrects documentation and grammar.']);
        // Was referenced in several places (ExpertController, RecipientResolver,
        // admin/profile.blade.php) but never actually seeded, so no expert
        // could ever hold it and "Subject Adviser" recipient resolution never
        // worked. Seeded properly now that role assignment is a real,
        // deliberate CAPSA action (AdminApprovalController::approve()) rather
        // than a checkbox nobody's write path actually saved.
        Role::updateOrCreate(['role_name' => 'capstone_adviser'], ['description' => 'Serves as Capstone Project Subject Adviser (CaPSA) or Thesis Subject Adviser for a program.']);
    }
}
