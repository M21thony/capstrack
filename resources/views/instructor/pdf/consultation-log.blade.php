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

    .label-cell { font-weight: bold; text-align: left; vertical-align: middle; font-size: 11pt; }
    .section-title { font-weight: bold; text-align: center; font-size: 11pt; vertical-align: top; }
    .col-header-sm { font-weight: bold; text-align: center; vertical-align: middle; font-size: 9pt; padding: 2px 4px; }
    .center { text-align: center; }
    .vcenter { height: 85px; vertical-align: middle; text-align: center; }
    .no-left { border-left: none; }
    .data-cell { height: 57px; text-align: center; vertical-align: middle; }
    .ref-table { width: 97.3%; table-layout: fixed; border-collapse: collapse; margin: 10px auto 0; }
    .ref-table td, .ref-table th {
        border: 1px solid #000;
        padding: 4px 6px;
        font-size: 10pt;
        word-wrap: break-word;
    }
</style>
</head>
<body>

{{-- The title sits in its own one-row table on purpose. DomPDF fixes the column
     widths from the FIRST row it meets, so a full-width title row there would
     have forced eight equal columns and thrown every box below out of size. --}}
<table>
    <tr>
        <td class="center" style="height:22px; font-weight:bold; letter-spacing:1px; vertical-align:middle; border-bottom:none;">
            CONSULTATION LOGS FORM
        </td>
    </tr>
</table>

<table>
    {{-- Column widths are the printed form's own, converted from the .docx grid
         (867+839, 1421, 1427, 1427, 1097, 1214, 1194, 1622 of 11108 twips).
         Without them every column came out the same width and no box matched.
         They have to be percentages on single-column cells in the very first
         row: DomPDF reads widths from row 0 only, and silently discards any
         width given in pt (Cellmap.php, calculate_column_widths). This thin
         empty band is in the printed form too, so it does the job. --}}
    <tr style="height:22px;">
        <td style="width:15.36%">&nbsp;</td>
        <td style="width:12.79%">&nbsp;</td>
        <td style="width:12.85%">&nbsp;</td>
        <td style="width:12.85%">&nbsp;</td>
        <td style="width:9.88%">&nbsp;</td>
        <td style="width:10.93%">&nbsp;</td>
        <td style="width:10.75%">&nbsp;</td>
        <td style="width:14.60%">&nbsp;</td>
    </tr>

    <tr>
        <td class="label-cell" style="height:46px;">Capstone Project Title:</td>
        <td colspan="7" class="no-left">{{ $projectTitle ?? '—' }}</td>
    </tr>

    <tr>
        <td class="label-cell" style="height:35px;">Proponents:</td>
        <td colspan="7" class="no-left">
            @forelse ($proponents as $name)
                {{ $name }}@if (! $loop->last),@endif <br>
            @empty
                —
            @endforelse
        </td>
    </tr>

    <tr>
        <td class="label-cell" style="height:29px;">Alias:</td>
        <td colspan="7" class="no-left">{{ $alias ?? '—' }}</td>
    </tr>

    <tr>
        <td class="label-cell" style="height:46px;">Total # of Modules:</td>
        <td class="no-left">&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td colspan="2" class="center" style="font-weight:bold; vertical-align:middle;">as approved by the CAPSA /TSA:</td>
        <td colspan="2">&nbsp;</td>
    </tr>

    <tr style="height:70px;">
        <td class="section-title" style="white-space:nowrap; vertical-align:middle;">PROTOTYPE</td>
        <td class="col-header-sm">Date of<br>Consultation</td>
        <td class="col-header-sm"># of Modules<br>Fully Implemented</td>
        <td class="col-header-sm"># of Modules<br>Partially Implemented</td>
        <td class="col-header-sm">Running<br>Score</td>
        <td class="col-header-sm">Percentage</td>
        <td class="col-header-sm">Project Manager's<br>Signature</td>
        <td class="col-header-sm">CAPA/CAPSA/<br>Consultant/<br>Grammarian Signature</td>
    </tr>

    <tr>
        <td class="section-title" rowspan="2" style="border-bottom:none;">{{ $chapter }}</td>
        <td class="data-cell">{{ $consultationDate ? \Illuminate\Support\Carbon::parse($consultationDate)->format('m/d/Y') : '—' }}</td>
        <td class="data-cell">&nbsp;</td>
        <td class="data-cell">&nbsp;</td>
        <td class="data-cell">&nbsp;</td>
        <td class="data-cell">&nbsp;</td>
        <td class="data-cell">&nbsp;</td>
        <td class="data-cell">&nbsp;</td>
    </tr>

    <tr>
        <td colspan="7" class="no-left" style="height:23px; font-weight:bold;">Remarks:</td>
    </tr>

    <tr>
        <td style="border-top:none; border-bottom:none;">&nbsp;</td>
        <td colspan="7" rowspan="2" class="no-left remarks-box" style="vertical-align:top; padding:0;">
            {{-- 370px is the writing space the printed form leaves here (5552
                 twips). It used to be height:auto, so the box shrank to the
                 text and there was nothing left to write on. --}}
            <div style="height:370px; padding:4px 6px; box-sizing:border-box;">
                @if ($approvedAt)
                    <p style="margin:0 0 6px 0; font-weight:bold;">
                        APPROVED &mdash; {{ \Illuminate\Support\Carbon::parse($approvedAt)->format('F j, Y') }}
                    </p>
                @endif

                @if(!empty($generalRemarks) && count($generalRemarks))
                    <p style="margin:0 0 4px 0; font-weight:bold;">General Comment:</p>
                    @foreach ($generalRemarks as $comment)
                        <p style="margin:0 0 10px 0">{{ $loop->iteration }}. {!! nl2br(e($comment)) !!}</p>
                    @endforeach
                @endif

                @foreach ($remarksBySection as $section => $items)
                    <p style="margin:8px 0 2px 0; font-weight:bold;">{{ $section }}</p>
                    @foreach ($items as $item)
                        <p style="margin:0 0 4px 0">&bull; {!! nl2br(e($item->comment)) !!}</p>
                    @endforeach
                @endforeach

                @forelse ($remarks as $remark)
                    <p style="margin:0 0 4px 0">&bull; {!! nl2br(e($remark->comment)) !!}</p>
                @empty
                    @if((empty($generalRemarks) || !count($generalRemarks)) && (empty($remarksBySection) || !count($remarksBySection)))
                        <p>No remarks recorded for this consultation.</p>
                    @endif
                @endforelse
            </div>
        </td>
    </tr>

    <tr>
        <td class="label-cell" style="vertical-align:middle; padding:0;">
            <div style="height:131px; padding:4px 6px; box-sizing:border-box;">
                Deadline: {{ $deadline ? \Illuminate\Support\Carbon::parse($deadline)->format('F j, Y') : '' }}
            </div>
        </td>
    </tr>
</table>

{{-- Comment References Table - only shown if there are comments with selected text --}}
@if(!empty($comments))
<table class="ref-table" style="margin-top:10px;">
    <tr>
        <th colspan="3" style="text-align:center;font-size:11pt;font-weight:bold;padding:4px 6px;">
            COMMENT REFERENCES
        </th>
    </tr>
    <tr>
        <th style="text-align:center;font-size:9pt;width:8%;">No.</th>
        <th style="text-align:center;font-size:9pt;width:12%;">Page</th>
        <th style="text-align:center;font-size:9pt;">Referenced Text (first 80 characters)</th>
    </tr>
    @foreach($comments as $index => $comment)
    <tr>
        <td style="text-align:center;font-weight:bold;">#{{ $index + 1 }}</td>
        <td style="text-align:center;">Page {{ $comment['page_number'] ?? '—' }}</td>
        <td style="font-style:italic;">
            &ldquo;{{ \Illuminate\Support\Str::limit($comment['selected_text'] ?? $comment['comment'] ?? '—', 80, '...') }}&rdquo;
        </td>
    </tr>
    @endforeach
</table>
@endif

@include('partials.escape-closes-modals')
</body>
</html>