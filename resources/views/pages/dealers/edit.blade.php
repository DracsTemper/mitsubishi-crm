@extends('layouts.app')

@section('title', 'Edit '.$dealer->name)

@section('content')
<x-page-heading title="Edit Dealer" subtitle="Update {{ $dealer->name }} without changing its organizational relationships." eyebrow="ADMIN · DEALER MANAGEMENT" />
<div class="panel">
    <div class="panel-header"><h3 class="panel-title">Organization details</h3></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('dealers.update', $dealer) }}">
            @include('pages.dealers._form')
        </form>
    </div>
</div>
@endsection
