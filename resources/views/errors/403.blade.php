@extends('errors.layout')

@section('title', 'Access denied')
@section('status', '403')
@section('heading', 'Access denied')
@section('message', 'Your account does not have permission to open this page in the current workspace.')
@section('hint', 'Switch to a workspace where you have access or sign in with an account that has the required role.')
@section('rail', 'The route exists, but the current workspace role is not allowed to use it.')
