<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;

abstract class Controller
{
    protected function logActivity(int $studentId, string $type, string $description, ?array $metadata = null): void
    {
        ActivityLog::create([
            'student_id' => $studentId,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
