{{--
  The Teams hub, the same for a CaPSA (Team Management) and an expert (My
  Teams): To do, Teams, and for a CaPSA a Milestones tab that sets up the
  checklist.
--}}
<div class="tmx tm-list tm-hub" data-portal="{{ $portal }}">
  @include('capsa.team-management._hub-head')

  @if (in_array($tab, ['todo', 'teams'], true))
    <section class="hub-pane" id="tm-todo" role="tabpanel" @if ($tab !== 'todo') hidden @endif>
      @include('capsa.team-management._todo')
    </section>
    <section class="hub-pane" id="tm-teams-tab" role="tabpanel" @if ($tab !== 'teams') hidden @endif>
      @include('capsa.team-management._teams')
    </section>
  @else
    <section class="hub-pane hub-page" role="tabpanel">
      @include('capsa.partials.team-management.' . $tab, $tabData)
    </section>
  @endif
</div>
