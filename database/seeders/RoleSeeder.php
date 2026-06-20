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
        Role::updateOrCreate(['role_name' => 'capstone_adviser'], ['description' => 'Serves as CaPSA/Capstone Project Subject Adviser or Thesis Subject Adviser.']);
    }
}
