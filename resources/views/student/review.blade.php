<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Review Viewer - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
</head>
<body>
@include('student.partials.sidebar')

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Review Viewer</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'review'])
<div class="row" style="margin-bottom:14px;gap:8px">
  <select class="input" style="width:280px"><option>Chapter 2 Draft — May 3, 2025</option><option>Chapter 1 v2 — May 1, 2025</option></select>
  <span class="badge badge-amber">3 Annotations</span>
  <span style="flex:1"></span>
  <button class="btn btn-ghost" style="font-size:11px">Download Annotated PDF</button>
</div>
<div class="grid-2">
  <div class="card" style="height:420px;overflow-y:auto">
    <div class="card-title" style="margin-bottom:12px">Document Preview</div>
    <div class="doc-preview">
      <p style="margin-bottom:10px;font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:1px">Chapter 2 — Review of Related Literature</p>
      <p style="margin-bottom:10px">Facial recognition technology has gained significant traction in recent years due to advancements in <span class="highlight">deep learning architectures, particularly convolutional neural networks (CNNs)</span>. These models have demonstrated remarkable accuracy in identifying individuals across diverse environmental conditions.</p>
      <p style="margin-bottom:10px">Studies by Zhang et al. (2023) indicate that <span class="highlight">real-time facial recognition systems achieve upward of 98.7% accuracy under controlled lighting</span>, though performance degrades significantly in uncontrolled settings.</p>
      <p>The integration of such systems in educational institutions presents both opportunities and <span class="highlight">ethical considerations that must be carefully addressed</span> through institutional policy frameworks...</p>
    </div>
  </div>
  <div class="card" style="height:420px;overflow-y:auto">
    <div class="card-title" style="margin-bottom:12px">Expert Annotations</div>
    <div class="annotation-box">
      <div class="annotation-user">Dr. Cynthia Santos · Annotation 1</div>
      <div class="annotation-text">Good foundation here. Please cite the specific CNN architectures used (ResNet, VGG, etc.) and include year of publication for each reference.</div>
    </div>
    <div class="annotation-box" style="background:var(--red-bg);border-color:var(--red)">
      <div class="annotation-user" style="color:var(--red)">Dr. Cynthia Santos · Annotation 2</div>
      <div class="annotation-text">This statistic needs a proper citation. "Zhang et al." is vague — provide full author list and journal name. This is a critical claim.</div>
    </div>
    <div class="annotation-box" style="background:var(--accent-glow);border-color:var(--accent)">
      <div class="annotation-user" style="color:var(--accent)">Dr. Cynthia Santos · Annotation 3</div>
      <div class="annotation-text">Expand this section significantly. Discuss GDPR, data privacy laws in PH (RA 10173), and informed consent requirements for biometric data collection.</div>
    </div>
    <div>
      <label class="label">Reply to Expert</label>
      <textarea class="input" rows="3" placeholder="Type your response or clarification..." style="resize:none;height:72px"></textarea>
      <button class="btn btn-primary" style="margin-top:8px;font-size:11px">Send Reply</button>
    </div>
  </div>
</div>
</div>
</div>

</body>
</html>
