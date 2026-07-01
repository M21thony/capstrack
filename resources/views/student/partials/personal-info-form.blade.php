@php
    $yearParts = explode(' · ', $student->year_section ?? '');
    $selectedYear = $yearParts[0] ?? '4th Year';
    $selectedSection = $yearParts[1] ?? '';
    $startStep = 1;
    if ($errors->has('avatar')) {
        $startStep = 2;
    } elseif ($errors->hasAny(['first_name', 'last_name', 'student_id'])) {
        $startStep = 3;
    } elseif ($errors->hasAny(['course', 'year', 'section'])) {
        $startStep = 4;
    }
    $totalSteps = 4;
@endphp

<style>
  [hidden] { display: none !important; }
  .pi-wizard-actions { display: flex !important; flex-direction: row !important; justify-content: space-between !important; align-items: center !important; gap: 12px !important; margin-top: 32px !important; padding-top: 24px !important; border-top: 1px solid var(--border) !important; }
  .pi-wizard-actions .pi-btn { flex: none !important; width: auto !important; min-width: 120px !important; justify-content: center !important; }
  .pi-wizard-actions #pi-next, .pi-wizard-actions #pi-submit { margin-left: auto !important; }
  .pi-label { display: block !important; font-size: 14px !important; font-weight: 500 !important; color: #1e293b !important; margin-bottom: 6px !important; }
  .pi-label-optional { color: #64748b !important; font-weight: 400 !important; font-size: 13px !important; display: block !important; margin-top: 2px !important; }
  .pi-row-3 { display: grid !important; grid-template-columns: repeat(3, 1fr) !important; gap: 16px !important; align-items: end !important; }
  .pi-row-3 .pi-field { display: flex !important; flex-direction: column !important; justify-content: flex-end !important; height: 100% !important; }
  .pi-row-3 .pi-input { width: 100% !important; }
  .pi-row-2 { display: grid !important; grid-template-columns: repeat(2, 1fr) !important; gap: 16px !important; }
  .pi-field:not(.pi-row-3 *) { display: flex !important; flex-direction: column !important; margin-top: 24px !important; }
  .pi-field:not(.pi-row-3 *) .pi-label { margin-bottom: 6px !important; }
  .pi-row-3 + .pi-field, .pi-field + .pi-row-3 { margin-top: 24px !important; }
  @media (max-width: 600px) { .pi-row-3 { display: flex !important; flex-direction: column !important; gap: 16px !important; } .pi-row-2 { grid-template-columns: 1fr; } }
  .pi-input { border: 1px solid #cbd5e1 !important; border-radius: 8px !important; padding: 10px 14px !important; font-size: 15px !important; color: #334155 !important; transition: all 0.15s ease-in-out !important; box-sizing: border-box !important; }
  .pi-input:focus { border-color: #2563eb !important; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important; }
  .tutorial-note { background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 16px; border-radius: 8px; font-size: 13px; color: #1e40af; margin-bottom: 24px; line-height: 1.5; display: flex; align-items: flex-start; gap: 10px; }
  .tutorial-note svg { width: 18px; height: 18px; stroke: #3b82f6; fill: none; stroke-width: 2; flex-shrink: 0; margin-top: 2px; }
  .tutorial-note.warn { background-color: #fef3c7; border-left-color: #f59e0b; color: #92400e; }
  .tutorial-note.warn svg { stroke: #f59e0b; }
  .tutorial-note svg.lock { stroke: #94a3b8; }
  .tutorial-note.locked { background-color: #f8fafc; border-left-color: #94a3b8; color: #64748b; }
  .tutorial-note.locked svg { stroke: #94a3b8; }
  .pi-input-readonly { background-color: #f8fafc !important; color: #64748b !important; cursor: not-allowed !important; border-color: #e2e8f0 !important; }
  .pi-input-readonly:focus { border-color: #e2e8f0 !important; box-shadow: none !important; }
  .pi-locked-field { position: relative; }
  .pi-locked-field::after { content: ''; position: absolute; inset: 0; z-index: 1; }
</style>

<form method="POST" action="{{ route('student.personal-info.store') }}" class="pi-form" id="pi-form" data-start-step="{{ $startStep }}" data-total-steps="{{ $totalSteps }}">
    @csrf

    <div class="pi-wizard-progress" role="progressbar" aria-valuemin="1" aria-valuemax="{{ $totalSteps }}" aria-valuenow="{{ $startStep }}" id="pi-progress">
        <div class="pi-wizard-progress-label">
            <span id="pi-step-label">Step {{ $startStep }} of {{ $totalSteps }}</span>
            <span class="pi-wizard-progress-name" id="pi-step-name"></span>
        </div>
        <div class="pi-wizard-dots">
            @for ($i = 1; $i <= $totalSteps; $i++)
                <span class="pi-wizard-dot {{ $i <= $startStep ? 'done' : '' }} {{ $i === $startStep ? 'active' : '' }}" data-dot="{{ $i }}" aria-hidden="true"></span>
            @endfor
        </div>
        <div class="pi-wizard-bar"><div class="pi-wizard-bar-fill" id="pi-bar-fill" style="width:{{ ($startStep / $totalSteps) * 100 }}%"></div></div>
    </div>

    <div class="pi-wizard-panels">
        <section class="pi-wizard-panel {{ $startStep === 1 ? 'active' : '' }}" data-step="1" aria-labelledby="pi-section-1">
            <div class="pi-section-head">
                <span class="pi-step" aria-hidden="true">1</span>
                <h2 class="pi-section-title" id="pi-section-1">Institutional account</h2>
            </div>
            <p class="pi-hint">Signed in with your CSPC Google account. Email cannot be changed here.</p>
            
            <div class="tutorial-note">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <div>
                    <strong>Guide:</strong> Your account is connected to your official school Google Email. This guarantees that all platform notifications and grading updates reach you safely.
                </div>
            </div>

            <div class="pi-field">
                <label class="pi-label" for="pi-email">Email</label>
                <input type="email" id="pi-email" class="pi-input pi-input-readonly" value="{{ $student->email }}" readonly aria-readonly="true">
            </div>
        </section>

        <section class="pi-wizard-panel {{ $startStep === 2 ? 'active' : '' }}" data-step="2" aria-labelledby="pi-section-2">
            <div class="pi-section-head">
                <span class="pi-step" aria-hidden="true">2</span>
                <h2 class="pi-section-title" id="pi-section-2">Choose your avatar</h2>
            </div>
            <p class="pi-hint">Tap a preset avatar. Custom uploads are not allowed.</p>
            
            <div class="tutorial-note {{ $hasTeam ? 'warn' : '' }}">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <div>
                    @if ($hasTeam)
                        <strong>Avatar is editable:</strong> Since you're in a capstone team, you can only update your avatar here. Other profile fields are locked — contact your program coordinator or registrar to update personal or academic records.
                    @else
                        <strong>Tip:</strong> Choose an avatar that represents you! This profile icon is visible to your capstone team members, panel, and advisers.
                    @endif
                </div>
            </div>

            <div class="pi-avatar-grid" role="radiogroup" aria-label="Preset avatar">
                @foreach ($presets as $key => $preset)
                    <label class="pi-avatar-option {{ old('avatar', $student->p_picture) === $key ? 'selected' : '' }}" aria-label="{{ $preset['label'] }} avatar">
                        <input type="radio" name="avatar" value="{{ $key }}" {{ old('avatar', $student->p_picture) === $key ? 'checked' : '' }}>
                        <img src="{{ asset('images/avatars/'.$key.'.svg') }}" alt="">
                        <span>{{ $preset['label'] }}</span>
                    </label>
                @endforeach
            </div>
            @error('avatar')
                <p class="pi-error">{{ $message }}</p>
            @enderror
        </section>

        <section class="pi-wizard-panel {{ $startStep === 3 ? 'active' : '' }}" data-step="3" aria-labelledby="pi-section-3">
            <div class="pi-section-head">
                <span class="pi-step" aria-hidden="true">3</span>
                <h2 class="pi-section-title" id="pi-section-3">Personal details</h2>
            </div>
            @if ($hasTeam)
                <div class="tutorial-note locked">
                    <svg class="lock" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <div>
                        <strong>Locked while in a team:</strong> Personal details cannot be changed while you're part of a capstone team. To update your name or student ID, please visit the registrar's office.
                    </div>
                </div>
            @else
                <p class="pi-hint">Use your name as it appears on school records.</p>
                
                <div class="tutorial-note">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <div>
                        <strong>Note:</strong> Please enter your legal name exactly as it appears in school records. Use a 9-digit Student ID (e.g., 221000001) to match your registrar enrollment profile.
                    </div>
                </div>
            @endif

            <div class="pi-row-3">
                <div class="pi-field">
                    <label class="pi-label" for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" class="pi-input {{ $hasTeam ? 'pi-input-readonly' : '' }}" value="{{ old('first_name', $student->f_name) }}" {{ $hasTeam ? 'readonly' : 'required' }} autocomplete="given-name">
                    @error('first_name')<p class="pi-error">{{ $message }}</p>@enderror
                </div>
                <div class="pi-field">
                    <label class="pi-label" for="middle_name">Middle name <span class="pi-label-optional">(optional)</span></label>
                    <input type="text" id="middle_name" name="middle_name" class="pi-input {{ $hasTeam ? 'pi-input-readonly' : '' }}" value="{{ old('middle_name', $student->m_name) }}" {{ $hasTeam ? 'readonly' : '' }} autocomplete="additional-name">
                </div>
                <div class="pi-field">
                    <label class="pi-label" for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" class="pi-input {{ $hasTeam ? 'pi-input-readonly' : '' }}" value="{{ old('last_name', $student->l_name) }}" {{ $hasTeam ? 'readonly' : 'required' }} autocomplete="family-name">
                    @error('last_name')<p class="pi-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="pi-field">
                <label class="pi-label" for="student_id">Student ID</label>
                <input type="text" id="student_id" name="student_id" class="pi-input {{ $hasTeam ? 'pi-input-readonly' : '' }}" value="{{ old('student_id', $student->student_number) }}" placeholder="e.g. 221000001" {{ $hasTeam ? 'readonly' : 'required' }}>
                @error('student_id')<p class="pi-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="pi-wizard-panel {{ $startStep === 4 ? 'active' : '' }}" data-step="4" aria-labelledby="pi-section-4">
            <div class="pi-section-head">
                <span class="pi-step" aria-hidden="true">4</span>
                <h2 class="pi-section-title" id="pi-section-4">Academic information</h2>
            </div>
            @if ($hasTeam)
                <div class="tutorial-note locked">
                    <svg class="lock" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <div>
                        <strong>Locked while in a team:</strong> Academic information cannot be changed while you're part of a capstone team. To update your course, year, or section, please contact your program coordinator.
                    </div>
                </div>
            @else
                <p class="pi-hint">Your course, year level, and section (e.g. BSCS-4A).</p>
                
                <div class="tutorial-note">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <div>
                        <strong>Guide:</strong> Select your course, year (3rd or 4th), and section (e.g. A, B, C). This ensures you are assigned to the correct advisers and group categories.
                    </div>
                </div>
            @endif

            <div class="pi-field">
                <label class="pi-label" for="course">Course</label>
                <select id="course" name="course" class="pi-input {{ $hasTeam ? 'pi-input-readonly' : '' }}" {{ $hasTeam ? 'disabled' : 'required' }}>
                    @foreach ([
                        'Bachelor of Science in Computer Science',
                        'Bachelor of Science in Information Technology',
                        'Bachelor of Science in Information Systems',
                        'Bachelor of Library and Information Science',
                    ] as $courseOption)
                        <option value="{{ $courseOption }}" @selected(old('course', $student->course) === $courseOption)>{{ $courseOption }}</option>
                    @endforeach
                </select>
                @error('course')<p class="pi-error">{{ $message }}</p>@enderror
            </div>
            <div class="pi-row-2">
                <div class="pi-field">
                    <label class="pi-label" for="year">Year</label>
                    <select id="year" name="year" class="pi-input {{ $hasTeam ? 'pi-input-readonly' : '' }}" {{ $hasTeam ? 'disabled' : 'required' }}>
                        @foreach (['3rd', '4th'] as $yearOption)
                            <option value="{{ $yearOption }}" @selected(old('year', $selectedYear) === $yearOption)>{{ $yearOption }}</option>
                        @endforeach
                    </select>
                    @error('year')<p class="pi-error">{{ $message }}</p>@enderror
                </div>
                <div class="pi-field">
                    <label class="pi-label" for="section">Section</label>
                    <input type="text" id="section" name="section" class="pi-input {{ $hasTeam ? 'pi-input-readonly' : '' }}" value="{{ old('section', $selectedSection) }}" placeholder="e.g. A" {{ $hasTeam ? 'readonly' : 'required' }} maxlength="1" {{ $hasTeam ? '' : 'oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, \'\')"' }}>
                    @error('section')<p class="pi-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>
    </div>

    <p class="pi-wizard-error" id="pi-wizard-error" role="alert" hidden></p>

    <div class="pi-actions pi-wizard-actions">
        <button type="button" class="pi-btn pi-btn-ghost" id="pi-back" {{ $startStep === 1 ? 'disabled' : '' }}>Back</button>
        <button type="button" class="pi-btn pi-btn-primary" id="pi-next">Next</button>
        <button type="submit" class="pi-btn pi-btn-primary" id="pi-submit" hidden>Save changes</button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('pi-form');
    if (!form) return;

    var hasTeam = @json($hasTeam);
    var total = parseInt(form.dataset.totalSteps, 10) || 4;
    var step = parseInt(form.dataset.startStep, 10) || 1;
    var stepNames = {
        1: 'Account',
        2: 'Avatar',
        3: 'Personal details',
        4: 'Academic info'
    };

    var panels = form.querySelectorAll('.pi-wizard-panel');
    var dots = form.querySelectorAll('.pi-wizard-dot');
    var backBtn = document.getElementById('pi-back');
    var nextBtn = document.getElementById('pi-next');
    var submitBtn = document.getElementById('pi-submit');
    var errEl = document.getElementById('pi-wizard-error');
    var barFill = document.getElementById('pi-bar-fill');
    var stepLabel = document.getElementById('pi-step-label');
    var stepName = document.getElementById('pi-step-name');
    var progress = document.getElementById('pi-progress');

    function showError(msg) {
        if (!errEl) return;
        if (msg) {
            errEl.textContent = msg;
            errEl.hidden = false;
        } else {
            errEl.textContent = '';
            errEl.hidden = true;
        }
    }

    function validateStep(n) {
        showError('');
        if (n === 1) return true;
        if (n === 2) {
            if (!form.querySelector('input[name="avatar"]:checked')) {
                showError('Please choose an avatar to continue.');
                return false;
            }
            return true;
        }
        if (n === 3) {
            if (hasTeam) return true;
            var fn = document.getElementById('first_name');
            var ln = document.getElementById('last_name');
            var sid = document.getElementById('student_id');
            if (!fn.value.trim()) { showError('First name is required.'); fn.focus(); return false; }
            if (!ln.value.trim()) { showError('Last name is required.'); ln.focus(); return false; }
            if (!sid.value.trim()) { showError('Student ID is required.'); sid.focus(); return false; }
            return true;
        }
        if (n === 4) {
            if (hasTeam) return true;
            var course = document.getElementById('course');
            var year = document.getElementById('year');
            var section = document.getElementById('section');
            if (!course.value) { showError('Please select your course.'); return false; }
            if (!year.value) { showError('Please select your year.'); return false; }
            if (!section.value.trim()) { showError('Section is required (e.g. A).'); section.focus(); return false; }
            return true;
        }
        return true;
    }

    function goTo(n) {
        step = Math.max(1, Math.min(total, n));
        panels.forEach(function (p) {
            p.classList.toggle('active', parseInt(p.dataset.step, 10) === step);
        });
        dots.forEach(function (d) {
            var i = parseInt(d.dataset.dot, 10);
            d.classList.toggle('active', i === step);
            d.classList.toggle('done', i < step);
        });
        if (barFill) barFill.style.width = ((step / total) * 100) + '%';
        if (stepLabel) stepLabel.textContent = 'Step ' + step + ' of ' + total;
        if (stepName) stepName.textContent = stepNames[step] || '';
        if (progress) progress.setAttribute('aria-valuenow', String(step));
        
        // Correctly handle visibility, display, and disabled states
        if (backBtn) {
            backBtn.hidden = step === 1;
            backBtn.disabled = step === 1;
        }
        if (nextBtn) {
            nextBtn.hidden = step === total;
        }
        if (submitBtn) {
            submitBtn.hidden = step !== total;
        }
        
        showError('');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    if (backBtn) {
        backBtn.addEventListener('click', function () {
            goTo(step - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (validateStep(step)) {
                goTo(step + 1);
            }
        });
    }

    form.querySelectorAll('.pi-avatar-option input').forEach(function (input) {
        input.addEventListener('change', function () {
            form.querySelectorAll('.pi-avatar-option').forEach(function (el) {
                el.classList.remove('selected');
            });
            input.closest('.pi-avatar-option').classList.add('selected');
            showError('');
        });
    });

    form.addEventListener('submit', function (e) {
        if (step !== total) {
            e.preventDefault();
            if (validateStep(step)) {
                goTo(total);
            }
            return;
        }
        if (!validateStep(4)) {
            e.preventDefault();
        }
    });

    goTo(step);
})();
</script>
