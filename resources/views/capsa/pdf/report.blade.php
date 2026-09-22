<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{{ $name }}</title>
<style>
  /* DejaVu Sans because dompdf's own fonts have no peso sign. */
  @page { margin: 28px 32px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e2128; }
  /* Fixed layout: dompdf takes each column's width from the first row only. */
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .top td { font-size: 9px; color: #656b7a; padding-bottom: 8px; border-bottom: 2px solid #c9a82c; }
  .top .brand { font-weight: bold; color: #1e2128; font-size: 10px; }
  .top .brand span { background: #c9a82c; color: #18191f; padding: 1px 5px; margin-right: 4px; }
  h1 { font-size: 16px; margin: 12px 0 0; }
  .scope { color: #4a5060; margin: 2px 0 12px; }
  .tiles td { border: 1px solid #e0e2e8; background: #f7f8fa; padding: 6px 9px; vertical-align: top; }
  .tiles b { display: block; font-size: 13px; }
  .tiles span { color: #656b7a; font-size: 8.5px; }
  .data { margin-top: 12px; }
  .data th, .data td { border: 1px solid #c8cad4; padding: 4px 6px; word-wrap: break-word; text-align: left; vertical-align: top; }
  .data th { background: #f2f3f5; font-size: 8.5px; color: #4a5060; }
  .data .n { text-align: right; }
  .data .total td { font-weight: bold; background: #f7f8fa; }
  .legend { margin-top: 10px; font-size: 9px; color: #4a5060; }
  .legend span { margin-right: 12px; }
  .ok { color: #16a34a; } .warn { color: #d97706; } .bad { color: #dc2626; } .none { color: #9aa0ad; }
</style>
</head>
<body>
  <table class="top">
    <tr>
      <td class="brand"><span>C</span>CapsTrack · CaPSA</td>
      <td style="text-align:right">Generated {{ now()->format('M j, Y') }} by {{ $by }}</td>
    </tr>
  </table>
  <h1>{{ $name }}</h1>
  <div class="scope">{{ $scope }}</div>

  <table class="tiles">
    <tr>
      @foreach ($tiles as $tile)
        <td style="width:{{ 100 / count($tiles) }}%"><b>{{ $tile['value'] }}</b><span>{{ $tile['label'] }}</span></td>
      @endforeach
    </tr>
  </table>

  <table class="data">
    <thead>
      <tr>
        @foreach ($table['headings'] as $i => $heading)
          <th @class(['n' => $table['numeric'][$i]]) style="width:{{ 100 / count($table['headings']) }}%">@if ($table['tones'][$i])<span class="{{ $table['tones'][$i] }}">●</span> @endif{{ $heading }}</th>
        @endforeach
      </tr>
    </thead>
    {{-- The total is the last body row: dompdf misplaces a tfoot. --}}
    <tbody>
      @foreach ([...$table['rows'], ...($table['total'] ? [$table['total']] : [])] as $line)
        <tr @class(['total' => $loop->last && $table['total']])>
          @foreach ($line as $i => $c)
            <td @class(['n' => $table['numeric'][$i]])>@if ($c['tone'] && ! $table['tones'][$i])<span class="{{ $c['tone'] }}">●</span> @endif{{ $c['text'] }}</td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>

  @if ($table['legend'])
    <div class="legend"><b>Legend</b>&nbsp;&nbsp;
      @foreach ($table['legend'] as $text => [$tone])
        <span><span class="{{ $tone }}">●</span> {{ $text }}</span>
      @endforeach
    </div>
  @endif
</body>
</html>
