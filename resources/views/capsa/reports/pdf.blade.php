<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $name }}</title>
<style>
  @page { margin: 36px 40px 46px; }
  /* DejaVu carries the peso sign; the PDF's built-in fonts do not. */
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #111; }
  .letterhead { text-align: center; line-height: 1.35; }
  .letterhead .small { font-size: 9px; }
  .letterhead .school { font-size: 12.5px; font-weight: bold; letter-spacing: .02em; }
  .letterhead .college { font-size: 10.5px; font-weight: bold; margin-top: 2px; }
  .rule { border-top: 2px solid #111; margin: 8px 0 10px; }
  .title { text-align: center; }
  .title .name { font-size: 13px; font-weight: bold; letter-spacing: .06em; text-transform: uppercase; }
  .title .scope { font-size: 9.5px; margin-top: 2px; }
  .title .asof { font-size: 8.5px; color: #444; margin-top: 1px; }
  .note { font-size: 8.5px; margin-top: 3px; }
  .note.lead { font-weight: bold; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  th, td { border: 1px solid #111; padding: 3px 5px; vertical-align: top; text-align: left; }
  thead th { background: #e8e8e8; font-weight: bold; }
  thead tr.bands th { background: #d4d4d4; text-align: center; font-size: 8.5px; text-transform: uppercase; letter-spacing: .04em; }
  td.r, th.r { text-align: right; }
  td.b { font-weight: bold; }
  tr.total td { background: #f2f2f2; font-weight: bold; }
  .empty { text-align: center; padding: 18px; color: #555; }
  .summary { margin-top: 6px; font-size: 8.5px; color: #444; }
  .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8px; color: #555; border-top: 1px solid #bbb; padding-top: 3px; }
</style>
</head>
<body>
  <div class="footer">Generated from CapsTrack on {{ now()->format('F j, Y') }} by {{ $by }}</div>

  <div class="letterhead">
    <div class="small">Republic of the Philippines</div>
    <div class="school">CAMARINES SUR POLYTECHNIC COLLEGES</div>
    <div class="small">Nabua, Camarines Sur</div>
    <div class="college">COLLEGE OF COMPUTER STUDIES</div>
  </div>
  <div class="rule"></div>
  <div class="title">
    <div class="name">{{ $name }} Report</div>
    <div class="scope">Capstone Project · {{ $scope }}</div>
    <div class="asof">As of {{ now()->format('F j, Y') }}</div>
  </div>
  @foreach ($sheet['notes'] ?? [] as $note)
    <div class="note {{ $loop->first ? 'lead' : '' }}">{{ $note }}</div>
  @endforeach

  <table>
    <thead>
      @if ($sheet['bands'])
        <tr class="bands">
          @foreach ($sheet['bands'] as $band)
            <th colspan="{{ $band['span'] }}">{{ $band['label'] }}</th>
          @endforeach
        </tr>
      @endif
      <tr>
        @foreach ($sheet['headings'] as $i => $heading)
          <th class="{{ $sheet['right'][$i] ? 'r' : '' }}">{{ $heading }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse ($sheet['rows'] as $row)
        <tr>
          @foreach ($row as $i => $cell)
            <td class="{{ $sheet['right'][$i] ? 'r' : '' }} {{ $cell['bold'] ? 'b' : '' }}">{!! nl2br(e($cell['text'])) !!}</td>
          @endforeach
        </tr>
      @empty
        <tr><td class="empty" colspan="{{ max(1, count($sheet['headings'])) }}">{{ $sheet['empty'] }}</td></tr>
      @endforelse
      @if ($sheet['total'])
        <tr class="total">
          @foreach ($sheet['total'] as $i => $cell)
            <td class="{{ $sheet['right'][$i] ? 'r' : '' }}">{!! nl2br(e($cell['text'])) !!}</td>
          @endforeach
        </tr>
      @endif
    </tbody>
  </table>
  <div class="summary">{{ $sheet['summary'] }}</div>
</body>
</html>
