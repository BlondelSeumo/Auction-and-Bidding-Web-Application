@extends('layouts.admin')
@section('page-title')
    {{ __('Income Vs Expense Summary') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Income vs Expense Summary') }}</li>
@endsection

@push('theme-script')
    <script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
@endpush

@php
    if(isset($_GET['category']) && $_GET['period'] == 'yearly')
    {
        $chartArr = [];

foreach ($profit as $innerArray) {
    foreach ($innerArray as $value) {
        $chartArr[] = $value;
    }
}
    }
    else {
        $chartArr = $profit[0];
    }
@endphp

@push('script-page')
    <script>
        (function() {
            var chartBarOptions = {
                series: [{
                    name: '{{ __('Profit') }}',
                    data: {!! json_encode($chartArr) !!},

                }, ],

                chart: {
                    height: 300,
                    type: 'area',
                    // type: 'line',
                    dropShadow: {
                        enabled: true,
                        color: '#000',
                        top: 18,
                        left: 7,
                        blur: 10,
                        opacity: 0.2
                    },
                    toolbar: {
                        show: false
                    }
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    width: 2,
                    curve: 'smooth'
                },
                title: {
                    text: '',
                    align: 'left'
                },
                xaxis: {
                    categories: {!! json_encode($monthList) !!},
                    title: {
                        text: '{{ __('Months') }}'
                    }
                },
                colors: ['#ffa21d', '#FF3A6E'],

                grid: {
                    strokeDashArray: 4,
                },
                legend: {
                    show: false,
                },
                // markers: {
                //     size: 4,
                //     colors: ['#6fd944', '#6fd944'],
                //     opacity: 0.9,
                //     strokeWidth: 2,
                //     hover: {
                //         size: 7,
                //     }
                // },
                yaxis: {
                    title: {
                        text: '{{ __('Profit') }}'
                    },

                }

            };
            var arChart = new ApexCharts(document.querySelector("#chart-sales"), chartBarOptions);
            arChart.render();
        })();
    </script>
    <script type="text/javascript" src="{{ asset('js/html2pdf.bundle.min.js') }}"></script>
    <script>
        var year = '{{ $currentYear }}';
        var filename = $('#filename').val();

        function saveAsPDF() {
            var element = document.getElementById('printableArea');

            // element.style.width = '100%';
            // element.style.overflow = 'visible';

            var opt = {
                margin: 0.3,
                filename: filename,
                image: {
                    type: 'jpeg',
                    quality: 1
                },
                html2canvas: {
                    scale: 4,
                    dpi: 72,
                    letterRendering: true,
                },
                jsPDF: {
                    unit: 'in',
                    format: 'A2'
                }
            };
            html2pdf().set(opt).from(element).save();

        }
    </script>
@endpush


@section('action-btn')
    <div class="float-end">
        {{--        <a class="btn btn-sm btn-primary" data-bs-toggle="collapse" href="#multiCollapseExample1" role="button" aria-expanded="false" aria-controls="multiCollapseExample1" data-bs-toggle="tooltip" title="{{__('Filter')}}"> --}}
        {{--            <i class="ti ti-filter"></i> --}}
        {{--        </a> --}}

        <a href="#" class="btn btn-sm btn-primary" onclick="saveAsPDF()"data-bs-toggle="tooltip"
            title="{{ __('Download') }}" data-original-title="{{ __('Download') }}">
            <span class="btn-inner--icon"><i class="ti ti-download"></i></span>
        </a>

    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="mt-2 " id="multiCollapseExample1">
                <div class="card">
                    <div class="card-body">
                        {{ Form::open(['route' => ['report.income.vs.expense.summary'], 'method' => 'GET', 'id' => 'income_vs_expense_summary']) }}
                        <div class="row align-items-center justify-content-end">
                            <div class="col-xl-10">
                                <div class="row">
                                    @if(isset($_GET['period']) && $_GET['period'] == 'yearly')
                                    <div class="col-xl-2 col-lg-2 col-md-6 col-sm-12 col-12">

                                    </div>

                                    <div class="col-xl-2 col-lg-2 col-md-6 col-sm-12 col-12">
                                        <div class="btn-box">
                                            {{ Form::label('period', __('Income Period'),['class'=>'form-label']) }}
                                            {{ Form::select('period', $periods,isset($_GET['period'])?$_GET['period']:'', array('class' => 'form-control select period','required'=>'required')) }}
                                        </div>
                                    </div>
                                    @else
                                    <div class="col-xl-2 col-lg-2 col-md-6 col-sm-12 col-12">
                                        <div class="btn-box">
                                            {{ Form::label('period', __('Income Period'),['class'=>'form-label']) }}
                                            {{ Form::select('period', $periods,isset($_GET['period'])?$_GET['period']:'', array('class' => 'form-control select period','required'=>'required')) }}
                                        </div>
                                    </div>

                                    <div class="col-xl-2 col-lg-2 col-md-6 col-sm-12 col-12">
                                        <div class="btn-box">
                                            {{ Form::label('year', __('Year'),['class'=>'form-label'])}}
                                            {{ Form::select('year',$yearList,isset($_GET['year'])?$_GET['year']:'', array('class' => 'form-control select')) }}
                                        </div>
                                    </div>

                                    @endif
                                    <div class="col-xl-2 col-lg-2 col-md-6 col-sm-12 col-12">
                                        <div class="btn-box">
                                            {{ Form::label('category', __('Category'), ['class' => 'form-label']) }}
                                            {{ Form::select('category', $category, isset($_GET['category']) ? $_GET['category'] : '', ['class' => 'form-control select']) }}
                                        </div>
                                    </div>

                                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                        <div class="btn-box">
                                            {{ Form::label('customer', __('Customer'), ['class' => 'form-label']) }}
                                            {{ Form::select('customer', $customer, isset($_GET['customer']) ? $_GET['customer'] : '', ['class' => 'form-control select']) }}
                                        </div>
                                    </div>

                                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                        <div class="btn-box">
                                            {{ Form::label('vender', __('Vendor'), ['class' => 'form-label']) }}
                                            {{ Form::select('vender', $vender, isset($_GET['vender']) ? $_GET['vender'] : '', ['class' => 'form-control select']) }}
                                        </div>
                                    </div>


                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="row">
                                    <div class="col-auto mt-4">
                                        <a href="#" class="btn btn-sm btn-primary me-1"
                                            onclick="document.getElementById('income_vs_expense_summary').submit(); return false;"
                                            data-bs-toggle="tooltip" title="{{ __('Apply') }}"
                                            data-original-title="{{ __('apply') }}">
                                            <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                                        </a>
                                        <a href="{{ route('report.income.vs.expense.summary') }}"
                                            class="btn btn-sm btn-danger " data-bs-toggle="tooltip"
                                            title="{{ __('Reset') }}" data-original-title="{{ __('Reset') }}">
                                            <span class="btn-inner--icon"><i
                                                    class="ti ti-refresh text-white-off "></i></span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>


    <div id="printableArea">
        <div class="row mt-3">
            <div class="col">
                <input type="hidden"
                    value="{{ $filter['category'] . ' ' . __('Income Vs Expense Summary') . ' ' . 'Report of' . ' ' . $filter['startDateRange'] . ' to ' . $filter['endDateRange'] }}"
                    id="filename">
                <div class="card p-4 mb-4">
                    <h7 class="report-text gray-text mb-0">{{ __('Report') }} :</h7>
                    <h6 class="report-text mb-0">{{ __('Income Vs Expense Summary') }}</h6>
                </div>
            </div>
            @if ($filter['category'] != __('All'))
                <div class="col">
                    <div class="card p-4 mb-4">
                        <h7 class="report-text gray-text mb-0">{{ __('Category') }} :</h7>
                        <h6 class="report-text mb-0">{{ $filter['category'] }}</h6>
                    </div>
                </div>
            @endif
            @if ($filter['customer'] != __('All'))
                <div class="col">
                    <div class="card p-4 mb-4">
                        <h7 class="report-text gray-text mb-0">{{ __('Customer') }} :</h7>
                        <h6 class="report-text mb-0">{{ $filter['customer'] }}</h6>
                    </div>
                </div>
            @endif
            @if ($filter['vender'] != __('All'))
                <div class="col">
                    <div class="card p-4 mb-4">
                        <h7 class="report-text gray-text mb-0">{{ __('Vendor') }} :</h7>
                        <h6 class="report-text mb-0">{{ $filter['vender'] }}</h6>
                    </div>
                </div>
            @endif
            <div class="col">
                <div class="card p-4 mb-4">
                    <h7 class="report-text gray-text mb-0">{{ __('Duration') }} :</h7>
                    @if(isset($_GET['period']) && $_GET['period'] == 'yearly')
                    <h6 class="report-text mb-0">{{array_key_last($yearList).' to '.array_key_first($yearList)}}</h6>
                    @else
                    <h6 class="report-text mb-0">{{$filter['startDateRange'].' to '.$filter['endDateRange']}}</h6>
                    @endif
                </div>
            </div>
        </div>


        <div class="row">
            <div class="col-12" id="chart-container">
                <div class="card">
                    <div class="card-body">
                        <div class="scrollbar-inner">
                            <div id="chart-sales" data-color="primary" data-height="300"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card">
                    <div class="card-body table-border-style">

                        {{-- quarterly --}}
                        @if (isset($_GET['category']) && $_GET['period'] == 'quarterly')
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Type') }}</th>
                                            @foreach ($monthList as $month)
                                                <th>{{ $month }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="13" class="text-dark"><span>{{ __('Income : ') }}</span></td>
                                        </tr>
                                        <tr>

                                            <td>{{ __('Revenue') }}</td>
                                            @foreach ($revenueIncomeTotal as $revenue)
                                            @foreach ($revenue as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                        <tr>
                                            <td>{{ __('Invoice') }}</td>
                                            @foreach ($invoiceIncomeTotal as $invoice)
                                            @foreach ($invoice as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach
                                        </tr>
                                        <tr>
                                            <td colspan="13" class="text-dark"><span>{{ __('Expense : ') }}</span></td>
                                        </tr>
                                        <tr>
                                            <td>{{ __('Payment') }}</td>
                                            @foreach ($paymentExpenseTotal as $payment)
                                            @foreach ($payment as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                        <tr>
                                            <td>{{ __('Bill') }}</td>
                                            @foreach ($billExpenseTotal as $bill)
                                            @foreach ($bill as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                        <tr>
                                            <td colspan="13" class="text-dark">
                                                <span>{{ __('Profit = Income - Expense ') }}</span></td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <h6>{{ __('Profit') }}</h6>
                                            </td>
                                            @foreach ($profit as $prft)
                                            @foreach ($prft as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                    </tbody>
                                </table>
                            </div>


                            {{-- half yearly --}}
                        @elseif(isset($_GET['category']) && $_GET['period'] == 'half-yearly')
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Type') }}</th>
                                            @foreach ($monthList as $month)
                                                <th>{{ $month }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="13" class="text-dark"><span>{{ __('Income : ') }}</span></td>
                                        </tr>
                                        <tr>

                                            <td>{{ __('Revenue') }}</td>
                                            @foreach ($revenueIncomeTotal as $revenue)
                                            @foreach ($revenue as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                        <tr>
                                            <td>{{ __('Invoice') }}</td>
                                            @foreach ($invoiceIncomeTotal as $invoice)
                                            @foreach ($invoice as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach
                                        </tr>
                                        <tr>
                                            <td colspan="13" class="text-dark"><span>{{ __('Expense : ') }}</span></td>
                                        </tr>
                                        <tr>
                                            <td>{{ __('Payment') }}</td>
                                            @foreach ($paymentExpenseTotal as $payment)
                                            @foreach ($payment as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                        <tr>
                                            <td>{{ __('Bill') }}</td>
                                            @foreach ($billExpenseTotal as $bill)
                                            @foreach ($bill as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                        <tr>
                                            <td colspan="13" class="text-dark">
                                                <span>{{ __('Profit = Income - Expense ') }}</span></td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <h6>{{ __('Profit') }}</h6>
                                            </td>
                                            @foreach ($profit as $prft)
                                            @foreach ($prft as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            {{-- yearly --}}
                        @elseif(isset($_GET['category']) && $_GET['period'] == 'yearly')
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Type') }}</th>
                                            @foreach ($monthList as $month)
                                                <th>{{ $month }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="13" class="text-dark"><span>{{ __('Income : ') }}</span></td>
                                        </tr>
                                        <tr>

                                            <td>{{ __('Revenue') }}</td>
                                            @foreach ($revenueIncomeTotal as $revenue)
                                            @foreach ($revenue as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                        <tr>
                                            <td>{{ __('Invoice') }}</td>
                                            @foreach ($invoiceIncomeTotal as $invoice)
                                            @foreach ($invoice as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach
                                        </tr>
                                        <tr>
                                            <td colspan="13" class="text-dark"><span>{{ __('Expense : ') }}</span></td>
                                        </tr>
                                        <tr>
                                            <td>{{ __('Payment') }}</td>
                                            @foreach ($paymentExpenseTotal as $payment)
                                            @foreach ($payment as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                        <tr>
                                            <td>{{ __('Bill') }}</td>
                                            @foreach ($billExpenseTotal as $bill)
                                            @foreach ($bill as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                        <tr>
                                            <td colspan="13" class="text-dark">
                                                <span>{{ __('Profit = Income - Expense ') }}</span></td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <h6>{{ __('Profit') }}</h6>
                                            </td>
                                            @foreach ($profit as $prft)
                                            @foreach ($prft as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Type') }}</th>
                                            @foreach ($monthList as $month)
                                                <th>{{ $month }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="13" class="text-dark"><span>{{ __('Income : ') }}</span></td>
                                        </tr>
                                        <tr>

                                            <td>{{ __('Revenue') }}</td>
                                            @foreach ($revenueIncomeTotal as $revenue)
                                            @foreach ($revenue as $value)
                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                        <tr>
                                            <td>{{ __('Invoice') }}</td>
                                            @foreach ($invoiceIncomeTotal as $invoice)
                                            @foreach ($invoice as $value)

                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach
                                        </tr>
                                        <tr>
                                            <td colspan="13" class="text-dark"><span>{{ __('Expense : ') }}</span></td>
                                        </tr>
                                        <tr>
                                            <td>{{ __('Payment') }}</td>
                                            @foreach ($paymentExpenseTotal as $payment)
                                            @foreach ($payment as $value)

                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                        <tr>
                                            <td>{{ __('Bill') }}</td>
                                            @foreach ($billExpenseTotal as $bill)
                                            @foreach ($bill as $value)

                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                        <tr>
                                            <td colspan="13" class="text-dark">
                                                <span>{{ __('Profit = Income - Expense ') }}</span></td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <h6>{{ __('Profit') }}</h6>
                                            </td>
                                            @foreach ($profit as $prft)
                                            @foreach ($prft as $value)

                                                <td>{{ \Auth::user()->priceFormat($value) }}</td>
                                            @endforeach
                                            @endforeach

                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
