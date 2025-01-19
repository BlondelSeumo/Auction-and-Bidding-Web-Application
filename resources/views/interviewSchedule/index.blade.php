@extends('layouts.admin')
@section('page-title')
    {{__('Manage Interview Schedule')}}
@endsection
@push('css-page')
{{--    <link rel="stylesheet" href="{{ asset('assets/libs/fullcalendar/dist/fullcalendar.min.css') }}">--}}
@endpush
@php
    $setting = \App\Models\Utility::settings();
@endphp


@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{route('dashboard')}}">{{__('Dashboard')}}</a></li>
    <li class="breadcrumb-item">{{__('Interview Schedule')}}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        @can('create interview schedule')
            <a href="#" data-url="{{ route('interview-schedule.create') }}" data-bs-toggle="tooltip" title="{{__('Create')}}" data-ajax-popup="true" data-title="{{__('Create New Interview Schedule')}}" class="btn btn-sm btn-primary">
                <i class="ti ti-plus"></i>
            </a>
        @endcan
    </div>
@endsection

@section('content')

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-lg-6">
                            <h5>{{ __('Calendar') }}</h5>
                        </div>
                        <div class="col-lg-6">
                            @if (isset($setting['google_calendar_enable']) && $setting['google_calendar_enable'] == 'on')
                                <select class="form-control" name="calender_type" id="calender_type"  onchange="get_data()">
                                    <option value="goggle_calender">{{__('Google Calender')}}</option>
                                    <option value="local_calender" selected="true">{{__('Local Calender')}}</option>
                                </select>
                            @endif
                            <input type="hidden" id="interview_calendar" value="{{url('/')}}">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id='calendar' class='calendar'></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">{{__('Schedule List')}}</h4>
                    <ul class="event-cards list-group list-group-flush mt-3 w-100">
                        <li class="list-group-item card mb-3">
                            <div class="row align-items-center justify-content-between">
                                <div class=" align-items-center">
                                    @if(!$schedules->isEmpty())
                                        @foreach ($schedules as $schedule)
                                            <div class="card mb-3 border shadow-none">
                                                <div class="px-3">
                                                    <div class="row align-items-center">
                                                        <div class="col ml-n2">
                                                            <h5 class="text-sm mb-0">
                                                                <a href="#!">{{!empty($schedule->applications) ? !empty($schedule->applications->jobs) ? $schedule->applications->jobs->title : '' : ''}}</a>
                                                            </h5>
                                                            <p class="card-text small text-muted">
                                                                {{ !empty($schedule->applications)?$schedule->applications->name:'' }}
                                                            </p>
                                                            <p class="card-text small text-muted">
                                                                {{  \Auth::user()->dateFormat($schedule->date).' '. \Auth::user()->timeFormat($schedule->time) }}
                                                            </p>
                                                        </div>
                                                        <div class="col-auto text-right">
                                                            @can('edit interview schedule')
                                                                <div class="action-btn me-2">
                                                                    <a href="#" data-url="{{ route('interview-schedule.edit',$schedule->id) }}" data-title="{{__('Edit Interview Schedule')}}" data-ajax-popup="true" class="mx-3 btn btn-sm  align-items-center bg-info" data-bs-toggle="tooltip" title="{{__('Edit')}}" data-original-title="{{__('Edit')}}"><i class="ti ti-pencil text-white"></i></a>
                                                                </div>
                                                            @endcan
                                                            @can('delete interview schedule')
                                                                    <div class="action-btn ">
                                                                        {!! Form::open(['method' => 'DELETE', 'route' => ['interview-schedule.destroy', $schedule->id],'id'=>'delete-form-'.$schedule->id]) !!}
                                                                            <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para bg-danger" data-bs-toggle="tooltip" title="{{__('Delete')}}" data-original-title="{{__('Delete')}}" data-confirm="{{__('Are You Sure?').'|'.__('This action can not be undone. Do you want to continue?')}}" data-confirm-yes="document.getElementById('delete-form-{{$schedule->id}}').submit();"><i class="ti ti-trash text-white"></i></a>
                                                                        {!! Form::close() !!}
                                                                    </div>
                                                            @endcan
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="text-center">
                                            {{__('No Interview Scheduled!')}}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>


@endsection

@push('script-page')
    <script src="{{ asset('assets/js/plugins/main.min.js') }}"></script>

    <script type="text/javascript">

        $(document).ready(function()
        {
            get_data();
        });

        function get_data()
        {
            var calender_type=$('#calender_type :selected').val();
            $('#calendar').removeClass('local_calender');
            $('#calendar').removeClass('goggle_calender');
            if(calender_type==undefined){
                $('#calendar').addClass('local_calender');
            }
            $('#calendar').addClass(calender_type);
            $.ajax({
                url: $("#interview_calendar").val()+"/interview-schedule/get_interview_data" ,
                method:"POST",
                data: {"_token": "{{ csrf_token() }}",'calender_type':calender_type},
                success: function(data) {
                    // console.log(data);
                    (function () {
                        var etitle;
                        var etype;
                        var etypeclass;
                        var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                            headerToolbar: {
                                left: 'prev,next today',
                                center: 'title',
                                right: 'timeGridDay,timeGridWeek,dayGridMonth'
                            },
                            buttonText: {
                                timeGridDay: "{{__('Day')}}",
                                timeGridWeek: "{{__('Week')}}",
                                dayGridMonth: "{{__('Month')}}"
                            },
                            slotLabelFormat: {
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: false,
                            },
                            themeSystem: 'bootstrap',
                            // slotDuration: '00:10:00',
                            allDaySlot:false,
                            navLinks: true,
                            droppable: true,
                            selectable: true,
                            selectMirror: true,
                            editable: true,
                            dayMaxEvents: true,
                            handleWindowResize: true,
                            height: 'auto',
                            timeFormat: 'H(:mm)',
                            events: data,
                        });
                        calendar.render();
                    })();
                }
            });
        }
    </script>
@endpush
