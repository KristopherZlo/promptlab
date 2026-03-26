@extends('errors.layout')

@section('title', 'Page not found')
@section('status', '404')
@section('heading', 'Page not found')
@section('message', 'The requested route does not exist or is no longer available in this Evala instance.')
@section('hint', 'Check the address, use the workspace navigation, or return to the main entry point.')
@section('rail', 'A route lookup failed, so Evala could not match this URL to a page.')
