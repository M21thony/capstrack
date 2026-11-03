@extends('errors.layout')

@section('title', 'Announcement emails')
@section('tone', $done ? 'neutral' : 'amber')
@section('heading', $done ? 'Announcement emails are off' : 'Turn off announcement emails?')
@section('body', $done ? 'Turn them back on under Notifications in your profile.' : 'Posts still show in your bell.')

@section('icon')
<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"></rect><polyline points="3 7 12 13 21 7"></polyline></svg>
@endsection

@section('actions')
@if ($done)
<a href="{{ url('/') }}" class="btn btn-primary">Back to CapsTrack</a>
@else
<form method="POST" action="{{ request()->fullUrl() }}">
  <button type="submit" class="btn btn-primary">Turn off</button>
</form>
@endif
@endsection
