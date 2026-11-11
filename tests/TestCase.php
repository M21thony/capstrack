<?php

namespace Tests;

use App\Models\CapstoneExpert;
use App\Models\Term;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Term::activeId() memoises, and the memo is static - so it would carry
     * from one test into the next even though RefreshDatabase has rebuilt
     * the database underneath it.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Term::forgetActive();
    }

    /**
     * Make an expert a CaPSA/TSA.
     *
     * Being a CaPSA/TSA is holding the capstone_adviser role - there is no
     * is_capsa column to set any more (see CapstoneExpert::CAPSA_ROLE), so
     * fixtures grant the role the same way the app does.
     */
    protected function makeCapsa(CapstoneExpert $expert): CapstoneExpert
    {
        $roleId = DB::table('roles')->where('role_name', CapstoneExpert::CAPSA_ROLE)->value('role_id');

        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'role_name' => CapstoneExpert::CAPSA_ROLE,
                'description' => 'Capstone Subject Adviser',
            ]);
        }

        DB::table('expert_roles')->insertOrIgnore([
            'expert_id' => $expert->expert_id,
            'role_id' => $roleId,
        ]);

        return $expert->fresh();
    }
}
