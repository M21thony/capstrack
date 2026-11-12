<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $name }}</title>
<style>
  @page { margin: 36px 40px 46px; }
  /* DejaVu carries the peso sign; the PDF's built-in fonts do not. */
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #111; }
  /* The grading sheet's letterhead: both logos, the lines beside them, a gold then grey rule. */
  table.letterhead { width: auto; margin-top: 0; line-height: 1.2; color: #333; }
  table.letterhead td, table.rule td { border: 0; padding: 0; vertical-align: middle; }
  table.letterhead td.logo { padding-right: 6px; }
  table.letterhead td.lines { padding-left: 4px; }
  .letterhead .small { font-size: 8px; }
  .letterhead .school { font-size: 9.5px; font-weight: bold; }
  .letterhead .iso { font-size: 8px; font-style: italic; }
  .letterhead .college { font-size: 14px; font-weight: bold; color: #6b6b6b; margin-top: 1px; }
  table.rule { margin: 6px 0 10px; }
  table.rule td { height: 3px; font-size: 1px; line-height: 1px; }
  table.rule td.gold { width: 61%; background: #f6d96b; }
  table.rule td.grey { background: #8a8a8a; }
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

  @php
    $seal = \App\Support\GradingSheet::logo('cspc');
    $shield = \App\Support\GradingSheet::logo('ccs');
  @endphp
  <table class="letterhead">
    <tr>
      @if ($seal)<td class="logo"><img src="{{ $seal }}" width="58" height="58" alt=""></td>@endif
      @if ($shield)<td class="logo"><img src="{{ $shield }}" width="56" height="55" alt=""></td>@endif
      <td class="lines">
        <div class="small">Republic of the Philippines</div>
        <div class="school">Camarines Sur Polytechnic Colleges</div>
        <div class="small">Nabua, Camarines Sur</div>
        <div class="iso">ISO 9001:2015 Certified</div>
        <div class="college">COLLEGE of COMPUTER STUDIES</div>
      </td>
    </tr>
  </table>
  <table class="rule"><tr><td class="gold">&nbsp;</td><td class="grey">&nbsp;</td></tr></table>
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
