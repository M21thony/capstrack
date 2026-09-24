{{--
  The submit-document form, on its own so the drawer can fetch it.
  It is a fragment, not a page: no <html>, no styles, no scripts.
  The drawer asks for it fresh every time it opens, so the version
  number, the recipients and the checklist lines are never stale.

  Laid out as numbered steps (numbers come from a CSS counter, so they
  stay right when the checklist step is absent). Every id the script in
  resources/js/submission-form.js looks up is kept as it was.
--}}
@php
    $roleLabels = [
        'adviser' => 'Adviser',
        'consultant' => 'Consultant',
        'grammarian' => 'Grammarian',
        'subject_adviser' => 'Subject Adviser',
    ];
    $roleIcons = [
        'adviser' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
        'consultant' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
        'grammarian' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15Z',
        'subject_adviser' => 'M12 2 3 7v6c0 5.25 3.75 9.75 9 11 5.25-1.25 9-5.75 9-11V7l-9-5Z',
    ];
    $assignedRecipients = collect($roleLabels)->filter(fn ($label, $roleKey) => ! is_null(($recipients[$roleKey]['expert_id'] ?? null)));
@endphp

<div id="submission-form-root"
     data-submit-url="{{ route('student.submission.store') }}"
     data-next-version-url="{{ route('student.submission.next-version') }}"
     data-check-url="{{ route('student.submission.warnings') }}"
     data-folders-url="{{ route('student.submission.subject-adviser-folders') }}"
     data-csrf="{{ csrf_token() }}">

  <div class="dwb">
    @if ($checklistItems->isNotEmpty())
      <section class="form-section step">
        <div class="step-head">
          <span class="step-num" aria-hidden="true"></span>
          <div>
            <h3 class="step-title">What is this for?</h3>
            <p class="step-sub">Choose the requirement this file completes.</p>
          </div>
        </div>
        <div class="select-wrap">
          <select class="input" id="checklistItem" aria-label="Checklist requirement this file is for">
            <option value="">Not a checklist requirement (feedback only)</option>
            @foreach ($checklistItems->groupBy('phase') as $phase => $group)
              <optgroup label="{{ $phase }}">
                @foreach ($group as $ci)
                  {{-- A locked milestone's items can be read but not chosen. --}}
                  <option value="{{ $ci->id }}"
                          @selected($ci->id === $selectedItemId && ! $ci->locked)
                          @disabled($ci->locked)
                          data-kind="{{ $ci->kind }}"
                          data-role="{{ $ci->role }}">{{ $ci->title }}{{ $ci->note ? ' — '.$ci->note : '' }}</option>
                @endforeach
              </optgroup>
            @endforeach
          </select>
        </div>
        <p class="recipient-hint" id="checklistHint">Pick the requirement and your Progress Tracker updates on its own.</p>
      </section>
    @endif

    <section class="form-section step">
      <div class="step-head">
        <span class="step-num" aria-hidden="true"></span>
        <div>
          <h3 class="step-title">Upload your file</h3>
          <p class="step-sub">You can add several files at once.</p>
        </div>
      </div>
      <div class="drop-zone" id="dropZone" role="button" tabindex="0" aria-label="Choose files to upload">
        <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" multiple style="display:none" />
        <div class="drop-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
        </div>
        <div class="drop-info">
          <p id="dropText">Click or drag files to upload</p>
          <span id="dropSub">PDF up to 25MB each - several at once is fine</span>
        </div>
        <span class="drop-browse">Browse files</span>
      </div>
      {{-- One row per file, each with the checklist line it goes to. --}}
      <div class="file-list" id="fileList" aria-label="Files to submit"></div>
    </section>

    <section class="form-section step">
      <div class="step-head">
        <span class="step-num" aria-hidden="true"></span>
        <div>
          <h3 class="step-title">Send to</h3>
          <p class="step-sub">Tick who should review this submission.</p>
        </div>
      </div>
      <div class="recipient-group" id="recipientGroup">
        @foreach ($roleLabels as $roleKey => $roleLabel)
          @php
              $info = $recipients[$roleKey] ?? ['expert_id' => null, 'name' => null];
              $isAssigned = ! is_null($info['expert_id']);
          @endphp
          <label class="recipient-check {{ ! $isAssigned ? 'recipient-check--disabled' : '' }}">
            <input type="checkbox"
                   name="recipients[]"
                   value="{{ $roleKey }}"
                   data-role="{{ $roleKey }}"
                   {{ ! $isAssigned ? 'disabled' : '' }}>
            <span class="recipient-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="{{ $roleIcons[$roleKey] }}"/></svg>
            </span>
            <span class="recipient-text">
              {{ $roleLabel }}
              <small>{{ $isAssigned ? $info['name'] : 'Not yet assigned' }}</small>
            </span>
          </label>
        @endforeach
      </div>
      @if ($assignedRecipients->isEmpty())
        <p class="recipient-hint recipient-hint--warn">No adviser, consultant, grammarian, or subject adviser is assigned to your team yet — once one is, you'll be able to select them here.</p>
      @endif

      @if ($assignedRecipients->has('subject_adviser'))
        <div class="folder-select-wrap" id="folderSelectWrap" style="display:none">
          <label class="pm-label" for="subjectAdviserFolder">Folder for Subject Adviser</label>
          <div class="select-wrap">
            <select class="input" id="subjectAdviserFolder">
              <option value="">Loading folders…</option>
            </select>
          </div>
          <p class="folder-select-hint">Pick the folder your Subject Adviser told you to submit to.</p>
        </div>
      @endif
    </section>

    <section class="form-section step">
      <div class="step-head">
        <span class="step-num" aria-hidden="true"></span>
        <div>
          <h3 class="step-title">Review &amp; submit</h3>
          <p class="step-sub">A quick check before it goes out.</p>
        </div>
      </div>
      <dl class="doc-info-grid">
        <div class="info-row">
          <dt class="info-label">File name</dt>
          <dd class="info-value" id="fileNameLabel">No file selected yet</dd>
        </div>
        <div class="info-row">
          <dt class="info-label">Detected chapter</dt>
          <dd class="info-value" id="chapterLabel">—</dd>
        </div>
        <div class="info-row">
          <dt class="info-label">Version</dt>
          <dd class="info-value"><span class="version-pill" id="versionLabel">Version {{ $nextVersion }}</span></dd>
        </div>
      </dl>
      <div class="tip">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.74V17h8v-2.26A7 7 0 0 0 12 2Z"/></svg>
        <p class="recipient-hint" id="chapterHint"><strong>Tip:</strong> put "Chapter N" in your file name (e.g. Chapter1_2026-08-12.pdf) so resubmissions keep the same version count even if the rest of the name changes.</p>
      </div>
    </section>
  </div>

  <div class="dwf">
    <button class="btn-cancel" type="button" data-drawer-close>Cancel</button>
    <button class="btn-submit" type="button" id="submitBtn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2 11 13M22 2 15 22l-4-9-9-4 20-7Z"/></svg>
      Submit for Review
    </button>
  </div>
</div>
