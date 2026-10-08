<?php

namespace App\Support;

/**
 * Every kind of notification CapsTrack can send, in one place.
 *
 * The settings card, the bell's icons and the Notifier all read this, so a
 * new kind of notification is one entry here rather than three edits that
 * can drift apart.
 *
 * 'email' says whether an email goes out for this event, so the settings card
 * shows an Email switch for it. Most are sent by the bell notification itself
 * (see NotificationMailer): the bell row is written, and the same thing lands
 * in the person's Google mail. The ones in OWN_EMAIL have a dedicated email
 * sent by their own code, so the bell must not send a second copy.
 *
 * 'web' is false for events that only ever reach the bell. A desktop popup is
 * only sent by Notifier, and these are written straight to the table.
 *
 * Milestone deadlines and new checklist items stay bell-only on purpose:
 * milestone_due was built without an email to keep it from being spam.
 */
final class NotificationTypes
{
    /** Events whose email is a dedicated Mailable, sent by its own code. */
    public const OWN_EMAIL = [
        'consultation_booked',
        'consultation_cancelled',
        'consultation_reminder',
        'review_reminder',
        'defense_scheduled',
    ];

    public const EXPERT = [
        'expert_application' => [
            'label' => 'A team applies to me, or a CaPSA assigns me to or removes me from a team',
            'email' => true,
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
            'email' => true,
        ],
        'paper_to_check' => [
            'label' => 'A team hands in a signed paper for me to check',
            'email' => true,
        ],
        'review_reminder' => [
            'label' => 'Documents are still waiting on my review',
            'email' => true,
        ],
        'leave_request_received' => [
            'label' => 'A student asks to leave a team, or a leader asks to remove a member',
            'email' => true,
        ],
        'announcement' => [
            'label' => 'A CaPSA posts an important or urgent announcement for experts',
            'email' => true,
        ],
    ];

    public const STUDENT = [
        'invitation_received' => [
            'label' => 'A team invites me to join',
            'email' => true,
            'web' => false,
        ],
        'request_received' => [
            'label' => 'A student asks to join my team',
            'email' => true,
            'web' => false,
        ],
        'invitation_accepted' => [
            'label' => 'My invitation or join request is accepted',
            'email' => true,
            'web' => false,
        ],
        'team_verified' => [
            'label' => 'The administrator verifies our team',
            'email' => true,
            'web' => false,
        ],
        'leadership_transfer_request' => [
            'label' => 'I am asked to become team leader',
            'email' => true,
            'web' => false,
        ],
        'leadership_transferred' => [
            'label' => 'Team leadership changes hands',
            'email' => true,
            'web' => false,
        ],
        'leave_request_sent' => [
            'label' => 'A member asks to leave, or I am asked to be removed',
            'email' => true,
            'web' => false,
        ],
        'leave_request_decided' => [
            'label' => 'A leave or removal request is approved or declined',
            'email' => true,
            'web' => false,
        ],
        'member_removed' => [
            'label' => 'I am removed from a team',
            'email' => true,
            'web' => false,
        ],
        'team_locked' => [
            'label' => 'Our team is locked after Title Defense',
            'email' => true,
            'web' => false,
        ],
        'expert_rejected' => [
            'label' => 'An expert turns down our request',
            'email' => true,
            'web' => false,
        ],
        'expert_removal' => [
            'label' => 'An expert is removed from our team, or a removal request is decided',
            'email' => true,
            'web' => false,
        ],
        'title_confirmation' => [
            'label' => 'Our title is confirmed or needs changes',
            'email' => true,
            'web' => false,
        ],
        'defense_scheduled' => [
            'label' => 'Our defense is scheduled or moved',
            'email' => true,
            'web' => false,
        ],
        'defense_outcome' => [
            'label' => 'The panel decides our defense',
            'email' => true,
            'web' => false,
        ],
        'consultation_cancellation' => [
            'label' => 'An expert answers our consultation cancellation',
            'email' => true,
            'web' => false,
        ],
        'team_mention' => [
            'label' => 'Our team is mentioned in an announcement',
            'email' => true,
            'web' => false,
        ],
        'announcement' => [
            'label' => 'A CaPSA posts an important or urgent announcement',
            'email' => true,
            'web' => false,
        ],
        'review_finished' => [
            'label' => 'An expert finishes reviewing our document',
            'email' => true,
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
        'evaluation_due' => [
            'label' => 'We passed a defense and our experts are waiting to be evaluated',
            'email' => true,
        ],
    ];

    /**
     * Bell types that share one switch, so the settings card stays a readable
     * length. The type written to the bell is the key, the switch the value.
     */
    private const SWITCH_FOR = [
        'request_accepted' => 'invitation_accepted',
        'leave_request_approved' => 'leave_request_decided',
        'leave_request_declined' => 'leave_request_decided',
        'expert_removed' => 'expert_removal',
        'expert_removal_approved' => 'expert_removal',
        'expert_removal_declined' => 'expert_removal',
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

    /** The switch that governs a bell type, which may be shared with others. */
    public static function switchFor(string $type): string
    {
        return self::SWITCH_FOR[$type] ?? $type;
    }

    /**
     * Should the bell itself send an email for this type? Only when an email
     * exists for it and no dedicated Mailable already covers it. Announcements
     * are mailed by the announcement code, which knows the priority.
     */
    public static function mailedByBell(string $audience, string $type): bool
    {
        $switch = self::switchFor($type);

        return self::hasEmail($audience, $switch)
            && ! in_array($switch, self::OWN_EMAIL, true)
            && $switch !== 'announcement';
    }
}
