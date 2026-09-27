<?php

namespace App\Support;

/**
 * The face each kind of activity wears, in every portal's feed and on the
 * Activity Log: an icon, a colour that says what sort of thing happened, and
 * a name a person reads instead of the stored code.
 *
 * Tones: green done or approved, red left, removed or declined, amber changed
 * or waiting, blue a request or a message, purple the team itself, gold a
 * post, a document or a defense, gray anything else.
 */
final class ActivityIcons
{
    /** Inner SVG, for a 24x24 viewBox, stroked. */
    private const PATHS = [
        'team' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>',
        'user-minus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="22" y1="11" x2="16" y2="11"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'send' => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/>',
        'crown' => '<path d="M2 18h20l-2-11-5 4-3-6-3 6-5-4-2 11z"/>',
        'swap' => '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="m18.5 2.5 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'refresh' => '<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
        'sliders' => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'door' => '<path d="M13 4h3a2 2 0 0 1 2 2v14"/><path d="M2 20h3"/><path d="M13 20h9"/><path d="M10 12v.01"/><path d="M13 4.56v16.87a.5.5 0 0 1-.63.49L5 20V5.56a1 1 0 0 1 .77-.97l6-1.5a1 1 0 0 1 1.23.97z"/>',
        'badge' => '<circle cx="12" cy="8" r="6"/><path d="M15.48 12.89 17 22l-5-3-5 3 1.52-9.11"/>',
        'file-up' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-6M9 15l3-3 3 3"/>',
        'file-check' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 13"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'user-clock' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><circle cx="19" cy="15" r="3.5"/><polyline points="19 13.5 19 15 20 16"/>',
        'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
    ];

    /** type => [icon, tone, label] */
    private const TYPES = [
        'team_created' => ['team', 'purple', 'Created a team'],
        'team_joined' => ['user-plus', 'purple', 'Joined a team'],
        'member_joined' => ['user-plus', 'purple', 'Member joined'],
        'team_left' => ['log-out', 'red', 'Left a team'],
        'leave_force' => ['log-out', 'red', 'Left a team'],
        'member_removed' => ['user-minus', 'red', 'Member removed'],
        'team_confirmed' => ['check', 'green', 'Team confirmed'],
        'team_verified' => ['shield', 'green', 'Team verified'],
        'team_updated' => ['edit', 'amber', 'Team updated'],
        'team_status_changed' => ['refresh', 'amber', 'Team status changed'],
        'title_corrected' => ['edit', 'amber', 'Title corrected'],
        'member_count_changed' => ['sliders', 'amber', 'Team size changed'],
        'team_auto_closed' => ['lock', 'red', 'Team closed'],
        'team_locked' => ['lock', 'amber', 'Members locked'],
        'request_sent' => ['send', 'blue', 'Asked to join'],
        'request_accepted' => ['check', 'green', 'Request accepted'],
        'request_declined' => ['x', 'red', 'Request declined'],
        'invitation_sent' => ['mail', 'blue', 'Sent an invitation'],
        'invitation_accepted' => ['check', 'green', 'Invitation accepted'],
        'invitation_declined' => ['x', 'red', 'Invitation declined'],
        'leadership_transfer_requested' => ['swap', 'amber', 'Asked to pass leadership'],
        'leadership_transferred' => ['crown', 'gold', 'Leadership passed on'],
        'leadership_transfer_declined' => ['x', 'red', 'Leadership handover declined'],
        'leave_requested' => ['door', 'amber', 'Asked to leave'],
        'leave_request_sent' => ['door', 'amber', 'Asked the CaPSA to leave'],
        'leave_approved' => ['check', 'green', 'Leave approved'],
        'leave_declined' => ['x', 'red', 'Leave declined'],
        'post_created' => ['edit', 'gold', 'Posted to recruit'],
        'post_updated' => ['refresh', 'amber', 'Updated a post'],
        'post_deleted' => ['trash', 'red', 'Removed a post'],
        'role_chosen' => ['badge', 'purple', 'Chose a role'],
        'expert_removed' => ['user-minus', 'red', 'Expert removed'],
        // Not written to activity_log: the dashboards' own events.
        'expert_requested' => ['user-clock', 'amber', 'Expert account requested'],
        'expert_approved' => ['shield', 'green', 'Expert approved'],
        'expert_application' => ['send', 'blue', 'Applied for an expert'],
        'document_submitted' => ['file-up', 'gold', 'Document submitted'],
        'document_reviewed' => ['file-check', 'green', 'Document reviewed'],
        'announcement' => ['bell', 'gold', 'Announcement'],
        'defense_scheduled' => ['calendar', 'gold', 'Defense scheduled'],
    ];

    /** @return array{icon: string, tone: string, label: string, svg: string} */
    public static function for(?string $type): array
    {
        [$icon, $tone, $label] = self::TYPES[$type] ?? ['activity', 'gray', self::humanize((string) $type)];

        return [
            'icon' => $icon,
            'tone' => $tone,
            'label' => $label,
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true">'.self::PATHS[$icon].'</svg>',
        ];
    }

    /** "team_status_changed" reads as "Team status changed". */
    public static function humanize(string $type): string
    {
        return $type === '' ? 'Activity' : ucfirst(str_replace('_', ' ', $type));
    }
}
