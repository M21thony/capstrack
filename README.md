# CapsTrack — Technical Reviewer for the Final Defense

A centralized web-based capstone project management system for the CSPC College of Computer Studies.

This file is a study guide for the defense. It explains, in plain terms:

- what CapsTrack is built with, and why;
- what each part of the system does;
- what the Qwen AI does;
- the questions the panel is most likely to ask, including the unexpected ones, with suggested answers.

Every number and file path here was taken from the code in this folder.

---

## Contents

1. [CapsTrack in one paragraph](#1-capstrack-in-one-paragraph)
2. [Technology stack at a glance](#2-technology-stack-at-a-glance)
3. [System architecture](#3-system-architecture)
4. [Users, roles, and portals](#4-users-roles-and-portals)
5. [The 15 modules and how they work](#5-the-15-modules-and-how-they-work)
6. [The Qwen AI: what it does in CapsTrack](#6-the-qwen-ai-what-it-does-in-capstrack)
7. [Libraries and what each one is for](#7-libraries-and-what-each-one-is-for)
8. [Database](#8-database)
9. [Security](#9-security)
10. [Notifications, background jobs, and scheduled tasks](#10-notifications-background-jobs-and-scheduled-tasks)
11. [Testing](#11-testing)
12. [Deployment](#12-deployment)
13. [Running CapsTrack locally](#13-running-capstrack-locally)
14. [Defense questions: the common ones](#14-defense-questions-the-common-ones)
15. [Defense questions: the unexpected ones](#15-defense-questions-the-unexpected-ones)
16. [Known limitations (say these before the panel finds them)](#16-known-limitations-say-these-before-the-panel-finds-them)
17. [Quick numbers to remember](#17-quick-numbers-to-remember)

---

## 1. CapsTrack in one paragraph

CapsTrack moves the whole capstone process of CSPC-CCS into one web system. It covers:

- forming teams and applying to an adviser, a consultant, and a grammarian;
- submitting and reviewing documents;
- checking a pre-proposal title against past capstones with AI;
- booking consultations;
- assigning panels, scheduling defenses, and grading them;
- verifying defense fee receipts;
- tracking every team's milestones;
- archiving finished projects.

Each user signs in with their CSPC Google account. They land in the portal of their role: **Student**, **Expert** (adviser, consultant, grammarian, or panel member), **CaPSA** (Capstone Subject Adviser), or **Super Admin**.

---

## 2. Technology stack at a glance

| Layer | Technology | Used for |
|---|---|---|
| Language (server) | **PHP 8.4** | All server-side code |
| Framework | **Laravel 13** | Routing, controllers, Eloquent ORM, validation, authentication guards, queues, the scheduler, mail |
| Views | **Blade** templates | Server-rendered pages for all four portals |
| Styling | Custom CSS per portal + **Tailwind CSS 4** (sign-in page) | Layout, responsive (mobile) design |
| Front-end build | **Vite 8** with the Laravel Vite plugin | Bundling JavaScript and CSS |
| Interactive parts | Plain JavaScript + **React 19** | React runs the PDF review and annotation tool; the rest is plain JavaScript |
| Database | **MySQL 8.4** | All records (utf8mb4) |
| AI model | **Qwen 2.5** through **Ollama** | Duplication checking, expert suggestions, rubric drafting |
| Embeddings | **nomic-embed-text** through Ollama | Turning titles and abstracts into vectors for similarity search |
| Sign-in | **Google OAuth 2.0** + Google **reCAPTCHA** | Login with CSPC Google accounts, bot protection |
| Google services | **Google Drive API** and **Google Calendar API** | Storing submissions in the expert's Drive, adding consultations and defenses to calendars |
| Push notifications | **Web Push** (VAPID) + a service worker | Desktop notifications even when CapsTrack is in another tab |
| Email | SMTP | Email notifications |
| Web server (production) | **Nginx** + **PHP-FPM** on **Ubuntu 24.04**, **Supervisor**, **cron**, **Certbot** (HTTPS) | Hosting |
| Testing | **PHPUnit 12** (feature tests) and **Playwright** (browser tests) | Automated testing |
| Code style | **Laravel Pint** | Keeps PHP formatting consistent |

**Why Laravel?**
- It is a mature and well-documented PHP framework.
- It has built-in protection against the common web attacks (CSRF, SQL injection, XSS).
- It has separate authentication guards, so students and experts can have their own logins.
- It already has queues and a task scheduler.
- It runs on inexpensive Linux hosting.

**Why MySQL?**
- It is a relational database, and capstone data is very relational: teams, members, experts, milestones, defenses, and grades all link to each other.
- It is free, and it is the standard database for Laravel hosting.

---

## 3. System architecture

CapsTrack follows the **Model-View-Controller (MVC)** pattern of Laravel. It is organized in four layers:

```
 Browser (Student / Expert / CaPSA / Super Admin portals)
        │  HTTPS
        ▼
 Presentation layer   Blade views, CSS, JavaScript, React PDF reviewer
        │
        ▼
 Application layer    Routes → Middleware (login, role, idle timeout) → Controllers
                      → Services / Support classes (business rules)
        │
        ▼
 Data layer           Eloquent models → MySQL database, file storage
        │
        ▼
 External services    Google OAuth, Drive, Calendar, reCAPTCHA · Ollama (Qwen, nomic-embed-text)
                      · SMTP mail · Web Push
```

- **Routes** (`routes/web.php`, `routes/api.php`): 326 routes.
- **Middleware** (`app/Http/Middleware`): these checks run before a page opens.
  - Is the user signed in?
  - Is this expert approved?
  - Is the user a CaPSA or the Super Admin?
  - Is the student's profile complete?
  - Has the user been idle for 10 minutes?
  - Is the browser prevented from showing cached pages after logout?
- **Controllers** (`app/Http/Controllers`, 85 files): receive the request and return a page or JSON.
- **Services** (`app/Services`): the larger pieces of logic.
  - `SimilarityService`: the AI duplication checker.
  - `OllamaSSHClient`: talks to the AI server.
  - `GoogleDriveService`, `GoogleCalendarService`.
  - `Notifier`: every notification.
  - `DefenseOutcomeRecorder`, `DefenseScheduleNotifier`.
  - `TeamLock`, `RecipientResolver`.
- **Support classes** (`app/Support`): business rules that pages share, such as `MilestoneProgress`, `DefenseScores`, `DefenseAnalytics`, `CapsaReports`, `FeeBoard`, and `SimilarityBand`.
- **Models** (`app/Models`, 56 models): one per database table.

---

## 4. Users, roles, and portals

### How a person signs in

1. They click **Continue with CSPC Mail**. Google **reCAPTCHA** is checked first.
2. Google OAuth 2.0 returns their name and email. CapsTrack never sees or stores a password.
3. The email domain decides the kind of account:
   - `@my.cspc.edu.ph` → **student**.
   - `@cspc.edu.ph` or `@gmail.com` → **expert** (faculty).
   - Anything else is refused.
4. A **student** gets in only if their email is on a section list uploaded by the CaPSA. Someone removed from the list cannot sign back in.
5. An **expert** gets in only after their account is approved. A deactivated expert is refused.

Students and experts use two separate Laravel **authentication guards**: `web` for students and `expert` for faculty.

### Roles

| Role | Stored as | Portal | What they do |
|---|---|---|---|
| Student | `students` table, `web` guard | Student Portal | Team, experts, submissions, consultations, progress, fees, archive |
| Adviser / Consultant / Grammarian | `roles` attached to an expert | Expert Portal | Accept teams, review documents, set consultation hours |
| Panel member | assigned per defense | Expert Portal (Team Grading) | Score teams with the rubric |
| CaPSA (Capstone Subject Adviser) | role `capstone_adviser` + assigned sections | CaPSA Portal | Runs their sections: students, experts, teams, checklists, panels, defenses, fees, announcements, reports |
| Super Admin | `is_admin` flag | Admin Portal | Sets the school year and sections, adds CaPSAs, manages student accounts |

- One expert can hold several roles at once, for example an adviser who is also a CaPSA. The portal shows the menus of every role the person holds.
- A team engages an expert **for one role**. The same expert cannot be a team's adviser and its consultant at the same time.

---

## 5. The 15 modules and how they work

| # | Module | How it works in CapsTrack |
|---|---|---|
| 1 | **User Account and Role Management** | Google sign-in. The CaPSA uploads student and expert lists from Excel. Account approval, role assignment, section-based access per CaPSA. |
| 2 | **Capstone Experts and Group/Slot Management** | Teams of up to 5 members (the limit is a setting). Invitations, open slots, leadership transfer, leave or removal requests decided by the CaPSA, team verification. Each expert has a team capacity (`max_teams`), so workload stays even. |
| 3 | **Project Submission and Document Management** | Upload per checklist requirement, versioning (`file_version`), preview before sending, a warning if the file looks like the wrong chapter (`RevisionCheck`), storage in Laravel storage and in the reviewer's Google Drive. |
| 4 | **Capstone Experts' Application** | The team leader applies for an adviser, consultant, and grammarian. The expert accepts or declines with a reason. Once approved, the expert appears in the team's slots. |
| 5 | **Consultation Management** | Experts publish available dates and times, their booking window, and a daily maximum. Teams book only those slots. Before saving, the server checks the slot's capacity for that date, the expert's daily limit, and that the team has no other open session with the same expert. Cancellation has rules, including a 3-day cutoff. Bookings go to Google Calendar. |
| 6 | **Review, Evaluation, and Annotation** | A React PDF reviewer (react-pdf + pdf-lib) with highlights, comments, correction marks, and a verdict. Students read the feedback in the Review Viewer. Versions can be compared word by word (`versionDiff.js`). |
| 7 | **Defense Management** | Panel assignment with rules (the Panel Chair needs a Master's degree or higher, and the team's own adviser and consultant are left out of its panel). Defense windows, **Generate schedule** (it checks other sections so no room or panelist is booked twice), export to Excel, Google Calendar. |
| 8 | **Progress Tracking and Monitoring** | A milestone checklist per team, with requirements and due dates. It updates itself from submissions, reviews, CaPSA checks, and defense results. Fee posting and receipt verification live here too. |
| 9 | **Duplication Checking** | The AI checker. See [Section 6](#6-the-qwen-ai-what-it-does-in-capstrack). |
| 10 | **Analytics** | Projects per year, results per defense stage, pass rates, project types. Filtered by school year, program, and section. Can be exported. |
| 11 | **Evaluation and Grading** | Rubrics (with an AI draft option). Each panelist scores independently. The team's score is the panel average (for example (65 + 85 + 75) / 3). It is shown only when the whole panel has graded. The Panel Chair records one outcome for the defense. |
| 12 | **Notification** | In-app bell, pop-up toasts, web push, email. Automatic reminders. CaPSA announcements. Each person can switch kinds off. |
| 13 | **Archiving** | Finished capstones with title, authors, year, program, abstract, and tags. Search and filters. The archive is also the data that the duplication checker compares against. |
| 14 | **Dashboard** | A separate dashboard per role: status, schedules, pending tasks, progress, announcements, recent activity. |
| 15 | **Reports** | Section and team progress, defense grading, financial records, activity log. Downloaded as PDF (DomPDF) or Excel (PhpSpreadsheet). |

**Defense outcomes** (`app/Support/DefenseOutcome.php`):
- Passed
- Passed with revision
- Redefense
- Redefense with a new title (only for early stages)

There is no "rejected". A team that does not make it is one that runs out of its defense window.

**Defense stages:** Pre-proposal Defense → Title Defense → Pre-Oral Defense → Final Oral Defense.

---

## 6. The Qwen AI: what it does in CapsTrack

### The model and where it runs

- **Qwen 2.5** is an open-source large language model from Alibaba Cloud. CapsTrack runs it through **Ollama**, a program that serves AI models locally.
- The model is set in `.env` as `OLLAMA_MODEL`.
  - The default in `config/services.php` is `qwen2.5:7B`.
  - The development machine currently uses `qwen2.5:3B`, which is smaller and faster.
- A second model, **nomic-embed-text**, turns text into **embeddings**. An embedding is a list of numbers that represents the meaning of the text.
- The AI runs on a separate machine. CapsTrack reaches it in one of two ways (`app/Services/OllamaSSHClient.php`):
  - **directly over HTTPS**, if `OLLAMA_URL` is set (for example a cloud GPU server);
  - **through an SSH tunnel** (phpseclib) to the machine that runs Ollama.
- Why a local, self-hosted model instead of ChatGPT or another cloud API?
  - **Privacy:** unpublished student proposals never leave the school's control.
  - **Cost:** no charge per request.
  - **Control:** the model and its prompts can be changed and fine-tuned.

### Every job Qwen does in the system

| # | Job | Function (file) | What happens |
|---|---|---|---|
| 1 | **Is this really a pre-proposal?** | `isPreProposal()` in `SimilarityService` | Qwen reads the first 4,000 characters and decides if the upload is a real capstone pre-proposal. It refuses a CV, a receipt, a full thesis, a blank scan, and similar files. This keeps junk out of the check. |
| 2 | **Extract the key concepts** | `extractKeyConceptsWithAi()` | Qwen reads the pre-proposal form and returns JSON: title, main topic, domain, up to 10 keywords, problem statement, proposed solution, scope, limitations, SDGs, research agenda, methodology, technologies, target users. These fill the Pre-Proposal Details form. |
| 3 | **Find similar studies (retrieval)** | `findSimilarDocuments()` | The concepts are joined, stop words are removed, and **nomic-embed-text** turns them into an embedding. That embedding is compared with the stored embedding of every archived capstone using **cosine similarity**. Results below 40% are dropped as noise, and the top 10 are kept. |
| 4 | **Judge each close match** | `judgeCandidates()` / `buildJudgePrompt()` | For each candidate scoring 40% or more (the top one is always judged), Qwen compares it with the pre-proposal and returns a score, whether it truly matches, the reason, the similarities, the differences, and a suggestion. **Each candidate is judged in its own prompt**, because testing showed that judging several at once made the model rank them against each other. The prompts are sent together in one round trip and run in parallel (`generateMany()`), which saves time. |
| 5 | **Fallback search** | `findSimilarDocumentsLegacy()` | If embeddings fail, or fewer than 5 archived studies have embeddings, Qwen expands the topic into 15 related search terms and synonyms, and CapsTrack runs a keyword search with them. |
| 6 | **Suggest experts** | `suggestInstructors()` / `bestForRole()` | Qwen reads the pre-proposal and the list of advisers, consultants, and grammarians with their expertise, then picks the best matches per role with a one-sentence reason. It may only use IDs from the list, so it cannot invent a person. |
| 7 | **Draft a grading rubric** | `aiGenerate()` in `Api/AdminGradingRubricController` | Qwen writes criteria and the description of each level. **The points are computed by the code, not by the AI**, so every rubric always totals exactly 100. The Title Defense rubric uses the official wording directly, because the model dropped criteria when asked to copy all 27. |
| 8 | **Prepare training data (fine-tuning)** | `php artisan duplication:export-training-data` | Exports the 7B model's validated answers as prompt/response pairs, used to fine-tune a smaller, faster model for the same tasks. Answers that are not valid JSON are left out. |
| 9 | **Embed the archive** | `php artisan embeddings:generate` (`GenerateEmbeddings`) | Creates the stored embedding for every archived capstone, so new checks can compare against them. |

### How to read the similarity result (`app/Support/SimilarityBand.php`)

| Score | Label shown |
|---|---|
| 65% and above | **Close to existing work** (the "highly similar" line) |
| 40% – 64% | **Some overlap** |
| Below 40% | **Little overlap** |
| Not judged | **Not assessed** (never given a made-up number) |

- **The AI does not approve or reject a title. The CaPSA decides.** The checker only informs that decision.
- When Qwen judged a match, its own score is shown. Otherwise the embedding score is shown.
- The embedding score is the cosine similarity converted to a percentage: `((cosine + 1) / 2) × 100`.
- **Exact words are counted as well as meaning.** The upload is compared three words at a time with the PDF saved for a recommended or pending title, or with an archive study's abstract. At 50% or more, the exact-words number replaces the score if it is higher, so an identical PDF shows 100%. Below 50% it can be only the form's own headings, so it is shown but not used.

### Safeguards around the AI

- The AI must answer in **JSON**. The reply is parsed defensively:
  - the first complete JSON object is taken;
  - a missing comma between fields is repaired;
  - fields are never invented.
- **Place and school names are removed** from the highlighting. Otherwise "College of Computer Studies" would make every CSPC study look alike.
- The model is **warmed up** before a check, and kept in memory for 30 minutes (`OLLAMA_KEEP_ALIVE`) so it is not reloaded mid-check.
- Requests time out at 300 seconds and are retried.
- Measured on the labelled fine-tuning examples: the true matching study is inside the **top 4 in 92%** of cases and inside the **top 10 in 98%**. That is why 10 candidates are judged.

---

## 7. Libraries and what each one is for

### PHP (Composer)

| Package | Used for |
|---|---|
| `laravel/framework` | The framework itself |
| `google/apiclient` | Google Drive and Google Calendar APIs |
| `phpseclib/phpseclib` | The SSH/SFTP connection to the AI server |
| `smalot/pdfparser` | Reading the text of uploaded PDFs (pre-proposals, wrong-chapter check) |
| `phpoffice/phpspreadsheet` | Importing student and expert lists from Excel; exporting defense schedules and reports to Excel |
| `barryvdh/laravel-dompdf` | Generating PDF reports and consultation logs |
| `minishlink/web-push` | Sending desktop (web push) notifications |
| `laravel/socialite` | Installed. Google sign-in itself is written directly against Google's OAuth 2.0 endpoints in `AuthController`, which gives control over the Drive and Calendar scopes and the refresh token |
| `phpunit/phpunit`, `mockery`, `fakerphp/faker` | Automated tests, fake objects, sample data |
| `laravel/pint` | Code formatting |

### JavaScript (npm)

| Package | Used for |
|---|---|
| `react`, `react-dom` | The PDF review and annotation tool |
| `react-pdf` | Displaying PDF pages in the browser |
| `pdf-lib` | Writing the remarks and highlights into the PDF when a review is finished |
| `vite`, `laravel-vite-plugin`, `@vitejs/plugin-react` | Building the front-end files |
| `tailwindcss` | Styling the sign-in page |
| `@playwright/test` | Browser tests |

---

## 8. Database

- **MySQL 8.4**, character set `utf8mb4`. Laravel **migrations** create and change the tables: 178 migration files, so the whole schema can be rebuilt with one command.
- **56 Eloquent models**. The main groups:
  - **People:** `students`, `capstone_experts`, `roles`, `expert_roles`, `student_s_y` (a student's school year, section, and team).
  - **Teams:** `team`, invitations, recruitment posts, leave requests, `team_expert_assignment` (applications and approved experts).
  - **Work:** `capstone_documents` (submissions and versions), document reviews and annotations, checklists, milestones, deliverables, and deliverable checks.
  - **Consultations:** consultation schedules and bookings.
  - **Defenses:** defense schedules, panels, rubrics and criteria, scores, outcomes.
  - **Money:** expense posts (fees) and receipts.
  - **Titles:** duplication checks, pre-proposals, `archived_documents` (with an `embedding` column).
  - **Communication:** notifications, announcements, push subscriptions, activity logs, settings.
- **Relationships** (Eloquent): a team has many members and many expert assignments. An expert has many roles. A checklist has milestones, and milestones have deliverables. A defense schedule has a panel and scores.
- Foreign keys with cascade rules keep related rows consistent. An expert is **soft-deleted** (`deactivated_at`) instead of erased, so their history stays.

---

## 9. Security

| Threat / need | How CapsTrack handles it |
|---|---|
| Passwords | None are stored. Sign-in is through **Google OAuth 2.0** only. |
| Who may sign in | Email **domain check**. Students must be on a CaPSA's **uploaded list**. Experts must be **approved**, and deactivated experts are refused. |
| Bots on the login page | **Google reCAPTCHA**, verified on the server |
| Access to pages of another role | **Middleware** on every route group: `expert.auth`, `expert.capsa`, `expert.admin`, `expert.approved`, `profile.complete`. A CaPSA only sees their own sections. |
| SQL injection | Eloquent and the query builder use **parameter binding**. User input is never glued into SQL. |
| Cross-site scripting (XSS) | Blade `{{ }}` **escapes output** by default. Text from users (reasons, abstracts, notes) is inserted as text, never as HTML, in JavaScript too. |
| Cross-site request forgery (CSRF) | Laravel's **CSRF token** on every form and every fetch request |
| Walk-away sessions | **Automatic sign-out after 10 idle minutes** (`EndIdleSession` + `partials/idle-logout`). A one-minute warning appears first, and the server keeps its own count. |
| Back button after logout | `PreventBackHistory` (`no.cache`) stops the browser from showing cached pages |
| Uploads | Validation of type and size: receipts must be images (up to 10 MB), application proposals PDF (up to 5 MB), Excel lists `.xlsx`, and submissions up to 25 MB. Document submissions are saved in **private** storage (`storage/app/private`), outside the public web folder, and opened through signed-in routes. |
| Google access | Only the scopes needed: `openid email profile`, `calendar`, and `drive.file`. `drive.file` means CapsTrack can only touch files it created, not the user's whole Drive. |
| HTTPS | Certbot / Let's Encrypt certificate on Nginx in production |
| Secrets | Kept in `.env`, which is never committed to Git |

---

## 10. Notifications, background jobs, and scheduled tasks

- **One entry point:** `app/Services/Notifier.php`. Every notification:
  1. checks the person's preferences;
  2. saves a row (for the bell);
  3. queues a web push.
- **Channels:**
  - **in-app bell**;
  - **pop-up toast** in the bottom corner, which checks for new ones every 45 seconds;
  - **web push** to the desktop;
  - **email**.
- **Queue:** the `database` queue driver. A Supervisor-managed worker (`deploy/capstrack-worker.conf`) sends queued emails and pushes.
- **Scheduled tasks** (`routes/console.php`, run by cron every minute through `php artisan schedule:run`):

| Time | Command | Purpose |
|---|---|---|
| 00:05 daily | `consultations:send-reminders` | Remind experts and teams of today's consultations |
| 02:00 daily | `checklists:purge-removed` | Clean up removed checklist items |
| 07:00 daily | `milestones:send-review-reminders` | Remind experts of documents still waiting for review |
| 07:05 daily | `milestones:send-due-reminders` | Remind teams of upcoming milestone deadlines |

---

## 11. Testing

- **PHPUnit feature tests:** 187 test files and about **1,570 test methods** in `tests/Feature`. They run against an in-memory SQLite database, so they never touch real data.
- **Playwright** browser tests are configured in `playwright.config.js`.
- The testing chapter uses **black-box testing**: functional, integration, security, performance (Apache JMeter), and user acceptance.

```bash
php artisan test --compact                                   # all tests
php artisan test --compact tests/Feature/IdleSessionTimeoutTest.php   # one file
```

---

## 12. Deployment

See `docs/DEPLOYMENT.md` and the `deploy/` folder.

- **Server:** Ubuntu Server 24.04 LTS.
- **Stack:** Nginx (`deploy/nginx.conf`), PHP 8.4-FPM (`deploy/php.ini`), and MySQL.
- **Deploy script:** `deploy/deploy.sh` pulls the code, runs `composer install`, `npm run build`, and the migrations, caches the configuration, and restarts the queue worker.
- **Background services:**
  - **Supervisor** keeps the queue worker running;
  - **cron** runs the scheduler;
  - **Certbot** provides HTTPS.
- **Configuration:** production settings go in `.env`; there is a template at `deploy/env.production.example`.

---

## 13. Running CapsTrack locally

```bash
composer install
npm install
cp .env.example .env       # then fill in DB, Google, reCAPTCHA, Ollama, VAPID values
php artisan key:generate
php artisan migrate
npm run build              # or: npm run dev
php artisan serve
php artisan queue:work     # in another terminal, for mail and web push
```

---

## 14. Defense questions: the common ones

**Q: What is CapsTrack and what problem does it solve?**
It is a centralized web system for managing capstone projects in CSPC-CCS. Before it, applications to experts, submissions, feedback, scheduling, grading, receipts, and monitoring were done by hand and through scattered channels (chat, email, paper). CapsTrack puts all of these in one place, with role-based portals and automatic tracking.

**Q: What technologies did you use?**
PHP 8.4 with Laravel 13 and MySQL on the server. Blade, JavaScript, and React for the pages. Qwen 2.5 and nomic-embed-text through Ollama for the AI. Google OAuth, Drive, and Calendar. Web Push and email for notifications. See [Section 2](#2-technology-stack-at-a-glance).

**Q: Why Laravel and not plain PHP or another framework?**
Laravel gives security (CSRF, escaping, parameter binding), authentication guards, database migrations, queues, and a scheduler out of the box. This let us spend our time on the capstone workflow instead of rebuilding basics. It is also PHP, which the school's hosting and our training already support.

**Q: What software development methodology did you use?**
Agile. The 15 modules were built in sprints, and each finished module was tested while the next one was being built. The CaPSA's feedback was folded into the following sprint.

**Q: How does the duplication checker work?**
In four steps:
1. Qwen checks that the upload is really a pre-proposal and extracts its key concepts.
2. The concepts are turned into an embedding with nomic-embed-text and compared by meaning (cosine similarity) with every archived capstone.
3. The 10 closest studies are kept.
4. Qwen judges each close one separately and explains the similarities and differences, with a score.

65% and above is shown as close to existing work. See [Section 6](#6-the-qwen-ai-what-it-does-in-capstrack).

**Q: What exactly does Qwen do in your system?**
Nine jobs, all listed in [Section 6](#6-the-qwen-ai-what-it-does-in-capstrack):
1. checks the document type;
2. extracts the concepts;
3. retrieves similar studies with embeddings (nomic-embed-text);
4. judges and explains each close match;
5. expands the search terms for the fallback keyword search;
6. suggests experts per role;
7. drafts rubric wording;
8. produces fine-tuning data;
9. embeds the archive (nomic-embed-text).

**Q: How accurate is the duplication checker?**
On our labelled examples, the real matching study appears in the top 4 results 92% of the time and in the top 10 results 98% of the time. That is why all top 10 are judged. The score is a guide for the CaPSA, not a final decision.

**Q: Why not just compare keywords?**
Keywords miss studies that say the same thing in different words, for example "attendance monitoring" and "presence tracking". Embeddings compare meaning. We still keep a keyword search as a fallback, with Qwen adding synonyms, for when the embedding service is down.

**Q: How do you make sure the AI's answer is not simply wrong?**
- It is only a recommendation; the CaPSA decides.
- Each candidate is judged in its own prompt, so the model cannot rank candidates against each other.
- The answer must be valid JSON.
- Studies that were not judged are shown as "Not assessed" instead of being given a number.
- Expert suggestions may only use IDs from the real list.
- Rubric points are computed by code, not by the AI.

**Q: How do users log in? Is it secure?**
With their CSPC Google account through Google OAuth 2.0. No passwords are stored in CapsTrack. The email domain decides whether the person is a student or an expert. Students must also be on a section list uploaded by the CaPSA, and experts must be approved. reCAPTCHA protects the login.

**Q: What are the user roles?**
- Student.
- Expert: adviser, consultant, grammarian, and panel member.
- CaPSA.
- Super Admin.

Each role has its own portal and menu, enforced by middleware on the server, not only by hiding buttons.

**Q: How is the progress of a team computed?**
Each team has a milestone checklist of requirements with due dates. A requirement closes on its own when its condition is met:
- a document is uploaded;
- the adviser approves the CLF;
- the CaPSA checks a signed form;
- the panel records a pass.

Progress is the share of closed requirements. Overdue items are flagged automatically.

**Q: How are defenses scheduled?**
The CaPSA sets a defense window and presses Generate schedule. Only teams whose panel is assigned and whose requirements and fees are done can be scheduled. The generator also reads other sections' defenses, so no room or panelist is booked twice. The schedule can be exported to Excel, and it is added to Google Calendar.

**Q: How is grading done?**
Each panelist scores the team independently with the rubric for that defense stage. The team's score is the average of all panelists, and it appears only once the whole panel has graded. The Panel Chair records the panel's one decision: passed, passed with revision, or redefense.

**Q: How do users get notified?**
In four ways: the in-app bell, a pop-up in the corner of any page, web push to the desktop, and email. Reminders for consultations, reviews, and deadlines are sent automatically every morning by the scheduler. Each user can turn off the kinds they do not want.

**Q: How did you test the system?**
- Black-box testing: functional, integration, security, performance with JMeter, and user acceptance with students and experts.
- Automated feature tests: about 1,570 of them, in 187 files, which we run after every change.

**Q: Can it be used on a phone?**
Yes. Every portal is responsive and has a bottom tab bar on mobile. Receipt photos can be chosen from the phone's gallery.

**Q: Where are files stored?**
Document submissions are saved in the server's private Laravel storage, outside the public web folder, and a copy is sent to the reviewing expert's Google Drive. The Drive scope is `drive.file`, so CapsTrack only sees files it created. Receipt photos and expert-application proposals are saved in the public storage folder under random file names.

---

## 15. Defense questions: the unexpected ones

**Q: What happens if the AI server is down during a duplication check?**
The embedding step fails and CapsTrack automatically falls back to keyword search. If Qwen is also unreachable, the check shows an error and can be run again. The rest of CapsTrack (submissions, scheduling, grading) does not depend on the AI and keeps working.

**Q: Why run Qwen locally instead of using ChatGPT?**
- Privacy: pre-proposals are unpublished student work, and they should not be sent to a third party.
- Cost: no charge per request.
- Control: we can fine-tune the model and pin its version.

The trade-off is that we need our own machine with enough memory to run it.

**Q: What is an embedding? What is cosine similarity?**
An embedding is a list of numbers that represents the meaning of a text. Texts with similar meaning get similar lists. Cosine similarity measures the angle between two such lists: 1 means the same direction (very similar), 0 means unrelated. We convert it to a 0–100% scale.

**Q: What is fine-tuning, and did you fine-tune Qwen?**
Fine-tuning is training an existing model a little more on examples of a specific task. CapsTrack can export the larger 7B model's validated answers as training pairs (`duplication:export-training-data`), to teach a smaller model the same task. The smaller model is faster on modest hardware. Which model is active is one setting in `.env`.

**Q: Can students cheat the duplication checker by rewording their title?**
Rewording does not help much, because the checker compares meaning (embeddings) and not exact words. It also reads the whole form: problem, solution, and scope, not only the title. The result still goes to the CaPSA, who decides.

**Q: Why is the similarity threshold 65%? Who decided it?**
It is the level that the college has always treated as "highly similar", and it is set in one place (`SimilarityBand::CLOSE`), so it can be changed. Results below 40% are dropped because at that level the overlap is mostly generic capstone words like "system" or "management".

**Q: What if two panelists give very different scores?**
Each panelist grades independently, and the team's mark is the average. The outcome itself is not averaged: the panel confers and the Panel Chair records one decision. A team that needs another try is given a redefense, which the system schedules within the defense window.

**Q: What stops a student from opening an expert or CaPSA page by typing the URL?**
Server-side middleware. Every route group checks the guard and the role before the controller runs, so typing a URL returns a redirect or an error, not the page. A CaPSA is also limited to the sections assigned to them.

**Q: How do you prevent SQL injection and XSS?**
- SQL injection: all database access goes through Eloquent or the query builder, which use parameter binding.
- XSS: all output in Blade is escaped, and user text inserted by JavaScript is set as text, never as HTML.
- CSRF: every form and request carries a CSRF token.

**Q: What if someone leaves CapsTrack open on a shared computer?**
After 10 minutes without activity, a warning appears. After one more minute, the user is signed out and the sign-in page says "Session expired". The server also enforces the timeout, so it works even if the page's timer is stopped.

**Q: What happens to the data when an expert leaves the college?**
The expert is deactivated (soft-deleted), not erased. Their history (reviews, grades, past assignments) stays intact for records. They can no longer sign in, and they no longer appear as available.

**Q: Can two teams book the same consultation slot?**
No. Before saving a booking, the server counts the bookings already on that slot for that date and refuses a booking once the slot's limit is reached. It also refuses a booking when:
- the expert's daily maximum is reached;
- the date is outside the expert's booking window;
- the team already has an open session with that expert.

If they press on "at the exact same millisecond": the check and the save are not yet wrapped in a database lock. Two requests arriving at literally the same instant could in theory both pass. The fix is a transaction with a row lock, and it is listed under known limitations.

**Q: Why are there no passwords? What if Google is down?**
Using the school's Google accounts means no passwords to store, leak, or reset, and only real CSPC accounts can enter. If Google sign-in is unavailable, new sign-ins wait until it is back. Users who are already signed in keep working.

**Q: How does the system scale when there are many more teams?**
- The database is relational and indexed.
- Slow work (emails, pushes) runs in a background queue.
- The AI checker only judges the top 10 candidates, whatever the size of the archive.
- To grow, the web server and the AI machine can be upgraded separately, because they are separate machines.

**Q: How do you back up the data?**
The MySQL database can be exported with `mysqldump`, and the steps are in `docs/DEPLOYMENT.md`. Uploaded files also have copies in the experts' Google Drive. A scheduled daily backup on the production server is recommended.

**Q: Is the AI biased toward some topics?**
It can reflect what is in the archive. A topic with many past studies will have more close matches. That is one reason the checker never rejects a title on its own. Place and institution names are removed from the comparison so that being a CSPC study is not counted as similarity.

**Q: Why React only in the PDF reviewer and not everywhere?**
The PDF reviewer is the one screen with heavy interaction: drawing highlights, placing comments, and exporting the annotated PDF. React fits that well. The other pages are mostly forms and lists, which server-rendered Blade handles faster and more simply.

**Q: What would you add if you had more time?**
- Rate limiting on the sign-in and AI endpoints.
- Server-side file-type checks on every upload, and private storage for receipts.
- A database lock around consultation booking.
- A scheduled automatic backup.
- A larger, labelled dataset to measure and improve the checker's accuracy.
- A native mobile app.

---

## 16. Known limitations (say these before the panel finds them)

- **The AI needs its own machine.** The duplication checker, expert suggestions, and rubric drafts need the Ollama server to be running.
- **No request rate limiting** is set on the login or the AI check yet. reCAPTCHA protects the login form.
- **General document submissions** are limited by size (25 MB). The page asks for PDF, but the server does not refuse other file types there yet. Receipts, proposals, and Excel lists are type-checked.
- **Receipt photos and expert-application proposals** are stored on the public disk (`storage/app/public`) under random names. Anyone who has the exact link can open them, while document submissions are private. Moving receipts to private storage behind a signed-in route would close this.
- **Consultation booking** checks the slot's capacity before saving, but not inside a database lock. Two bookings sent at the very same instant could both pass. A transaction with `lockForUpdate()` would close this.
- **The accuracy figures** (92% in the top 4, 98% in the top 10) come from the team's labelled examples, not from an external benchmark.
- **Performance and user acceptance results** come from the testing sessions in Chapter 4, not from the code.

---

## 17. Quick numbers to remember

| | |
|---|---|
| Portals | 4 (Student, Expert, CaPSA, Super Admin) |
| Modules | 15 |
| Routes | 326 |
| Controllers | 85 |
| Models | 56 |
| Migrations | 178 |
| Automated tests | ~1,570 test methods in 187 files |
| Max team size | 5 (a setting) |
| Idle sign-out | 10 minutes, with a 1-minute warning |
| Similarity bands | 65%+ close · 40–64% some · below 40% little |
| AI candidates judged | Top 10 |
| Defense stages | 4 (Pre-proposal, Title, Pre-Oral, Final Oral) |
| Submission size limit | 25 MB |
| Daily reminders | 00:05 consultations · 07:00 reviews · 07:05 deadlines |
