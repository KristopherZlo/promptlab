@extends('errors.layout')

@section('title', 'Page expired')
@section('status', '419')
@section('heading', 'Page expired')
@section('message', 'Your session token is no longer valid for this request.')
@section('hint', 'Refresh the page and try the action again. If you were inactive for a while, sign in again before retrying.')
@section('rail', 'This usually happens after a long idle period or an outdated form submission.')
