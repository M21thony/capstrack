<?php

namespace App\Support;

/**
 * Every kind of notification CapsTrack can send, in one place.
 *
 * The settings card, the bell's icons and the Notifier all read this, so a
 * new kind of notification is one entry here rather than three edits that
 * can drift apart.
 *
 * 'email' says whether an email already goes out for this event. It is false
 * for most of them: before this feature, an expert heard about an application
 * or a submitted document only by opening the page and looking. Those four
 * that are true are existing emails, and they keep sending exactly as they
 * did - the switch only lets one person opt out of their own copy.
 */
final class NotificationTypes
{
    public const EXPERT = [
        'expert_application' => [
            'label' => 'A team applies to me',
            'email' => false,
        ],
        'consultation_booked' => [
            'label' => 'A team books a consultation',
            'email' => true,
        ],
        'consultation_cancelled' => [
            'label' => 'A team cancels a consultation',
            'email' => true,
        ],
        'consultation_reminder' => [
            'label' => 'A consultation is happening today',
            'email' => true,
        ],
        'document_submitted' => [
            'label' => 'A team sends a document for my review',
            'email' => false,
        ],
        'paper_to_check' => [
            'label' => 'A team hands in a signed paper for me to check',
            'email' => false,
        ],
        'review_reminder' => [
            'label' => 'Documents are still waiting on my review',
            'email' => true,
        ],
    ];

    public const STUDENT = [
        'review_finished' => [
            'label' => 'An expert finishes reviewing our document',
            'email' => false,
        ],
        'milestone_due' => [
            'label' => 'A milestone deadline is coming up',
            'email' => false,
        ],
        'checklist_item_added' => [
            'label' => 'Our CaPSA adds something new to our checklist',
            'email' => false,
        ],
        'consultation_reminder' => [
            'label' => 'Our consultation is happening today',
            'email' => true,
        ],
    ];

    public static function for(string $audience): array
    {
        return $audience === 'expert' ? self::EXPERT : self::STUDENT;
    }

    /** Whether an email exists for this event at all, whatever the person set. */
    public static function hasEmail(string $audience, string $type): bool
    {
        return (bool) (self::for($audience)[$type]['email'] ?? false);
    }
}
