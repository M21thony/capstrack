{{--
  The CaPSA and Admin bell is the shared expert bell.

  It used to show Recent Activity - every student action in scope - which is a
  log, not a notification: nothing in it was ever addressed to the person
  reading it. A CaPSA is very often also an adviser, consultant or grammarian,
  and on these pages they were told nothing about their own teams at all.

  Recent Activity is not gone. It has a page of its own, in the sidebar.
--}}
@include('partials.notification-bell', [
  'bellSettingsUrl' => auth('expert')->user()?->is_capsa ? route('capsa.profile') : null,
  'bellActivityUrl' => route(auth('expert')->user()?->is_capsa ? 'capsa.activitylog' : 'admin.activitylog'),
])
