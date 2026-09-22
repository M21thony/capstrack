@extends('errors.layout')

@section('code', '403')
@section('title', 'Not yours to open')
@section('tone', 'neutral')
@section('heading', 'That is not yours to open')
@section('body', 'It belongs to another section, or to a role your account does not hold. If it should be yours, ask the CaPSA to check your role.')

@section('icon')
<svg viewBox="0 0 24 24"><rect x="4" y="10.5" width="16" height="10.5" rx="2"></rect><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"></path></svg>
@endsection

@section('actions')
<a href="{{ url('/') }}" class="btn btn-primary">Back to CapsTrack</a>
@endsection
