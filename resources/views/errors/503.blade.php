@extends('errors.layout')

@section('code', '503')
@section('title', 'Down for maintenance')
@section('tone', 'amber')
@section('heading', 'CapsTrack is down for maintenance')
@section('body', 'The department is updating the system. Nothing you have submitted is affected, and everything will be where you left it.')

@section('icon')
<svg viewBox="0 0 24 24"><path d="M10.3 3.9L1.8 18.1A2 2 0 0 0 3.5 21h17a2 2 0 0 0 1.7-2.9L13.7 3.9a2 2 0 0 0-3.4 0z"></path><line x1="12" y1="9" x2="12" y2="13.5"></line><line x1="12" y1="17" x2="12" y2="17"></line></svg>
@endsection

@section('actions')
<a href="{{ url('/') }}" class="err-btn-2 btn">Try again</a>
@endsection
