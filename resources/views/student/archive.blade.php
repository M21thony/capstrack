<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Archived Capstones - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
</head>
<body>
@include('student.partials.sidebar')

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Archived Capstones</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'archive'])
<div class="row" style="margin-bottom:16px;gap:8px;flex-wrap:wrap">
  <input class="input" id="archive-search" placeholder="Search by title, proponents, or keyword..." style="flex:1;min-width:200px;max-width:380px" oninput="filterArchive()" />
  <select class="input" id="archive-year" style="width:120px" onchange="filterArchive()"><option value="">All Years</option><option>2025</option><option>2024</option><option>2023</option><option>2022</option></select>
  <select class="input" id="archive-tag" style="width:160px" onchange="filterArchive()">
    <option value="">All Categories</option>
    <option value="Web-Based">Web-Based</option>
    <option value="Mobile">Mobile</option>
    <option value="IoT">IoT</option>
    <option value="AI/ML">AI/ML</option>
    <option value="Desktop">Desktop</option>
    <option value="Data Analytics">Data Analytics</option>
    <option value="Blockchain">Blockchain</option>
  </select>
  <span style="margin-left:auto;font-size:12px;color:var(--text3)" id="archive-count">Showing 8 studies</span>
</div>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px" id="archive-grid">
  <div class="archive-card" data-tags="Web-Based,AI/ML" data-year="2025" data-title="capstrack centralized web-based capstone project management"><div class="archive-year">2025</div><div class="archive-title">CapsTrack: A Centralized Web-Based Capstone Project Management System at CSPC-CCS</div><div class="archive-proponents">Aurillas, J.K. · Balilla, M.A. · Llorin, A.N. · Rabusa, J. · Tumambing, I.C.</div><div class="archive-tags"><span class="archive-tag tag-web">Web-Based</span><span class="archive-tag tag-ai">AI/ML</span></div><div class="archive-footer"><span style="font-size:11px;color:var(--text3)">BSIT · CCS</span></div></div>
  <div class="archive-card" data-tags="Mobile,AI/ML" data-year="2024" data-title="ai-powered attendance system facial recognition higher education"><div class="archive-year">2024</div><div class="archive-title">AI-Powered Attendance System Using Facial Recognition for Higher Education</div><div class="archive-proponents">Santos, C. · Reyes, J. · Cruz, L. · Peña, R.</div><div class="archive-tags"><span class="archive-tag tag-mobile">Mobile</span><span class="archive-tag tag-ai">AI/ML</span></div><div class="archive-footer"><span style="font-size:11px;color:var(--text3)">BSCS · CCS</span></div></div>
  <div class="archive-card" data-tags="IoT,Data Analytics" data-year="2024" data-title="smart campus energy monitoring iot sensors dashboard"><div class="archive-year">2024</div><div class="archive-title">Smart Campus Energy Monitoring System Using IoT Sensors and Dashboard Analytics</div><div class="archive-proponents">Dela Cruz, M. · Bautista, R. · Magno, P.</div><div class="archive-tags"><span class="archive-tag tag-iot">IoT</span><span class="archive-tag tag-data">Data Analytics</span></div><div class="archive-footer"><span style="font-size:11px;color:var(--text3)">BSIT · CCS</span></div></div>
  <div class="archive-card" data-tags="Web-Based" data-year="2023" data-title="online enrollment registration system cspc laravel mysql"><div class="archive-year">2023</div><div class="archive-title">Online Enrollment and Registration System for CSPC Using Laravel and MySQL</div><div class="archive-proponents">Garcia, A. · Mendoza, B. · Torres, C. · Villanueva, D.</div><div class="archive-tags"><span class="archive-tag tag-web">Web-Based</span></div><div class="archive-footer"><span style="font-size:11px;color:var(--text3)">BSIT · CCS</span></div></div>
  <div class="archive-card" data-tags="Mobile,AI/ML" data-year="2023" data-title="plant disease detection deep learning mobile application farmers"><div class="archive-year">2023</div><div class="archive-title">Plant Disease Detection Using Deep Learning and Mobile Application for Farmers</div><div class="archive-proponents">Lim, K. · Ramos, S. · Fernandez, T.</div><div class="archive-tags"><span class="archive-tag tag-mobile">Mobile</span><span class="archive-tag tag-ai">AI/ML</span></div><div class="archive-footer"><span style="font-size:11px;color:var(--text3)">BSCS · CCS</span></div></div>
  <div class="archive-card" data-tags="Desktop,Data Analytics" data-year="2022" data-title="school inventory management system analytics dashboard cspc"><div class="archive-year">2022</div><div class="archive-title">School Inventory Management System with Analytics Dashboard for CSPC</div><div class="archive-proponents">Aquino, M. · Pascual, R. · Soriano, L. · Navarro, A.</div><div class="archive-tags"><span class="archive-tag tag-desktop">Desktop</span><span class="archive-tag tag-data">Data Analytics</span></div><div class="archive-footer"><span style="font-size:11px;color:var(--text3)">BSIT · CCS</span></div></div>
  <div class="archive-card" data-tags="Web-Based,Blockchain" data-year="2022" data-title="blockchain document verification system academic records authentication"><div class="archive-year">2022</div><div class="archive-title">Blockchain-Based Document Verification System for Academic Records Authentication</div><div class="archive-proponents">Castillo, F. · Ibañez, G. · Molina, H.</div><div class="archive-tags"><span class="archive-tag tag-web">Web-Based</span><span class="archive-tag tag-blockchain">Blockchain</span></div><div class="archive-footer"><span style="font-size:11px;color:var(--text3)">BSIS · CCS</span></div></div>
  <div class="archive-card" data-tags="IoT,Mobile" data-year="2025" data-title="smart parking system iot sensors mobile application cspc campus"><div class="archive-year">2025</div><div class="archive-title">Smart Parking System Using IoT Sensors and Mobile Application for CSPC Campus</div><div class="archive-proponents">Hernandez, J. · Flores, M. · Aguilar, N. · Reyes, O.</div><div class="archive-tags"><span class="archive-tag tag-iot">IoT</span><span class="archive-tag tag-mobile">Mobile</span></div><div class="archive-footer"><span style="font-size:11px;color:var(--text3)">BSIT · CCS</span></div></div>
</div>
<div id="archive-empty" style="display:none;text-align:center;padding:48px;color:var(--text3);font-size:13px">No archived capstones match your search.</div>
  </div>
</div>


</body>
</html>
