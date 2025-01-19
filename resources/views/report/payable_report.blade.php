@extends('layouts.admin')
@section('page-title')
    {{ __('Payable Reports') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Payable Reports') }}</li>
@endsection
@push('script-page')

    <script type="text/javascript" src="{{ asset('js/html2pdf.bundle.min.js') }}"></script>
    <script>
        var filename = $('#filename').val();

        function saveAsPDF() {
            var printContents = document.getElementById('printableArea').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
        }
    </script>

    <script>
        $(document).ready(function() {
            $("#filter").click(function() {
                $("#show_filter").toggle();
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            callback();

            function callback() {
                var start_date = $(".startDate").val();
                var end_date = $(".endDate").val();

                $('.start_date').val(start_date);
                $('.end_date').val(end_date);
            }
        });
    </script>

    <script>
        $(document).ready(function() {
            var id1 = $('.nav-item .active').attr('href');
            $('.report').val(id1);

            $("ul.nav-pills > li > a").click(function() {
                var report = $(this).attr('href');
                $('.report').val(report);
            });
        });
    </script>
@endpush

@section('action-btn')
<div class="float-end">
    <a href="#" onclick="saveAsPDF()" class="btn btn-sm btn-primary me-1" data-bs-toggle="tooltip"
        title="{{ __('Print') }}" data-original-title="{{ __('Print') }}"><i class="ti ti-printer"></i></a>
</div>

    <div class="float-end me-2" id="filter">
        <button id="filter" class="btn btn-sm btn-primary"><i class="ti ti-filter"></i></button>
    </div>

@endsection

@section('content')
    <div class="mt-4">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="mt-2" id="multiCollapseExample1">
                    <div class="card" id="show_filter" style="display:none;">
                        <div class="card-body">
                            {{ Form::open(['route' => ['report.payables'], 'method' => 'GET', 'id' => 'report_payable_summary']) }}
                            <div class="row align-items-center justify-content-end">
                                <div class="col-xl-10">
                                    <div class="row">
                                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                            <div class="btn-box">
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                            <div class="btn-box">
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                            <div class="btn-box">
                                                {{ Form::label('start_date', __('Start Date'), ['class' => 'form-label']) }}
                                                {{ Form::date('start_date', $filter['startDateRange'], ['class' => 'startDate form-control']) }}
                                            </div>
                                        </div>

                                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                            <div class="btn-box">
                                                {{ Form::label('end_date', __('End Date'), ['class' => 'form-label']) }}
                                                {{ Form::date('end_date', $filter['endDateRange'], ['class' => 'endDate form-control']) }}
                                            </div>
                                        </div>
                                        <input type="hidden" name="report" class="report">
                                    </div>
                                </div>
                                <div class="col-auto mt-4">
                                    <div class="row">
                                        <div class="col-auto">
                                            <a href="#" class="btn btn-sm btn-primary"
                                                onclick="document.getElementById('report_payable_summary').submit(); return false;"
                                                data-bs-toggle="tooltip" title="{{ __('Apply') }}"
                                                data-original-title="{{ __('apply') }}">
                                                <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                                            </a>

                                            <a href="{{ route('report.payables') }}" class="btn btn-sm btn-danger "
                                                data-bs-toggle="tooltip" title="{{ __('Reset') }}"
                                                data-original-title="{{ __('Reset') }}">
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
    </div>

    <div class="row">
        <div class="col-12" id="invoice-container">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between w-100">
                        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="payable-tab1" data-bs-toggle="pill" href="#vendor_balance"
                                    role="tab" aria-controls="pills-vendor-balance"
                                    aria-selected="true">{{ __('Vendor Balance') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="payable-tab2" data-bs-toggle="pill" href="#payable_summary"
                                    role="tab" aria-controls="pills-payable-summary"
                                    aria-selected="false">{{ __('Payable Summary') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="payable-tab3" data-bs-toggle="pill" href="#payable_details"
                                    role="tab" aria-controls="pills-payable-details"
                                    aria-selected="false">{{ __('Payable Details') }}</a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card-body" id="printableArea">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="tab-content" id="myTabContent2">
                                <div class="tab-pane fade fade show active" id="vendor_balance" role="tabpanel"
                                    aria-labelledby="payable-tab1">
                                    <div class="table-responsive">

                                    <table class="table pc-dt-simple" id="report-vendor-balance">
                                        <thead>
                                            <tr>
                                                <th width="33%"> {{ __('Vendor Name') }}</th>
                                                <th width="33%"> {{ __('Billed Amount') }}</th>
                                                <th width="33%"> {{ __('Available Debit') }}</th>
                                                <th class="text-end"> {{ __('Closing Balance') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $mergedArray = [];
                                                foreach ($payableVendors as $item) {
                                                    $name = $item['name'];

                                                    if (!isset($mergedArray[$name])) {
                                                        $mergedArray[$name] = [
                                                            'name' => $name,
                                                            'price' => 0.0,
                                                            'pay_price' => 0.0,
                                                            'total_tax' => 0.0,
                                                            'debit_price' => 0.0,
                                                        ];
                                                    }

                                                    $mergedArray[$name]['price'] += floatval($item['price']);
                                                    if ($item['pay_price'] !== null) {
                                                        $mergedArray[$name]['pay_price'] += floatval($item['pay_price']);
                                                    }
                                                    $mergedArray[$name]['total_tax'] += floatval($item['total_tax']);
                                                    $mergedArray[$name]['debit_price'] += floatval($item['debit_price']);
                                                }
                                                $resultArray = array_values($mergedArray);
                                                $total = 0;
                                            @endphp
                                            @foreach ($resultArray as $receivableCustomer)
                                                <tr>
                                                    @php
                                                        $customerBalance = $receivableCustomer['price'] + $receivableCustomer['total_tax'] - $receivableCustomer['pay_price'];
                                                        $balance = $customerBalance - $receivableCustomer['debit_price'];
                                                        $total += $balance;
                                                    @endphp
                                                    <td> {{ $receivableCustomer['name'] }}</td>
                                                    <td> {{ \Auth::user()->priceFormat($customerBalance) }} </td>
                                                    <td> {{ !empty($receivableCustomer['debit_price']) ? \Auth::user()->priceFormat($receivableCustomer['debit_price']) : \Auth::user()->priceFormat(0) }}
                                                    </td>
                                                    <td class="text-end"> {{ \Auth::user()->priceFormat($balance) }} </td>
                                                </tr>
                                            @endforeach
                                            @if ($payableVendors != [])
                                                <tr>
                                                    <th>{{ __('Total') }}</th>
                                                    <td></td>
                                                    <td></td>
                                                    <th class="text-end">{{ \Auth::user()->priceFormat($total) }}</th>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                                </div>
                                <div class="tab-pane fade fade show" id="payable_summary" role="tabpanel"
                                    aria-labelledby="payable-tab2">
                                    <div class="table-responsive">

                                    <table class="table pc-dt-simple" id="report-payable-summary">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Vendor Name') }}</th>
                                                <th>{{ __('Date') }}</th>
                                                <th>{{ __('Transaction') }}</th>
                                                <th>{{ __('Status') }}</th>
                                                <th>{{ __('Transaction Type') }}</th>
                                                <th>{{ __('Total') }}</th>
                                                <th>{{ __('Balance') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $total = 0;
                                                $totalAmount = 0;

                                                function compare($a, $b)
                                                {
                                                    return strtotime($b['bill_date']) - strtotime($a['bill_date']);
                                                }
                                                usort($payableSummaries, 'compare');
                                            @endphp
                                            @foreach ($payableSummaries as $payableSummary)
                                                <tr>
                                                    @php
                                                        if ($payableSummary['bill']) {
                                                            $payableBalance = $payableSummary['price'] + $payableSummary['total_tax'];
                                                        } else {
                                                            $payableBalance = -$payableSummary['price'];
                                                        }
                                                        $pay_price = ($payableSummary['pay_price'] != null) ? $payableSummary['pay_price'] : 0;
                                                        $balance = $payableBalance - $pay_price;
                                                        $total += $balance;
                                                        $totalAmount += $payableBalance;
                                                    @endphp
                                                    <td> {{ $payableSummary['name'] }}</td>
                                                    <td> {{ $payableSummary['bill_date'] }}</td>
                                                    @if ($payableSummary['bill'])
                                                        @if ($payableSummary['type'] == 'Bill')
                                                            <td> {{ \Auth::user()->billNumberFormat($payableSummary['bill']) }}
                                                            </td>
                                                        @elseif($payableSummary['type'] == 'Expense')
                                                            <td> {{ \Auth::user()->expenseNumberFormat($payableSummary['bill']) }}
                                                            </td>
                                                        @endif
                                                        @else
                                                        <td>{{ __('Debit Note') }}</td>
                                                    @endif
                                                    </td>
                                                    <td>
                                                        @if ($payableSummary['status'] == 0)
                                                            <span
                                                                class="status_badge badge bg-secondary p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$payableSummary['status']]) }}</span>
                                                        @elseif($payableSummary['status'] == 1)
                                                            <span
                                                                class="status_badge badge bg-warning p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$payableSummary['status']]) }}</span>
                                                        @elseif($payableSummary['status'] == 2)
                                                            <span
                                                                class="status_badge badge bg-danger p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$payableSummary['status']]) }}</span>
                                                        @elseif($payableSummary['status'] == 3)
                                                            <span
                                                                class="status_badge badge bg-info p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$payableSummary['status']]) }}</span>
                                                        @elseif($payableSummary['status'] == 4)
                                                            <span
                                                                class="status_badge badge bg-primary p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$payableSummary['status']]) }}</span>
                                                        @else
                                                            <span class="p-2 px-3">-</span>
                                                        @endif
                                                    </td>
                                                    @if ($payableSummary['bill'])
                                                        <td> {{ $payableSummary['type'] }}
                                                        @else
                                                        <td>{{ __('Debit Note') }}</td>
                                                    @endif
                                                    <td> {{ \Auth::user()->priceFormat($payableBalance) }} </td>

                                                    <td> {{ \Auth::user()->priceFormat($balance) }} </td>

                                                    </td>
                                                </tr>
                                            @endforeach
                                            @if ($payableSummaries != [])
                                                <tr>
                                                    <th>{{ __('Total') }}</th>
                                                    <th></th>
                                                    <th></th>
                                                    <th></th>
                                                    <th></th>
                                                    <th>{{ \Auth::user()->priceFormat($totalAmount) }}</th>
                                                    <th>{{ \Auth::user()->priceFormat($total) }}</th>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                                </div>

                                <div class="tab-pane fade fade show" id="payable_details" role="tabpanel"
                                    aria-labelledby="payable-tab3">
                                    <div class="table-responsive">

                                    <table class="table pc-dt-simple" id="report-payable-details">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Vendor Name') }}</th>
                                                <th>{{ __('Date') }}</th>
                                                <th>{{ __('Transaction') }}</th>
                                                <th>{{ __('Status') }}</th>
                                                <th>{{ __('Transaction Type') }}</th>
                                                <th>{{ __('Item Name') }}</th>
                                                <th>{{ __('Quantity Ordered') }}</th>
                                                <th>{{ __('Item Price') }}</th>
                                                <th>{{ __('Total') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $total = 0;
                                                $totalQuantity = 0;

                                                function compares($a, $b)
                                                {
                                                    return strtotime($b['bill_date']) - strtotime($a['bill_date']);
                                                }
                                                usort($payableDetails, 'compares');
                                            @endphp
                                            @foreach ($payableDetails as $payableDetail)
                                                <tr>
                                                    @php
                                                        if ($payableDetail['bill']) {
                                                            $receivableBalance = $payableDetail['price'];
                                                        } else {
                                                            $receivableBalance = -$payableDetail['price'];
                                                        }
                                                        if ($payableDetail['bill']) {
                                                            $quantity = $payableDetail['quantity'];
                                                        }
                                                        else {
                                                            $quantity = 0;
                                                        }

                                                        if ($payableDetail['bill']) {
                                                            $itemTotal = $receivableBalance * $payableDetail['quantity'];
                                                        } else {
                                                            $itemTotal = -$payableDetail['price'];
                                                        }
                                                        $total += $itemTotal;
                                                        $totalQuantity += $quantity;
                                                    @endphp
                                                    <td> {{ $payableDetail['name'] }}</td>
                                                    <td> {{ $payableDetail['bill_date'] }}</td>
                                                    @if ($payableDetail['bill'])
                                                        @if ($payableDetail['type'] == 'Bill')
                                                            <td> {{ \Auth::user()->billNumberFormat($payableDetail['bill']) }}
                                                            </td>
                                                        @elseif($payableDetail['type'] == 'Expense')
                                                            <td> {{ \Auth::user()->expenseNumberFormat($payableDetail['bill']) }}
                                                            </td>
                                                        @endif
                                                        @else
                                                        <td>{{ __('Debit Note') }}</td>
                                                    @endif
                                                    </td>
                                                    <td>
                                                        @if ($payableDetail['status'] == 0)
                                                            <span
                                                                class="status_badge badge bg-secondary p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$payableDetail['status']]) }}</span>
                                                        @elseif($payableDetail['status'] == 1)
                                                            <span
                                                                class="status_badge badge bg-warning p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$payableDetail['status']]) }}</span>
                                                        @elseif($payableDetail['status'] == 2)
                                                            <span
                                                                class="status_badge badge bg-danger p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$payableDetail['status']]) }}</span>
                                                        @elseif($payableDetail['status'] == 3)
                                                            <span
                                                                class="status_badge badge bg-info p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$payableDetail['status']]) }}</span>
                                                        @elseif($payableDetail['status'] == 4)
                                                            <span
                                                                class="status_badge badge bg-primary p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$payableDetail['status']]) }}</span>
                                                        @else
                                                            <span
                                                                class="p-2 px-3">-</span>
                                                        @endif
                                                    </td>
                                                    @if ($payableDetail['bill'])
                                                        <td> {{ $payableDetail['type'] }}
                                                        @else
                                                        <td>{{ __('Debit Note') }}</td>
                                                    @endif
                                                    <td>{{ $payableDetail['product_name'] }}</td>
                                                    <td> {{ $quantity }}</td>
                                                    <td>{{ \Auth::user()->priceFormat($receivableBalance) }}</td>
                                                    <td>{{ \Auth::user()->priceFormat($itemTotal) }}</td>

                                                </tr>
                                            @endforeach
                                            @if ($payableDetails != [])
                                                <tr>
                                                    <th>{{ __('Total') }}</th>
                                                    <th></th>
                                                    <th></th>
                                                    <th></th>
                                                    <th></th>
                                                    <th></th>
                                                    <th>{{ $totalQuantity }}</th>
                                                    <th></th>
                                                    <th>{{ \Auth::user()->priceFormat($total) }}</th>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
