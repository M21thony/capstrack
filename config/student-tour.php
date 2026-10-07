<?php

/*
|--------------------------------------------------------------------------
| Student portal guided tour
|--------------------------------------------------------------------------
|
| The walkthrough a new account is shown once: the screen dims, one part of
| the portal is outlined, and a short note with an arrow says
| what it is. In the order a team actually meets these things - form a team,
| get experts, submit a title, and so on.
|
| 'target' is what gets outlined. A name like 'student.team' is a sidebar
| link, marked with data-tour-target in partials/sidebar; several names
| outline them together, so they must sit next to each other there. Any
| other string is a CSS selector. A step with no target is a card in the
| middle of the screen - the welcome and the send-off.
|
| Keep 'label' to a few words: it is drawn large, in Poppins, next to
| the arrow. 'body' is one plain sentence under it, read by someone on
| their first day, often on a phone.
|
*/

return [
    'steps' => [
        [
            'key' => 'welcome',
            'label' => 'Welcome to CapsTrack, :name!',
            'body' => 'This is where your team runs its capstone. Let us show you around - it takes less than a minute.',
            'target' => [],
        ],
        // The team is step 1: nothing else on the portal works without one.
        [
            'key' => 'team',
            'label' => 'Start here: form your team',
            'body' => 'Create a team and invite your classmates, or join a team looking for members.',
            'target' => ['student.team'],
        ],
        [
            'key' => 'experts',
            'label' => 'Choose and book your experts',
            'body' => 'Ask an adviser, a consultant and a grammarian. Once one accepts, book consultations with them here too.',
            'target' => ['student.experts'],
        ],
        [
            'key' => 'title',
            'label' => 'Submit your title',
            'body' => 'Each title is checked against past capstones, so you know early if it is too similar.',
            'target' => ['student.duplication'],
        ],
        [
            'key' => 'documents',
            'label' => 'Send chapters, read feedback',
            'body' => 'Send your documents here, and open any of them to read your experts\' comments.',
            'target' => ['student.documents'],
        ],
        [
            'key' => 'progress',
            'label' => 'Track your progress',
            'body' => 'See which milestones are done. Receipts for defense fees are under Team Management, in Financial Records.',
            'target' => ['student.progress'],
        ],
        [
            'key' => 'reference',
            'label' => 'Templates and past work',
            'body' => 'Download guides from your CaPSA and browse past capstones for ideas.',
            'target' => ['student.resources', 'student.archive'],
        ],
        [
            'key' => 'profile',
            'label' => 'This is you',
            'body' => 'Tap here to see your details, your team and your role.',
            'target' => ['#profile-trigger'],
        ],
        [
            'key' => 'notifications',
            'label' => 'Updates land here',
            'body' => 'When an expert answers or something needs you, it shows up here first.',
            'target' => ['#notif-btn'],
        ],
        [
            'key' => 'done',
            'label' => 'You are all set!',
            'body' => 'That is everything. Good luck with your capstone!',
            'target' => [],
        ],
    ],
];
