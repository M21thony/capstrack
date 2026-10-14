{{--
  The Team Applications tab: every team asking this person to be their
  adviser, consultant or grammarian.

  The To do lists the waiting ones as a shortcut; this is the whole list,
  with what was declined. A team accepted moves to the Teams tab, so it is not
  kept here. Read opens the application in the PDF window, the document they
  applied with beside what to decide, and Accept and Decline use the To do's
  own dialog.
--}}
@php
  $waiting = $applications['waiting'];
  $declined = $applications['declined'];
  $details = $applications['details'];
  $acceptEffect = fn ($d) => 'You become the '.strtolower($d['role']).' of Team '.$d['team'].'. The team is told.'
    .($applications['maxSlots'] > 0 ? ' Uses 1 of your '.$applications['maxSlots'].' slots.' : '');
@endphp

<div class="tools apps-tools">
  <div class="seg" role="group" aria-label="Show">
    <button type="button" data-apps-mode="waiting" aria-pressed="true">Waiting for you <span class="num" id="tm-apps-nw">{{ $waiting->count() }}</span></button>
    <button type="button" data-apps-mode="declined" aria-pressed="false">Declined <span class="num" id="tm-apps-nd">{{ $declined->count() }}</span></button>
  </div>
  <label class="search">
    <x-tm-icon n="search" s="15" />
    <input type="search" id="tm-apps-q" placeholder="Search team or leader" aria-label="Search team or leader" autocomplete="off">
  </label>
  <select id="tm-apps-role" class="sel" aria-label="Role">
    <option value="">All roles</option>
    <option value="adviser">Adviser</option>
    <option value="consultant">Consultant</option>
    <option value="grammarian">Grammarian</option>
  </select>
  {{-- View Report and Download Excel, for whichever list is showing. --}}
  <span class="apps-exp" id="tm-apps-exp-waiting"></span>
  <span class="apps-exp" id="tm-apps-exp-declined" hidden></span>
</div>

<div class="card apps" id="tm-apps">
  <div id="tm-apps-waiting">
    @if ($waiting->isEmpty())
      <div class="empty"><b>No teams are waiting for you</b>When a team asks you to be their adviser, consultant or grammarian, it shows up here and in your To do.</div>
    @else
      <div class="apps-scroll">
        <table class="apps-t" data-export="Team Applications" data-export-into="#tm-apps-exp-waiting">
          <colgroup><col><col style="width:128px"><col class="apps-opt" style="width:170px"><col style="width:130px"><col style="width:300px"></colgroup>
          <thead><tr><th scope="col">Team</th><th scope="col">Role</th><th scope="col" class="apps-opt">Leader</th><th scope="col">Applied</th><th scope="col" class="ar"><span class="vh">Actions</span></th></tr></thead>
          <tbody>
            @foreach ($waiting as $assignment)
              @php $d = $details[$assignment->assignment_id]; @endphp
              <tr data-q="{{ mb_strtolower($d['team'].' '.$d['leader'].' '.$d['project']) }}" data-role="{{ strtolower($d['role']) }}">
                <td data-label="Team"><div class="nm">{{ $d['team'] }}</div>@if ($d['project'])<div class="pj">{{ $d['project'] }}</div>@endif</td>
                <td data-label="Role"><span class="role-p">{{ $d['role'] }}</span></td>
                <td data-label="Leader" class="apps-opt">{{ $d['leader'] ?: '—' }}</td>
                <td data-label="Applied"><time datetime="{{ $assignment->created_at?->toIso8601String() }}" title="{{ $d['applied'] }}" @class(['late' => $d['stale']])>{{ $d['waited'] }}</time></td>
                <td class="ar">
                  <div class="acts">
                    <button type="button" class="btn rd" data-read="application" data-read-id="{{ $d['id'] }}"><x-tm-icon n="file" s="15" />Read</button>
                    <button type="button" class="btn" data-decide="decline"
                            data-url="{{ $d['acceptUrl'] }}" data-field="status" data-value="rejected" data-note="reason" data-min="20"
                            data-title="Decline: Team {{ $d['team'] }}" data-sub="Asked you to be their {{ strtolower($d['role']) }}">Decline</button>
                    <button type="button" class="btn p" data-decide="approve"
                            data-url="{{ $d['acceptUrl'] }}" data-field="status" data-value="approved" data-note="reason"
                            data-effect="{{ $acceptEffect($d) }}"
                            data-title="Accept Team {{ $d['team'] }}" data-sub="As their {{ strtolower($d['role']) }}">Accept</button>
                  </div>
                </td>
              </tr>
            @endforeach
            <tr class="apps-none" hidden><td colspan="5">No team matches that.</td></tr>
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div id="tm-apps-declined" hidden>
    @if ($declined->isEmpty())
      <div class="empty"><b>Nothing declined</b>Teams you decline stay here for a while, with the reason you gave them.</div>
    @else
      <div class="apps-scroll">
        <table class="apps-t" data-export="Declined Applications" data-export-into="#tm-apps-exp-declined">
          <colgroup><col><col style="width:128px"><col class="apps-opt" style="width:170px"><col style="width:130px"><col style="width:200px"></colgroup>
          <thead><tr><th scope="col">Team</th><th scope="col">Role</th><th scope="col" class="apps-opt">Leader</th><th scope="col">Declined</th><th scope="col" class="ar"><span class="vh">Actions</span></th></tr></thead>
          <tbody>
            @foreach ($declined as $assignment)
              @php $d = $details[$assignment->assignment_id]; @endphp
              <tr data-q="{{ mb_strtolower($d['team'].' '.$d['leader'].' '.$d['project']) }}" data-role="{{ strtolower($d['role']) }}">
                <td data-label="Team"><div class="nm">{{ $d['team'] }}</div>@if ($d['reason'])<div class="pj">Your reason: {{ $d['reason'] }}</div>@endif</td>
                <td data-label="Role"><span class="role-p">{{ $d['role'] }}</span></td>
                <td data-label="Leader" class="apps-opt">{{ $d['leader'] ?: '—' }}</td>
                <td data-label="Declined">{{ $d['declinedOn'] }}</td>
                <td class="ar">
                  <div class="acts">
                    <button type="button" class="btn rd" data-read="application" data-read-id="{{ $d['id'] }}"><x-tm-icon n="file" s="15" />Read</button>
                    <button type="button" class="btn" data-decide="remove" data-method="DELETE"
                            data-url="{{ $d['removeUrl'] }}" data-no-note="1"
                            data-effect="It leaves this list. The team isn't told again, and they can still apply to you."
                            data-title="Clear Team {{ $d['team'] }} from this list?" data-sub="Declined {{ $d['declinedOn'] }}">Clear</button>
                  </div>
                </td>
              </tr>
            @endforeach
            <tr class="apps-none" hidden><td colspan="5">No team matches that.</td></tr>
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div class="apps-foot">
    <span id="tm-apps-count">{{ $waiting->count() }} {{ \Illuminate\Support\Str::plural('team', $waiting->count()) }} · waiting longest first</span>
    <span>Teams you accept are in the <a href="{{ route($portal === 'capsa' ? 'capsa.team_management' : 'instructor.teams', ['tab' => 'teams']) }}" data-hub-tab="teams">Teams</a> tab.</span>
  </div>
</div>

{{-- What the application window shows beside the document, read when Read
     is clicked. Data, never run. --}}
@php
  $appsData = ['details' => $details, 'titles' => $applications['titles'], 'usedSlots' => $applications['usedSlots'], 'maxSlots' => $applications['maxSlots']];
@endphp
<script type="application/json" id="tm-apps-data">@json($appsData)</script>
