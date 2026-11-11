<?php

/*
|--------------------------------------------------------------------------
| CaPSA/TSA, Admin and Expert guided tours
|--------------------------------------------------------------------------
|
| The same walkthrough students get (config/student-tour.php), one for each
| of the other portals: the screen dims, one part of the portal is outlined,
| and a short note with an arrow says what it is. Shown once to an account
| that has not seen it. There is no button to replay it.
|
| 'target' is what gets outlined. A route name is that page's link in the
| sidebar; any other string is a CSS selector. A step whose target is not on
| the page - a link this account does not have - is skipped. A step with no
| target is a card in the middle of the screen: the welcome and the send-off.
|
| Keep 'label' to a few words (it is drawn large, next to the arrow) and
| 'body' to one plain sentence.
|
*/

return [
    'capsa' => [
        ['key' => 'welcome', 'label' => 'Welcome, :name!', 'target' => [],
            'body' => 'This is where you run your sections\' capstones - **teams, defenses, documents and fees**. Here is a quick look around.'],
        ['key' => 'dashboard', 'label' => 'Your overview', 'target' => ['capsa.dashboard'],
            'body' => 'Students, experts, teams and **what is waiting on you**, at a glance.'],
        ['key' => 'approval', 'label' => 'Approve expert accounts', 'target' => ['capsa.approval'],
            'body' => 'Faculty who sign up wait here until you **approve them** as advisers, consultants or grammarians.'],
        ['key' => 'sections', 'label' => 'Your student lists', 'target' => ['capsa.sections'],
            'body' => 'Upload each section\'s **class list** so every student lands in the **right section**.'],
        ['key' => 'teams', 'label' => 'Teams, milestones and requests', 'target' => ['capsa.team_management'],
            'body' => '**Verify teams**, follow their **checklist**, and decide on **leave requests** - all from **one page**.'],
        ['key' => 'defenses', 'label' => 'Panels and defense dates', 'target' => ['capsa.defenses'],
            'body' => 'Give each team its **panel** and **make it final**, then set **dates, times and venues** - rooms and panelists are **never double-booked**, across every section.'],
        ['key' => 'rubrics', 'label' => 'Grading rubrics', 'target' => ['capsa.rubric_management'],
            'body' => 'The **rubric** each panelist scores a defense with, **one for every stage**.'],
        ['key' => 'financial', 'label' => 'Check receipts', 'target' => ['capsa.financial'],
            'body' => '**Verify the fees** teams upload **before they can be scheduled**.'],
        ['key' => 'archive', 'label' => 'The capstone archive', 'target' => ['capsa.archive'],
            'body' => '**Finished capstones**, and the **manuscripts** teams hand in for you to publish.'],
        ['key' => 'announcements', 'label' => 'Tell your sections', 'target' => ['capsa.announcements'],
            'body' => 'Post **reminders and deadlines** that show on your **students\' dashboards**.'],
        ['key' => 'bell', 'label' => 'Your notifications', 'target' => ['#notif-btn'],
            'body' => 'Requests and updates that **need you** arrive here.'],
        ['key' => 'done', 'label' => 'You are all set', 'target' => [],
            'body' => 'That is everything. **Welcome to CapsTrack!**'],
    ],

    'admin' => [
        ['key' => 'welcome', 'label' => 'Welcome, :name!', 'target' => [],
            'body' => 'This is where you look after CapsTrack itself - **who runs it, who is in it, and how it is set up**.'],
        ['key' => 'dashboard', 'label' => 'Your overview', 'target' => ['admin.dashboard'],
            'body' => 'Students, experts, teams and the archive, **at a glance**.'],
        ['key' => 'experts', 'label' => 'Name the CaPSA/TSAs', 'target' => ['admin.experts'],
            'body' => 'Add the faculty who **run each section\'s capstones**, and **choose their sections**.'],
        ['key' => 'students', 'label' => 'Student accounts', 'target' => ['admin.students'],
            'body' => '**Every student** in the system - **their CaPSA/TSA adds and edits them**.'],
        ['key' => 'analytics', 'label' => 'How the capstones are going', 'target' => ['admin.analytics'],
            'body' => '**Pass rates and progress** across programs and school years.'],
        ['key' => 'activity', 'label' => 'What happened, and when', 'target' => ['admin.activitylog'],
            'body' => 'A **record** of what students and teams have done.'],
        ['key' => 'settings', 'label' => 'System settings', 'target' => ['admin.settings'],
            'body' => 'The **current school year** and the **sections** students can belong to.'],
        ['key' => 'bell', 'label' => 'Your notifications', 'target' => ['#notif-btn'],
            'body' => 'Anything that **needs you** arrives here.'],
        ['key' => 'done', 'label' => 'You are all set', 'target' => [],
            'body' => 'That is everything. **Welcome to CapsTrack!**'],
    ],

    'expert' => [
        ['key' => 'welcome', 'label' => 'Welcome, :name!', 'target' => [],
            'body' => 'This is where you **advise and review** capstone teams. Here is a quick look around.'],
        ['key' => 'profile', 'label' => 'This is you', 'target' => ['#ep-trigger'],
            'body' => 'Your profile: your **expertise**, your **team limit**, and what you get **notified** about.'],
        ['key' => 'dashboard', 'label' => 'Your overview', 'target' => ['instructor.dashboard'],
            'body' => 'Applications, documents and consultations **waiting on you**, at a glance.'],
        ['key' => 'applications', 'label' => 'Teams that ask for you', 'target' => ['instructor.assignments'],
            'body' => 'Teams apply to have you as their adviser, consultant or grammarian - **accept or decline** here.'],
        ['key' => 'teams', 'label' => 'Your teams', 'target' => ['instructor.teams'],
            'body' => '**Every team** you work with, and **how far along** each one is.'],
        ['key' => 'reviews', 'label' => 'Review their documents', 'target' => ['instructor.docreview'],
            'body' => 'Chapters your teams send come here for **your comments**.'],
        ['key' => 'schedule', 'label' => 'Consultation hours', 'target' => ['instructor.schedule'],
            'body' => 'Set **when teams can book you**, and see **who has booked**.'],
        ['key' => 'grading', 'label' => 'Grade defenses', 'target' => ['instructor.grading'],
            'body' => 'When you sit on a panel, you **score the team\'s defense** here.'],
        ['key' => 'bell', 'label' => 'Your notifications', 'target' => ['#notif-btn'],
            'body' => 'New **applications, documents and bookings** arrive here.'],
        ['key' => 'done', 'label' => 'You are all set', 'target' => [],
            'body' => 'That is everything. **Welcome to CapsTrack!**'],
    ],
];
