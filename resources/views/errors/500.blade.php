@extends('errors.layout')

@section('code', '500')
@section('title', 'Something broke')
@section('tone', 'red')
@section('heading', 'Something broke on our side')
@section('body', 'Your last change was not saved, and nothing else was altered. Try again in a moment.')

@section('icon')
<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="13"></line><line x1="12" y1="16.5" x2="12" y2="16.5"></line></svg>
@endsection

@section('extra')
<div class="err-note">If it keeps happening, tell the CaPSA what you were doing and roughly when. That is enough to find it in the log.</div>
@endsection

@section('actions')
<a href="{{ url('/') }}" class="btn btn-primary">Back to CapsTrack</a>
@endsection
