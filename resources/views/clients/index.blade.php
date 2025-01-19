@extends('layouts.admin')
@php
   // $profile=asset(Storage::url('uploads/avatar/'));
    $profile=\App\Models\Utility::get_file('uploads/avatar/');
@endphp
@section('page-title')
    {{__('Manage Client')}}
@endsection
@push('script-page')
@endpush
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{route('dashboard')}}">{{__('Dashboard')}}</a></li>
    <li class="breadcrumb-item">{{__('Client')}}</li>
@endsection
@section('action-btn')
    <div class="float-end">
        <a href="#" data-size="md" data-url="{{ route('clients.create') }}" data-ajax-popup="true"  data-bs-toggle="tooltip" title="{{__('Create New Client')}}"  data-bs-original-title="{{__('create New Client')}}" class="btn btn-sm btn-primary">
            <i class="ti ti-plus"></i>
        </a>
    </div>
@endsection
@section('content')
    <div class="row">
        <div class="col-xxl-12">
            <div class="row">
                @foreach($clients as $client)
                    <div class="col-xl-3 col-lg-4 col-md-4 col-sm-6 col-12 d-flex flex-column">
                        <div class="card text-center h-100">
                            <div class="card-header border-0 pb-0">

                                <div class="card-header-right">
                                    <div class="btn-group card-option">
                                        <button type="button" class="btn dropdown-toggle"
                                                data-bs-toggle="dropdown" aria-haspopup="true"
                                                aria-expanded="false">
                                            <i class="ti ti-dots-vertical"></i>
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-end">
{{--                                            <a href="{{ route('clients.show',$client->id) }}"  class="dropdown-item" data-bs-original-title="{{__('View')}}">--}}
{{--                                                <i class="ti ti-eye"></i>--}}
{{--                                                <span>{{__('Show')}}</span>--}}
{{--                                            </a>--}}

                                            @can('edit client')
                                                <a href="#!" data-size="md" data-url="{{ route('clients.edit',$client->id) }}" data-ajax-popup="true" class="dropdown-item" data-bs-original-title="{{__('Edit Client')}}">
                                                    <i class="ti ti-pencil"></i>
                                                    <span>{{__('Edit')}}</span>
                                                </a>
                                            @endcan

                                            @can('delete client')
                                                {!! Form::open(['method' => 'DELETE', 'route' => ['clients.destroy', $client['id']],'id'=>'delete-form-'.$client['id']]) !!}
                                                <a href="#!"  class="dropdown-item bs-pass-para">
                                                    <i class="ti ti-archive"></i>
                                                    <span> @if($client->delete_status!=0){{__('Delete')}} @else {{__('Restore')}}@endif</span>
                                                </a>

                                                {!! Form::close() !!}
                                            @endcan

                                            @if ($client->is_enable_login == 1)
                                            <a href="{{ route('users.login', \Crypt::encrypt($client->id)) }}"
                                                class="dropdown-item">
                                                <i class="ti ti-road-sign"></i>
                                                <span class="text-danger"> {{ __('Login Disable') }}</span>
                                            </a>
                                        @elseif ($client->is_enable_login == 0 && $client->password == null)
                                            <a href="#" data-url="{{ route('clients.reset', \Crypt::encrypt($client->id)) }}"
                                                data-ajax-popup="true" data-size="md" class="dropdown-item login_enable"
                                                data-title="{{ __('New Password') }}" class="dropdown-item">
                                                <i class="ti ti-road-sign"></i>
                                                <span class="text-success"> {{ __('Login Enable') }}</span>
                                            </a>
                                        @else
                                            <a href="{{ route('users.login', \Crypt::encrypt($client->id)) }}"
                                                class="dropdown-item">
                                                <i class="ti ti-road-sign"></i>
                                                <span class="text-success"> {{ __('Login Enable') }}</span>
                                            </a>
                                        @endif

                                            <a href="#!" data-url="{{route('clients.reset',\Crypt::encrypt($client->id))}}" data-ajax-popup="true" class="dropdown-item" data-bs-original-title="{{__('Reset Password')}}">
                                                <i class="ti ti-adjustments"></i>
                                                <span>  {{__('Reset Password')}}</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body full-card">
                                <div class="img-fluid rounded-circle card-avatar">
                                    <img src="{{(!empty($client->avatar))? asset(Storage::url("uploads/avatar/".$client->avatar)): asset(Storage::url("uploads/avatar/avatar.png"))}}"  class="img-user img-fluid rounded border-2 border border-primary" width="120px" height="120px" alt="user-image">
                                </div>
                                <h4 class="mt-2 text-primary">{{ $client->name }}</h4>
                                <p></p>
                                <div class="row">
                                    <div class="col-12 col-sm-12">
                                        <div class="d-grid text-primary">
                                            {{ $client->email }}
                                        </div>
                                    </div>
                                </div>
                                <div class="align-items-center h6 mt-2" data-bs-toggle="tooltip" title="{{__('Last Login')}}">
                                    {{ (!empty($client->last_login_at)) ? $client->last_login_at : '' }}
                                </div>
                            </div>
                            <div class="card-footer p-3">
                                <div class="row">
                                    <div class="col-6">
                                        <h6 class="mb-0"> @if($client->clientDeals)
                                                {{$client->clientDeals->count()}}
                                            @endif</h6>
                                        <p class="text-muted text-sm mb-0">{{__('Deals')}}</p>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="mb-0">@if($client->clientProjects)
                                                {{ $client->clientProjects->count() }}
                                            @endif</h6>
                                        <p class="text-muted text-sm mb-0">{{__('Projects')}}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <script>
        $(document).on('change', '#password_switch', function() {
            if ($(this).is(':checked')) {
                $('.ps_div').removeClass('d-none');
                $('#password').attr("required", true);

            } else {
                $('.ps_div').addClass('d-none');
                $('#password').val(null);
                $('#password').removeAttr("required");
            }
        });
        $(document).on('click', '.login_enable', function() {
            setTimeout(function() {
                $('.modal-body').append($('<input>', {
                    type: 'hidden',
                    val: 'true',
                    name: 'login_enable'
                }));
            }, 2000);
        });
    </script>
@endpush
