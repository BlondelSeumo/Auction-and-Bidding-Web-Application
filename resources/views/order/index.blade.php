@extends('layouts.admin')

@section('page-title')
    {{ __('Orders') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Order') }}</li>
@endsection
@php
    $admin_payment_setting = Utility::getAdminPaymentSetting();
@endphp

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Order Id') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Plan Name') }}</th>
                                    <th>{{ __('Price') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Payment Type') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Coupon') }}</th>
                                    <th>{{ __('Invoice') }}</th>
                                    @if (\Auth::user()->type == 'super admin')
                                        <th>{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>

                            <tbody>
                                @php
                                    $path = \App\Models\Utility::get_file('uploads/order');
                                @endphp
                                @foreach ($orders as $key => $order)
                                    <tr>
                                        <td>{{ $order->order_id }}</td>
                                        <td>{{ $order->user_name }}</td>
                                        <td>{{ $order->plan_name }}</td>
                                        <td>{{ isset($admin_payment_setting['currency_symbol']) ? $admin_payment_setting['currency_symbol'] : '$' }}{{ number_format($order->price) }}
                                        </td>

                                        <td>
                                            @if ($order->payment_status == 'success' || $order->payment_status == 'Approved')
                                                <span
                                                    class="status_badge badge bg-primary p-2 px-3 rounded">{{ ucfirst($order->payment_status) }}</span>
                                            @elseif($order->payment_status == 'succeeded')
                                                <span
                                                    class="status_badge badge bg-primary p-2 px-3 rounded">{{ __('Success') }}</span>
                                            @elseif($order->payment_status == 'Pending')
                                                <span
                                                    class="status_badge badge bg-warning p-2 px-3 rounded">{{ __('Pending') }}</span>
                                            @else
                                                <span
                                                    class="status_badge badge bg-danger p-2 px-3 rounded">{{ ucfirst($order->payment_status) }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $order->payment_type }}</td>
                                        <td>{{ $order->created_at->format('d M Y') }}</td>
                                        <td class="text-center">
                                            {{ !empty($order->total_coupon_used) ? (!empty($order->total_coupon_used->coupon_detail) ? $order->total_coupon_used->coupon_detail->code : '-') : '-' }}
                                        </td>
                                        <td class="Id">
                                            @if ($order->payment_type == 'Manually')
                                                <p>{{ __('Manually plan upgraded by Super Admin') }}</p>
                                            @elseif($order->receipt == 'free coupon')
                                                <p>{{ __('Used 100 % discount coupon code.') }}</p>
                                            @elseif($order->payment_type == 'STRIPE')
                                                <a href="{{ $order->receipt }}" target="_blank">
                                                    <i class="ti ti-file-invoice"></i> {{ __('Receipt') }}
                                                </a>
                                            @elseif(!empty($order->receipt) && $order->payment_type == 'Bank Transfer')
                                                <a href="{{ $path . '/' . $order->receipt }}" target="_blank">
                                                    <i class="ti ti-file-invoice"></i> {{ __('Receipt') }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        @if (\Auth::user()->type == 'super admin')
                                            <td class="Action">
                                                @if ($order->payment_type == 'Bank Transfer' && $order->payment_status == 'Pending')
                                                    <span>
                                                        <div class="action-btn me-2">
                                                            <a href="#"
                                                                data-url="{{ URL::to('order/' . $order->id . '/action') }}"
                                                                data-size="lg" data-ajax-popup="true"
                                                                data-title="{{ __('Payment Status') }}"
                                                                class="btn btn-sm align-items-center bg-info"
                                                                data-bs-toggle="tooltip" title="{{ __('Payment Status') }}"
                                                                data-original-title="{{ __('Payment Status') }}">
                                                                <i class="ti ti-caret-right text-white"></i>
                                                            </a>
                                                        </div>
                                                    </span>
                                                @endif
                                                @php
                                                    $user = App\Models\User::find($order->user_id);
                                                @endphp
                                                <span>
                                                    <div class="action-btn me-2">
                                                        {!! Form::open([
                                                            'method' => 'DELETE',
                                                            'route' => ['order.destroy', $order->id],
                                                            'id' => 'delete-form-' . $order->id,
                                                        ]) !!}
                                                        <a href="#"
                                                            class="btn btn-sm align-items-center bs-pass-para bg-danger"
                                                            data-bs-toggle="tooltip" title="{{ __('Delete') }}"
                                                            data-original-title="{{ __('Delete') }}"
                                                            data-confirm="{{ __('Are You Sure?') . '|' . __('This action can not be undone. Do you want to continue?') }}"
                                                            data-confirm-yes="document.getElementById('delete-form-{{ $order->id }}').submit();">
                                                            <i class="ti ti-trash text-white"></i>
                                                        </a>
                                                        {!! Form::close() !!}
                                                    </div>
                                                    @foreach ($userOrders as $userOrder)
                                                        @if (
                                                            $user->plan == $order->plan_id &&
                                                                $order->order_id == $userOrder->order_id &&
                                                                $order->is_refund == 0 &&
                                                                !preg_match('/\bManually\b/i', $order->payment_type))
                                                            <div class="badge bg-warning p-2 px-3">
                                                                <a href="{{ route('order.refund', [$order->id, $order->user_id]) }}"
                                                                    class="align-items-center" data-bs-toggle="tooltip"
                                                                    title="{{ __('Refund') }}"
                                                                    data-original-title="{{ __('Refund') }}">
                                                                    <span class ="text-white">{{ __('Refund') }}</span>
                                                                </a>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </span>
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
