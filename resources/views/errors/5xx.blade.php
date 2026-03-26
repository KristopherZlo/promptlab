@extends('errors.layout')

@section('title', 'Application error')
@section('heading', 'Application error')
@section('message', 'Evala encountered an internal problem while serving this page.')
@section('hint', 'Retry the request after a short pause. If the problem remains, inspect the deployment and backend logs.')
@section('rail', 'The failure happened inside the application or one of its runtime dependencies.')
