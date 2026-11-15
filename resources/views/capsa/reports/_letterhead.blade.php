{{--
  The school's letterhead for the PDFs made with dompdf: both logos, the
  lines beside them, then a gold and a grey rule. The Reports' PDF, the
  defense schedule and the team ratings all start with it, so they read as
  one set. Bring the styles in once, in the <style> of the page:

    @include('capsa.reports._letterhead', ['part' => 'style'])

  and the letterhead itself where it goes:

    @include('capsa.reports._letterhead')
--}}
@if (($part ?? 'markup') === 'style')
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
@else
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
@endif
