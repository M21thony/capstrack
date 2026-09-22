@extends('errors.layout')

@section('code', '404')
@section('title', 'Page not found')
@section('tone', 'neutral')
@section('heading', 'That page is not here')
@section('body', 'The link may be from an earlier term, or the record it pointed to was removed. Everything you can open is in the sidebar.')

@section('icon')
<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7.5"></circle><line x1="16.5" y1="16.5" x2="21" y2="21"></line></svg>
@endsection

@section('actions')
<a href="{{ url('/') }}" class="btn btn-primary">Back to CapsTrack</a>
@endsection
