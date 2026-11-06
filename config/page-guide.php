<?php

/*
 * The Page Guide: what the ? beside the bell opens on every page.
 * Keyed by the page's route name, so a page shared by two portals (a CaPSA
 * and an adviser both have a Profile) has one guide for each.
 *
 *   title    the page's name
 *   summary  one sentence on what the page is for
 *   steps    the quick guide: [the words in bold, the rest of the sentence]
 *   tour     the interactive tour: [heading, sentence, CSS selector of what it outlines]
 *   related  other pages of the same portal: route name => link text
 *   tip      one thing worth knowing, or left out
 */

// Experts and Consultations are one page, so they share one tour. Evaluations
// is a view of that page, not a tab: its steps end the main tour.
$expertTour = [
    ['Two tabs', 'Your experts shows the ones you have. Find experts lets you request new ones.', '.xh-seg'],
    ['Rate experts', 'Opens the evaluations of your experts. It shows for the leader after a defense passes.', '.xh-due .xh-btn'],
    ['At a glance', 'How many experts you have, upcoming consultations and pending evaluations.', '.xh-stats'],
    ['Your experts', 'Each expert with their consultations so far and the next one booked.', '.xh-mine'],
    ['Schedule', 'Opens a calendar. Pick a date, then a time, choose your deliverables and press Book session. It then shows in both schedules.', '.xh-mine .xh-btn[data-xh="schedule"]', 'one'],
    ['Evaluate', 'Opens the evaluations of your experts.', '.xh-mine [data-tab="evaluations"]', 'one'],
    ['Profile', 'Opens that expert\'s profile. You can apply to them from there too.', '.xh-mine [data-xh="profile"]', 'one'],
    ['View application', 'Shows what you sent. Withdraw or Remove from list asks you to confirm first.', '.xh-mine [data-xh="app"]', 'one'],
    ['Find an expert', 'Goes to Find experts for that role.', '.xh-mine a.xh-btn:not([data-tab]):not([data-xh])', 'one'],
    ['Upcoming consultations', 'Every consultation you have booked, soonest first.', '.xh-upc'],
    ['All sessions', 'Opens the same calendar with your sessions listed, upcoming and past.', '.xh-link[data-xh="schedule"]'],
    ['Sent evaluations', 'Opens the evaluations your team has sent.', '.xh-link[data-tab="evaluations"]'],
    ['Details', 'Opens that session. Ask to cancel sends your request; it stays booked until the expert answers. Too close to the date, you message the expert instead.', '.xh-upc-row [data-xh="session"]', 'one'],
    // The Evaluations view
    ['Anonymous', 'Your CaPSA and the expert see the answers, never who sent them. 1 is Poor, 5 is Excellent.', '.xh-ev-anon'],
    ['Evaluation form', 'Open one expert at a time and pick one circle on each row.', '.xh-ev-form'],
    ['Next expert', 'Moves to the next expert\'s form.', '#xh-panel-eval [data-next]', 'one'],
    ['Send all', 'Sends every evaluation at once.', '.xh-ev-foot button[type="submit"]'],
    ['Back', 'Returns to Your experts.', '.xh-crumb'],
    ['Find experts', 'Experts you can ask to join your team. Click it to open it; the tour goes on there.', '#xh-tab-find'],
];

$expertTabs = [
    'find' => ['button' => '#xh-tab-find', 'tour' => [
        ['Your roles', 'One card per role. Click one to look for that role.', '.xh-slot', 'one'],
        ['Asked to meet you', 'An expert wants to meet first. Pick a time and press Book this time; it shows in both schedules.', '#xh-meet'],
        ['Request a change', 'Opens a form for your reason and the new expert. Your section\'s CaPSA decides; your expert stays until then.', '[data-xh="change"]', 'one'],
        ['Take back', 'Asks you to confirm, then keeps them as your expert.', '[data-xh="takeback"]', 'one'],
        ['Search', 'Type a name or research area and press Enter.', '#xh-q'],
        ['Filter by skill', 'Opens a list of skills. Pick one to show only the experts who have it.', '#xh-skill-trigger'],
        ['Clear the skill', 'Takes the skill filter off.', '.xh-chip-x'],
        ['Your applications', 'Experts you have applied to. Click one to see what you sent.', '.xh-app', 'one'],
        ['Open slots', 'Switches between experts with open slots and those who are full or not taking teams.', '[data-xh="side"]', 'one'],
        ['Pages', 'Moves to the next or previous page of experts.', '.xh-pg'],
        ['View profile', 'Opens that expert\'s profile.', '.xh-viewlink', 'one'],
        ['Apply', 'Opens a form: pick a role, write a short message, check it and send it.', '[data-xh="apply"]', 'one'],
    ]],
];

// Teams and CaPSA Team Management are one hub: To do is the page's own tour,
// these are its other two tabs.
$hubTabs = [
    'applications' => ['button' => '.hub-tabs [data-hub-tab="applications"]', 'tour' => [
        ['Waiting or Declined', 'Waiting for you shows teams to answer. Declined shows the ones you turned down.', '[data-apps-mode="waiting"]'],
        ['Find an application', 'Search by team or leader, or show one role.', '#tm-apps-q'],
        ['View Report and Download Excel', 'View Report opens this list as a report. Download Excel asks which columns you want, then downloads the list.', '#tm-apps-exp-waiting .tx-btn, #tm-apps-exp-declined .tx-btn'],
        ['Read', 'Opens what the team sent, with their title and why they chose you.', '.apps-t [data-read="application"]', 'one'],
        ['Decline', 'Asks for your reason, at least 20 characters. The team sees it.', '.apps-t [data-decide="decline"]', 'one'],
        ['Meet first', 'Asks for a note, at least 10 characters. The team books one of your consultation times, and you accept or decline after.', '.apps-t [data-decide="meet"]', 'one'],
        ['Accept', 'Asks you to confirm. You become that team\'s expert, one of your slots is used, and the team is told.', '.apps-t [data-decide="approve"]', 'one'],
        ['Clear', 'Takes a declined team off this list. The team isn\'t told again and can still apply to you.', '.apps-t [data-decide="remove"]', 'one'],
        ['Teams', 'Click it to see every team you handle.', '.hub-tabs [data-hub-tab="teams"]'],
    ]],
    'teams' => ['button' => '.hub-tabs [data-hub-tab="teams"]', 'tour' => [
        ['Quick views', 'Click one to show only those teams. The number says how many.', '.tm-quick'],
        ['Search', 'Finds a team by name. Press / to jump here.', '#tm-q'],
        ['Your role', 'Shows only the teams where you hold that role.', '.tm-roles'],
        ['Status', 'Shows only the teams in that status.', '#tm-st'],
        ['Clear filters', 'Puts the list back to all teams.', '[data-clear]'],
        ['Sort', 'Click a heading to sort by it. Click again to flip the order.', 'button.sort', 'one'],
        ['A team', 'Opens that team\'s page: members, experts, progress and papers.', 'a.tn', 'one'],
        ['Progress', 'One block per milestone, in order. The legend below says what each colour means.', '.strip', 'one'],
        ['View Report', 'Opens this list as a report you can download.', 'button.tx-btn', 'one'],
        ['Download Excel', 'Asks which columns you want, then downloads the list.', 'button.tx-btn + button.tx-btn', 'one'],
    ]],
];

// CaPSA's Team Management: the same hub, plus the section's own lists.
$capsaHubTabs = [
    'applications' => $hubTabs['applications'],
    'teams' => ['button' => '.hub-tabs [data-hub-tab="teams"]', 'tour' => [
        ['Quick views', 'Click one to show only those teams. The number says how many.', '.tm-quick'],
        ['Search', 'Finds a team by name. Press / to jump here.', '#tm-q'],
        ['Section', 'Shows one section. The page reloads with it.', 'select.sel', 'one'],
        ['Status', 'Shows only the teams in that status.', '#tm-st'],
        ['Clear filters', 'Puts the list back to all teams.', '[data-clear]'],
        ['Sort', 'Click a heading to sort by it. Click again to flip the order.', 'button.sort', 'one'],
        ['A team', 'Opens that team\'s page: members, experts, progress and papers.', 'a.tn', 'one'],
        ['Progress', 'One block per milestone, in order. The legend below says what each colour means.', '.strip', 'one'],
        ['View Report', 'Opens this list as a report you can download.', 'button.tx-btn', 'one'],
        ['Download Excel', 'Asks which columns you want, then downloads the list.', 'button.tx-btn + button.tx-btn', 'one'],
        ['Milestones', 'Click it to see this program\'s list of milestones.', '.hub-tabs a[href*="tab=milestone_list"]'],
    ]],
    'milestone_list' => ['button' => '.hub-tabs a[href*="tab=milestone_list"]', 'tour' => [
        ['New milestone', 'Opens a form for a new milestone in this program\'s list.', 'a.mlx-btn[href$="/milestones/new"]'],
        ['Set up milestones', 'Opens the first setup of this program\'s list.', 'a.mlx-btn[href*="milestones/setup"]'],
        ['Open or Finished', 'Switches between the milestones still running and the finished ones.', '.mlx-tabs'],
        ['Show', 'Sorts the list by checklist order, due date, most complete or name.', '#ml-sort'],
        ['Move a milestone', 'Drag it, or use the up and down arrow keys, to change the order teams see.', '.ml-grip', 'one'],
        ['A milestone', 'Opens it, with its requirements and each team\'s progress.', 'a.mlx-name', 'one'],
        ['Open to teams', 'Opens it for the whole section. Nothing opens it on its own.', '[data-body=\'{"opened":true}\']', 'one'],
        ['Mark finished', 'Closes it for the whole section. It moves to Finished.', '[data-body=\'{"closed":true}\']', 'one'],
        ['Reopen', 'Takes it out of Finished and leaves it open.', '[data-body=\'{"closed":false}\']', 'one'],
        ['Show to teams again', 'Brings back a milestone you hid.', '[data-body=\'{"archived":false}\']', 'one'],
        ['Open', 'Opens the milestone, the same as clicking its name.', 'a.mlx-btn.icon.sm', 'one'],
        ['Open in Milestones', 'Click it to see how each team is doing and check their papers.', 'a.mlx-btn[href*="tab=milestones"]'],
        ['Team Ratings', 'Click it to see how members rate each other.', '.hub-tabs a[href*="tab=team_ratings"]'],
    ]],
    'milestones' => ['button' => 'a.mlx-btn[href*="tab=milestones"]', 'tour' => [
        ['Manage milestones', 'Goes to the list where you add, open and finish milestones.', 'a.ms-btn'],
        ['Sections', 'Click a section to see its teams. The number says how many papers wait for you.', '.ms-sec', 'one'],
        ['Show', 'All teams, only those behind, only those with papers to check, or those on track.', '.ms-ftab', 'one'],
        ['Find a team', 'Type part of a team\'s name.', '.ms-search'],
        ['A team', 'Click its name to open its milestones and requirements.', '.ms-tname', 'one'],
        ['Check', 'Opens the paper. Press Complete, or Send back with a note saying why.', '.ms-btn-gold', 'one'],
    ]],
    'team_ratings' => ['button' => '.hub-tabs a[href*="tab=team_ratings"]', 'tour' => [
        ['View rubric', 'Shows what students rate each other on.', '#tr-rub'],
        ['Export to Excel', 'Downloads the ratings as an Excel file.', 'a.btn[href*="spreadsheet"]', 'one'],
        ['Create a rating', 'Opens a form: name it, pick the sections and activities, and set how many days students have and when it opens.', '#tr-new'],
        ['Open now', 'Opens the rating today and tells the students right away. The days to rate count from today.', '[data-now]', 'one'],
        ['Change due date', 'Picks a new last day, from today on.', '[data-due]', 'one'],
        ['Remind', 'Sends a notification to those who haven\'t rated. A reminder also goes out by itself the day before it is due.', '[data-remind]', 'one'],
        ['Close', 'Asks you to confirm. Students can\'t rate after this, and those who didn\'t submit are left out of their teammates\' averages.', '[data-shut]', 'one'],
        ['Add activity', 'Adds another activity to this rating.', '[data-add]', 'one'],
    ]],
    'leave_requests' => ['button' => '#tab-pending', 'tour' => [
        ['Leave Requests', 'Members who asked to leave, and experts asking to drop or remove someone.', '.rr-head'],
        ['Waiting', 'Requests that need your answer. Each shows when it declines by itself.', '#tab-pending'],
        ['Decline', 'Asks why. Your reason is required, and the team sees it.', '[data-decide="declined"]', 'one'],
        ['Approve', 'Shows what will change and takes an optional note to the team. This cannot be undone from here.', '[data-decide="approved"]', 'one'],
        ['Decided', 'Click it to see what was approved, declined, withdrawn or auto-declined.', '#tab-decided'],
    ]],
    'decided' => ['button' => '#tab-decided', 'tour' => [
        ['Decided', 'Every request already settled, and how.', '#tab-decided'],
    ]],
];

// The Defenses board: one page, three steps, two sidebar links into it.
$boardTop = [
    ['The defenses', 'Click one to work on it. Each shows how many teams have a date.', '#stages'],
    ['The window', 'The days this defense can be held, for every section. Press it to set the first day, last day and length of each defense. Saving tells the other CaPSAs, and nobody is scheduled until someone uses Generate schedule.', '#windowChip'],
    ['No rubric yet', 'Panelists can\'t grade this defense until it has an active rubric. The link opens Grading Rubrics.', '#rubricWarn'],
    ['Shortcuts', 'Lists the keys that do the same jobs, like / to search.', '.keys-btn'],
    ['Export to Excel', 'Downloads this defense\'s schedule as an Excel file.', '#exportBtn'],
    ['Generate schedule', 'Drafts a date for every ready team, checked against other sections and panelists\' days away. Change any slot, then press Save schedule. With no window yet, it asks for one first.', '#genBtn'],
    ['Where teams are', 'Click a step to see its teams.', '#pipe'],
];
$boardFind = [
    ['Search', 'Finds a team. Press / to jump here.', '#q'],
    ['Section', 'Shows one section.', '#secF'],
];
$boardTeam = [['A team', 'Click its name for a side panel with its stages and what to do next.', '[data-act="drawer"]', 'one']];
$boardReady = array_merge($boardFind, [
    ['Which teams', 'All waiting, first try, cancelled or redefense.', '#kindF'],
], $boardTeam, [
    ['Panelist can\'t sit', 'Opens the final panel. Pick the seat, then mark that person away for a few days or replace them.', '[data-act="cantsit"]', 'one'],
    ['Change panel', 'Opens the panel picker for this team.', '[data-act="assign-one"]', 'one'],
    ['Schedule', 'Opens a form for the venue, date and time slot. Press Save schedule. Missing requirements don\'t stop it.', '[data-act="schedule"]', 'one'],
]);
$boardPanel = array_merge($boardFind, $boardTeam, [
    ['Finalize all', 'Finalizes every draft panel in the list. Finalized panels can\'t be changed.', '[data-act="finalize-all"]'],
    ['Pick teams', 'Tick teams to give them one panel. A bar then offers Assign panel to selected, Finalize drafts, and Clear.', '#pickAll'],
    ['Assign panel', 'Opens the panel picker. It saves as a draft until you finalize it. Turned off, with the reason, if the team is missing its experts.', '[data-act="assign-one"]', 'one'],
    ['Finalize', 'Asks you to confirm. After this the panel can\'t be changed, and it sits every defense through Final Oral.', '[data-act="finalize"]', 'one'],
]);
$boardSched = array_merge([
    ['List, Week or Rooms', 'Shows the schedule as a list, a week or by room.', '.viewseg'],
    ['Other sections', 'Adds other sections\' defenses, to see why a room or panelist is taken.', '#othersT'],
], $boardFind, $boardTeam, [
    ['CaPA\'s answer', 'Records CaPA\'s answer: Go, the team defends on this date, or Not ready.', '[data-act="capa"]', 'one'],
    ['Change', 'Opens the date, time and venue. Press Save.', '[data-act="move"]', 'one'],
]);
$boardGoPanel = [['Panel not final', 'Click it to give teams their panels.', '#pipe [data-step="panel"]']];
$boardGoReady = [['Ready', 'Click it to see the teams ready for a date.', '#pipe [data-step="ready"]']];
$boardGoSched = [['Scheduled', 'Click it to see the teams with a date.', '#pipe [data-step="scheduled"]']];
// The first step of each is the open tab itself, so a tab with no teams still has one.
$boardPanelTab = ['panel' => ['button' => '#pipe [data-step="panel"]', 'tour' => array_merge(
    [['Panel not final', 'Teams with no panel yet, or with a draft one.', '#pipe [data-step="panel"][aria-selected="true"]']],
    $boardPanel,
    $boardGoSched
)]];
$boardReadyTab = ['ready' => ['button' => '#pipe [data-step="ready"]', 'tour' => array_merge(
    [['Ready to schedule', 'Teams waiting for a date.', '#pipe [data-step="ready"][aria-selected="true"]']],
    $boardReady,
    $boardGoSched
)]];
$boardSchedTab = ['scheduled' => ['button' => '#pipe [data-step="scheduled"]', 'tour' => array_merge(
    [['Scheduled', 'Teams with a date, or a result.', '#pipe [data-step="scheduled"][aria-selected="true"]']],
    $boardSched
)]];

$evaluationForm = [
    ['Back', 'Returns to Evaluations without saving.', '.ev-back'],
    ['The defense', 'Which defense this evaluation follows.', '#ev-stage'],
    ['Title', 'The name leaders see.', '#ev-title'],
    ['Questions', 'What leaders answer about each expert. Type in a box to change a question.', '#ev-qlist'],
    ['Move up', 'Moves that question up the list.', '.ev-ibtn', 'one'],
    ['Remove', 'Takes that question out.', '.ev-ibtn.is-danger', 'one'],
    ['Add a question', 'Adds an empty question to the list.', '#ev-add'],
    ['Standard questions', 'Fills the list with the standard questions.', '#ev-standard'],
    ['Cancel', 'Leaves without saving.', '.ev-form-foot a.ev-btn'],
    ['Save', 'Saves the evaluation and returns to the list.', '.ev-form-foot button.ev-btn.gold'],
];

return [

    // ── Student ──

    'student.dashboard' => [
        'title' => 'Dashboard',
        'summary' => 'Your home page: your progress, the latest announcements, your consultations and your team.',
        'steps' => [
            ['Look at Your Capstone Progress', 'to see how much is done and what is due next.'],
            ['Read the announcements', 'for defense dates and CCS updates.'],
            ['Use Quick Actions', 'to go straight to the page you need.'],
        ],
        'tour' => [
            ['Your next step', 'Takes you to the page for what your team should do next.', 'a.ns-go'],
            ['Your Capstone Progress', 'How many requirements are done, what is overdue, and what is due next.', '.db-progress'],
            ['Quick Actions', 'Shortcuts to the pages you use most.', '.db-qa-grid'],
            ['Announcements', 'News from your CaPSA and CCS.', '#announcements-card'],
            ['An announcement', 'Opens it in a popup so you can read all of it.', '.ann-item', 'one'],
            ['Your Team', 'Your teammates, and the way to Team Management.', '.db-team-top'],
        ],
        'related' => [
            'student.progress' => 'See your progress',
            'student.documents' => 'Submit a paper',
            'student.team' => 'Manage your team',
        ],
        'tip' => 'Press an announcement to read all of it.',
    ],

    'student.experts' => [
        'title' => 'Experts',
        'summary' => 'Choose your adviser, consultant and grammarian, and book consultations with them.',
        'steps' => [
            ['Open Find experts', 'to see who is open and matched to your project.'],
            ['Send a request', 'to the expert you want for each role.'],
            ['Press Schedule', 'on an accepted expert to book a consultation.'],
        ],
        'tour' => $expertTour,
        'tabs' => $expertTabs,
        'related' => [
            'student.consultation' => 'Book a consultation',
            'student.duplication' => 'Check your titles',
            'student.team' => 'Manage your team',
        ],
        'tip' => 'Changing an approved expert goes to your section\'s CaPSA to decide.',
    ],

    'student.consultation' => [
        'title' => 'Consultations',
        'summary' => 'Book and keep track of consultations with your adviser, consultant and grammarian.',
        'steps' => [
            ['Press Schedule', 'on the expert you want to meet.'],
            ['Pick a free time', 'from their calendar and send the request.'],
            ['Watch Upcoming consultations', 'for the ones they accept.'],
        ],
        'tour' => $expertTour,
        'tabs' => $expertTabs,
        'related' => [
            'student.experts' => 'Find experts',
            'student.documents' => 'Submit a paper',
        ],
        'tip' => 'You can cancel a booking from Upcoming consultations if plans change.',
    ],

    'student.documents' => [
        'title' => 'Documents',
        'summary' => 'Send your papers to your experts and read their remarks. Signed papers go in on Progress Tracker.',
        'steps' => [
            ['Press Submit a paper,', 'pick who reads it, and check the preview before sending.'],
            ['Open a paper sent back', 'to see what your reviewer asked, then send the revision.'],
            ['Press View', 'to read your paper with the reviewer\'s remarks beside it.'],
        ],
        'tour' => [
            // The list
            ['Your team\'s documents', 'Every paper your team has sent.', '.dl-head'],
            ['Submit a paper', 'Opens a form: pick who reads it, choose your PDF, check the preview and send it.', '.dl-head .up-btn.primary'],
            ['Filter by status', 'Show only the papers that need revision, are in review, or are approved.', '.dl-tabs'],
            ['Search', 'Find a paper by its name.', '.dl-search'],
            ['Your papers', 'Each paper with its reviewer and status.', '.dl-list'],
            ['Read the remarks', 'Opens the paper at what your reviewer wrote.', '.dl-remark a', 'one'],
            ['Send the revision', 'Opens the same form for your revised PDF. It goes in as the next version of this paper.', '.dl-acts .up-btn.primary', 'one'],
            ['View', 'Opens the paper with the reviewer\'s remarks beside it.', '.dl-acts a.up-btn', 'one'],
            ['Unsubmit', 'Asks you to confirm. The paper goes back to not handed in and reviewers no longer see it, until you upload it again.', '.dl-acts .up-btn.danger', 'one'],
            ['Earlier versions', 'Shows the versions of this paper sent before.', '.dl-more', 'one'],
            // One paper, opened with View
            ['Back', 'Returns to your list of papers.', '.dc-back'],
            ['Versions', 'Switches to another version of this paper.', '.dc-vers'],
            ['Download CLF', 'Saves the Consultation Log Form for this review.', 'a.rv-btn.ghost:not([target])'],
            ['Download Annotated PDF', 'Opens your paper with the reviewer\'s marks on it.', 'a.rv-btn.ghost[target]'],
            ['Unsubmit', 'Asks you to confirm. The paper goes back to not handed in and reviewers no longer see it, until you upload it again.', '.rv-btn.danger'],
            ['Send the revision', 'Opens the form for your revised PDF. It goes in as the next version of this paper.', '.rv-btn.gold'],
        ],
        'related' => [
            'student.progress' => 'Hand in signed papers',
            'student.experts' => 'See your experts',
            'student.resources' => 'Get templates',
        ],
        'tip' => 'Papers go in as PDF. A diagram can ride along with the PDF.',
    ],

    'student.duplication' => [
        'title' => 'Pre-Proposal Submission',
        'summary' => 'Check your pre-proposal titles against past CCS capstones and this year\'s teams.',
        'steps' => [
            ['Drop your pre-proposal PDF', 'into Check Document.'],
            ['Press Run Similarity Check', 'and wait for the scores.'],
            ['Read the closest matches', 'and turn in your titles from Your Pre-Proposals.'],
        ],
        'tour' => [
            ['The result', 'Your closest match and how close it is to existing work.', '.scan-card'],
            ['Revise your title', 'Type the new wording and press Save revised title. Once the Signed Final Project Title Form is in, the title is final.', 'input[aria-label="Revised title"] + button'],
            ['Change your title', 'Type the new title and your reason, then send it to your adviser. It changes only once they approve.', '#title-change'],
            ['Choose This One', 'Asks you to confirm, then makes this your team\'s title and drops the other one.', '[onclick^="confirmChooseTitle"]', 'one'],
            ['See what is similar', 'Opens the full report of this check.', '#full-report-btn'],
            ['Clear this result', 'Takes this result off the card.', '#clear-result-btn'],
            ['Check a document', 'Drop your pre-proposal PDF here.', '.duplication-form'],
            ['Run Similarity Check', 'Compares your PDF with this year\'s teams and past capstones. The scores show here when it is done.', '#check-btn'],
            ['Complete Pre-Proposal Details', 'Shows after a check. Opens a form for your pre-proposal that saves as you type.', '#open-pp-form-btn'],
            ['Suggested experts', 'Experts whose field matches your titles.', '#instructor-card'],
            ['This year', 'Titles from other teams this school year that look like yours.', '#ty-card'],
            ['From the archive', 'The ten past capstones closest to your titles.', '#matches-panel'],
            ['A match', 'Opens it side by side with your pre-proposal.', 'a.mrow', 'one'],
            ['Your pre-proposals', 'Your titles and whether they are turned in for checking.', '.pp-card'],
            ['Pick which to turn in', 'Only the ticked titles go in.', '.pp-pick', 'one'],
            ['Similarity Report', 'Opens the full report for this title.', '.pp-card a.golink', 'one'],
            ['Remove', 'Asks you to confirm, then removes this title.', '.pp-card .match-abstract-toggle', 'one'],
            ['Submit for Checking', 'Asks you to confirm, then turns in the ticked titles for your CaPSA to check.', '.pp-btn-submit'],
            ['Unsubmit', 'Takes your titles back, until your CaPSA starts reviewing them.', '.pp-btn-unsubmit', 'one'],
        ],
        'related' => [
            'student.archive' => 'Browse past capstones',
            'student.experts' => 'Request your experts',
        ],
        'tip' => 'A high score is a warning, not a rejection. Talk it over with your adviser.',
    ],

    'student.progress' => [
        'title' => 'Progress Tracker',
        'summary' => 'Every requirement of your capstone, in order, with where each one stands.',
        'steps' => [
            ['Look at the counts', 'for what is overdue, waiting or closed.'],
            ['Open a requirement', 'to hand in its signed paper or see its feedback.'],
            ['Press Panel scores', 'to read your defense results.'],
        ],
        'tour' => [
            ['Your team', 'Your team, section, adviser and how far along you are.', '.pt-hero'],
            ['See panel scores', 'Opens your defense scores and the panel\'s remarks.', '.pt-complete a.pt-btn'],
            ['The counts', 'How many requirements are overdue, waiting and closed.', '.pt-stats'],
            ['Go to a stage', 'Jumps to that stage further down and opens it.', 'a[href^="#pt-g-"]', 'one'],
            ['Panel scores', 'Your defense scores and the panel\'s remarks.', '.pt-scores'],
            ['Upload', 'Opens the upload form for this requirement. Pick your file, check it and send it. Red means it is overdue.', 'button.pt-btn[onclick*="openSubmission"]', 'one'],
            ['Evaluate now', 'Goes to Experts to evaluate your experts. Only the leader sees it.', 'a.pt-btn-primary[href*="evaluations"]', 'one'],
            ['Requirements', 'Each requirement in order.', '.pt-list-wrap'],
            ['A stage', 'Click it to open or close its requirements.', 'details.pt-group > summary', 'one'],
            ['A file', 'Opens the file you handed in, or its document page.', 'a.pt-file', 'one'],
        ],
        'related' => [
            'student.documents' => 'Submit a paper',
            'student.defense-scores' => 'See your panel scores',
            'student.dashboard' => 'Back to your dashboard',
        ],
        'tip' => 'Overdue items show in red at the top of their stage.',
    ],

    'student.defense-scores' => [
        'title' => 'Panel Scores',
        'summary' => 'What each panel member gave your team at each defense, and their remarks.',
        'steps' => [
            ['Pick a defense', 'from the tabs at the top.'],
            ['Read the panel decision', 'first.'],
            ['Go through each criterion', 'and every panel member\'s remarks.'],
        ],
        'tour' => [
            ['Defenses', 'One tab for each defense your team has had. Click one to see its scores.', '.ds-tabs'],
            ['Scores', 'Each criterion with the mark every panel member gave.', '.ds-grid'],
            ['Remarks', 'What each panel member wrote about your defense.', '.ds-card'],
        ],
        'related' => [
            'student.progress' => 'See your progress',
            'student.documents' => 'Send a revision',
        ],
    ],

    'student.resources' => [
        'title' => 'Resources',
        'summary' => 'Templates, guides and rubrics from CCS for your capstone.',
        'steps' => [
            ['Search', 'for a template or guide by name.'],
            ['Pick a category', 'to narrow the list.'],
            ['Press a file', 'to download it.'],
        ],
        'tour' => [
            ['Search', 'Find a file by its name.', '.rs-search'],
            ['Category', 'Show only one kind of file, such as rubrics.', '#rs-cat'],
            ['Sort', 'Changes the order of the list.', '#rs-sort'],
            ['Clear filters', 'Takes off the search and category so every file shows again.', '#rs-clear'],
            ['Files', 'Every file you can download.', '#rs-list'],
            ['A file', 'Click its row to open it. View shows a preview beside the list, where you can also download it or open it in Drive. Download saves it to your device.', '.rs-row', 'one'],
        ],
        'related' => [
            'student.documents' => 'Submit a paper',
            'student.archive' => 'Browse past capstones',
        ],
    ],

    'student.archive' => [
        'title' => 'Archived Capstones',
        'summary' => 'Past CCS capstones, for ideas and to see what has been done before.',
        'steps' => [
            ['Search', 'by title, proponent or keyword.'],
            ['Narrow by school year', 'or category.'],
            ['Open a capstone', 'to read its abstract.'],
        ],
        'tour' => [
            ['Search', 'Find a capstone by title, proponent or keyword.', '.arx-search'],
            ['School year', 'Show only one school year.', '#archive-year'],
            ['Category', 'Show only one kind of project.', '#archive-tag'],
            ['Submit to Archive', 'Opens a form with your title and proponents filled in. Type your abstract and press Send Archive Entry.', '#open-manuscript'],
            ['Capstones', 'Every past capstone that matches.', '#archive-grid'],
            ['A capstone', 'Click it to read its abstract in a popup.', 'article.arx-card', 'one'],
        ],
        'related' => [
            'student.duplication' => 'Check your titles',
            'student.resources' => 'Get templates',
        ],
    ],

    'student.team' => [
        'title' => 'Team Management',
        'summary' => 'Where your team is formed, and where its members, panel and fees are kept.',
        'steps' => [
            ['Invite classmates', 'into the open slots, or put up a recruitment post.'],
            ['Wait for them to accept', 'from their notifications.'],
            ['Check your defense panel', 'once your experts accept.'],
        ],
        // A 4th item 'one' outlines only the first match: one button of a list.
        'tour' => [
            // In a team
            ['Your team', 'Your team name, section and project title, with how many slots are still open.', '.tm-hero'],
            ['Update team', 'Opens a form to change the team name and project title. Saving changes it for the whole team.', '.tm-hero-actions > button:nth-child(1)'],
            ['Transfer leadership', 'Pick a member to hand the leader role to. You stay on the team as a member. It stays grey until someone else has joined.', '.tm-hero-actions > button:nth-child(2)'],
            ['Leave team', 'Asks you to confirm, then takes you out of the team. A leader hands leadership over first; if you are the only member, leaving ends the team.', '#leave-team-form button'],
            ['Team members', 'Everyone in your team, with their role. LEADER and YOU mark who is who.', '.tm-members'],
            ['Ask CaPSA to leave', 'Opens a form for your reason. Your CaPSA reads it and decides; you stay on the team until then.', '.tm-leave'],
            ['Invite a classmate', 'Opens a list of classmates without a team. Pick one to send an invite; they join once they accept it.', '.tm-slot.is-open', 'one'],
            ['Remove', 'Shows that member and asks you to confirm. Once you do, they are taken off the team.', '.tm-link[onclick^="openRemoveMemberModal"]', 'one'],
            ['Remove', 'After Title Defense, removing a member goes to your CaPSA. Opens a form for your reason; your CaPSA decides.', '.tm-link[onclick^="openCapsaLeaveModal(\'remove\'"]', 'one'],
            ['Cancel invite', 'Asks you to confirm, then takes back the invite, so that classmate can no longer accept it.', '.tm-link[onclick^="cancelPendingInvite"]', 'one'],
            ['Defense panel', 'The experts who will sit on your defense. Your CaPSA sets it once your team is ready.', '.tm-panel'],
            ['Waiting on your CaPSA', 'Leave and remove requests your CaPSA has not decided yet. One left too long is declined by itself.', 'section[aria-labelledby="tm-waiting-h"]'],
            ['Withdraw', 'Takes back your request before your CaPSA decides on it.', '.tm-request form .tm-act', 'one'],
            ['Declined automatically', 'Requests your CaPSA did not act on in time. You can send them again.', 'section[aria-labelledby="tm-declined-h"]'],
            ['Your recruitment post', 'Your post in Team selection, so classmates can find your team.', '#my-post-section'],
            ['Edit post', 'Opens your post in a form. Saving changes what classmates see in Team selection.', '#my-post-section [onclick^="openEditModal"]'],
            ['Delete post', 'Asks you to confirm, then takes your post down.', '#my-post-section [onclick^="openDeleteModal"]'],
            ['Join requests', 'Classmates asking to join your team.', '.tm-requests-card'],
            ['Accept', 'Asks you to confirm, then that classmate joins your team and their post is taken down.', '.tm-requests-card .nt-btn-dark', 'one'],
            ['Decline', 'Opens a box to confirm turning the request down.', '.tm-requests-card .nt-btn-line', 'one'],
            // Not in a team yet
            ['Build a team', 'Opens a form to name your team and say who you need. Posting it makes you the leader.', '[data-nt-build]'],
            ['Post: looking for a team', 'Opens a form for a post saying you want a team, so leaders can invite you.', '[data-nt-seek]'],
            ['Team invites', 'Teams that invited you.', '.nt-invites'],
            ['An invite', 'Opens it in full: the team, its title and its members.', '.nt-invite-head', 'one'],
            ['Accept', 'Asks you to confirm, then you join that team.', '.nt-invites .nt-btn-dark', 'one'],
            ['Decline', 'Opens a box to confirm turning the invite down.', '.nt-invites .nt-btn-line', 'one'],
            // Team selection
            ['Browse posts', 'Shows or hides the posts from classmates in your section.', '.ts-fold-toggle'],
            ['Filter by goal', 'Show only teams looking for members, or only classmates looking for a team.', '.ts-tabs'],
            ['Filter by skill', 'Show only posts that list that skill.', '#feed-skill-filter'],
            ['A post', 'View post, Ask to join or Invite to team opens the post in full, where you send the request.', '.ts-card .ts-btn', 'one'],
            ['Load more', 'Shows the next posts.', '[data-ts-more]'],
            ['Section overview', 'How many in your section have teamed up so far.', '.ts-aside'],
            // The other tabs: a click opens it and the tour goes on there.
            ['Financial Records', 'Your defense fees and receipts. Click it to open it; the tour goes on there.', '#tm-tab-financial'],
            ['Team Rating', 'Where you rate your teammates once your CaPSA opens a rating. Click it to open it.', '#tm-tab-rating'],
        ],
        'tabs' => [
            'financial' => ['button' => '#tm-tab-financial', 'tour' => [
                ['Defense fees', 'One tab for each defense fee. Pick one to see its receipt.', '#s-fees'],
                ['Your receipt', 'Your receipt for this fee and where it is: sent, checked, or sent back.', '.fx-sb-mine'],
                ['OR number', 'Type it exactly as printed on the receipt. It is checked against the photo.', '#u-or'],
                ['Photo of the receipt', 'Take or choose a photo of it. JPG or PNG, up to 10 MB.', '#u-pick'],
                ['Send to CaPSA', 'Sends the receipt to your CaPSA to check. This card then shows it as waiting, verified or sent back.', '#u-send'],
                ['Team payments', 'Who in your team has a verified receipt for this fee, and who is still waiting.', '.fx-sb-team'],
            ]],
            'rating' => ['button' => '#tm-tab-rating', 'tour' => [
                ['Nothing open', 'Your CaPSA opens a rating when an activity is due, and you get a notice.', '.trs-empty'],
                ['Open now', 'The rating that is open and what it covers.', '.trs-card[id^="trs-form-"]'],
                ['Rate', 'Opens the rating form for that teammate. Edit changes scores you already gave.', 'a.trs-btn', 'one'],
                ['Review & submit', 'Once every teammate is rated, shows your totals to check. Submit ratings sends them to your CaPSA only.', '[data-trs-review]', 'one'],
                ['Other ratings', 'Ratings already sent, and ones not open yet.', '.trs-olist'],
            ]],
        ],
        'related' => [
            'student.experts' => 'Request your experts',
            'student.documents' => 'Submit a paper',
            'student.progress' => 'See your progress',
        ],
        'tip' => 'Leaving a team goes to your section\'s CaPSA to decide, not straight out.',
    ],

    'student.duplication.report' => [
        'title' => 'Similarity Report',
        'summary' => 'The full result of one check: how close your pre-proposal is to this year\'s teams and past capstones.',
        'steps' => [
            ['Read the result', 'at the top for your closest match.'],
            ['Go down the lists', 'of this year\'s titles and the archive.'],
            ['Open a match', 'to put it side by side with yours.'],
        ],
        'tour' => [
            ['The result', 'Your title, its closest match and how close it is.', '.rp-head'],
            ['Back', 'Goes back to your pre-proposals.', '.rp-back'],
            ['Your PDF', 'Opens your pre-proposal PDF in a new tab.', 'a.pbtn'],
            ['Your pre-proposal', 'What the check read from your document.', '.rp-mine'],
            ['More', 'Opens your full pre-proposal in a popup.', '.rp-more', 'one'],
            ['Read the full pre-proposal', 'Opens all of it in a popup.', '.rp-read'],
            ['This year', 'Titles from other teams this school year that look like yours.', 'section.rp-ty'],
            ['From the archive', 'The ten past capstones closest to yours.', 'section.rp-list:not(.rp-ty)'],
            ['A match', 'Opens it side by side with your pre-proposal.', 'a.rp-row', 'one'],
            ['Suggested experts', 'Experts whose field matches your titles.', '.rp-experts'],
            ['Previous and next', 'Shows the previous or next suggested experts.', '.rp-page-btn'],
        ],
        'related' => [
            'student.duplication' => 'Back to Pre-Proposal Submission',
            'student.archive' => 'Browse past capstones',
        ],
        'tip' => 'A high score is a warning, not a rejection. Talk it over with your adviser.',
    ],

    'student.duplication.similar' => [
        'title' => 'Side-by-Side Match',
        'summary' => 'Your pre-proposal next to one similar title, with the words they share marked in gold.',
        'steps' => [
            ['Read the score', 'and what it means at the top.'],
            ['Compare the two columns', 'and look at the gold words.'],
            ['Read the similarities and differences', 'at the bottom.'],
        ],
        'tour' => [
            ['Back', 'Goes back to the list you came from.', '.pager > a.pbtn'],
            ['Previous and next', 'Moves to the title before or after this one in the list.', '.pager > div .pbtn'],
            ['The score', 'How close this title is to yours.', '.verdict'],
            ['How it is worked out', 'What the score is made of.', '.how'],
            ['Side by side', 'Yours on the left, theirs on the right. Gold marks words found in both.', '.vs'],
            ['Similarities and differences', 'Where the two projects meet and where they part.', '.grid3'],
        ],
        'related' => [
            'student.duplication' => 'Back to Pre-Proposal Submission',
            'student.archive' => 'Browse past capstones',
        ],
        'tip' => 'Gold marks are words matched literally, not by the AI.',
    ],

    'student.team_rating.rate' => [
        'title' => 'Rate a Teammate',
        'summary' => 'Score each teammate on how they took part, one criterion at a time.',
        'steps' => [
            ['Pick a teammate', 'from the list on the left.'],
            ['Score every criterion', 'for each activity. Every score is needed.'],
            ['Press Next', 'to go to the next teammate, then send when all are done.'],
        ],
        'tour' => [
            ['Teammates', 'Everyone you rate, with how many scores you have picked.', '.trr-steps'],
            ['A teammate', 'Click a name to rate that teammate.', 'a.trr-step', 'one'],
            ['This teammate', 'Who you are rating now.', '.trr-head'],
            ['View full rubric', 'Opens a popup that explains each score.', '#trr-rub-open'],
            ['The scores', 'One row per activity. Pick 10, 7, 5, 3 or 1.', '#trr-list'],
            ['Moving on', 'Your picks save as you go.', '.trr-foot'],
            ['Previous', 'Goes back to the teammate before.', 'a.trr-btn[data-trr-go]:not(#trr-next)'],
            ['Next', 'Goes to the next teammate once every score is picked. On the last one it reads Review & submit and takes you to anyone unfinished, then to your totals.', '#trr-next'],
        ],
        'related' => [
            'student.team' => 'Back to Team Management',
        ],
        'tip' => 'Your picks are saved as a draft until you send them.',
    ],

    // ── Adviser, consultant and grammarian (the instructor portal) ──

    'instructor.dashboard' => [
        'title' => 'Dashboard',
        'summary' => 'Your home page: your teams, what is waiting on you and what is coming up.',
        'steps' => [
            ['Read the tiles', 'for your teams, reviews, graded defenses and consultations.'],
            ['Check Upcoming Tasks', 'for what is waiting on you. Press a row to open it.'],
            ['Read Consultations', 'for your next consultations and defenses.'],
        ],
        'tour' => [
            ['Your work in numbers', 'Your teams, documents to review, defenses graded and consultations.', '.db-tiles'],
            ['My workload', 'Your team slots and everything still on your plate.', '.db-x-work'],
            ['Team distribution', 'Your teams per section.', '.db-x-team'],
            ['Consultations, tasks and announcements', 'What is next, what is waiting on you, and the latest news.', '.db-xside'],
            ['Recent activity', 'The latest things that happened to you.', '.db-x-act'],
            ['Quick access', 'Shortcuts to the pages you open most.', '.db-x-quick'],
        ],
        'related' => [
            'instructor.teams' => 'See your teams',
            'instructor.grading' => 'Grade a defense',
        ],
        'tip' => 'Press a tile to open the page behind it.',
    ],

    'instructor.teams' => [
        'title' => 'Teams',
        'summary' => 'Every team you handle, what they need from you, and teams applying to you.',
        'steps' => [
            ['Start with To do', 'for what a team needs from you now.'],
            ['Open Team Applications', 'to accept or decline a team that asked for you.'],
            ['Open Teams', 'and press a team to see its members, progress and papers.'],
        ],
        'tour' => [
            ['Your teams and slots', 'What is waiting, how many teams you have, and how many slots are left.', '.hub-head'],
            ['Your slots', 'Teams you have and teams you can still take, per program. Change the numbers on your Profile.', '.slots'],
            ['The lists', 'To do, Team Applications and Teams. The number shows how many are in each.', '.hub-tabs'],
            ['Pending or Completed', 'Pending is what still needs you. Completed is what you already settled; search it and press Open to see one.', '.seg'],
            ['See all', 'Opens every team that applied to you, in Team Applications.', '.see-all'],
            ['Something to do', 'What it is, which team, and how long it has waited.', '.todo li[data-item]', 'one'],
            ['The team\'s name', 'Opens that team\'s page.', '.todo a.team', 'one'],
            ['Read', 'Opens what the team sent, so you can read it before you answer.', '.todo .btn.rd', 'one'],
            ['Decline', 'Asks for your reason, and says what will happen, before anything is sent. The team sees the reason.', '.todo [data-decide="decline"]', 'one'],
            ['Approve', 'Shows what will happen and asks you to confirm. The team is told.', '.todo [data-decide="approve"]', 'one'],
            ['The main button', 'Goes to where this is done, like a paper to check or a score sheet.', '.todo a.btn.p', 'one'],
            ['See overdue teams', 'With nothing to do, this opens Teams showing the teams that are behind.', 'a[data-quick-go="behind"]'],
            ['Team Applications', 'Click it to see the teams asking for you.', '.hub-tabs [data-hub-tab="applications"]'],
            ['Teams', 'Click it to see every team you handle.', '.hub-tabs [data-hub-tab="teams"]'],
        ],
        'tabs' => $hubTabs,
        'related' => [
            'instructor.progress' => 'See milestone progress',
            'instructor.profile' => 'Change your team slots',
        ],
        'tip' => 'Anything waiting 3 days or more is marked in red.',
    ],

    'instructor.teams.show' => [
        'title' => 'Team Page',
        'summary' => 'One team: its members, experts, panel, milestone progress and papers.',
        'steps' => [
            ['Look at the top', 'for the team\'s title, section and what is due next.'],
            ['Press a milestone', 'under Progress to see its requirements.'],
            ['Open Papers', 'to see what the team sent.'],
        ],
        'tour' => [
            ['The team', 'Its title, section and what is due next.', '.tp-head'],
            ['Previous and next team', 'Moves to the team before or after this one.', 'a.btn.btn-o', 'one'],
            ['First thing waiting', 'Goes to what this team needs from you first. The rest are in To do.', '.btn.btn-p', 'one'],
            ['People', 'The members, the team\'s experts and its defense panel.', 'div.tp-panel'],
            ['Progress', 'Every milestone in order.', 'section.tp-panel', 'one'],
            ['A milestone', 'Click one to see its requirements.', 'a[href*="milestone="]', 'one'],
            ['See all', 'Opens the rest of the list.', '.tp-more[data-tp-more]'],
            ['Papers and activity', 'Click a heading to switch between the team\'s title, the papers it sent and what it did lately.', '.tp-tabs'],
            ['See all', 'Opens one page with every paper or update.', 'a.tp-more[href*="/papers"], a.tp-more[href*="/activity"]', 'one'],
        ],
        'related' => [
            'instructor.teams' => 'Back to Teams',
        ],
    ],

    'instructor.teams.log' => [
        'title' => 'Team Papers and Activity',
        'summary' => 'Everything behind See all on a team page: every paper, or every update.',
        'steps' => [
            ['Read down the list', 'newest first.'],
            ['Go back', 'to return to the team page.'],
        ],
        'tour' => [
            ['The team', 'Which team this is.', '.th'],
            ['Back', 'Returns to the team\'s page.', 'a.btn.btn-o', 'one'],
            ['The list', 'Every paper or update, newest first.', 'section.tp-panel'],
        ],
        'related' => [
            'instructor.teams' => 'Back to Teams',
        ],
    ],

    // Left the sidebar for To do's Completed (45b9e00f): its own guide stays, nothing links to it.
    'instructor.notifications.page' => [
        'title' => 'Notifications',
        'search' => false,
        'summary' => 'Everything you have been sent, newest first.',
        'steps' => [
            ['Read down the list', 'to see what came in. A dot marks one you have not opened.'],
            ['Press a notification', 'to open what it is about.'],
            ['Use Prev and Next', 'to go through older ones.'],
        ],
    ],

    'instructor.docreview' => [
        'title' => 'Document Review',
        'search' => false,
        'summary' => 'Documents your teams sent you to check.',
        'steps' => [
            ['Look at the numbers', 'for how many wait on you and how many are over 5 days.'],
            ['Find a document', 'by its name or team, or by its status.'],
            ['Press Open Annotator', 'to mark it up and send your remarks back.'],
        ],
        'tour' => [
            ['Where you stand', 'Documents waiting on you, those over 5 days, and how many you reviewed.', '.content .grid-3'],
            ['Search', 'Finds a document by its name or team.', '#dr-search'],
            ['Team', 'Shows one team\'s documents.', '#dr-team'],
            ['Status', 'Shows only pending or reviewed documents.', '#dr-status'],
            ['A document', 'Who sent it, when, and where its review stands.', '.dr-table tbody tr.dr-row', 'one'],
            ['Open Annotator', 'Opens the document, where you mark it up and turn in your review.', '.dr-card a.btn.btn-primary', 'one'],
            ['CLF', 'Downloads the Consultation Logs Form for this document.', '.dr-card a.btn.btn-ghost', 'one'],
        ],
        'related' => [
            'instructor.teams' => 'See your teams',
            'instructor.dashboard' => 'Back to the dashboard',
        ],
        'tip' => 'A red edge marks a document waiting 5 days or more.',
    ],

    'instructor.docreview.annotate' => [
        'title' => 'Annotator',
        'summary' => 'Read a team\'s document and mark it up with remarks.',
        'tour' => [
            ['Back', 'Returns to the list. Your remarks are saved as you go.', '.an-back'],
            ['Saved', 'Says your remarks are saved. If it shows an error, don\'t leave yet.', '.an-saved'],
            ['Show changes', 'On a later version, marks what the team changed since the last one.', '.an-switch'],
            ['Side by side', 'On a later version, puts the earlier version beside this one. Press Close side by side to end it.', '.an-bar .rr-btn.ghost:has(+ .rr-btn.ghost + .rr-btn.gold)'],
            ['Export PDF', 'Downloads this document with your remarks on it.', '.an-bar .rr-btn.ghost:has(+ .rr-btn.gold)'],
            ['Apply Changes', 'Shows a summary first. With no remarks the paper is approved; with remarks the team is asked for a revision. Nothing goes out until you confirm there.', '.an-bar .rr-btn.gold'],
            ['Pages', 'Click a page to jump to it.', '.rr-strip'],
            ['Select text', 'Select words on the page, then write your remark and pick its kind: Must fix, Format, Suggestion, Question or Good.', '.rr-tools [aria-label="Select text"]'],
            ['Box', 'Drag over a figure to leave a remark on it.', '.rr-tools [aria-label="Draw a box over a figure"]'],
            ['Pin', 'Click one spot to leave a remark there.', '.rr-tools [aria-label="Pin one spot"]'],
            ['Pick a shape', 'On a diagram, click a box or arrow to leave a remark on it.', '.rr-tools [aria-label="Pick a box or arrow on a diagram"]'],
            ['Change page', 'Moves one page up or down.', '.rr-tools [aria-label="Next page"]'],
            ['Zoom', 'Makes the page bigger or smaller. The last button fits it to the width.', '.rr-tools [aria-label="Zoom in"]'],
            ['Remarks', 'Every remark you left. Click one to go to it. Use + General remark for one about the whole document.', '.rr-tab:nth-child(1)'],
            ['Figures', 'Every figure found in the paper. Press Mark checked on each one, so none is missed.', '.rr-tab:nth-child(2)'],
            ['What changed', 'On a later version, your earlier remarks and the changes. Press Fixed or Not yet on each one.', '.rr-tab:nth-child(3)'],
        ],
        'steps' => [
            ['Select words, or drag over a figure', 'to add a remark there.'],
            ['Pick what kind of remark it is', ': Must fix, Suggestion or Question.'],
            ['Press Apply Changes', 'when you are done.'],
        ],
        'related' => [
            'instructor.teams' => 'Back to Teams',
        ],
        'tip' => 'Export PDF gives you a copy with your remarks on it.',
    ],

    'instructor.grading' => [
        'title' => 'Team Grading',
        'summary' => 'Every defense you sit on, your seat on the panel and your grade.',
        'steps' => [
            ['Find the defense', 'in the list, or show only To grade or Done.'],
            ['Press Grade', 'to open the team\'s grading sheet.'],
            ['Use View Report or Download Excel', 'for a copy of the list.'],
        ],
        'tour' => [
            ['Your defenses', 'How many are left to grade.', '.gl-head'],
            ['Show', 'Shows all defenses, those to grade, or those done.', '.gl-filter'],
            ['The list', 'Each team, the defense, your seat, who grades, when it is and your grade.', '#grading-tbody'],
            ['Who grades', 'Lets one panelist grade for the others, or everyone grade on their own. Save to apply it.', '.gl-who', 'one'],
            ['Grade', 'Opens the score sheet for this team.', '.gl-go.primary', 'one'],
            ['Open or View', 'Opens a sheet you started or already submitted.', '.gl-go:not(.primary)', 'one'],
            ['View Report', 'Opens this list as a report you can download.', 'button.tx-btn', 'one'],
            ['Download Excel', 'Asks which columns you want, then downloads the list.', 'button.tx-btn + button.tx-btn', 'one'],
        ],
        'related' => [
            'instructor.dashboard' => 'Back to the dashboard',
            'instructor.schedule' => 'See your consultations',
        ],
        'tip' => 'One panelist can grade for the whole panel. Press the name under Who grades to choose.',
    ],

    'instructor.grading.show' => [
        'title' => 'Grading Sheet',
        'summary' => 'Score a team\'s defense on each criterion, then submit.',
        'tour' => [
            ['Back', 'Returns to Team Grading.', 'a.gs-iconbtn'],
            ['Saved', 'Your scores save as you go. This says when they last did.', '#saveStatus'],
            ['Sections', 'Each part of the rubric and how much of it you scored. Click one to go to it.', '.gs-step:not(.review)', 'one'],
            ['Next unscored', 'Jumps to the next criterion without a score. Press N for the same.', '#gs-jump'],
            ['A criterion', 'Click it to read it and score it.', '.gs-crit', 'one'],
            ['A score', 'Click the box that fits. It saves by itself. Keys 1 to 4 do the same.', '.gs-cell', 'one'],
            ['Previous and next', 'Moves to the next section or back.', '#gs-sheet-area .gs-btn-outline'],
            ['Graded together', 'Shows who you grade for. Change lets each of you grade alone instead.', '.gs-shared'],
            ['Your tally', 'Your total so far, section by section.', '#gs-sum-rows'],
            ['Enter from paper sheets', 'Graded on paper? Type each panelist\'s total here, pick the decision if you have it, and press Save grades.', '#gs-paper'],
            ['Withdraw abstention', 'Takes back your abstention so you can grade this team.', '#gs-withdraw'],
            ['Abstain', 'Asks for your reason, at least 10 characters. You won\'t score this team; it sees that you abstained, not why.', '.gs-foot .gs-btn-dashed'],
            ['Save draft', 'Keeps your scores without submitting them. The team sees nothing.', '.gs-foot .gs-btn:not(.gs-btn-dashed):not(.gs-btn-dark)'],
            ['Review and submit', 'Opens every score on one page, with your remarks for the team, then Submit grade. You can submit again to replace it.', '#gs-to-review'],
            ['Your scores', 'Click a row to go back and change that score.', '.gs-review-row', 'one'],
            ['Remarks', 'What the team should fix, and what worked.', '#gs-remarks-field'],
            ['Panel decision', 'Once every grade is in, the Panel Chair picks the decision and presses Record panel decision.', '.gs-decision'],
            ['Submit grade', 'Turns in your grade. You can submit again to replace it.', '#gs-submit'],
        ],
        'steps' => [
            ['Pick a score', 'for each criterion, or press 1 to 4.'],
            ['Go section by section', 'with the arrow keys or Next section.'],
            ['Press Review and submit', 'to check your scores and send them.'],
        ],
        'related' => [
            'instructor.grading' => 'Back to Team Grading',
        ],
        'tip' => 'Save draft keeps your scores without sending them. N jumps to the next one not scored.',
    ],

    'instructor.progress' => [
        'title' => 'Milestone Progress',
        'summary' => 'How far each of your teams is, section by section.',
        'steps' => [
            ['Pick a section', 'at the top.'],
            ['Show Behind or On track', 'or find a team by name.'],
        ],
        'tour' => [
            ['Sections', 'Click a section to see its teams.', '.ms-sec', 'one'],
            ['Show', 'All teams, those behind, or those on track.', '.ms-ftab', 'one'],
            ['Find a team', 'Shows the teams that match the name.', '.ms-search'],
            ['Each team', 'Where each team is on its milestones.', 'table.ms-table'],
            ['A team', 'Click it to see its milestones and requirements. Press the section name at the top to come back.', '.ms-tname', 'one'],
        ],
        'related' => [
            'instructor.teams' => 'Back to Teams',
        ],
    ],

    'instructor.schedule' => [
        'title' => 'Consultations',
        'summary' => 'Your consultation slots, and the sessions teams booked with you.',
        'steps' => [
            ['Press a day on the calendar', 'to add a slot teams can book.'],
            ['Look at the colours', 'for consultations, bookings and defenses.'],
            ['Check Sessions', 'for what is coming up.'],
        ],
        'tour' => [
            ['Your availability', 'Every slot you set, on each day it falls. Click a day to add a slot for that date, or a slot to delete it, skip one week or mark it unavailable.', '.cal-card'],
            ['Previous and next', 'Moves the calendar back or forward.', '.cal-nav-btn', 'one'],
            ['Month, Week, Day', 'Changes how much the calendar shows.', '#btn-view-month'],
            ['Set availability', 'Opens your weekly hours: the session length, the date range and your days. Save availability puts the slots on the calendar, and teams can book them.', '.mode-btn:not(.view-btn)'],
            ['Legend', 'What each colour means, and how weekly slots work.', '.legend-section'],
            ['This month', 'How much of this month is taken.', '.sc-month'],
            ['Cancel requests', 'Teams asking to cancel a session. Press Approve or Reject on each one.', '#sc-requests'],
            ['Sessions', 'The consultations teams booked with you. Switch between Upcoming and Past.', '.upcoming-card'],
            ['See all', 'Opens the full list.', '#sess-all'],
        ],
        'related' => [
            'instructor.teams' => 'See your teams',
            'instructor.dashboard' => 'Back to the dashboard',
        ],
        'tip' => 'A slot with a booking can\'t be moved. Cancel the booking first.',
    ],

    'instructor.evaluations' => [
        'title' => 'My Evaluations',
        'summary' => 'How team leaders rated you after each defense their team passed.',
        'steps' => [
            ['Read your rating', ': the average of the stars you were given.'],
            ['Look at each question', 'to see where you rate high or low.'],
        ],
        'tour' => [
            ['How it works', 'After each defense a team passes, its leader rates you 1 to 5 stars. No team or student is shown.', '.ev-head'],
            ['No ratings to show', 'Your rating shows here once enough team leaders have rated you.', '.ev-none'],
            ['Which defense', 'Click one to see only that defense\'s ratings.', '.ev-filter'],
            ['Your rating', 'Your average, and how the stars split.', '.ev-overview'],
            ['By defense', 'Your rating for each defense.', '.ev-bystage'],
            ['By question', 'How you did on each question.', '#ev-q-h'],
            ['Responses', 'What team leaders wrote.', '#ev-r-h'],
        ],
        'related' => [
            'instructor.teams' => 'See your teams',
        ],
        'tip' => 'Every response is anonymous.',
    ],

    'instructor.profile' => [
        'title' => 'Profile',
        'summary' => 'Your name, your team slots, your research areas and your emails.',
        'steps' => [
            ['Set your team slots', 'for each program. Leave a program at 0 if you do not take it.'],
            ['Add your research areas', 'so teams are matched to you.'],
            ['Choose your emails', ', then press Save in the bar at the bottom.'],
        ],
        'tour' => [
            ['You', 'Your name, email and roles. Roles are set by the administrator.', '.pp-id'],
            ['Degree', 'Shows beside your name on your profile.', '#pp-degree'],
            ['Edit name', 'Opens your name so you can change it.', '#pp-name-btn'],
            ['Team slots', 'Type how many teams you take from each program. Leave it at 0 for a program you don\'t take.', '.pp-progs'],
            ['Your areas', 'Click a tab to see your research areas by group. Press the x on an area to take it off.', '.pp-tabs'],
            ['Add an area', 'Search for one, or type your own and add it. Teams are matched to you by these.', '.pp-finder'],
            ['Strengths', 'The CaPSA sees these when seating a defense panel. Tick one or more.', '.pp-strengths'],
            ['Email notifications', 'What also goes to your email. Everything always shows in the bell.', '.pp-aside'],
            ['Save', 'This appears when you change something. Save keeps it; Discard puts it back.', '.pp-save'],
        ],
        'related' => [
            'instructor.teams' => 'See your teams',
            'instructor.schedule' => 'Set your consultation slots',
        ],
        'tip' => 'Your photo comes from your Google account.',
    ],

    // ── CaPSA (the capstone subject adviser) ──

    'capsa.dashboard' => [
        'title' => 'Dashboard',
        'summary' => 'Your sections at a glance: what is waiting on you, what is coming up, and what just happened.',
        'steps' => [
            ['Read the tiles', 'for teams, students, requirements done and the defense pass rate.'],
            ['Check Pending Issues', 'to see what is waiting on you.'],
            ['Press View all', 'to open the full list behind a card.'],
        ],
        'tour' => [
            ['Your sections in numbers', 'Teams, students, requirements done and the defense pass rate.', '.db-tiles'],
            ['Team and expert coverage', 'How many teams have all their experts, and whether you are short of any.', '.db-row-2 .db-card'],
            ['Pending issues', 'What is waiting on you. Press a row to open it.', '#issues'],
            ['Upcoming deadlines', 'The next defenses and requirements.', '.db-tabs'],
            ['Recent activity', 'The latest team changes in your sections.', '.db-row-3 .db-card:nth-child(2)'],
            ['Quick actions', 'Shortcuts to the pages you open most.', '.db-qa-dark'],
        ],
        'related' => [
            'capsa.team_management' => 'Open Team Management',
            'capsa.defenses' => 'Schedule defenses',
            'capsa.analytics' => 'Open Analytics',
        ],
        'tip' => 'Press a tile to open the page behind it.',
    ],

    'capsa.announcements' => [
        'title' => 'Announcements',
        'summary' => 'Post news to your students and experts, and see what was posted.',
        'steps' => [
            ['Write a post', 'in New announcement: a title, the message and who it is for.'],
            ['Press Post Announcement', 'to send it. It shows in their bell.'],
            ['Use Edit or Delete', 'on a post you made.'],
        ],
        'tour' => [
            ['Posts', 'Every announcement, newest first.', '#announcements-list'],
            ['Search', 'Finds a post by its words.', '#ann-search'],
            ['Show', 'All posts, only yours, or only those for students, experts or one team.', '#ann-show'],
            ['Edit', 'Puts the post in the form so you can change it.', 'button.ann-btn.sm:not(.del)', 'one'],
            ['Delete', 'Asks you to confirm, then removes the post.', '.ann-btn.sm.del', 'one'],
            ['New announcement', 'Write it here and pick who sees it.', '#announcement-form-card'],
            ['Title', 'The heading of your post.', '#ann-title'],
            ['Message', 'What you want to say. Type @ to name one team.', '#ann-message'],
            ['For', 'Who sees it.', '#ann-for'],
            ['Post', 'Posts it for the people you picked.', '#ann-post-btn'],
        ],
        'related' => [
            'capsa.dashboard' => 'Back to the dashboard',
        ],
        'tip' => 'Deleting a post removes it for everyone who got it.',
    ],

    'capsa.experts' => [
        'title' => 'Expert Management',
        'summary' => 'The advisers, consultants and grammarians on your panel, and their slots.',
        'steps' => [
            ['Filter by role or status', 'to find an expert fast.'],
            ['Search', 'by name or research area.'],
            ['Press Add Expert or Upload List', 'to bring in new experts.'],
        ],
        'tour' => [
            ['Your panel', 'How many experts you have.', '.page-head'],
            ['View Report', 'Opens the expert list as a report.', 'a.tk-btn-ghost[href*="expert_list"]'],
            ['Download Excel', 'Asks which columns you want, then downloads the list.', '[onclick*="openExpertExportModal"]'],
            ['Upload List', 'Adds the whole faculty from one file.', '[onclick*="openExpertRosterModal"]'],
            ['Add Expert', 'Opens a form to add one person.', '[onclick*="openAddExpertModal"]'],
            ['Haven\'t signed in', 'Shows the experts who have not signed in yet.', '#tkShowAwaiting'],
            ['Role', 'Show one role at a time.', '#experts-role-filter'],
            ['Search', 'Find an expert by name or research area.', 'label.experts-search'],
            ['Status', 'Online, full, offline, or not signed in yet.', '#statusFilter'],
            ['The list', 'Each expert, their roles, research areas and slots.', 'table.table'],
            ['Tick to pick', 'Tick experts and a bar offers Set roles and Deactivate for all of them.', 'table.table tbody input[type="checkbox"]', 'one'],
            ['More roles', 'Shows the rest of that expert\'s roles.', '.tk-more', 'one'],
            ['The row\'s menu', 'Set roles, mark or end study leave, copy the email of someone not signed in, deactivate or reactivate. Never on your own row.', '.rowmenu-btn', 'one'],
        ],
        'related' => [
            'capsa.sections' => 'See your students',
            'capsa.reports' => 'Get the expert list report',
        ],
        'tip' => 'Download Excel gives you the list as it is filtered.',
    ],

    'capsa.sections' => [
        'title' => 'Student Section List',
        'summary' => 'Every student in your sections, their team and whether they have signed in.',
        'steps' => [
            ['Filter', 'to students without a team, not signed in, or enrolled.'],
            ['Search', 'by name or student number.'],
            ['Press Add Student or Upload List', 'to add students to a section.'],
        ],
        'tour' => [
            ['Your students', 'How many students are in your sections.', '.page-head'],
            ['Sections', 'Click one to show only that section.', 'a.tk-secf', 'one'],
            ['View Report', 'Opens this list as a report.', '.page-head button.tx-btn', 'one'],
            ['Download Excel', 'Asks which columns you want, then downloads the list.', '.page-head button.tx-btn + button.tx-btn', 'one'],
            ['Upload List', 'Adds students from one file.', '[onclick*="Roster"], [onclick*="Upload"]', 'one'],
            ['Add Student', 'Opens a form to add one student.', '[onclick*="AddStudent"]'],
            ['Show', 'All, without a team, not signed in, or enrolled.', '#team-filter'],
            ['Search', 'Find a student by name or number.', '#student-search'],
            ['Program', 'Shows one program.', '#program-filter'],
            ['The list', 'Each student, their section, team and status.', 'table.table'],
            ['Tick to pick', 'Tick students and a bar offers Set program, Transfer section and Remove from section.', 'table.table tbody input[type="checkbox"]', 'one'],
            ['The row\'s menu', 'Edit student details, or remove the student from the section.', '.rowmenu-btn', 'one'],
        ],
        'related' => [
            'capsa.team_management' => 'Open Team Management',
            'capsa.experts' => 'See your experts',
        ],
        'tip' => 'Year Promotion moves your students up a year at the end of the school year.',
    ],

    'capsa.team_management' => [
        'title' => 'Team Management',
        'summary' => 'Verify teams, check their papers, and follow their milestones and ratings.',
        'steps' => [
            ['Start with To do', 'for teams to verify and papers to check. Days waiting show in red.'],
            ['Open Teams', 'and press a team to see its members, experts and progress.'],
            ['Open Milestones', 'to set up and follow what each team must finish.'],
        ],
        'tour' => [
            ['What is waiting', 'How much is to do, how long it has waited, and your teams.', '.hub-head'],
            ['Your slots', 'Teams you have and teams you can still take, per program. Change the numbers on your Profile.', '.slots'],
            ['Team rules', 'Opens the rules for your sections: the most members per program, and whether students can create or join teams. Press Save to apply them.', '#setBtn'],
            ['The lists', 'To do, Team Applications, Teams, Milestones and Team Ratings.', '.hub-tabs'],
            ['Pending or Completed', 'Pending is what still needs you. Completed is what you already settled; search it and press Open to see one.', '.seg'],
            ['See all', 'Opens every team that applied to you, in Team Applications.', '.see-all'],
            ['Something to do', 'What it is, which team, and how long it has waited.', '.todo li[data-item]', 'one'],
            ['The team\'s name', 'Opens that team\'s page.', '.todo a.team', 'one'],
            ['Read', 'Opens what the team sent, so you can read it before you answer.', '.todo .btn.rd', 'one'],
            ['Decline', 'Asks for your reason, and says what will happen, before anything is sent. The team sees the reason.', '.todo [data-decide="decline"]', 'one'],
            ['Approve', 'Shows what will happen and asks you to confirm. The team is told.', '.todo [data-decide="approve"]', 'one'],
            ['The main button', 'Goes to where this is done, like a team to verify or a paper to check.', '.todo a.btn.p', 'one'],
            ['See overdue teams', 'With nothing to do, this opens Teams showing the teams that are behind.', 'a[data-quick-go="behind"]'],
            ['Team Applications', 'Click it to see the teams asking for you.', '.hub-tabs [data-hub-tab="applications"]'],
            ['Teams', 'Click it to see every team in your sections.', '.hub-tabs [data-hub-tab="teams"]'],
            ['Milestones', 'Click it to see this program\'s list of milestones.', '.hub-tabs a[href*="tab=milestone_list"]'],
            ['Team Ratings', 'Click it to see how members rate each other.', '.hub-tabs a[href*="tab=team_ratings"]'],
        ],
        'tabs' => $capsaHubTabs,
        'related' => [
            'capsa.sections' => 'See your students',
            'capsa.defenses' => 'Schedule defenses',
        ],
        'tip' => 'Team rules sets how many members a team may have.',
    ],

    'capsa.team_management.show' => [
        'title' => 'Team',
        'summary' => 'One team: its members, experts, progress, papers and activity.',
        'steps' => [
            ['Check the members and experts', 'on the left.'],
            ['Follow Progress', 'to see the milestones done.'],
            ['Press Verify team', 'once its titles are settled.'],
        ],
        'tour' => [
            ['The team', 'Its title, section and what is due next.', '.tp-head'],
            ['Previous and next team', 'Moves to the team before or after this one.', 'a.btn.btn-o', 'one'],
            ['First thing waiting', 'Goes to what this team needs from you first, like Verify team. The rest are in To do.', '.btn.btn-p', 'one'],
            ['People', 'The members, the team\'s experts and its defense panel.', 'div.tp-panel'],
            ['Progress', 'Every milestone in order.', 'section.tp-panel', 'one'],
            ['A milestone', 'Click one to see its requirements.', 'a[href*="milestone="]', 'one'],
            ['See all', 'Opens the rest of the list.', '.tp-more[data-tp-more]'],
            ['Titles, papers and activity', 'Click a heading to switch between the titles the team sent, its papers and what it did lately.', '.tp-tabs'],
            ['See all', 'Opens one page with every paper or update.', 'a.tp-more[href*="/papers"], a.tp-more[href*="/activity"]', 'one'],
        ],
        'related' => [
            'capsa.team_management' => 'Back to Team Management',
        ],
        'tip' => 'Use the arrows at the top to go to the next team.',
    ],

    'capsa.team_management.log' => [
        'title' => 'Team papers and activity',
        'summary' => 'Every paper the team sent, or everything the team did, in one list.',
        'tour' => [
            ['The team', 'Which team this is, and how many there are.', '.th'],
            ['Back', 'Returns to the team\'s page.', 'a.btn.btn-o', 'one'],
            ['The list', 'Every paper or update, newest first.', 'section.tp-panel'],
        ],
        'steps' => [
            ['Read down the list', 'newest first.'],
            ['Press a paper', 'to open it.'],
        ],
        'related' => [
            'capsa.team_management' => 'Back to Team Management',
        ],
    ],

    'capsa.team_management.pre_proposals' => [
        'title' => 'Pre-proposals',
        'summary' => 'The titles the team proposed, and your decision on each.',
        'steps' => [
            ['Press a title', 'to read its form and similarity report.'],
            ['Filter', 'to recommended or rejected titles.'],
            ['Press Verify Team', 'once every title has a decision.'],
        ],
        'tour' => [
            ['Verify this team', 'Ready once every title has a decision and the team has one final title. If you recommend two, the team picks first.', 'section.card.vfb'],
            ['Verify Team', 'Settles the team\'s members and title for the semester, and makes it eligible for a panel.', '.vfb .btn.btn-p'],
            ['The titles', 'Each proposed title and its decision.', 'section.card.ppc'],
            ['Search', 'Finds a title.', '#ppq'],
            ['Show', 'All titles, only the recommended ones, or only the rejected ones.', '[data-f="all"]'],
            ['A title', 'Opens the title, with its form and similarity report.', 'section.card.ppc a.rw', 'one'],
        ],
        'related' => [
            'capsa.team_management' => 'Back to Team Management',
        ],
    ],

    'capsa.team_management.title' => [
        'title' => 'Pre-proposal title',
        'summary' => 'One proposed title: its form and the studies most like it.',
        'steps' => [
            ['Read the form', 'or press View original PDF.'],
            ['Check the similarity report', 'for studies that are close to it.'],
            ['Press a similar study', 'to see why it matched.'],
        ],
        'tour' => [
            ['View original PDF', 'Opens the form the team uploaded.', '#pdf-open'],
            ['Recommend', 'Asks you to confirm, with a suggestion if you want one. One recommended title becomes theirs. Recommend a second and the team picks between them.', '#rec-open'],
            ['Reject', 'Asks for your reason, which is sent to the team.', '#rej-open'],
            ['Pre-proposal form', 'What the team wrote about this title.', 'section.card.ppd:not(.rep)'],
            ['A tag', 'Opens Analytics showing that research area.', 'a[href*="analytics?tag="]', 'one'],
            ['Similarity report', 'The closest studies and how close each one is.', 'section.card.ppd.rep'],
            ['A similar study', 'Opens the study, to compare it with this title.', '.rep a.rw', 'one'],
        ],
        'related' => [
            'capsa.archive' => 'Search the archive',
        ],
    ],

    'capsa.team_management.similar' => [
        'title' => 'Similar study',
        'summary' => 'One study close to the proposed title, and why it matched.',
        'steps' => [
            ['Read Why it matched', 'for what the two have in common.'],
            ['Read their abstract', 'to judge it yourself.'],
            ['Use Previous and Next', 'to go through the other similar studies.'],
        ],
        'tour' => [
            ['The study', 'Its title and how close it is.', '.th.tpg'],
            ['Why it matched', 'What it shares with the proposed title, and its abstract.', '.card.ppd'],
            ['Previous study', 'Opens the closer study before this one.', 'a.btn.btn-o.pv'],
            ['Next study', 'Opens the next study on the list.', 'a.btn.btn-o.nx'],
        ],
    ],

    'capsa.team_management.milestones.show' => [
        'title' => 'Milestone',
        'summary' => 'One milestone and the requirements teams must finish in it.',
        'steps' => [
            ['Read the requirements', 'teams must hand in for it.'],
            ['Press Add requirement', 'to add one.'],
            ['Press Mark finished', 'when the milestone is over.'],
        ],
        'tour' => [
            ['The milestone', 'Its name and due date.', '.ms-head'],
            ['Edit milestone', 'Opens its name, description and due date.', 'a.ms-btn', 'one'],
            ['Open or finish', 'Opens it to teams, marks it finished, or reopens it, for the whole section.', '#ml-close'],
            ['Requirements', 'What teams must hand in for it.', 'section.ms-card'],
            ['Add requirement', 'Opens a form for a new requirement.', 'a.ms-btn.p.sm'],
            ['Move a requirement', 'Drag it, or use the up and down arrow keys, to change the order.', '#ml-reqs .ml-grip', 'one'],
            ['A requirement', 'Opens it to edit.', 'a.ms-req-name', 'one'],
            ['Show to teams', 'Brings back a requirement you hid.', '[data-show]', 'one'],
            ['Edit', 'Opens that requirement, the same as clicking its name.', '#ml-reqs a.ms-btn.sm', 'one'],
            ['Teams', 'How far each team is on this milestone.', '#ms-teams-h'],
            ['Open in Milestones', 'Opens every team\'s progress, to check their papers.', 'a.ms-btn[href*="tab=milestones"]'],
        ],
        'related' => [
            'capsa.team_management' => 'Back to Team Management',
        ],
    ],

    'capsa.team_management.milestones.create' => [
        'title' => 'New milestone',
        'summary' => 'Add a milestone teams must reach.',
        'steps' => [
            ['Fill in the name and due date', 'and a short description.'],
            ['Pick its place in the list', 'so teams see the milestones in order.'],
            ['Press Create milestone', 'then add its requirements.'],
        ],
        'tour' => [
            ['Name', 'What teams see.', '.mf-inp[name="name"]'],
            ['Description', 'Optional. A short note for teams.', '[name="description"]'],
            ['Due date', 'The day it is due.', '[name="due_on"]'],
            ['Needs the one before', 'When ticked, teams finish the milestone before this one first.', '[name="requires_previous"]'],
            ['Hide', 'Keeps it from teams without losing their progress.', '[name="archived"]'],
            ['Delete milestone', 'Asks you to confirm. Its requirements go with it, and teams lose their progress on them. To keep that progress, hide it instead.', '#ml-delete'],
            ['Cancel', 'Leaves without saving.', 'a.mf-btn'],
            ['Save', 'Saves the milestone for every team in this list.', '.mf-btn.p'],
        ],
    ],

    'capsa.team_management.milestones.edit' => [
        'title' => 'Edit milestone',
        'summary' => 'Change a milestone, or delete it.',
        'steps' => [
            ['Change what you need', 'then press Save changes.'],
            ['Press Delete milestone', 'only if no team should do it anymore.'],
        ],
        'tour' => [
            ['Name', 'What teams see.', '.mf-inp[name="name"]'],
            ['Description', 'Optional. A short note for teams.', '[name="description"]'],
            ['Due date', 'The day it is due.', '[name="due_on"]'],
            ['Needs the one before', 'When ticked, teams finish the milestone before this one first.', '[name="requires_previous"]'],
            ['Hide', 'Keeps it from teams without losing their progress.', '[name="archived"]'],
            ['Delete milestone', 'Asks you to confirm. Its requirements go with it, and teams lose their progress on them. To keep that progress, hide it instead.', '#ml-delete'],
            ['Cancel', 'Leaves without saving.', 'a.mf-btn'],
            ['Save', 'Saves the milestone for every team in this list.', '.mf-btn.p'],
        ],
    ],

    'capsa.team_management.milestones.setup' => [
        'title' => 'Set up milestones',
        'summary' => 'Start a program with its milestones and requirements.',
        'tour' => [
            ['Where to start', 'Start from the department\'s list, or empty.', '#ml-setup'],
            ['What teams will see', 'A preview of the list.', '#su-pv-h'],
            ['Set up milestones', 'Creates the list for this program.', '.su-btn.p'],
        ],
        'steps' => [
            ['Look over the milestones', 'and change what your program needs.'],
            ['Save', 'to give them to every team in the program.'],
        ],
    ],

    'capsa.team_management.milestones.requirements.create' => [
        'title' => 'New requirement',
        'summary' => 'Add something teams must hand in for this milestone.',
        'steps' => [
            ['Name it and give a due date', 'with instructions for the team.'],
            ['Press Add requirement', 'to save it.'],
        ],
        'tour' => [
            ['Title', 'What teams see.', '[name="title"]'],
            ['Due date', 'The day it is due.', '[name="due_date"]'],
            ['Instructions', 'What teams need to do.', '[name="instructions"]'],
            ['Type', 'Upload file, or Defense for a requirement that a defense completes.', '[name="type"]'],
            ['Which defense', 'The defense that completes this requirement.', '#ml-stage'],
            ['Remove requirement', 'Asks you to confirm. Teams lose their progress on it. To keep that, hide it instead.', '#ml-remove'],
            ['Hide from teams', 'Hides it, or shows it again, without losing their progress.', '#ml-hide'],
            ['Cancel', 'Leaves without saving.', 'a.rq-btn'],
            ['Save', 'Saves the requirement and returns to the milestone.', '.rq-btn.p'],
        ],
    ],

    'capsa.team_management.milestones.requirements.edit' => [
        'title' => 'Edit requirement',
        'summary' => 'Change a requirement, hide it from teams, or remove it.',
        'steps' => [
            ['Change what you need', 'then press Save requirement.'],
            ['Press Hide from teams', 'to keep it but stop showing it.'],
            ['Press Remove requirement', 'to take it out.'],
        ],
        'tour' => [
            ['Title', 'What teams see.', '[name="title"]'],
            ['Due date', 'The day it is due.', '[name="due_date"]'],
            ['Instructions', 'What teams need to do.', '[name="instructions"]'],
            ['Type', 'Upload file, or Defense for a requirement that a defense completes.', '[name="type"]'],
            ['Which defense', 'The defense that completes this requirement.', '#ml-stage'],
            ['Remove requirement', 'Asks you to confirm. Teams lose their progress on it. To keep that, hide it instead.', '#ml-remove'],
            ['Hide from teams', 'Hides it, or shows it again, without losing their progress.', '#ml-hide'],
            ['Cancel', 'Leaves without saving.', 'a.rq-btn'],
            ['Save', 'Saves the requirement and returns to the milestone.', '.rq-btn.p'],
        ],
    ],

    'capsa.defenses' => [
        'title' => 'Defenses',
        'summary' => 'Give each team a panel, then a date, for every defense.',
        'steps' => [
            ['Pick the defense', 'at the top: Pre-proposal, Title, Pre Oral or Final Oral.'],
            ['Set the window', 'the days that defense can be held.'],
            ['Finalize each panel', 'then press Generate schedule to give every ready team a date.'],
        ],
        'tour' => array_merge($boardTop, $boardReady, $boardGoPanel, $boardGoSched),
        'tabs' => $boardPanelTab + $boardSchedTab,
        'related' => [
            'capsa.team_management' => 'Open Team Management',
            'capsa.rubric_management' => 'Set the grading rubrics',
        ],
        'tip' => 'Press / to jump to the search.',
    ],

    'capsa.defense_scheduling' => [
        'title' => 'Defense scheduling',
        'summary' => 'Give every team that is ready a date for its defense.',
        'steps' => [
            ['Pick the defense', 'at the top.'],
            ['Check the window', 'the days that defense can be held.'],
            ['Press Generate schedule', 'to give every ready team a date.'],
        ],
        'tour' => array_merge($boardTop, $boardReady, $boardGoPanel, $boardGoSched),
        'tabs' => $boardPanelTab + $boardSchedTab,
        'related' => [
            'capsa.defenses' => 'Open Defenses',
        ],
        'tip' => 'Press / to jump to the search.',
    ],

    'capsa.panel_assignment' => [
        'title' => 'Panel assignment',
        'summary' => 'Give each team its panel for the defense.',
        'steps' => [
            ['Pick the defense', 'at the top.'],
            ['Pick the panelists', 'for each team. A panel starts as a draft.'],
            ['Finalize the panel', 'once it is set, so the team can get a date.'],
        ],
        'tour' => array_merge($boardTop, $boardPanel, $boardGoReady, $boardGoSched),
        'tabs' => $boardReadyTab + $boardSchedTab,
        'related' => [
            'capsa.defenses' => 'Open Defenses',
        ],
        'tip' => 'Press / to jump to the search.',
    ],

    'capsa.rubric_management' => [
        'title' => 'Grading Rubrics',
        'summary' => 'The rubrics panelists grade each defense with.',
        'steps' => [
            ['Pick a defense', 'to see its rubrics.'],
            ['Press Open', 'to change a rubric and its categories.'],
            ['Press New Rubric', 'to make one.'],
        ],
        'tour' => [
            ['The defenses', 'How many rubrics each defense has. Click one to show only its rubrics.', 'button.mk-tile', 'one'],
            ['New Rubric', 'Opens a choice: start blank, or have AI draft one.', '.mk-btn.pri.big'],
            ['Search', 'Finds a rubric by name.', '#rb-search'],
            ['Show archived', 'Shows the archived rubrics too.', '#rb-show-archived'],
            ['The rubrics', 'Each rubric and the defense it is for.', '#rb-list'],
            ['Open', 'Opens the rubric to change it.', '#rb-list .mk-btn:not(.ghost)', 'one'],
            ['Archive or Restore', 'Asks you to confirm. Archive takes it out of use; Restore brings it back.', '#rb-list .mk-btn.ghost', 'one'],
        ],
        'related' => [
            'capsa.defenses' => 'Schedule defenses',
        ],
        'tip' => 'An archived rubric is kept for grades already given with it.',
    ],

    'capsa.evaluations' => [
        'title' => 'Expert Evaluations',
        'summary' => 'After a team passes a defense, its leader rates the team experts.',
        'steps' => [
            ['Press Create new evaluation', 'to set the questions for a defense.'],
            ['Read the results', 'for each expert once leaders answer.'],
        ],
        'tour' => [
            ['How it works', 'Leaders answer after their team passes a defense.', '.ev-head'],
            ['Create new evaluation', 'Opens a form for a defense and its questions.', 'a.ev-btn.gold'],
            ['Evaluations or Results', 'Results shows what leaders answered.', '.ev-tabs'],
            ['Needs an evaluation', 'This defense has none yet. Use the 5 standard questions, or write your own.', '.ev-req', 'one'],
            ['See the questions', 'Opens the list of questions.', 'summary', 'one'],
            ['Edit', 'Opens the evaluation to change it.', 'a.ev-btn.sm', 'one'],
            ['Turn off', 'Asks you to confirm. Leaders who still owe it won\'t be asked any more. What was answered stays in the results.', '.ghost-danger', 'one'],
            ['Turned off', 'Copy one into a new evaluation, or delete it. Its answers stay in the results.', '.ev-offs'],
            ['Results', 'Click it to see what leaders answered.', '.ev-tabs a[href*="tab=results"]'],
        ],
        'tabs' => [
            'results' => ['button' => '.ev-tabs a[href*="tab=results"]', 'tour' => [
                ['Results', 'What leaders answered about their experts. The number beside it is how many answers came in.', '.ev-tabs a[aria-current="page"][href*="tab=results"]'],
                ['Overall', 'How leaders answered, across every expert.', '.ev-sum-byq'],
                ['By expert', 'One line per expert.', '.ev-sum-by'],
                ['An expert', 'Opens what leaders said about that expert.', 'a.ev-sum-x', 'one'],
            ]],
        ],
        'related' => [
            'capsa.experts' => 'See your experts',
        ],
    ],

    'capsa.evaluations.create' => [
        'title' => 'New evaluation',
        'summary' => 'Set the questions team leaders answer about their experts.',
        'steps' => [
            ['Pick the defense', 'and give it a title.'],
            ['Add questions', 'or press Use the 5 standard questions.'],
            ['Press Create evaluation', 'to save it.'],
        ],
        'tour' => $evaluationForm,
    ],

    'capsa.evaluations.edit' => [
        'title' => 'Edit evaluation',
        'summary' => 'Change the questions team leaders answer about their experts.',
        'steps' => [
            ['Change the questions', 'you need.'],
            ['Save', 'when you are done.'],
        ],
        'tour' => $evaluationForm,
    ],

    'capsa.evaluations.expert' => [
        'title' => 'Expert results',
        'summary' => 'How team leaders rated one expert.',
        'tour' => [
            ['Answers', 'How teams answered each question, by role, and what they said.', 'section.ev-lk-card'],
        ],
        'steps' => [
            ['Read the scores', 'for each question.'],
            ['Read the comments', 'the leaders left.'],
        ],
        'related' => [
            'capsa.evaluations' => 'Back to Expert Evaluations',
        ],
    ],

    'capsa.financial' => [
        'title' => 'Financial Records',
        'summary' => 'The fee receipts students upload, for you to verify or send back.',
        'steps' => [
            ['Pick a fee', 'or press + New fee to post one.'],
            ['Show Waiting', 'for receipts to check.'],
            ['Press a receipt', 'to see it, then verify it or send it back.'],
        ],
        'tour' => [
            ['The fee', 'How much each member pays, and how many are verified.', '.fx-summary'],
            ['Which fee', 'Click a fee to see its receipts.', '#c-fees'],
            ['New fee', 'Opens a form for the defense and the amount per member.', '#c-new-fee'],
            ['Show', 'Waiting, sent back, not uploaded or verified.', '#c-chips'],
            ['Team', 'Shows one team.', '#c-team'],
            ['Search', 'Finds a member.', '#c-search'],
            ['View Report', 'Opens this list as a report you can download.', 'button.tx-btn', 'one'],
            ['Download Excel', 'Asks which columns you want, then downloads the list.', 'button.tx-btn + button.tx-btn', 'one'],
            ['Tick to pick', 'Tick members and a bar lets you verify them all at once, after you confirm.', '#c-all'],
            ['Receipts', 'Each member and their receipt. Click one to see it on the side.', '#c-rows'],
            ['Verify or send back', 'Verify accepts the receipt. Send back asks for your confirmation and returns it to the member.', '#c-insp'],
            ['Send to Dean', 'Sends the verified receipts to the dean after you confirm. A sent receipt can no longer change.', '#c-send'],
        ],
        'related' => [
            'capsa.reports' => 'Get a report',
        ],
        'tip' => 'A receipt sent back goes to the student with your reason.',
    ],

    'capsa.archive' => [
        'title' => 'Archive',
        'summary' => 'Finished capstones, to search and read.',
        'steps' => [
            ['Search', 'by title, author or keyword.'],
            ['Filter', 'by category or school year.'],
            ['Press View Abstract', 'to read one.'],
        ],
        'tour' => [
            ['Add Capstone', 'Opens a form to add a finished capstone.', '#topbar-action'],
            ['Waiting to publish', 'Teams sent these. View Abstract reads one; Publish to Archive adds it; Decline turns it down.', '[onclick*="decideSubmission"]', 'one'],
            ['Search', 'Find a capstone by title, author or keyword.', '#arch-search'],
            ['Year', 'Shows one year.', '#arch-year'],
            ['Category', 'Show one category at a time.', '#arch-tag'],
            ['The capstones', 'Each finished capstone.', '#arch-grid'],
            ['A title', 'Opens the capstone.', '.archive-title-text.is-link', 'one'],
            ['View', 'Opens its abstract.', '.arch-view', 'one'],
            ['Tags', 'Changes its categories.', '[onclick*="editTags"]', 'one'],
            ['Remove', 'Asks you to confirm, then takes it off the archive.', '[onclick*="deleteArchive"]', 'one'],
        ],
        'related' => [
            'capsa.team_management' => 'Open Team Management',
        ],
    ],

    'capsa.resources' => [
        'title' => 'Resources',
        'summary' => 'Templates and files you share with your students, kept in Google Drive.',
        'steps' => [
            ['Press Upload files', 'to share a file.'],
            ['Filter', 'by category, section or folder.'],
            ['Select files', 'to act on several at once.'],
        ],
        'tour' => [
            ['Google Drive', 'Connecting Drive lets you share files from it.', '#gdrive-banner'],
            ['Connect', 'Opens Google to link your Drive.', '#gdrive-connect-btn'],
            ['Disconnect', 'Unlinks your Drive.', '#gdrive-disconnect-btn'],
            ['Search', 'Finds a file by name.', '#search-input'],
            ['Category', 'Shows one category.', '#category-filter'],
            ['Section', 'Shows the files for one section.', '#section-filter'],
            ['Folder', 'Shows one folder.', '#folder-filter'],
            ['Sort', 'Changes the order of the list.', '#sort-filter'],
            ['Upload', 'Opens a form to add a file.', '#open-upload-btn'],
            ['Tick to pick', 'Tick files and a bar offers Remove from CapsTrack.', '#lib-all'],
            ['Your files', 'Everything you uploaded. Click a row for its details.', '#resource-list'],
        ],
        'related' => [
            'capsa.announcements' => 'Post an announcement',
        ],
        'tip' => 'Files are kept in your connected Google Drive.',
    ],

    'capsa.reports' => [
        'title' => 'Reports',
        'summary' => 'Ready-made reports on your teams, experts, defenses and fees.',
        'steps' => [
            ['Pick the school year and semester', 'at the top.'],
            ['Press Open', 'to see a report on screen.'],
            ['Press PDF or Excel', 'to download it.'],
        ],
        'tour' => [
            ['Period', 'The school year and semester the reports cover.', '#period'],
            ['The reports', 'What each one has in it, and how to get it.', 'section.rp-list'],
            ['Open', 'Opens the report on screen.', '.rp-btn.dark', 'one'],
            ['PDF', 'Downloads it as a PDF.', '.rp-btn.gold', 'one'],
            ['Excel', 'Downloads it as an Excel file.', '.rp-btn:not(.dark):not(.gold)', 'one'],
        ],
        'related' => [
            'capsa.analytics' => 'Open Analytics',
        ],
    ],

    'capsa.reports.show' => [
        'title' => 'Report',
        'summary' => 'One report on screen, to check before you download it.',
        'steps' => [
            ['Press Columns', 'to choose what shows.'],
            ['Press Preview PDF', 'to see the printed page.'],
            ['Press Download', 'to save it.'],
        ],
        'tour' => [
            ['Back', 'Returns to Reports, or to the page you came from.', '.crumbs a'],
            ['Which report', 'Switches to another report.', '.rp-tabs, #rp-pick'],
            ['Period', 'The school year and semester.', '#period'],
            ['Search', 'Finds a row.', '#rp-q'],
            ['Columns', 'Picks which columns show. Reset puts them back.', '#cols-btn'],
            ['Merge', 'Joins repeated cells, as they will print.', '#merge-btn'],
            ['Preview PDF', 'Shows the PDF before you download it.', '#preview-btn'],
            ['Download', 'Gets it as a PDF or Excel file.', '#dl-btn'],
            ['The report', 'Its rows, as they will print. Click a heading to sort.', '#rp-table'],
        ],
        'related' => [
            'capsa.reports' => 'Back to Reports',
        ],
    ],

    'capsa.analytics' => [
        'title' => 'Analytics',
        'summary' => 'How your teams do at each defense, in charts and by team.',
        'steps' => [
            ['Pick the school year', 'and other filters at the top.'],
            ['Read the charts', 'or press View as table.'],
            ['Scroll to Team by team', 'for each team at each defense.'],
        ],
        'tour' => [
            ['Export report', 'Opens the Analytics report you can download.', 'a.btn-ghost-nav'],
            ['Filters', 'The school year, program and section to count.', '#an-filters'],
            ['What to count', 'Click one to change what the page counts.', 'a.an-chip', 'one'],
            ['In numbers', 'Teams, passes and more.', '.an-kpis'],
            ['Charts', 'How many teams pass each defense.', 'section.an-fig'],
            ['View as table', 'Shows that chart as a table.', '.an-toggle', 'one'],
            ['A research area', 'Shows only that research area.', '.an-tag-link', 'one'],
            ['Find a team', 'Type part of a team\'s name.', '#an-team-search'],
            ['Team by team', 'Each team at each defense.', '#an-team-table'],
        ],
        'related' => [
            'capsa.reports' => 'Get a report',
        ],
    ],

    'capsa.activitylog' => [
        'title' => 'Activity Log',
        'summary' => 'What students did: joining or leaving teams, and more.',
        'steps' => [
            ['Filter', 'by action, then press Filter.'],
            ['Press a row', 'to see all of it.'],
            ['Press Download Excel', 'to save the list.'],
        ],
        'tour' => [
            ['Filters', 'Pick the kind of action to show, then press Filter.', 'form.log-filters'],
            ['View Report', 'Opens this log as a report you can download.', 'button.tx-btn', 'one'],
            ['Download Excel', 'Asks which columns you want, then downloads the list.', 'button.tx-btn + button.tx-btn', 'one'],
            ['The log', 'Each action, newest first.', '.list-card'],
            ['A link in the log', 'Opens what that action was about.', '.list-card a', 'one'],
        ],
        'related' => [
            'capsa.team_management' => 'Open Team Management',
        ],
    ],

    'capsa.profile' => [
        'title' => 'Profile',
        'summary' => 'Your name and details.',
        'steps' => [
            ['Fix your details', 'in Personal information.'],
            ['Press Save changes', 'when you are done.'],
        ],
        'tour' => [
            ['You', 'Your name and roles.', '.pf-hero'],
            ['Personal information', 'Your name and credentials.', '#pf-panel-details'],
        ],
        'tip' => 'Your photo comes from your Google account.',
    ],

    // ── Admin ──

    'admin.dashboard' => [
        'title' => 'Dashboard',
        'summary' => 'The whole system: every section, every program.',
        'steps' => [
            ['Read the tiles', 'for students, teams, experts, CaPSAs and the archive.'],
            ['Check Needs Attention', 'and fix each line.'],
            ['Read the cards', 'for students, experts, defenses and fees.'],
        ],
        'tour' => [
            ['In numbers', 'Students, teams, experts, CaPSAs, sections without a CaPSA and archived capstones.', '.ad-tiles'],
            ['A tile', 'Opens the page behind that number.', '.ad-tiles a.db-tile', 'one'],
            ['Students Overview', 'How many students are active, and how many each school year had.', '.ad-row-2 > .db-card:first-child'],
            ['Experts by Role', 'How many experts hold each role. An expert with two roles counts under each.', '.ad-row-2 > .db-card:last-child'],
            ['Defense Results', 'How many teams passed this school year.', '.ad-row-3 > .db-card:first-child'],
            ['Fees and Archive', 'The fees CaPSAs have sent you, and archived capstones by year.', '.ad-row-3 > .db-card:last-child'],
            ['View Details', 'Opens the page with the full numbers.', '.ad-row-3 a.db-btn-dark', 'one'],
            ['Needs Attention', 'What is missing: the school year, sections with no CaPSA, or years with no uploads.', '.ad-attn'],
            ['Fix it', 'Opens the page where you fix that line.', 'a.ad-chip', 'one'],
        ],
        'related' => [
            'admin.experts' => 'Manage CaPSAs',
            'admin.students' => 'See every student',
            'admin.settings' => 'Set the school year',
        ],
        'tip' => 'Press a tile to open the page behind it.',
    ],

    'admin.fees' => [
        'title' => 'Fees',
        'summary' => 'The fees each section\'s CaPSA has sent you.',
        'steps' => [
            ['Read a row', 'for the amount and how many receipts it covers.'],
            ['Check Status', 'to see which sections have not sent yet.'],
        ],
        'tour' => [
            ['The sections', 'Every section, what it sent, when and who sent it. A CaPSA sends after verifying the receipts.', '.list-card'],
        ],
        'related' => [
            'admin.archive' => 'Open Archived Capstones',
        ],
    ],

    'admin.archive' => [
        'title' => 'Archived Capstones',
        'summary' => 'Every finished capstone, to read. CaPSAs add and remove them.',
        'steps' => [
            ['Search', 'by title, author or keyword.'],
            ['Filter', 'by category or school year.'],
            ['Press View Abstract', 'to read one.'],
        ],
        'tour' => [
            ['Search', 'Find a capstone by title, author or keyword.', '#arch-search'],
            ['Year', 'Shows one year.', '#arch-year'],
            ['Category', 'Show one category at a time.', '#arch-tag'],
            ['The capstones', 'Each finished capstone.', '#arch-grid'],
            ['A title', 'Opens the capstone.', '.archive-title-text.is-link', 'one'],
            ['View', 'Opens its abstract.', '.arch-view', 'one'],
        ],
        'related' => [
            'admin.fees' => 'Open Fees',
        ],
    ],

    'admin.experts' => [
        'title' => 'CaPSAs',
        'summary' => 'The capstone subject advisers and the sections each one handles.',
        'steps' => [
            ['Check the sections without a CaPSA', 'at the top.'],
            ['Press Add CaPSA', 'to make one.'],
            ['Press Assign sections or Edit sections', 'to change what a CaPSA handles.'],
        ],
        'tour' => [
            ['CaPSAs', 'How many there are, and how many sections they cover.', '.page-head'],
            ['Add CaPSA', 'Opens a form. Type the email and pick sections if you want to. The account is made if it does not exist yet, and is approved straight away.', '.tk-actions .tk-btn-pri'],
            ['View Report', 'Opens this list as a report you can download.', 'button.tx-btn', 'one'],
            ['Download Excel', 'Asks which columns you want, then downloads the list.', 'button.tx-btn + button.tx-btn', 'one'],
            ['Sections without a CaPSA', 'These sections can\'t form teams until someone covers them.', '.tk-alert'],
            ['Hide or Show', 'Folds this list away, or brings it back.', '#tkCovToggle'],
            ['Assign sections', 'Opens the same form as Add CaPSA.', '.tk-alert .tk-btn-pri'],
            ['All, Active or Deactivated', 'Shows only those accounts. The page stays where it is.', '.tk-toolbar button.tk-tab', 'one'],
            ['Search', 'Finds someone by name, email or research area.', '#experts-search-input'],
            ['Program', 'Shows one program.', '#tkProgramFilter'],
            ['Tick accounts', 'Tick accounts to act on them together. A bar then offers Deactivate accounts, which asks you first.', '.rs-box', 'one'],
            ['More research areas', 'Opens this person\'s details, with every research area.', 'button.tk-more', 'one'],
            ['Edit sections', 'Opens the section picker. A section another CaPSA already holds is not listed.', 'button[title="Edit sections"]', 'one'],
            ['More options', 'Opens this person\'s options. Remove CaPSA takes away what the role can do, and making them CaPSA again undoes it. Make CaPSA opens the section picker. Deactivate stops them signing in and takes them off the lists students choose from; nothing is deleted, and Reactivate switches them back on. Remove and Deactivate ask you first.', 'button.rowmenu-btn', 'one'],
        ],
        'related' => [
            'admin.students' => 'See every student',
            'admin.settings' => 'Add sections',
        ],
    ],

    'admin.students' => [
        'title' => 'Students',
        'summary' => 'Every student in the system. Each section CaPSA adds and edits them.',
        'steps' => [
            ['Filter', 'to students in a team or without one.'],
            ['Search', 'by name or student number, or pick a program and year.'],
            ['Press Download Excel', 'to save the list.'],
        ],
        'tour' => [
            ['Students', 'How many there are.', '.page-head'],
            ['View Report', 'Opens this list as a report you can download.', 'button.tx-btn', 'one'],
            ['Download Excel', 'Asks which columns you want, then downloads the list.', 'button.tx-btn + button.tx-btn', 'one'],
            ['All, In a team or No team', 'Reloads the list with only those students. The number says how many.', '#studentFilters .tk-tabs'],
            ['Search', 'Searches by name, email or student number a moment after you stop typing.', '#studentFilters input[name="search"]'],
            ['Program', 'Reloads the list as soon as you pick one.', '#filterProgram'],
            ['Year', 'Reloads the list as soon as you pick one.', '#filterYear'],
            ['Section', 'Reloads the list as soon as you pick one.', '#filterSection'],
            ['Clear filters', 'Puts the list back to every student.', '#studentFilters a.tk-btn-ghost'],
            ['The list', 'One row per student.', 'table.std-table'],
            ['More options', 'Opens Copy email and Remove student. Remove student asks you first, then deletes the account and takes them off their team. If they lead it, the next member takes over. If no one else is on it, the team goes too.', 'button.rowmenu-btn', 'one'],
        ],
        'related' => [
            'admin.experts' => 'Manage CaPSAs',
        ],
    ],

    'admin.settings' => [
        'title' => 'Settings',
        'summary' => 'The current school year, and the sections of each program.',
        'steps' => [
            ['Set the school year', 'and press Save. Uploads and reports are stamped with it.'],
            ['Add sections', 'by picking programs and letters.'],
        ],
        'tour' => [
            ['School year', 'Type it like 2026-2027. Uploads and reports are stamped with it.', '#current_school_year'],
            ['Save', 'Saves the school year.', 'form:has(#current_school_year) .set-btn'],
            ['Programs', 'Tick the programs that get the new sections.', 'label.opt', 'one'],
            ['Letters', 'Click the first letter and the last.', '#letterStrip'],
            ['Preview', 'Shows the sections you are about to add.', '#sectionPreview'],
            ['Add sections', 'Adds them to the list.', '#addSections'],
            ['The sections', 'Every section, by program.', '.sec-group', 'one'],
            ['Remove a section', 'Takes that section off the list straight away. It does not ask first.', '.sec-item button', 'one'],
        ],
        'related' => [
            'admin.experts' => 'Give sections a CaPSA',
        ],
    ],

    'admin.analytics' => [
        'title' => 'Analytics',
        'summary' => 'How teams do at each defense, across every section.',
        'steps' => [
            ['Pick the school year', 'and other filters at the top.'],
            ['Read the charts', 'or press View as table.'],
            ['Scroll to Team by team', 'for each team at each defense.'],
        ],
        'tour' => [
            ['Export report', 'Opens the Analytics report you can download.', 'a.btn-ghost-nav'],
            ['Filters', 'The school year, program and section to count.', '#an-filters'],
            ['What to count', 'Click one to change what the page counts.', 'a.an-chip', 'one'],
            ['In numbers', 'Teams, passes and more.', '.an-kpis'],
            ['Charts', 'How many teams pass each defense.', 'section.an-fig'],
            ['View as table', 'Shows that chart as a table.', '.an-toggle', 'one'],
            ['A research area', 'Shows only that research area.', '.an-tag-link', 'one'],
            ['Find a team', 'Type part of a team\'s name.', '#an-team-search'],
            ['Team by team', 'Each team at each defense.', '#an-team-table'],
        ],
        'related' => [
            'admin.dashboard' => 'Back to the dashboard',
        ],
    ],

    'admin.activitylog' => [
        'title' => 'Activity Log',
        'summary' => 'What students did across the system: joining or leaving teams, and more.',
        'steps' => [
            ['Filter', 'by action, then press Filter.'],
            ['Press a row', 'to see all of it.'],
            ['Press Download Excel', 'to save the list.'],
        ],
        'tour' => [
            ['Filters', 'Pick the kind of action to show, then press Filter.', 'form.log-filters'],
            ['View Report', 'Opens this log as a report you can download.', 'button.tx-btn', 'one'],
            ['Download Excel', 'Asks which columns you want, then downloads the list.', 'button.tx-btn + button.tx-btn', 'one'],
            ['The log', 'Each action, newest first.', '.list-card'],
            ['A link in the log', 'Opens what that action was about.', '.list-card a', 'one'],
        ],
        'related' => [
            'admin.students' => 'See every student',
        ],
    ],

];
