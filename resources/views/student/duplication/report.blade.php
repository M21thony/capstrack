{{--
  One saved title's Similarity Report.

  This used to be a modal built from a hidden copy of itself sitting further
  down the pre-proposal page, so the report could not be reloaded, linked,
  printed or shown to an adviser, and was gone the moment the page closed. It
  is a page with an address now, laid out the way the CaPSA's copy is, so both
  sides read the same thing.
--}}
@extends('student.duplication.layout')

@section('page-title', 'Similarity Report')

@section('dup')
@php
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;

  $score = $check->top_score;
  // 65% and above is highly similar. Below that the checker says nothing at
  // all, because recommending or rejecting a title is the CaPSA's call.
  $flagged = $score !== null && $score >= 65;

  $when = $proposal?->submitted_at
      ? 'turned in '.Carbon::parse($proposal->submitted_at)->format('j M Y')
      : 'checked '.($check->created_at?->format('j M Y') ?? 'earlier');

  $standing = match (true) {
      ! $proposal => 'not saved as a pre-proposal',
      $proposal->status === 'approved' => 'recommended by your CaPSA',
      $proposal->status === 'rejected' => 'rejected',
      (bool) $proposal->submitted_at => 'awaiting review',
      default => 'draft',
  };
@endphp

<nav class="dup-crumb" aria-label="Breadcrumb">
  <a href="{{ route('student.duplication') }}">Pre-Proposal</a>
  <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <a href="{{ route('student.duplication') }}">Duplication check</a>
  <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <span class="here">{{ Str::limit($title, 46) }} &middot; Similarity Report</span>
</nav>

<div class="pager">
  <a class="pbtn" href="{{ route('student.duplication') }}">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    Back to your pre-proposals
  </a>
  @if ($proposal && $check->document_path)
    <a class="pbtn" href="{{ route('student.pre-proposal.document', $proposal->id) }}" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Open the file you checked
    </a>
  @endif
</div>

<div class="card verdict">
  <div class="bigscore" style="color:{{ $flagged ? 'var(--red)' : 'var(--text2)' }}">{{ $score }}<span style="font-size:22px">%</span></div>
  <div class="doc">
    <div class="t">{{ $title }}</div>
    <div class="m">
      Checked against {{ number_format($archiveTotal) }} archived capstones &middot;
      {{ $when }} &middot; {{ count($matches) }} came back &middot; {{ $standing }}
    </div>
  </div>
  @if ($flagged)
    <span class="stateline s-red">
      <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Highly similar
    </span>
  @endif
</div>

<div class="capsa2">
  <div class="card hilite">
    <div class="card-header">
      <span class="card-title">Pre-proposal Content</span>
      <span style="font-size:11px;color:var(--text3);font-weight:400">saved with the title &middot; the PDF is a separate file</span>
    </div>
    <div class="scrolls">
      <div class="teamfacts">
        @forelse ($fields as $label => $value)
          <div class="field">
            <div class="k">{{ $label }}</div>
            <div class="v">{{ $value }}</div>
          </div>
        @empty
          <div class="dup-none">Nothing was saved with this check.</div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">{{ count($matches) }} archived {{ Str::plural('capstone', count($matches)) }} came back</span>
      <span style="font-size:11px;color:var(--text3);font-weight:400">highest first &middot; click one to read it</span>
    </div>
    <div class="scrolls dup-rows">
      @forelse ($matches as $i => $m)
        @php
          // A match nothing scored carries a retrieval rank, not a similarity,
          // so it shows no number at all rather than a misleading one.
          $mScore = ($m['assessed'] ?? true) ? ($m['score'] ?? null) : null;
          $mFlagged = $mScore !== null && $mScore >= 65;
          $why = $m['similarities'] ?? ($m['reason'] ?? null);
        @endphp
        <a class="mrow" href="{{ route('student.duplication.similar', [$check->id, $i + 1]) }}">
          <div>
            <div class="pc" style="color:{{ $mScore === null ? 'var(--text3)' : ($mFlagged ? 'var(--red)' : 'var(--text2)') }}">{{ $mScore === null ? '—' : $mScore.'%' }}</div>
            <span class="bar"><i style="width:{{ $mScore ?? 0 }}%;background:{{ $mFlagged ? 'var(--red)' : 'var(--green)' }}"></i></span>
            <div class="rk">#{{ $i + 1 }}</div>
          </div>
          <div>
            <div class="t">{{ $m['title'] ?? '' }}</div>
            <div class="mt">{{ $m['academic_year'] ?? 'N/A' }} &middot; {{ $m['program'] ?? '' }}</div>
            @if ($why)
              <div class="wy">{{ $why }}</div>
            @endif
          </div>
        </a>
      @empty
        <div class="dup-none">Nothing in the archive came back for this title.</div>
      @endforelse
    </div>
  </div>
</div>

@if (count($check->instructors_suggested ?? []))
  <div class="card" style="margin-top:14px">
    <div class="card-header"><span class="card-title">Suggested Experts</span></div>
    @foreach ($check->suggestionsForDisplay() as $i)
      <div class="instructor-item">
        <div class="avatar">{{ strtoupper(substr($i['name'] ?? '?', 0, 1)) }}</div>
        <div style="min-width:0">
          @if (! empty($i['role_label']))
            <div class="sx-role">Recommended {{ $i['role_label'] }}</div>
          @endif
          <div style="font-size:12px;font-weight:500;color:var(--text)">{{ $i['name'] ?? '' }}</div>
          <div style="font-size:10px;color:var(--primary,#5b5bd6);margin-top:1px">{{ $i['expertise'] ?? '' }}</div>
          <div style="font-size:10px;color:var(--text3);margin-top:2px">{{ $i['reason'] ?? '' }}</div>
        </div>
        @if (! empty($i['id']))
          @if ($applyOpen)
            <a class="dup-apply-btn" href="{{ route('student.experts', ['apply' => $i['id']]) }}">
              <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
              Apply
            </a>
          @else
            {{-- The wrapper carries the tooltip: a disabled button fires no
                 hover events of its own. --}}
            <span class="dup-apply-wrap" data-tip="{{ $applyLockReason }}" tabindex="0" role="note" aria-label="{{ $applyLockReason }}">
              <button type="button" class="dup-apply-btn locked" disabled>
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                {{ $applyLockShort }}
              </button>
            </span>
          @endif
        @endif
      </div>
    @endforeach
    @unless ($applyOpen)
      <div class="dup-apply-note">{{ $applyLockReason }}</div>
    @endunless
  </div>
@endif
@endsection
