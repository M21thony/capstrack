{{--
  Every team rating of the CaPSA/TSA as a PDF, one rating to a page: what
  Export on Team Ratings shows first, before Download PDF or Download Excel.
  The same tables as the Excel file's sheets (TeamRatingController::sheets()).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Team Ratings</title>
<style>
  @page { margin: 36px 40px 46px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #111; }
  @include('capsa.reports._letterhead', ['part' => 'style'])
  .rating { page-break-after: always; }
  .rating:last-child { page-break-after: auto; }
  .title { text-align: center; }
  .title .name { font-size: 13px; font-weight: bold; letter-spacing: .06em; text-transform: uppercase; }
  .title .scope { font-size: 9.5px; margin-top: 2px; }
  .title .asof { font-size: 8.5px; color: #444; margin-top: 1px; }
  table.data { width: 100%; border-collapse: collapse; margin-top: 12px; }
  .data th, .data td { border: 1px solid #111; padding: 3px 5px; vertical-align: top; text-align: left; }
  .data thead th { background: #e8e8e8; font-weight: bold; }
  .data td.r, .data th.r { text-align: right; }
  .data tr.grp td { background: #f6f6f6; font-weight: bold; }
  .low { color: #b42318; font-weight: bold; }
  .empty { text-align: center; padding: 18px; color: #555; }
  .summary { margin-top: 6px; font-size: 8.5px; color: #444; }
  .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8px; color: #555; border-top: 1px solid #bbb; padding-top: 3px; }
</style>
</head>
<body>
  <div class="footer">Team Ratings · generated from CapsTrack on {{ now()->format('F j, Y') }} by {{ $by }}</div>

  @forelse ($sheets as $sheet)
    <div class="rating">
      @include('capsa.reports._letterhead')
      <div class="title">
        <div class="name">{{ $sheet['title'] }}</div>
        <div class="scope">Team Rating · {{ $sheet['scope'] }}</div>
        <div class="asof">As of {{ now()->format('F j, Y') }}</div>
      </div>
      <table class="data">
        <thead>
          <tr>
            @foreach ($sheet['headings'] as $i => $heading)
              <th class="{{ $i > 2 ? 'r' : '' }}">{{ $heading }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @forelse ($sheet['rows'] as $row)
            <tr>
              @foreach ($row as $i => $cell)
                @php $percent = $i === count($row) - 2 && str_ends_with($cell, '%') ? (float) $cell : null; @endphp
                <td class="{{ $i > 2 ? 'r' : '' }} {{ $percent !== null && $percent < 50 ? 'low' : '' }}">{{ $cell }}</td>
              @endforeach
            </tr>
          @empty
            <tr><td class="empty" colspan="{{ count($sheet['headings']) }}">No students in this rating's sections yet.</td></tr>
          @endforelse
        </tbody>
      </table>
      <div class="summary">{{ count($sheet['rows']) }} {{ count($sheet['rows']) === 1 ? 'student' : 'students' }} · each score is the average of what teammates gave them · Rating % is out of every activity they were rated on</div>
    </div>
  @empty
    @include('capsa.reports._letterhead')
    <div class="title">
      <div class="name">Team Ratings</div>
      <div class="scope">No ratings yet</div>
    </div>
    <div class="empty">Create a team rating on Team Management › Team Ratings, and it shows here.</div>
  @endforelse
</body>
</html>
