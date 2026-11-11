<?php

namespace App\Console\Commands;

use App\Models\MemberLeaveRequest;
use Illuminate\Console\Command;

/**
 * Declines the requests to leave a team, or to remove a member, that a CaPSA/TSA
 * left undecided for MemberLeaveRequest::DECIDE_WITHIN_DAYS, and tells the
 * team they ran out of time.
 *
 * The Team page and the CaPSA/TSA's Leave Requests tab do the same before they
 * show anything, so this is what reaches a team nobody has opened since.
 */
class ExpireLeaveRequests extends Command
{
    protected $signature = 'leave-requests:expire';

    protected $description = 'Decline leave and removal requests the CaPSA/TSA did not decide within the allowed days';

    public function handle(): int
    {
        $declined = MemberLeaveRequest::expireOverdue();

        $this->info("Declined {$declined} request(s) left without a decision.");

        return self::SUCCESS;
    }
}
