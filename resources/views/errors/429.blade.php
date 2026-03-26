@extends('errors.layout')

@section('title', 'Too many requests')
@section('status', '429')
@section('heading', 'Too many requests')
@section('message', 'This action was rate limited to protect the workspace and the connected AI services.')
@section('hint', 'Wait a moment before trying again, especially for quick tests, experiment runs, and connection validation.')
@section('rail', 'Rate limiting prevented this request from running right now.')
