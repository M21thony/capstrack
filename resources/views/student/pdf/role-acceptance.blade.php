<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 93px 80px; }
    body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; color: #000; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    td { border: 1px solid #000; padding: 6px 7px 12px; vertical-align: top; }
    p { margin: 0 0 17px; line-height: 1.3; }
    .justify { text-align: justify; }
    .head { text-align: center; font-weight: bold; padding: 26px 7px 18px; line-height: 1.25; }
    .members p { margin: 0 0 6px; font-weight: bold; }
</style>
</head>
<body>

{{-- A copy of the CCS's D1 Role Acceptance Form, filled in. The Dean's name is
     the one printed on that form; change it here when the office changes. --}}
<table>
    <tr><td class="head">ROLE ACCEPTANCE FORM<br>College of Computer Studies</td></tr>
    <tr>
        <td>
            <p>Date: {{ $date->format('d/m/Y') }}</p>
            <p>To: <b>{{ $expertName }}</b></p>
            <p>We, the third-year students of Camarines Sur Polytechnic Colleges pursuing a degree in {{ $course }}, are currently enrolled in Capstone Project 1.</p>
            <p class="justify">We are writing to humbly request your service and expertise to serve as our {{ $role }} for our capstone project. We believe that your knowledge and experience will be essential to greatly enrich our work. <b>Attached are our capstone tentative title proposals for your kind reference.</b></p>
            <p>Thank you and looking forward to your favorable response of our request.</p>
            <p style="margin-bottom:6px">Respectfully,</p>
            <div class="members">
                @foreach ($members as $name)
                    <p>{{ $name }}@if (! $loop->last),@endif</p>
                @endforeach
            </div>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom:26px">
            <p style="margin-bottom:0">To: <b><u>MS. ROSEL O. ONESA</u></b></p>
            <p style="padding-left:23px">OIC Dean, CCS</p>
            <p>This formally signifies that I <b>ACCEPT/ REJECT</b> the request to serve as {{ $role }} of the team {{ $teamName }}.</p>
            <p class="justify">As {{ $role }}, I agree to perform my duties and responsibilities stipulated in Section 2.6 of the TCP Guidebook from Capstone Project 1 until Capstone Project 2.</p>
            <p class="justify">Furthermore, I agree to set the schedule for advising or consultation to help the students and ensure the success of the capstone project.</p>
            <p style="margin-bottom:40px">Conformed:</p>
            <p style="margin-bottom:0"><b><u>{{ $expertName }}</u></b></p>
            <p style="margin-bottom:0">Name and Signature</p>
        </td>
    </tr>
</table>

</body>
</html>
