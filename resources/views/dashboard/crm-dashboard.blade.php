@extends('layouts.admin')
@section('page-title')
    {{__('Dashboard')}}
@endsection
@push('script-page')
    <script>

    </script>
@endpush
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{route('dashboard')}}">{{__('Dashboard')}}</a></li>
    <li class="breadcrumb-item">{{__('CRM')}}</li>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-4 col-md-12 dashboard-card">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-auto mb-3 mb-sm-0">
                            <div class="d-flex align-items-center">
                                <div class="theme-avtar bg-primary badge">
                                    <i class="ti ti-layout-2"></i>
                                </div>
                                <div class="ms-3">
                                    <small class="text-muted">{{ __('Total') }}</small>
                                    <h6 class="m-0"><a href="{{ route('leads.index') }}" class="dashboard-link">{{ __('Lead') }}</a></h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto text-end">
                            <h4 class="m-0">{{$crm_data['total_leads']}}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-12 dashboard-card">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-auto mb-3 mb-sm-0">
                            <div class="d-flex align-items-center">
                                <div class="theme-avtar bg-warning badge">
                                    <i class="ti ti-layout-2"></i>
                                </div>
                                <div class="ms-3">
                                    <small class="text-muted">{{ __('Total') }}</small>
                                    <h6 class="m-0"><a href="{{ route('deals.index') }}" class="dashboard-link">{{ __('Deal') }}</a></h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto text-end">
                            <h4 class="m-0">{{$crm_data['total_deals']}}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-12 dashboard-card">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-auto mb-3 mb-sm-0">
                            <div class="d-flex align-items-center">
                                <div class="theme-avtar bg-info badge">
                                    <i class="ti ti-notebook"></i>
                                </div>
                                <div class="ms-3">
                                    <small class="text-muted">{{ __('Total') }}</small>
                                    <h6 class="m-0"><a href="{{ route('contract.index') }}" class="dashboard-link">{{ __('Contract') }}</a></h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto text-end">
                            <h4 class="m-0">{{$crm_data['total_contracts']}}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5>{{__('Lead Status')}}</h5>
                </div>
                <div class="card-body">
                    <div class="row ">
                        @foreach($crm_data['lead_status'] as $status => $val)
                            <div class="col-md-6 col-sm-6 mb-5">
                                <div class="align-items-start">
                                    <div class="ms-2">
                                        <p class="text-muted text-sm mb-0">{{$val['lead_stage']}}</p>
                                        <h3 class="mb-0 text-primary">{{ $val['lead_percentage'] }}%</h3>
                                        <div class="progress mb-0">
                                            <div class="progress-bar bg-primary" style="width:{{$val['lead_percentage']}}%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5>{{__('Deal Status')}}</h5>
                </div>
                <div class="card-body">
                    <div class="row ">
                        @foreach($crm_data['deal_status'] as $status => $val)
                            <div class="col-md-6 col-sm-6 mb-5">
                                <div class="align-items-start">
                                    <div class="ms-2">
                                        <p class="text-muted text-sm mb-0">{{$val['deal_stage']}}</p>
                                        <h3 class="mb-0 text-primary">{{ $val['deal_percentage'] }}%</h3>
                                        <div class="progress mb-0">
                                            <div class="progress-bar bg-primary" style="width:{{$val['deal_percentage']}}%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mt-1 mb-0">{{__('Latest Contract')}}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                <th></th>
                                <th>{{__('Subject')}}</th>
                                @if(\Auth::user()->type!='client')
                                <th>{{__('Client')}}</th>
                                @endif
                                <th>{{__('Project')}}</th>
                                <th>{{__('Contract Type')}}</th>
                                <th>{{__('Contract Value')}}</th>
                                <th>{{__('Start Date')}}</th>
                                <th>{{__('End Date')}}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($crm_data['latestContract'] as $contract)
                                <tr>
                                    <td>
                                        <a href="{{route('contract.show',$contract->id)}}" class="btn btn-outline-primary">{{\Auth::user()->contractNumberFormat($contract->id)}}</a>
                                    </td>
                                    <td>{{ $contract->subject}}</td>
                                    @if(\Auth::user()->type!='client')
                                        <td>{{ !empty($contract->clients)?$contract->clients->name:'-' }}</td>
                                    @endif
                                    <td>{{ !empty($contract->projects)?$contract->projects->project_name:'-' }}</td>
                                    <td>{{ !empty($contract->types)?$contract->types->name:'' }}</td>
                                    <td>{{ \Auth::user()->priceFormat($contract->value) }}</td>
                                    <td>{{ \Auth::user()->dateFormat($contract->start_date )}}</td>
                                    <td>{{ \Auth::user()->dateFormat($contract->end_date )}}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="text-center">
                                            <h6>{{__('There is no latest contract')}}</h6>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
