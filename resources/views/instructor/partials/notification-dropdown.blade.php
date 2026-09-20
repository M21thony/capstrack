{{--
  The instructor bell is the shared expert bell.

  It used to list pending applications straight from team_expert_assignment,
  which meant an adviser heard about a team applying but never about that same
  team booking a consultation or sending a document in. Applications are
  ordinary notifications now, alongside everything else an expert is told.
--}}
@include('partials.notification-bell', [
  'bellSettingsUrl' => route('instructor.profile'),
])
