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

    protected function countMeaningfulWords(string $text): int
    {
        $stopWords = [
            'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'by',
            'with', 'from', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has',
            'had', 'do', 'does', 'did', 'will', 'would', 'can', 'could', 'shall', 'should',
            'may', 'might', 'must', 'about', 'above', 'after', 'again', 'all', 'also', 'any',
            'as', 'at', 'before', 'between', 'both', 'each', 'few', 'more', 'most', 'other',
            'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'that',
            'then', 'there', 'these', 'they', 'this', 'those', 'through', 'too', 'very', 'etc',
            'up', 'down', 'out', 'off', 'over', 'under', 'here', 'what', 'when', 'where',
            'why', 'how', 'which', 'who', 'whom', 'whose',
        ];

        $words = preg_split('/\s+/', trim($text));
        $meaningful = array_filter($words, function ($w) use ($stopWords) {
            $cleaned = preg_replace('/[^a-zA-Z0-9\']/', '', $w);

            return strlen($cleaned) > 0 && ! in_array(strtolower($cleaned), $stopWords);
        });

        return count($meaningful);
    }
}
