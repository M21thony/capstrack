<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminExpertsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSchema();
    }

    protected function ensureSchema(): void
    {
        if (! Schema::hasTable('team')) {
            Schema::create('team', function (Blueprint $table): void {
                $table->id('team_id');
                $table->string('team_name');
                $table->string('project_title')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('capstone_experts')) {
            Schema::create('capstone_experts', function (Blueprint $table): void {
                $table->id('expert_id');
                $table->string('email')->unique();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('suffix')->nullable();
                $table->string('credentials')->nullable();
                $table->string('profile_picture')->nullable();
                $table->boolean('is_admin')->default(false);
                $table->boolean('approved_status')->default(false);
                $table->unsignedInteger('max_teams')->nullable();
                $table->string('department')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->unsignedInteger('reject_attempts')->default(0);
                $table->timestamp('reject_cooldown_until')->nullable();
                $table->string('google_token')->nullable();
                $table->timestamp('google_token_expires_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id('role_id');
                $table->string('role_name');
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('expert_roles')) {
            Schema::create('expert_roles', function (Blueprint $table): void {
                $table->unsignedBigInteger('expert_id');
                $table->unsignedBigInteger('role_id');
            });
        }

        if (! Schema::hasTable('expert_expertise')) {
            Schema::create('expert_expertise', function (Blueprint $table): void {
                $table->id('expertise_id');
                $table->unsignedBigInteger('expert_id');
                $table->string('expertise_field');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('team_expert_assignments')) {
            Schema::create('team_expert_assignments', function (Blueprint $table): void {
                $table->id('assignment_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('expert_id');
                $table->unsignedBigInteger('role_id')->nullable();
                $table->string('status')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->string('proposal_file')->nullable();
                $table->text('message')->nullable();
                $table->timestamps();
            });
        }

        DB::table('roles')->insert([
            ['role_name' => 'adviser', 'description' => 'Adviser', 'created_at' => now(), 'updated_at' => now()],
            ['role_name' => 'consultant', 'description' => 'Consultant', 'created_at' => now(), 'updated_at' => now()],
            ['role_name' => 'grammarian', 'description' => 'Grammarian', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_admin_experts_page_renders_expected_roles_and_actions(): void
    {
        $admin = CapstoneExpert::create([
            'email' => 'admin.experts@test.com',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $expert = CapstoneExpert::create([
            'email' => 'expert.one@test.com',
            'first_name' => 'Mara',
            'last_name' => 'Santos',
            'approved_status' => true,
        ]);

        $adviserRoleId = DB::table('roles')->where('role_name', 'adviser')->value('role_id');
        $consultantRoleId = DB::table('roles')->where('role_name', 'consultant')->value('role_id');

        DB::table('expert_roles')->insert([
            ['expert_id' => $expert->expert_id, 'role_id' => $adviserRoleId],
            ['expert_id' => $expert->expert_id, 'role_id' => $consultantRoleId],
        ]);

        $this->actingAs($admin, 'expert');

        $response = $this->get(route('admin.experts'));

        $response->assertOk();
        $response->assertSee('Role(s)', false);
        $response->assertSee('Action', false);
        $response->assertSee('Admin/Subject Adviser', false);
        $response->assertSee('System-wide access', false);
        $response->assertSee('Remove', false);
        $response->assertSee('Adviser', false);
        $response->assertSee('Consultant', false);
        $response->assertSee('Grammarian', false);
        $response->assertDontSee('Transfer Rights', false);
    }
}
