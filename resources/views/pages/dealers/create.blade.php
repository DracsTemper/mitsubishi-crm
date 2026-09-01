@extends('layouts.app')

@section('title', 'Create Dealer')

@section('content')
<x-page-heading title="Create Dealer" subtitle="Add a DHS Motors dealership organization to the network." eyebrow="ADMIN · DEALER MANAGEMENT" />
<div class="panel">
    <div class="panel-header"><h3 class="panel-title">Organization details</h3></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('dealers.store') }}">
            @include('pages.dealers._form')
        </form>
    </div>
</div>
@endsection
