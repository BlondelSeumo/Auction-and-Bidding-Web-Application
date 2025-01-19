@extends('layouts.admin')
@section('page-title')
    {{__('Manage Contract Type')}}
@endsection
@push('script-page')
@endpush
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{route('dashboard')}}">{{__('Dashboard')}}</a></li>
    <li class="breadcrumb-item">{{__('Contract Type')}}</li>
@endsection
@section('action-btn')
    <div class="float-end">
        <a href="#" data-size="md" data-url="{{ route('contractType.create') }}" data-ajax-popup="true" data-bs-toggle="tooltip" title="{{__('Create New Contract Type')}}" class="btn btn-sm btn-primary">
            <i class="ti ti-plus"></i>
        </a>
    </div>
@endsection


@section('content')
    <div class="row">
        <div class="col-md-3">
            @include('layouts.crm_setup')
        </div>
        <div class="col-md-9">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                            <tr>
                                <th>{{__('Name')}}</th>
                                @if(\Auth::user()->type=='company')
                                    <th class="text-end ">{{__('Action')}}</th>

                                @endif
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($types as $type)

                                <tr class="font-style">
                                    <td>{{ $type->name }}</td>

                                    @if(\Auth::user()->type=='company')
                                        <td class="action text-end">
                                            <div class="action-btn me-2">
                                                <a href="#" class="mx-3 btn btn-sm align-items-center bg-info" data-url="{{ route('contractType.edit',$type->id) }}" data-ajax-popup="true" data-size="md" data-bs-toggle="tooltip" title="{{__('Edit')}}" data-title="{{__('Edit Contract Type')}}">
                                                    <i class="ti ti-pencil text-white"></i>
                                                </a>
                                            </div>
                                            <div class="action-btn ">
                                                {!! Form::open(['method' => 'DELETE', 'route' => ['contractType.destroy', $type->id]]) !!}
                                                <a href="#" class="mx-3 btn btn-sm  align-items-center bs-pass-para bg-danger" data-bs-toggle="tooltip" title="{{__('Delete')}}"><i class="ti ti-trash text-white"></i></a>
                                                {!! Form::close() !!}
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection

