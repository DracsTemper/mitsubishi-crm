@extends('layouts.app')
@section('title', 'Manage Test Drive')
@section('content')
<a href="{{ route('salesman.test-drives.show', $testDrive) }}" class="text-secondary text-decoration-none">← Test Drive #{{ $testDrive->id }}</a>
<x-page-heading title="Manage Test Drive" subtitle="Confirm, complete, cancel, mark no-show, or reschedule this appointment." eyebrow="SALESMAN · APPOINTMENT CONTROL" />
<form class="panel panel-body mt-3" method="POST" action="{{ route('salesman.test-drives.update', $testDrive) }}">@include('pages.salesman.test-drives._form')</form>
@endsection
