{{-- The Bluebook Clearance slip: in the dean's dialog, and as the PDF (dompdf: tables only, no grid or flex). --}}
@if (! empty($pdf))
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>{{ $row->bluebook->slip_no }}</title>
<style>body{font-family:'DejaVu Sans',sans-serif;font-size:12px;color:#1e2128;margin:24px}</style>
</head><body>
@endif
<style nonce="{{ Vite::cspNonce() }}">
.bb-slip{padding:16px 20px 12px;color:#1e2128}
.bb-slip table{width:100%;border-collapse:collapse}
.bb-slip h2{margin:0;font-size:17px;font-weight:700}
.bb-slip .bs-top td{vertical-align:top;padding:0 0 8px;border-bottom:2px solid #18191f;font-size:12px;color:#4a5060}
.bb-slip .bs-top .r{text-align:right}
.bb-slip .bs-top b{display:block;font-size:13px;color:#1e2128}
.bb-slip .bs-dl{margin:9px 0}
.bb-slip .bs-dl th{width:92px;padding:2px 8px 2px 0;text-align:left;vertical-align:top;font-weight:400;color:#4a5060;font-size:12.5px}
.bb-slip .bs-dl td{padding:2px 0;font-weight:600;font-size:12.5px}
.bb-slip .bs-req th{height:26px;padding:0 8px;background:#f7f8fa;font-size:11px;font-weight:700;letter-spacing:.4px;text-align:left;text-transform:uppercase;color:#4a5060}
.bb-slip .bs-req td{height:27px;padding:0 8px;border-bottom:1px solid #eceef2;font-size:12.5px}
</style>
<div class="bb-slip">
  <table class="bs-top"><tr>
    <td><h2>Bluebook Clearance</h2></td>
    <td class="r"><b>No. {{ $row->bluebook->slip_no }}</b>{{ $row->bluebook->cleared_on->format('M j, Y') }}</td>
  </tr></table>
  <table class="bs-dl">
    <tr><th>Team</th><td>{{ $row->team->team_name }}</td></tr>
    <tr><th>Title</th><td>{{ $row->team->project_title ?: '—' }}</td></tr>
    <tr><th>Program</th><td>{{ $row->program ? $row->program.' · ' : '' }}{{ $row->section }}</td></tr>
    <tr><th>Members</th><td>{{ $row->members->join(', ') ?: '—' }}</td></tr>
  </table>
  <table class="bs-req">
    <thead><tr><th>Requirement</th><th>Type</th><th>Accepted On</th></tr></thead>
    <tbody>
    @foreach ($row->bluebook->lines as $l)
      <tr><td>{{ $l->item->title }}</td><td>{{ \App\Support\Bluebook::kind($l->file) }}</td><td>{{ $l->closed_at?->format('M j, Y') }}</td></tr>
    @endforeach
    </tbody>
  </table>
</div>
@if (! empty($pdf))
</body></html>
@endif
