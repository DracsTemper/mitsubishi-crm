@extends('layouts.app') @section('title','Edit Vehicle') @section('content')
<x-page-heading title="Edit Vehicle" subtitle="Update the model listing, Dealer assignment, or primary image." eyebrow="ADMIN · VEHICLE MANAGEMENT" />
<div class="panel"><div class="panel-body"><form method="POST" action="{{ route('vehicles.update', $vehicle) }}" enctype="multipart/form-data">@include('pages.vehicles._form')</form></div></div>
@endsection
