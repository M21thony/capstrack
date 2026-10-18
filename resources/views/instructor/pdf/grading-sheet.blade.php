@php
    $pt = fn ($n) => \App\Support\GradingSheet::points((float) $n);
    $levels = $sheet['levelCount'];
    // The form's own column widths ("PreProposal-Rubric.docx": 1920, 3228,
    // 3129, 3053, 2862, 824 of 15016), with the descriptor columns sharing
    // what the category and score columns leave, however many levels a rubric has.
    $catWidth = 12.8;
    $scoreWidth = 5.5;
    $levelWidth = (100 - $catWidth - $scoreWidth) / max(1, $levels);
    $seal = \App\Support\GradingSheet::logo('cspc');
    $shield = \App\Support\GradingSheet::logo('ccs');
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{{ $sheet['team'] }} - {{ $sheet['stage'] }} - Rating Sheet</title>
<style>
    /* Letter, landscape, the form's margins: 1.03in top for the letterhead, 0.5in sides. */
    @page { size: letter landscape; margin: 76pt 36pt 44pt 36pt; }
    body { font-family: 'Times New Roman', Times, serif; font-size: 10pt; color: #000; margin: 0; }

    .letterhead { position: fixed; top: -64pt; left: 0; right: 0; height: 54pt; }
    .letterhead img { position: absolute; top: 4pt; }
    .letterhead .seal { left: -10pt; width: 44pt; height: 44pt; }
    .letterhead .shield { left: 40pt; width: 42pt; height: 41pt; top: 5pt; }
    .letterhead .lines { position: absolute; left: 92pt; top: 2pt; line-height: 1.1; color: #333; }
    .letterhead .small { font-size: 6.5pt; }
    .letterhead .school { font-size: 7.5pt; font-weight: bold; }
    .letterhead .iso { font-size: 6.5pt; font-style: italic; }
    .letterhead .college { font-size: 12pt; font-weight: bold; color: #6b6b6b; margin-top: 1pt; }
    /* The form's rule: gold for most of the width, then grey. */
    .letterhead .rule-gold { position: absolute; left: -24pt; width: 61.5%; top: 55pt; border-top: 2.5pt solid #f6d96b; }
    .letterhead .rule-grey { position: absolute; right: -24pt; width: 46%; top: 55pt; border-top: 2.5pt solid #8a8a8a; }

    .foot { position: fixed; bottom: -32pt; left: 0; right: 0; font-size: 7pt; }
    .foot .note { color: #555; }

    table { width: 100%; border-collapse: collapse; }
    .head td { padding: 1pt 2pt; vertical-align: top; font-size: 10pt; }
    .head .k { white-space: nowrap; }
    .head .v { font-weight: bold; }

    .rubric { margin-top: 8pt; table-layout: fixed; }
    .rubric .widths td { border: 0; padding: 0; height: 0; }
    .rubric td { border: 0.75pt solid #000; padding: 2pt 4pt; vertical-align: top; font-size: 10pt; }
    .rubric tr { page-break-inside: avoid; }
    .rubric .title { background: #f2f2f2; font-weight: bold; text-align: center; }
    .rubric .subtitle { font-weight: bold; text-align: center; }
    .rubric .cat { font-weight: bold; }
    .rubric .lvl { font-weight: bold; text-align: center; }
    .rubric .desc { text-align: justify; line-height: 1.15; }
    .rubric .desc b { white-space: nowrap; }
    /* The category and score columns read as one cell down a category - and,
       unlike a merged cell, can carry on onto the next page. */
    .rubric .open-below { border-bottom: 0; }
    .rubric .open-above { border-top: 0; }
    .right { text-align: right; }

    .verdict { margin-top: 8pt; }
    .verdict b { display: block; margin-bottom: 3pt; font-style: italic; }
    .verdict .opt { margin: 1pt 0 1pt 14pt; }
    .sq { display: inline-block; width: 8pt; height: 8pt; border: 0.75pt solid #000; margin-right: 5pt; vertical-align: -1pt; }

    .remarks { margin-top: 8pt; }
    .remarks td { border: 0.75pt solid #000; padding: 3pt 4pt; }
    .remarks .blank { height: 54pt; }

    .sign { margin-top: 22pt; }
    .sign td { text-align: center; vertical-align: bottom; font-size: 10pt; }
    .sign .name { font-weight: bold; text-transform: uppercase; border-bottom: 0.75pt solid #000; padding-bottom: 1pt; height: 14pt; }
    .sign .seat { padding-top: 2pt; }
    .avoid { page-break-inside: avoid; }
</style>
</head>
<body>

<div class="letterhead">
    @if ($seal)<img class="seal" src="{{ $seal }}" alt="">@endif
    @if ($shield)<img class="shield" src="{{ $shield }}" alt="">@endif
    <div class="lines">
        <div class="small">Republic of the Philippines</div>
        <div class="school">Camarines Sur Polytechnic Colleges</div>
        <div class="small">Nabua, Camarines Sur</div>
        <div class="iso">ISO 9001:2015 Certified</div>
        <div class="college">COLLEGE <i>of</i> COMPUTER STUDIES</div>
    </div>
    <div class="rule-gold"></div>
    <div class="rule-grey"></div>
</div>

<div class="foot">
    <span class="note">{{ $sheet['note'] }}</span>
</div>

<table class="head">
    <tr>
        <td class="k" style="width:9.6%">Title</td><td style="width:2.4%">:</td>
        <td class="v" style="width:64.8%">{{ $sheet['title'] }}</td>
        <td style="width:1.9%"></td>
        <td class="k" style="width:4.5%">Date</td><td style="width:1.7%">:</td>
        <td class="v" style="width:15.1%">{{ $sheet['date'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="k">Team Alias</td><td>:</td>
        <td class="v">{{ $sheet['team'] }}</td>
        <td colspan="4"></td>
    </tr>
</table>

@php
    $widthRow = '<tr class="widths"><td style="width:'.$catWidth.'%"></td>'.str_repeat('<td style="width:'.$levelWidth.'%"></td>', $levels).'<td style="width:'.$scoreWidth.'%"></td></tr>';
@endphp
{{-- One table that flows from page to page, as the form does. No merged
     cells: DomPDF misdraws one that a page break runs through, so the
     category and score columns are drawn open between a category's rows. --}}
<table class="rubric">
    {!! $widthRow !!}
    <tr><td class="title" colspan="{{ $levels + 2 }}">{{ mb_strtoupper($sheet['rubric']) }}</td></tr>
    @if ($sheet['description'])
        <tr><td class="subtitle" colspan="{{ $levels + 2 }}">{{ mb_strtoupper($sheet['description']) }}</td></tr>
    @endif

    @foreach ($sheet['sections'] as $section)
        @php $rows = count($section['items']); @endphp
        <tr>
            <td class="cat{{ $rows ? ' open-below' : '' }}">{{ $section['name'] }}</td>
            @for ($k = 0; $k < $levels; $k++)
                <td class="lvl">{{ isset($section['levelTotals'][$k]) ? $pt($section['levelTotals'][$k]) : '' }}</td>
            @endfor
            <td class="lvl">Score</td>
        </tr>
        @foreach ($section['items'] as $i => $item)
            @php $edge = 'open-above'.($i < $rows - 1 ? ' open-below' : ''); @endphp
            <tr>
                <td class="{{ $edge }}"></td>
                @for ($k = 0; $k < $levels; $k++)
                    @php $level = $item['levels'][$k] ?? null; @endphp
                    <td class="desc">
                        @if ($level)
                            {{ $level['desc'] !== '' ? $level['desc'] : ($level['label'] !== '' ? $level['label'].' - '.$item['name'] : $item['name']) }} <b>({{ $pt($level['score']) }}pts)</b>
                        @endif
                    </td>
                @endfor
                {{-- One score for the category, as on the form. --}}
                <td class="{{ $edge }}"></td>
            </tr>
        @endforeach
    @endforeach

    <tr>
        <td class="right" style="font-weight:bold">Total</td>
        <td class="right">{{ $pt($sheet['total']) }}</td>
        @for ($k = 1; $k < $levels; $k++)<td></td>@endfor
        <td></td>
    </tr>
</table>

<div class="verdict avoid">
    <b>Verdict:</b>
    @foreach ($sheet['outcomes'] as $outcome)
        <div class="opt"><span class="sq"></span>{{ $outcome }}</div>
    @endforeach
</div>

<table class="remarks avoid">
    <tr><td><b>Remarks:</b></td></tr>
    <tr><td class="blank"></td></tr>
</table>

<table class="sign avoid">
    <tr>
        <td style="width:42%"><div class="name">{{ $sheet['signatures']['Panel Member 1'] ?? '' }}</div><div class="seat">Panel Member 1</div></td>
        <td style="width:16%"></td>
        <td style="width:42%"><div class="name">{{ $sheet['signatures']['Panel Member 2'] ?? '' }}</div><div class="seat">Panel Member 2</div></td>
    </tr>
</table>
<table class="sign avoid" style="margin-top:20pt">
    <tr>
        <td style="width:22%"></td>
        <td style="width:56%"><div class="name">{{ $sheet['signatures']['Panel Chair'] ?? '' }}</div><div class="seat">Panel Chairman</div></td>
        <td style="width:22%"></td>
    </tr>
</table>

</body>
</html>
