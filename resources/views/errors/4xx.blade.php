@extends('errors.layout')

@section('title', 'Request error')
@section('heading', 'Request error')
@section('message', 'The request could not be completed because the browser reached an invalid or unavailable route state.')
@section('hint', 'Try the workspace entry point again and verify that the current URL is correct.')
@section('rail', 'A client-side or routing issue prevented the request from completing normally.')
