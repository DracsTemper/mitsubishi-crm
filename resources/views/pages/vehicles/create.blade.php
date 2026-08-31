@extends('layouts.app') @section('title','Create Vehicle') @section('content')
<x-page-heading title="Create Vehicle" subtitle="Add a Mitsubishi model listing and assign it to a Dealer." eyebrow="ADMIN · VEHICLE MANAGEMENT" />
<div class="panel"><div class="panel-body"><form method="POST" action="{{ route('vehicles.store') }}" enctype="multipart/form-data">@include('pages.vehicles._form')</form></div></div>
@endsection
