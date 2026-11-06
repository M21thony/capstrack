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

return [

    // ── Student ──

    'student.dashboard' => [
        'title' => 'Dashboard',
        'summary' => 'Your home page: where your team stands, the latest announcements and what is due next.',
        'steps' => [
            ['Look at Where your team is', 'to see how much is done and what is overdue.'],
            ['Read the announcements', 'for defense dates and CCS updates.'],
            ['Check your milestones', 'further down for what comes next.'],
        ],
        'tour' => [
            ['Where your team is', 'How many requirements are done, the stage you are on, and what is overdue or waiting.', '#now-card'],
            ['Announcements', 'News from your CaPSA and CCS. Press one to read all of it.', '#announcements-card'],
            ['Your experts and team', 'Your adviser, consultant and grammarian, and your teammates.', '.dash-grid-v3'],
            ['Milestones', 'Every stage of your capstone, in order, with its requirements.', '.milestone-list'],
        ],
        'related' => [
            'student.progress' => 'See your progress',
            'student.documents' => 'Submit a paper',
            'student.team' => 'Manage your team',
        ],
        'tip' => 'Press a red overdue item to go straight to it.',
    ],

    'student.experts' => [
        'title' => 'Experts',
        'summary' => 'Choose your adviser, consultant and grammarian, and book consultations with them.',
        'steps' => [
            ['Open Find experts', 'to see who is open and matched to your project.'],
            ['Send a request', 'to the expert you want for each role.'],
            ['Press Schedule', 'on an accepted expert to book a consultation.'],
        ],
        'tour' => [
            ['Two tabs', 'Your experts shows the ones you have. Find experts lets you request new ones.', '.xh-seg'],
            ['At a glance', 'How many experts you have, upcoming consultations and pending evaluations.', '.xh-stats'],
            ['Your experts', 'Each expert with their consultations so far and the next one booked.', '.xh-mine'],
            ['Upcoming consultations', 'Every consultation you have booked, soonest first.', '.xh-upc'],
        ],
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
        'tour' => [
            ['At a glance', 'How many experts you have, upcoming consultations and pending evaluations.', '.xh-stats'],
            ['Your experts', 'Press Schedule on one of them to book a time.', '.xh-mine'],
            ['Upcoming consultations', 'Every consultation you have booked, soonest first.', '.xh-upc'],
        ],
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
            ['Your team\'s documents', 'Every paper your team has sent. Submit a paper is here too.', '.dl-head'],
            ['Filter by status', 'Show only the papers that need revision, are in review, or are approved.', '.dl-tabs'],
            ['Search', 'Find a paper by its name.', '.dl-search'],
            ['Your papers', 'Each paper with its reviewer and status. Earlier versions fold under it.', '.dl-list'],
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
            ['Check a document', 'Drop your pre-proposal PDF here and run the check.', '.duplication-form'],
            ['Suggested experts', 'Experts whose field matches your titles.', '#instructor-card'],
            ['This year', 'Titles from other teams this school year that look like yours.', '#ty-card'],
            ['From the archive', 'The ten past capstones closest to your titles.', '#matches-panel'],
            ['Your pre-proposals', 'Your titles and whether they are turned in for checking.', '.pp-card'],
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
            ['The counts', 'How many requirements are overdue, waiting and closed.', '.pt-stats'],
            ['Panel scores', 'Your defense scores and the panel\'s remarks.', '.pt-scores'],
            ['Requirements', 'Each requirement in order. Press one to open it.', '.pt-list-wrap'],
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
            ['Defenses', 'One tab for each defense your team has had.', '.ds-tabs'],
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
            ['Files', 'Every file you can download.', '#rs-list'],
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
            ['Capstones', 'Every past capstone that matches.', '#archive-grid'],
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
            ['Your pre-proposal', 'What the check read from your document.', '.rp-mine'],
            ['From the archive', 'The ten past capstones closest to yours. Open one to compare.', 'section.rp-list:not(.rp-ty)'],
            ['Suggested experts', 'Experts whose field matches your titles.', '.rp-experts'],
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
            ['This teammate', 'Who you are rating now. View full rubric explains each score.', '.trr-head'],
            ['The scores', 'One row per activity. Pick 10, 7, 5, 3 or 1.', '#trr-list'],
            ['Moving on', 'Your picks save as you go. Next takes you to the next teammate.', '.trr-foot'],
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
            ['Look at Your work at a glance', 'for your teams, reviews, consultations and defenses in numbers.'],
            ['Check Waiting on you', 'for team requests, documents to review and grades to submit.'],
            ['Read Coming up', 'for your next consultations and defenses.'],
        ],
        'tour' => [
            ['Your work at a glance', 'Teams you advise, documents reviewed, consultations held and defenses graded.', '.dc-tiles'],
            ['Your teams', 'Your team slots, teams per section, and your reviews, consultations and panels.', '.dc-grid.wide'],
            ['Waiting on you', 'Team requests, documents to review and grades still to submit.', '.content .grid-3'],
            ['Announcements and what is next', 'News for you, and your coming consultations and defenses.', '.content .grid-2'],
        ],
        'related' => [
            'instructor.teams' => 'See your teams',
            'instructor.grading' => 'Grade a defense',
        ],
        'tip' => 'Press an announcement to read all of it.',
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
            ['The lists', 'To do, Team Applications and Teams. The number shows how many are in each.', '.hub-tabs'],
            ['Pending or completed', 'Show what still needs you, or what you already settled.', '.seg'],
        ],
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
            ['People', 'The members, the team\'s experts and its defense panel.', 'div.tp-panel'],
            ['Progress', 'Every milestone in order. Press one to see its requirements.', 'section.tp-panel'],
            ['Papers and activity', 'Papers the team sent, and what it did lately.', 'section.tp-panel.tp-grow'],
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
            ['The list', 'Every paper or update, newest first.', 'section.tp-panel'],
        ],
        'related' => [
            'instructor.teams' => 'Back to Teams',
        ],
    ],

    // Left the sidebar for To do's Completed (45b9e00f): its own guide stays, nothing links to it.
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
            ['Find a document', 'Search by name or team, or show one team or one status.', '.dr-filters'],
            ['The documents', 'One row each. Open Annotator to review it; CLF downloads the Consultation Logs Form.', '.dr-card'],
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
            ['Your defenses', 'How many are left to grade. Show all, those to grade, or those done.', '.gl-head'],
            ['The list', 'Each team, the defense, your seat, who grades, when it is and your grade.', '#grading-tbody'],
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
            ['Your teams', 'All teams, those behind, or those on track. Or find one by name.', '.ms-panel-head'],
            ['Each team', 'Where each team is on its milestones.', 'table.ms-table'],
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
            ['Your availability', 'Every slot you set, on each day it falls.', '.cal-card'],
            ['Legend', 'What each colour means, and how weekly slots work.', '.legend-section'],
            ['This month', 'How much of this month is taken.', '.sc-month'],
            ['Sessions', 'The consultations teams booked with you.', '.upcoming-card'],
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
            ['How it works', 'After each defense a team passes, its leader rates you 1 to 5 stars.', '.ev-head'],
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
            ['Team slots', 'How many teams you take from each program.', '.pp-progs'],
            ['Research areas', 'Teams are matched to you by these.', '.pp-finder'],
            ['Email notifications', 'What also goes to your email. Everything always shows in the bell.', '.pp-aside'],
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
        'summary' => 'Your sections at a glance: teams, students, requirements, defenses and fees.',
        'steps' => [
            ['Read the tiles', 'for teams, students, requirements done and the defense pass rate.'],
            ['Check Team Progress and Defense Results', 'to see where your teams stand.'],
            ['Press a gold link', 'to open the full page behind a card.'],
        ],
        'tour' => [
            ['Your sections in numbers', 'Teams, students, requirements done and the defense pass rate.', '.dash-tiles'],
            ['Progress and defenses', 'Where your teams are, and how they did at each defense.', '.dc-grid.wide'],
            ['Teams and experts', 'Teams per section, and how many expert slots are left.', '#teams-vs-experts'],
        ],
        'related' => [
            'capsa.team_management' => 'Open Team Management',
            'capsa.defenses' => 'Schedule defenses',
            'capsa.analytics' => 'Open Analytics',
        ],
        'tip' => 'Press a tile to open the list behind it.',
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
            ['Show', 'All posts, only yours, or only those for students or experts.', '#ann-show'],
            ['New announcement', 'Write it here and pick who sees it.', '#announcement-form-card'],
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
            ['Role', 'Show one role at a time.', '#experts-role-filter'],
            ['Search', 'Find an expert by name or research area.', 'label.experts-search'],
            ['Status', 'Online, full, offline, or not signed in yet.', '#statusFilter'],
            ['The list', 'Each expert, their roles, research areas and slots.', 'table.table'],
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
            ['Show', 'All, without a team, not signed in, or enrolled.', '#team-filter'],
            ['Search', 'Find a student by name or number.', '#student-search'],
            ['The list', 'Each student, their section, team and status.', 'table.table'],
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
            ['The lists', 'To do, Team Applications, Teams, Milestones and Team Ratings.', '.hub-tabs'],
        ],
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
            ['The team', 'Its name and status, and the previous and next team.', '.tp-head'],
            ['Members and experts', 'Who is on the team, and who advises it.', '.tp-grid div.tp-panel'],
            ['Progress', 'The milestones and how far along they are.', '.tp-grid section.tp-panel:not(.tp-grow)'],
            ['Papers and activity', 'Papers to check and what the team did lately.', '.tp-grow'],
        ],
        'related' => [
            'capsa.team_management' => 'Back to Team Management',
        ],
        'tip' => 'Use the arrows at the top to go to the next team.',
    ],

    'capsa.team_management.log' => [
        'title' => 'Team papers and activity',
        'summary' => 'Every paper the team sent, or everything the team did, in one list.',
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
            ['Verify this team', 'Ready once every title has a decision.', 'section.card.vfb'],
            ['The titles', 'Each proposed title and its decision.', 'section.card.ppc'],
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
            ['Pre-proposal form', 'What the team wrote about this title.', 'section.card.ppd:not(.rep)'],
            ['Similarity report', 'The closest studies and how close each one is.', 'section.card.ppd.rep'],
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
            ['Other studies', 'The previous and next similar study.', '.mnav'],
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
            ['The milestone', 'Its name, with Edit milestone and Mark finished.', '.ms-head'],
            ['Requirements', 'What teams must hand in for it.', 'section.ms-card'],
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
            ['The milestone', 'Its name, description, due date and place in the list.', '#ml-milestone'],
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
            ['The milestone', 'Its name, description, due date and place in the list.', '#ml-milestone'],
        ],
    ],

    'capsa.team_management.milestones.setup' => [
        'title' => 'Set up milestones',
        'summary' => 'Start a program with its milestones and requirements.',
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
            ['The requirement', 'Its name, due date and instructions.', '#ml-requirement'],
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
            ['The requirement', 'Its name, due date and instructions.', '#ml-requirement'],
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
        'tour' => [
            ['The defenses', 'Each one, and how many teams have a date.', '#stages'],
            ['The window', 'The days this defense can be held. Press Change to move them.', '#windowChip'],
            ['Where teams are', 'Panel not final, ready to schedule, or scheduled.', '#pipe'],
            ['The teams', 'Search, filter, and give each team its panel and date.', 'section.card.board'],
            ['Schedule', 'Shortcuts, Export to Excel and Generate schedule.', '.head-acts'],
        ],
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
        'tour' => [
            ['The defenses', 'Each one, and how many teams have a date.', '#stages'],
            ['The window', 'The days this defense can be held. Press Change to move them.', '#windowChip'],
            ['Where teams are', 'Panel not final, ready to schedule, or scheduled.', '#pipe'],
            ['The teams', 'Search, filter, and give each team a date.', 'section.card.board'],
        ],
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
        'tour' => [
            ['The defenses', 'Each one, and how many teams have a date.', '#stages'],
            ['Where teams are', 'Panel not final, ready to schedule, or scheduled.', '#pipe'],
            ['The teams', 'Each team, its panel and its own experts.', 'section.card.board'],
        ],
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
            ['The defenses', 'How many rubrics each defense has.', '#rb-tiles'],
            ['Show', 'All rubrics, or the archived ones too.', '.mk-bar'],
            ['The rubrics', 'Open one to change it, or archive it.', '#rb-list'],
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
        'tour' => [
            ['The defense', 'Which defense it follows.', '#ev-stage'],
            ['Questions', 'What leaders answer about each expert.', '#ev-qlist'],
            ['Save', 'Cancel, or create the evaluation.', '.ev-form-foot'],
        ],
    ],

    'capsa.evaluations.edit' => [
        'title' => 'Edit evaluation',
        'summary' => 'Change the questions team leaders answer about their experts.',
        'steps' => [
            ['Change the questions', 'you need.'],
            ['Save', 'when you are done.'],
        ],
        'tour' => [
            ['The defense', 'Which defense it follows.', '#ev-stage'],
            ['Questions', 'What leaders answer about each expert.', '#ev-qlist'],
        ],
    ],

    'capsa.evaluations.expert' => [
        'title' => 'Expert results',
        'summary' => 'How team leaders rated one expert.',
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
            ['Show', 'Waiting, sent back, not uploaded or verified. Search a member.', '.fx-toolbar'],
            ['Receipts', 'Each member and their receipt.', '#c-rows'],
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
            ['Search', 'Find a capstone by title, author or keyword.', '#arch-search'],
            ['Category', 'Show one category at a time.', '#arch-tag'],
            ['The capstones', 'Each finished capstone.', '#arch-grid'],
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
            ['Find a file', 'Search, and filter by category, section or folder.', '.lib-bar'],
            ['Your files', 'Everything you uploaded.', '#resource-list'],
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
            ['The report', 'Its rows, as they will print.', '#rp-table'],
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
            ['Filters', 'The school year and what to count.', '#an-filters'],
            ['In numbers', 'Teams, passes and more.', '.an-kpis'],
            ['Charts', 'How many teams pass each defense.', 'section.an-fig'],
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
            ['Filters', 'Pick the kind of action to show.', 'form.log-filters'],
            ['The log', 'Each action, newest first.', '.list-card'],
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
            ['Check Sections Without a CaPSA', 'and give each one a CaPSA.'],
            ['Read the cards', 'for students, experts, defenses and fees.'],
        ],
        'tour' => [
            ['In numbers', 'Students, teams, experts, CaPSAs and archived capstones.', '.dash-tiles'],
            ['Students and experts', 'Students by program and experts by role.', '.dc-grid.wide'],
        ],
        'related' => [
            'admin.experts' => 'Manage CaPSAs',
            'admin.students' => 'See every student',
            'admin.settings' => 'Set the school year',
        ],
        'tip' => 'Press a tile to open the list behind it.',
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
            ['Coverage', 'How many sections have a CaPSA.', '.page-head'],
            ['Show', 'All, active or deactivated CaPSAs. Search by name.', '.tk-toolbar'],
            ['The list', 'Each CaPSA and the sections they handle.', 'table.table'],
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
            ['Show', 'Filter, search, program and year.', '#studentFilters'],
            ['The list', 'Each student, their section and team.', 'table.std-table'],
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
            ['School year', 'The current school year.', '#current_school_year'],
            ['Add sections', 'Pick the programs and letters, then press Add sections.', '#sectionForm'],
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
            ['Filters', 'The school year and what to count.', '#an-filters'],
            ['In numbers', 'Teams, passes and more.', '.an-kpis'],
            ['Charts', 'How many teams pass each defense.', 'section.an-fig'],
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
            ['Filters', 'Pick the kind of action to show.', 'form.log-filters'],
            ['The log', 'Each action, newest first.', '.list-card'],
        ],
        'related' => [
            'admin.students' => 'See every student',
        ],
    ],

    'admin.profile' => [
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

];
