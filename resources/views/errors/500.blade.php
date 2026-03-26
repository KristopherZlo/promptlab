@extends('errors.layout')

@section('title', 'Server error')
@section('status', '500')
@section('heading', 'Server error')
@section('message', 'Evala hit an unexpected internal failure while processing this request.')
@section('hint', 'Try again after a short pause. If the issue persists, inspect the server logs and the latest deployment changes.')
@section('rail', 'The request reached the application, but the server could not complete it safely.')
