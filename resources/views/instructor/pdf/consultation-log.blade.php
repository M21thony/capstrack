<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 15px; }
    body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; color: #000; margin: 0; }
    .form-title { text-align: center; font-size: 11pt; font-weight: bold; letter-spacing: 1px; height: 22px; }

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
    .grid { border: none; }
    .vcenter { height: 85px; vertical-align: middle; text-align: center; }
    .no-left { border-left: none; }
    .data-cell { border: 3px double #000; height: 57px; text-align: center; vertical-align: middle; }
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

{{-- The title is outside the table because in the printed form it has no
     box around it - all four of its borders are switched off. Keeping it out
     also makes the eight-cell band below the first row, which is the only row
     DomPDF reads column widths from. --}}
<div class="form-title">CONSULTATION LOGS FORM</div>

<table>
    {{-- Column widths are the printed form's own, converted from the .docx grid
         (867+839, 1421, 1427, 1427, 1097, 1214, 1194, 1622 of 11108 twips).
         Without them every column came out the same width and no box matched.
         They have to be percentages on single-column cells in the very first
         row: DomPDF reads widths from row 0 only, and silently discards any
         width given in pt (Cellmap.php, calculate_column_widths). This thin
         empty band is in the printed form too, so it does the job. --}}
    <tr style="height:22px;">
        <td class="grid" style="width:15.36%">&nbsp;</td>
        <td class="grid" style="width:12.79%">&nbsp;</td>
        <td class="grid" style="width:12.85%">&nbsp;</td>
        <td class="grid" style="width:12.85%">&nbsp;</td>
        <td class="grid" style="width:9.88%">&nbsp;</td>
        <td class="grid" style="width:10.93%">&nbsp;</td>
        <td class="grid" style="width:10.75%">&nbsp;</td>
        <td class="grid" style="width:14.60%">&nbsp;</td>
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

    <tr>
        <td class="section-title" style="height:70px; white-space:nowrap; vertical-align:middle;">PROTOTYPE</td>
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

    @php
        // One row per paragraph, not a single tall cell holding them all. A
        // cell that will not fit in what is left of the page gets moved to
        // the next page whole, which left the first page half empty; rows
        // carry on down the page and break between paragraphs instead.
        $lines = [];

        // A remark longer than the page cannot be moved out of the way whole,
        // so a very long one is handed over in pieces of about nine lines. The
        // break lands on a space when there is one to land on.
        $push = function ($bold, $text) use (&$lines) {
            while (mb_strlen($text) > 900) {
                $cut = mb_strrpos(mb_substr($text, 0, 900), ' ') ?: 900;
                $lines[] = [$bold, mb_substr($text, 0, $cut)];
                $text = ltrim(mb_substr($text, $cut));
                $bold = false;
            }

            $lines[] = [$bold, $text];
        };

        if ($approvedAt) {
            $push(true, 'APPROVED — '.\Illuminate\Support\Carbon::parse($approvedAt)->format('F j, Y'));
        }

        if (! empty($generalRemarks) && count($generalRemarks)) {
            $push(true, 'General Comment:');

            foreach ($generalRemarks as $i => $comment) {
                $push(false, ($i + 1).'. '.$comment);
            }
        }

        foreach ($remarksBySection as $section => $items) {
            $push(true, $section);

            foreach ($items as $item) {
                $push(false, '• '.$item->comment);
            }
        }

        foreach ($remarks as $remark) {
            $push(false, '• '.$remark->comment);
        }

        // An approved paper with nothing written on it says APPROVED and leaves
        // it there - "no remarks recorded" under it reads like something is
        // missing when nothing is.
        if (! $approvedAt && ! count($generalRemarks ?? []) && ! count($remarksBySection ?? []) && ! count($remarks)) {
            $push(false, 'No remarks recorded for this consultation.');
        }

        $deadlineText = $deadline
            ? 'Deadline: '.\Illuminate\Support\Carbon::parse($deadline)->format('F j, Y')
            : 'Deadline:';

        // On the filled-in form the remarks run straight down the right-hand
        // side and the deadline sits beside them in the left column, about
        // three-quarters of the way down - the two share one bottom edge and
        // there is no blank strip between them. So the deadline goes into the
        // left half of whichever remark row crosses that mark, rather than in
        // a row of its own underneath everything.
        //
        // DomPDF will not say how tall a row came out, so the mark is found by
        // guessing each one at roughly 95 characters to a line.
        // ponytail: an estimate, not a measurement. Only picks the split row.
        $heights = [];
        $used = 0;

        foreach ($lines as [$bold, $text]) {
            $heights[] = $h = (int) ceil(max(1, mb_strlen($text)) / 105) * 14;
            $used += $h;
        }

        // How much room is left under the header for the box. Padding the box
        // out to the printed form's 370px without asking cost a blank strip and
        // a second page carrying nothing but the deadline, on any form whose
        // title or list of proponents runs long enough to push the box down.
        // Letter minus its margins is 1026px; the rest is the rows above, at
        // their set heights or their wrapped height, whichever is taller.
        // 60 characters to a line across the wide columns, 13 down the narrow
        // chapter one: what capitals fit, which is the worst case. Prose fits
        // half as much again, so the box comes out a little short of the
        // printed one rather than a little over onto a page of its own.
        $wide = fn ($text) => (int) ceil(max(1, mb_strlen((string) $text)) / 60) * 18;
        $narrow = fn ($text) => (int) ceil(max(1, mb_strlen((string) $text)) / 13) * 18 + 8;

        // Letter minus its margins is 1026px; the 120 is slack, because every
        // height here is guessed from a character count and a guess that runs
        // over costs a whole extra page for one line of deadline.
        $room = 1026 - 120 - (22 + 22 + 55 + 90 + 57 + 23)
            - max(46, $wide($projectTitle) + 8)
            - max(35, collect($proponents)->sum($wide) + 8)
            - max(29, $wide($alias) + 8)
            // The chapter runs down the side of the data row and the Remarks
            // label; anything past the two of them pushes the box down.
            - max(0, $narrow($chapter) - 80);

        // The deadline is the bottom 131px of the left column, wherever the
        // bottom turns out to be. On a short form that is the printed box's
        // own 370px mark - or as near it as fits - padded out below; on a full
        // one it is 131px up from the last line of remarks, so the two columns
        // end together.
        $target = min(370, $room - 131);
        $mark = max($target, $used - 131);
        $at = 0;
        $cross = null;
        $first = null;

        foreach ($heights as $i => $h) {
            if ($at >= $mark) {
                $first ??= $i;

                // The label is two lines deep in a narrow column, so a
                // one-line remark beside it would leave a gap the height of
                // the label. Prefer a remark that is at least as tall.
                if ($h >= 28) {
                    $cross = $i;
                    break;
                }
            }

            $at += $h;
        }

        $cross ??= $first;
        $placed = $cross !== null;
        $rows = [];

        foreach ($lines as $i => [$bold, $text]) {
            $rows[] = [$bold, $text, $i === $cross];
        }
    @endphp

    @foreach ($rows as $i => [$bold, $text, $atDeadline])
        <tr>
            {{-- The empty half of the column has to be as short as its row,
                 or its own padding props every one-line remark open. --}}
            <td class="{{ $atDeadline ? 'label-cell' : '' }}" style="{{ $atDeadline ? '' : 'border-top:none; padding:0 6px; font-size:10pt; line-height:1.15;' }} {{ $loop->last && $placed ? '' : 'border-bottom:none;' }} vertical-align:top;">{{ $atDeadline ? $deadlineText : '' }}&nbsp;</td>
            <td colspan="7" class="no-left" style="border-top:none; padding:0 6px; font-size:10pt; line-height:1.15; {{ $loop->last && $placed ? '' : 'border-bottom:none;' }} {{ $bold ? 'font-weight:bold;' : '' }}">{!! nl2br(e($text)) !!}</td>
        </tr>
    @endforeach

    {{-- Remarks too short to reach that far down: pad the box back out to the
         printed form's height and give the deadline its own row at the foot,
         which is what the blank form looks like. --}}
    @unless ($placed)
        @if ($target - $used >= 20)
            <tr>
                <td style="height:{{ $target - $used }}px; border-top:none; border-bottom:none;">&nbsp;</td>
                <td colspan="7" class="no-left" style="border-top:none; border-bottom:none;">&nbsp;</td>
            </tr>
        @endif

        <tr>
            <td class="label-cell" style="height:131px; vertical-align:top;">{{ $deadlineText }}</td>
            <td colspan="7" class="no-left" style="border-top:none;">&nbsp;</td>
        </tr>
    @endunless

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