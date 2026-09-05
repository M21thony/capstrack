# Plan: import a CaPSA's existing Drive files

## Goal

A CaPSA who already keeps their templates in a Google Drive folder should be able to
point CapsTrack at those files instead of re-uploading each one. One-time import, not a
live sync.

## What exists today

- **Scope is `drive.file`** — `app/Services/GoogleDriveService.php:23`. Google grants this
  app access only to files it created itself, or files the user hands it through the
  Google Picker. A CaPSA's existing folder is invisible to us right now.
- **No Drive listing anywhere.** `listFolders()` reads the local `admin_drive_folders`
  table, and rows only ever land there from `createFolder()`. A "pick a folder" dropdown
  built today would show only folders CapsTrack made.
- **`uploadFile()` makes every upload link-viewable** — it calls `permissions->create`
  with `anyone / reader` right after creating the file, because students open these
  without any Drive access of their own. See `GoogleDriveService.php:164`.
- **`upload()` writes the `resources` row and its section rows** —
  `app/Http/Controllers/Admin/GoogleDriveController.php:126`. Import has to produce the
  same shape: `resource_type`, `category`, `title`, `file_name`, `file_type`, `file_size`,
  `drive_file_id`, `drive_folder_id`, `drive_web_view_link`, `uploaded_at`, `uploaded_by`,
  plus a `resource_sections` row per chosen section.

Staying on `drive.file` is the point of this approach: no Google security assessment, no
"unverified app" screen, and the CaPSA never grants blanket access to their whole Drive.

## Verify these two things before building anything

Both are cheap to test and either one can change the whole design, so they come first.

- [ ] **Does picking a *folder* grant access to the files inside it?** Google's published
      docs don't say. Test: select a folder in the Picker, then server-side call
      `files->listFiles` with `q = "'FOLDER_ID' in parents"`. If it comes back empty or
      403s, folder-picking is useless to us and the CaPSA multi-selects files instead —
      tedious once, then done. Design for per-file selection unless this passes.
- [ ] **Does a grant made in the browser reach the server's stored token?** The Picker runs
      client-side; our Drive calls run server-side off the token in `google_drive_tokens`.
      Same OAuth client, same user, so access *should* carry over — confirm it by picking a
      file in the browser then calling `files->get($fileId)` from Tinker as that CaPSA. If
      it fails, the import has to run against a browser-held token instead, which is a
      materially different build.

## The shape

1. A **"Choose from Drive"** button beside Upload on the Templates & Lessons page.
2. Google's Picker opens. Restrict it to My Drive + Shared drives, multi-select on, and
   limit selectable types to PDF/DOC/DOCX/PPT/PPTX plus the native Google types (see
   below).
3. On **Select**, JS receives `[{id, name, mimeType, sizeBytes}, ...]`.
4. Those land in a **review list in the browser — no database rows yet**. One line per
   file: the filename, a Category dropdown, the section checkboxes, and a Remove.
5. **"Import N files"** POSTs that array to a new endpoint.
6. Per file the server does: `files->get` for metadata and `webViewLink` →
   `permissions->create` (anyone/reader) → `Resource::create` → section rows.
7. Response returns the created resources; the list re-renders.

**Why the review happens in the browser and not as draft rows:** it needs no new column and
leaves no half-live records. An imported file with no sections would otherwise be visible
to every student that CaPSA handles the moment it lands — exactly wrong. Close the tab
mid-review and nothing was imported.

## Two ways in, one review list

A CaPSA has their files in one of two places, and each needs its own door.

**"They are on my computer"** - a folder upload, no Google involved. One
attribute does it: `<input type="file" webkitdirectory multiple>`. The browser
shows a folder picker, warns the user what is about to be uploaded, and hands
over every file inside, each carrying a `webkitRelativePath` so the subfolder it
came from is known. Chrome, Edge, Firefox and Safari all support it.

What it costs: `GoogleDriveController::upload` validates a single `file`. A
folder is N files, so it needs a loop - validate each, push each to Drive, write
N rows - and the same cap the import gets.

**"They are already in my Drive"** - the Picker. Nothing is re-uploaded, so no
25 MB limit and no second copy of a file the CaPSA already owns. It carries the
two unknowns above instead.

Both land in the same review list, so the category-and-sections screen is built
once and serves either route. If the Picker verifications go badly, build the
folder upload first - it needs no Google approval and no new scope.

## Steps

- [ ] Step 1: Run the two verifications above. Write down what they returned.
- [ ] Step 2: Google Cloud console — create a **Picker API key** and enable the Picker API
      on the existing project. Add it to `.env` / `config/services.php`.
- [ ] Step 3: Client-side token. Use Google Identity Services
      (`google.accounts.oauth2.initTokenClient`, scope `drive.file`) to get a token in the
      browser. Do **not** ship the stored server token to JS.
- [ ] Step 4: Load `https://apis.google.com/js/api.js` on the resources page and wire the
      Picker. If a CSP is in play, allow `apis.google.com` and `accounts.google.com`.
- [ ] Step 5: Build the review list UI — reuse the section checkbox picker and Category
      dropdown from the upload panel so there's one way to answer those questions.
- [ ] Step 6: `POST /admin/gdrive/resources/import` (`GoogleDriveController::import`).
      Validate `files.*.drive_file_id`, `files.*.category`, `files.*.sections[]` with the
      **same section/program rules `upload()` already uses** — a hand-made request must not
      import into another CaPSA's section.
- [ ] Step 7: `GoogleDriveService::importFile()` — `files->get`, set the anyone/reader
      permission, return the same array shape `uploadFile()` returns so the controller code
      stays one path.
- [ ] Step 8: Skip duplicates — a row with the same `drive_file_id` and `uploaded_by`
      already exists. Report them back as skipped rather than failing the batch.
- [ ] Step 9: Test — import succeeds, sections apply, a student in a targeted section sees
      the file and a student outside it doesn't, and a second import of the same file is
      skipped.

## Things that will bite

1. **Importing makes the CaPSA's own file link-public.** Students open these with no Drive
   access, so import has to set `anyone / reader` — a real change to sharing on a file they
   already own. The confirm step must say so plainly: *"These files will be viewable by
   anyone with the link."* Don't bury it.
2. **Native Google Docs / Sheets / Slides.** No file extension, no byte size, and they
   can't be fetched raw. Their folder will almost certainly contain some. Recommend
   accepting them: derive `file_type` from the mimeType, leave `file_size` null, set
   `is_downloadable` false, and let `drive_web_view_link` do the work — that link already
   drives the whole student-side experience.
3. **`drive_folder_id` on an imported file points at a folder with no
   `admin_drive_folders` row**, so the Folder filter would list a blank. For v1 store null
   there; that filter is for folders CapsTrack created.
4. **Batch size.** Two API calls per file, synchronous. Cap an import at 25 files. If
   anyone wants more, that's when it becomes a queued job — not before.
5. **Reuse `Resource::typeForCategory()`** for `resource_type` rather than setting it by
   hand, or imported rows will sort differently from uploaded ones.

## Not in this

- **Live sync** — drop a file in the Drive folder, it appears in CapsTrack. That needs
  repeated folder listing, which likely forces the broad `drive` scope and a Google
  security assessment. Separate decision, much bigger.
- Renaming or moving files in Drive from CapsTrack.
- Removing the anyone/reader permission when a resource is deleted. Worth considering
  later; today's `destroy()` doesn't do it for uploads either.
