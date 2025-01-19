@extends('layouts.admin')
@section('page-title')
    {{__('Dashboard')}}
@endsection

@section('content')
    {{-- <div class="row">
        <div class="col-sm-12">
            <div class="row">
                <div class="col-xxl-12">
                    <div class="text-center">
                        <h4>{{__('Welcome, '). ucfirst(\Auth::user()->name) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div> --}}
@endsection
