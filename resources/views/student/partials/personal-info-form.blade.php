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
            <p class="pi-hint">Use your name as it appears on school records.</p>
            <div class="pi-row">
                <div class="pi-field">
                    <label class="pi-label" for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" class="pi-input" value="{{ old('first_name', $student->f_name) }}" required autocomplete="given-name">
                    @error('first_name')<p class="pi-error">{{ $message }}</p>@enderror
                </div>
                <div class="pi-field">
                    <label class="pi-label" for="middle_name">Middle name <span class="pi-label-optional">(optional)</span></label>
                    <input type="text" id="middle_name" name="middle_name" class="pi-input" value="{{ old('middle_name', $student->m_name) }}" autocomplete="additional-name">
                </div>
                <div class="pi-field">
                    <label class="pi-label" for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" class="pi-input" value="{{ old('last_name', $student->l_name) }}" required autocomplete="family-name">
                    @error('last_name')<p class="pi-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="pi-field">
                <label class="pi-label" for="student_id">Student ID</label>
                <input type="text" id="student_id" name="student_id" class="pi-input" value="{{ old('student_id', $student->student_id) }}" placeholder="e.g. 2021-00456" {{ $isEditing && filled($student->student_id) ? 'readonly' : '' }} required>
                @error('student_id')<p class="pi-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="pi-wizard-panel {{ $startStep === 4 ? 'active' : '' }}" data-step="4" aria-labelledby="pi-section-4">
            <div class="pi-section-head">
                <span class="pi-step" aria-hidden="true">4</span>
                <h2 class="pi-section-title" id="pi-section-4">Academic information</h2>
            </div>
            <p class="pi-hint">Your course, year level, and section (e.g. BSCS-4A).</p>
            <div class="pi-field">
                <label class="pi-label" for="course">Course</label>
                <select id="course" name="course" class="pi-input" required>
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
            <div class="pi-row">
                <div class="pi-field">
                    <label class="pi-label" for="year">Year</label>
                    <select id="year" name="year" class="pi-input" required>
                        @foreach (['1st Year', '2nd Year', '3rd Year', '4th Year'] as $yearOption)
                            <option value="{{ $yearOption }}" @selected(old('year', $selectedYear) === $yearOption)>{{ $yearOption }}</option>
                        @endforeach
                    </select>
                    @error('year')<p class="pi-error">{{ $message }}</p>@enderror
                </div>
                <div class="pi-field">
                    <label class="pi-label" for="section">Section</label>
                    <input type="text" id="section" name="section" class="pi-input" value="{{ old('section', $selectedSection) }}" placeholder="e.g. BSCS-4A" required>
                    @error('section')<p class="pi-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>
    </div>

    <p class="pi-wizard-error" id="pi-wizard-error" role="alert" hidden></p>

    <div class="pi-actions pi-wizard-actions {{ $isEditing ? 'pi-actions-edit' : '' }}">
        @if ($isEditing)
            <a href="{{ route('student.dashboard') }}" class="pi-btn pi-btn-ghost pi-cancel-link">Cancel</a>
        @endif
        <button type="button" class="pi-btn pi-btn-ghost" id="pi-back" {{ $startStep === 1 ? 'disabled' : '' }}>Back</button>
        <button type="button" class="pi-btn pi-btn-primary" id="pi-next">Next</button>
        <button type="submit" class="pi-btn pi-btn-primary" id="pi-submit" hidden>
            {{ $isEditing ? 'Save changes' : 'Save and continue to Dashboard' }}
        </button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('pi-form');
    if (!form) return;

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
            var fn = document.getElementById('first_name');
            var ln = document.getElementById('last_name');
            var sid = document.getElementById('student_id');
            if (!fn.value.trim()) { showError('First name is required.'); fn.focus(); return false; }
            if (!ln.value.trim()) { showError('Last name is required.'); ln.focus(); return false; }
            if (!sid.value.trim()) { showError('Student ID is required.'); sid.focus(); return false; }
            return true;
        }
        if (n === 4) {
            var course = document.getElementById('course');
            var year = document.getElementById('year');
            var section = document.getElementById('section');
            if (!course.value) { showError('Please select your course.'); return false; }
            if (!year.value) { showError('Please select your year.'); return false; }
            if (!section.value.trim()) { showError('Section is required (e.g. BSCS-4A).'); section.focus(); return false; }
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
        if (backBtn) backBtn.disabled = step === 1;
        if (nextBtn) nextBtn.hidden = step === total;
        if (submitBtn) submitBtn.hidden = step !== total;
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
