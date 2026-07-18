<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 15px; }
    body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; color: #000; margin: 0; }
    h1 { text-align: center; font-size: 11pt; margin-bottom: 16px; letter-spacing: 1px; }

    table { width: 97.3%; table-layout: fixed; border-collapse: collapse; margin: 0 auto; }
    td, th {
        border: 1px solid #000;
        padding: 4px 6px;
        vertical-align: top;
        color: #000;
        font-size: 11pt;
        word-wrap: break-word;
    }

    .label-cell { font-weight: bold; text-align: left; vertical-align: middle; font-size: 11pt; width: 15%; }
    .section-title { font-weight: bold; text-align: center; font-size: 11pt; vertical-align: top; }
    .col-header-sm { font-weight: bold; text-align: center; vertical-align: middle; font-size: 9pt; padding: 2px 4px; }
    .center { text-align: center; }
    .vcenter { height: 85px; vertical-align: middle; text-align: center; }
    .no-left { border-left: none; }
    .data-cell { border: 1px solid #000; height: 45px; text-align: center; vertical-align: middle; padding: 0; }
    .data-cell-inner {
        border: 1px solid #000;
        margin: 2px;
        height: 41px;
        box-sizing: border-box;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>
</head>
<body>

<h1>CONSULTATION LOGS FORM</h1>

<table>
    <tr>
        <td class="label-cell">Capstone Project Title:</td>
        <td colspan="7" class="no-left">{{ $projectTitle ?? '—' }}</td>
    </tr>

    <tr>
        <td class="label-cell">Proponents:</td>
        <td colspan="7" class="no-left">
            @forelse ($proponents as $name)
                {{ $name }}@if (! $loop->last),@endif <br>
            @empty
                —
            @endforelse
        </td>
    </tr>

    <tr>
        <td class="label-cell">Alias:</td>
        <td colspan="7" class="no-left">{{ $alias ?? '—' }}</td>
    </tr>

    <tr>
        <td class="label-cell">Total # of Modules:</td>
        <td class="no-left">&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td colspan="2" class="center" style="font-weight:bold;">as approved by the CAPSA/TSA:</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
    </tr>

    <tr>
        <td class="section-title" style="white-space:nowrap;">PROTOTYPE</td>
        <td class="col-header-sm">Date of<br>Consultation</td>
        <td class="col-header-sm"># of Modules<br>Fully Implemented</td>
        <td class="col-header-sm"># of Modules<br>Partially Implemented</td>
        <td class="col-header-sm">Running<br>Score</td>
        <td class="col-header-sm">Percentage</td>
        <td class="col-header-sm">Project Manager's<br>Signature</td>
        <td class="col-header-sm">CAPA/CAPSA/Consultant/<br>Grammarian Signature</td>
    </tr>

<tr>
    <td class="section-title" rowspan="2" style="border-bottom:none;">{{ $chapter }}</td>
    <td class="data-cell">
        <div class="data-cell-inner">{{ $consultationDate ? \Illuminate\Support\Carbon::parse($consultationDate)->format('m/d/Y') : '—' }}</div>
    </td>
    <td class="data-cell"><div class="data-cell-inner">&nbsp;</div></td>
    <td class="data-cell"><div class="data-cell-inner">&nbsp;</div></td>
    <td class="data-cell"><div class="data-cell-inner">&nbsp;</div></td>
    <td class="data-cell"><div class="data-cell-inner">&nbsp;</div></td>
    <td class="data-cell"><div class="data-cell-inner">&nbsp;</div></td>
    <td class="data-cell"><div class="data-cell-inner">&nbsp;</div></td>
</tr>

    <tr>
        <!-- <td style="border-top:none; border-bottom:none;">&nbsp;</td> -->
        <td colspan="7" class="no-left" style="font-weight:bold;">Remarks:</td>
    </tr>

<tr>
    <td style="border-top:none; border-bottom:none;">&nbsp;</td>
    <td colspan="7" rowspan="2" class="no-left remarks-box" style="vertical-align:top; padding:0;">
        <div style="height: auto; padding:4px 6px; box-sizing:border-box;">
            @forelse ($remarks as $remark)
                <p style="margin:0 0 6px 0">&bull; {{ $remark }}</p>
            @empty
                <p>No remarks recorded for this consultation.</p>
            @endforelse
        </div>
    </td>
</tr>

<tr>
    <td class="label-cell" style="vertical-align:middle; padding:0;">
        <div style="height:40px; padding:4px 6px; box-sizing:border-box; align-items:center;">
            Deadline:
        </div>
    </td>
</tr>
</table>

</body>
</html>