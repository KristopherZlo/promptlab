@extends('errors.layout')

@section('title', 'Service unavailable')
@section('status', '503')
@section('heading', 'Service unavailable')
@section('message', 'Evala is temporarily unavailable, likely because maintenance mode is active or a dependency is down.')
@section('hint', 'Wait for the deployment or maintenance window to finish, then refresh the page.')
@section('rail', 'The application is currently refusing normal traffic.')
