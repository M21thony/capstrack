@extends('errors.layout')

{{--
  Reached only when a full page POST arrives with a stale session token, so the
  form that was filled in is already gone with it. This page does not promise
  otherwise. The version that CAN promise it - the dialog raised over an intact
  form on an XHR 419 - needs the unsaved values held client-side first.
--}}
@section('code', '419')
@section('title', 'Sign-in expired')
@section('tone', 'blue')
@section('heading', 'Your sign-in expired while this page was open')
@section('body', 'Nothing was saved. CapsTrack signs you out after a spell of no activity, and this page had been open past that point.')

@section('icon')
<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 7 12 12 15.5 14"></polyline></svg>
@endsection

@section('extra')
<div class="err-note">Sign in again and repeat the last step. If you were part-way through a long form, you will have to fill it in once more.</div>
@endsection

@section('actions')
<a href="{{ url('/') }}" class="btn btn-primary">Sign in again</a>
@endsection
