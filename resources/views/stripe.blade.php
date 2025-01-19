@extends('layouts.admin')
@push('script-page')
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script src="https://api.ravepay.co/flwv3-pug/getpaidx/api/flwpbf-inline.js"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="https://khalti.s3.ap-south-1.amazonaws.com/KPG/dist/2020.12.17.0.0.0/khalti-checkout.iffe.js"></script>
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>

    <script>
        var config = {
            "publicKey": "{{ isset($admin_payment_setting['khalti_public_key']) ? $admin_payment_setting['khalti_public_key'] : '' }}",
            "productIdentity": "1234567890",
            "productName": "demo",
            "productUrl": "{{env('APP_URL')}}",
            "paymentPreference": [
                "KHALTI",
                "EBANKING",
                "MOBILE_BANKING",
                "CONNECT_IPS",
                "SCT",
            ],
            "eventHandler": {
                onSuccess (payload) {
                    if(payload.status==200) {
                        $.ajaxSetup({
                                headers: {
                                    'X-CSRF-Token': '{{csrf_token()}}'
                                }
                            });
                        $.ajax({
                            url: '{{ route('plan.get.khalti.status') }}',
                            method: 'POST',
                            data : {
                                'payload' : payload,
                                'coupon_code' : $('.coupon_code').val(),
                                'plan_id' : $('.plan_id').val(),
                            },
                            beforeSend: function () {
                                $(".loader-wrapper").removeClass('d-none');
                            },
                            success: function(data) {
                                console.log(data);
                                $(".loader-wrapper").addClass('d-none');
                                if(data.status_code === 200){
                                    show_toastr('Success','Payment Done Successfully', 'success');
                                    setTimeout(() => {
                                        // location.reload();
                            location .href = '{{route('plans.index')}}';

                                    }, 2000);
                                }
                                else{
                                    show_toastr('Error','Payment Failed', 'error');
                                }
                            },
                            error: function(err) {
                                show_toastr('Error', err.response, 'error')
                            },
                        });
                    }
                },
                onError (error) {
                    show_toastr('Error', error, 'error')
                },
                onClose () {
                }
            }

        };

        var checkout = new KhaltiCheckout(config);
        var btn = document.getElementsByClassName("payment-btn")[0];
    </script>

    <script>
          $(document).on("click", ".payment-btn", function(event) {
                event.preventDefault()
                get_khalti_status();
        })

        function get_khalti_status(){
            var coupon_code = $('.coupon_code').val();
            var plan_id = $('.plan_id').val();

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.ajax({
                url: '{{ route('plan.pay.with.khalti') }}',
                method: 'POST',
                data : {
                    'coupon_code' : coupon_code,
                    'plan_id' : plan_id,
                },

                beforeSend: function () {
                    $(".loader-wrapper").removeClass('d-none');
                },
                success: function (data) {
                    $(".loader-wrapper").addClass('d-none');
                    if(data == 0)
                    {
                        toastrs('Success','Payment Done Successfully', 'success');
                        setTimeout(() => {
                            // location.reload();
                            location .href = '{{route('plans.index')}}';
                        }, 2000);
                    }
                    else
                    {
                        let price = data*100;
                        checkout.show({amount: price});
                    }
                }
            });
        }
    </script>
    <script type="text/javascript">
        @if (
            $plan->price > 0.0 &&
                $admin_payment_setting['is_stripe_enabled'] == 'on' &&
                !empty($admin_payment_setting['stripe_key']) &&
                !empty($admin_payment_setting['stripe_secret']))
            var stripe = Stripe('{{ $admin_payment_setting['stripe_key'] }}');
            var elements = stripe.elements();

            // Custom styling can be passed to options when creating an Element.
            var style = {
                base: {
                    // Add your base input styles here. For example:
                    fontSize: '14px',
                    color: '#32325d',
                },
            };

            // Create an instance of the card Element.
            var card = elements.create('card', {
                style: style,
            });

            // Add an instance of the card Element into the `card-element` <div>.
            card.mount('#card-element');

            // Create a token or display an error when the form is submitted.
            var form = document.getElementById('payment-form');
            form.addEventListener('submit', function(event) {
                event.preventDefault();

                stripe.createToken(card).then(function(result) {
                    if (result.error) {
                        $("#card-errors").html(result.error.message);
                        show_toastr('Error', result.error.message, 'error');
                    } else {
                        // Send the token to your server.
                        stripeTokenHandler(result.token);
                    }
                });
            });

            function stripeTokenHandler(token) {
                // Insert the token ID into the form so it gets submitted to the server
                var form = document.getElementById('payment-form');
                var hiddenInput = document.createElement('input');
                hiddenInput.setAttribute('type', 'hidden');
                hiddenInput.setAttribute('name', 'stripeToken');
                hiddenInput.setAttribute('value', token.id);
                form.appendChild(hiddenInput);

                // Submit the form
                form.submit();
            }
        @endif

        $(document).ready(function() {
            $(document).on('click', '.apply-coupon', function() {
                // alert('hello')

                var ele = $(this);
                var coupon = ele.closest('.row').find('.coupon').val();

                $.ajax({
                    url: '{{ route('apply.coupon') }}',
                    datType: 'json',
                    data: {
                        plan_id: '{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}',
                        coupon: coupon
                    },
                    success: function(data) {
                        $('.final-price').text(data.final_price);
                        $('#stripe_coupon, #paypal_coupon').val(coupon);
                        if (data != '') {
                            if (data.is_success == true) {
                                show_toastr('success', data.message, 'success');
                            } else {
                                show_toastr('Error', data.message, 'error');
                            }

                        } else {
                            show_toastr('Error', "{{ __('Coupon code required.') }}", 'error');
                        }
                    }
                })
            });
        });
        @if (isset($admin_payment_setting['is_paystack_enabled']) && $admin_payment_setting['is_paystack_enabled'] == 'on')
            $(document).on("click", "#pay_with_paystack", function() {
                $('#paystack-payment-form').ajaxForm(function(res) {
                    if (res.flag == 1) {
                        var paystack_callback = "{{ url('/plan/paystack') }}";
                        var order_id = '{{ time() }}';
                        var coupon_id = res.coupon;
                        var handler = PaystackPop.setup({
                            key: '{{ $admin_payment_setting['paystack_public_key'] }}',
                            email: res.email,
                            amount: res.total_price * 100,
                            currency: res.currency,
                            ref: 'pay_ref_id' + Math.floor((Math.random() * 1000000000) +
                                1
                            ), // generates a pseudo-unique reference. Please replace with a reference you generated. Or remove the line entirely so our API will generate one for you
                            metadata: {
                                custom_fields: [{
                                    display_name: "Email",
                                    variable_name: "email",
                                    value: res.email,
                                }]
                            },

                            callback: function(response) {
                                console.log(response.reference, order_id);
                                window.location.href = paystack_callback + '/' + response
                                    .reference + '/' + '{{ encrypt($plan->id) }}' +
                                    '?coupon_id=' + coupon_id
                            },
                            onClose: function() {
                                alert('window closed');
                            }
                        });
                        handler.openIframe();
                    } else if (res.flag == 2) {

                    } else {
                        show_toastr('Error', data.message, 'msg');
                    }

                }).submit();
            });
        @endif
        //    Flaterwave Payment

        @if (isset($admin_payment_setting['is_flutterwave_enabled']) && $admin_payment_setting['is_flutterwave_enabled'] == 'on')
            $(document).on("click", "#pay_with_flaterwave", function() {

                $('#flaterwave-payment-form').ajaxForm(function(res) {

                    if (res.flag == 1) {
                        var coupon_id = res.coupon;
                        var API_publicKey = '{{ $admin_payment_setting['flutterwave_public_key'] }}';
                        var nowTim = "{{ date('d-m-Y-h-i-a') }}";
                        var flutter_callback = "{{ url('/plan/flaterwave') }}";
                        var x = getpaidSetup({
                            PBFPubKey: API_publicKey,
                            customer_email: '{{ Auth::user()->email }}',
                            amount: res.total_price,
                            currency: '{{ $admin_payment_setting['currency'] }}',
                            txref: nowTim + '__' + Math.floor((Math.random() * 1000000000)) +
                                'fluttpay_online-' + {{ date('Y-m-d') }},
                            meta: [{
                                metaname: "payment_id",
                                metavalue: "id"
                            }],
                            onclose: function() {},
                            callback: function(response) {
                                var txref = response.tx.txRef;
                                if (
                                    response.tx.chargeResponseCode == "00" ||
                                    response.tx.chargeResponseCode == "0"
                                ) {
                                    window.location.href = flutter_callback + '/' + txref +
                                        '/' +
                                        '{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}?coupon_id=' +
                                        coupon_id;
                                } else {
                                    // redirect to a failure page.
                                }
                                x
                                    .close(); // use this to close the modal immediately after payment.
                            }
                        });
                    } else if (res.flag == 2) {

                    } else {
                        show_toastr('Error', data.message, 'msg');
                    }

                }).submit();
            });
        @endif
        // Razorpay Payment
        @if (isset($admin_payment_setting['is_razorpay_enabled']) && $admin_payment_setting['is_razorpay_enabled'] == 'on')
            $(document).on("click", "#pay_with_razorpay", function() {
                $('#razorpay-payment-form').ajaxForm(function(res) {
                    if (res.flag == 1) {

                        var razorPay_callback = '{{ url('/plan/razorpay') }}';
                        var totalAmount = res.total_price * 100;
                        var coupon_id = res.coupon;
                        var options = {
                            "key": "{{ $admin_payment_setting['razorpay_public_key'] }}", // your Razorpay Key Id
                            "amount": totalAmount,
                            "name": 'Plan',
                            "currency": '{{ $admin_payment_setting['currency'] }}',
                            "description": "",
                            "handler": function(response) {
                                window.location.href = razorPay_callback + '/' + response
                                    .razorpay_payment_id + '/' +
                                    '{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}?coupon_id=' +
                                    coupon_id;
                            },
                            "theme": {
                                "color": "#528FF0"
                            }
                        };
                        var rzp1 = new Razorpay(options);
                        rzp1.open();
                    } else if (res.flag == 2) {

                    } else {
                        show_toastr('Error', data.message, 'msg');
                    }

                }).submit();
            });
        @endif

        @if (
            $admin_payment_setting['is_payfast_enabled'] == 'on' &&
                !empty($admin_payment_setting['payfast_merchant_id']) &&
                !empty($admin_payment_setting['payfast_merchant_key']))
            $(document).ready(function() {
                get_payfast_status(amount = 0, coupon = null);
            })

            function get_payfast_status(amount, coupon) {
                var plan_id = $('#plan_id').val();

                $.ajax({
                    url: '{{ route('payfast.payment') }}',
                    method: 'POST',
                    data: {
                        'plan_id': plan_id,
                        'coupon_amount': amount,
                        'coupon_code': coupon
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(data) {
                        if (data.success == true) {
                            $('#get-payfast-inputs').append(data.inputs);

                        } else {
                            show_toastr('Error', data.inputs, 'error')
                        }
                    }
                });
            }
        @endif
    </script>
    <script>
        var scrollSpy = new bootstrap.ScrollSpy(document.body, {
            target: '#useradd-sidenav',
            offset: 300,
        })
        $(".list-group-item").click(function() {
            $('.list-group-item').filter(function() {
                return this.href == id;
            }).parent().removeClass('text-primary');
        });
    </script>
@endpush

@push('css-page')
    <style>
        #card-element {
            border: 1px solid #a3afbb !important;
            border-radius: 10px !important;
            padding: 10px !important;
        }
    </style>
@endpush

@php
    $dir = asset(Storage::url('uploads/plan'));
    $dir_payment = asset(Storage::url('uploads/payments'));
@endphp
@section('page-title')
    {{ __('Manage Order Summary') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('plans.index') }}">{{ __('Plan') }}</a></li>
    <li class="breadcrumb-item">{{ __('Order Summary') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="row">
                <div class="col-xl-3">
                    <div class="sticky-top" style="top:30px">
                        <div class="mt-5">
                            <div class="card price-card price-1 wow animate__fadeInUp" data-wow-delay="0.2s"
                                style="
                                                                        visibility: visible;
                                                                        animation-delay: 0.2s;
                                                                        animation-name: fadeInUp;
                                                                      ">
                                <div class="card-body">
                                    <span class="price-badge bg-primary">{{ $plan->name }}</span>
                                    <h3 class="mb-4 f-w-600  ">
                                        {{ isset($admin_payment_setting['currency_symbol']) ? $admin_payment_setting['currency_symbol'] : '$' }}{{ $plan->price . ' / ' . __(\App\Models\Plan::$arrDuration[$plan->duration]) }}</small>
                                        </small>
                                    </h3>
                                    <ul class="list-unstyled my-5 mt-3">
                                        <li>
                                            <span class="theme-avtar"><i class="text-primary ti ti-circle-plus"></i></span>
                                            {{ $plan->max_users == -1 ? __('Unlimited') : $plan->max_users }}
                                            {{ __('Users') }}
                                        </li>
                                        <li>
                                            <span class="theme-avtar"><i class="text-primary ti ti-circle-plus"></i></span>
                                            {{ $plan->max_customers == -1 ? __('Unlimited') : $plan->max_customers }}
                                            {{ __('Customers') }}
                                        </li>
                                        <li>
                                            <span class="theme-avtar"><i class="text-primary ti ti-circle-plus"></i></span>
                                            {{ $plan->max_venders == -1 ? __('Unlimited') : $plan->max_venders }}
                                            {{ __('Vendors') }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card ">
                            <div class="list-group list-group-flush" id="useradd-sidenav">
                                @if ($admin_payment_setting['is_manually_payment_enabled'] == 'on')
                                    <a href="#send_request"
                                        class="list-group-item list-group-item-action border-0 active">{{ __('Manually') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if ($admin_payment_setting['is_bank_transfer_enabled'] == 'on' && !empty($admin_payment_setting['bank_details']))
                                    <a href="#bank_payment"
                                        class="list-group-item list-group-item-action border-0 ">{{ __('Bank Transfer') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (
                                    $admin_payment_setting['is_stripe_enabled'] == 'on' &&
                                        !empty($admin_payment_setting['stripe_key']) &&
                                        !empty($admin_payment_setting['stripe_secret']))
                                    <a href="#stripe_payment"
                                        class="list-group-item list-group-item-action border-0 ">{{ __('Stripe') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif

                                @if (
                                    $admin_payment_setting['is_paypal_enabled'] == 'on' &&
                                        !empty($admin_payment_setting['paypal_client_id']) &&
                                        !empty($admin_payment_setting['paypal_secret_key']))
                                    <a href="#paypal_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Paypal') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif

                                @if (
                                    $admin_payment_setting['is_paystack_enabled'] == 'on' &&
                                        !empty($admin_payment_setting['paystack_public_key']) &&
                                        !empty($admin_payment_setting['paystack_secret_key']))
                                    <a href="#paystack_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Paystack') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif

                                @if (isset($admin_payment_setting['is_flutterwave_enabled']) && $admin_payment_setting['is_flutterwave_enabled'] == 'on')
                                    <a href="#flutterwave_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Flutterwave') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif

                                @if (isset($admin_payment_setting['is_razorpay_enabled']) && $admin_payment_setting['is_razorpay_enabled'] == 'on')
                                    <a href="#razorpay_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Razorpay') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif

                                @if (isset($admin_payment_setting['is_mercado_enabled']) && $admin_payment_setting['is_mercado_enabled'] == 'on')
                                    <a href="#mercado_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Mercado Pago') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif

                                @if (isset($admin_payment_setting['is_paytm_enabled']) && $admin_payment_setting['is_paytm_enabled'] == 'on')
                                    <a href="#paytm_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Paytm') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif

                                @if (isset($admin_payment_setting['is_mollie_enabled']) && $admin_payment_setting['is_mollie_enabled'] == 'on')
                                    <a href="#mollie_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Mollie') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif

                                @if (isset($admin_payment_setting['is_skrill_enabled']) && $admin_payment_setting['is_skrill_enabled'] == 'on')
                                    <a href="#skrill_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Skrill') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif

                                @if (isset($admin_payment_setting['is_coingate_enabled']) && $admin_payment_setting['is_coingate_enabled'] == 'on')
                                    <a href="#coingate_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Coingate') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif

                                @if (isset($admin_payment_setting['is_paymentwall_enabled']) && $admin_payment_setting['is_paymentwall_enabled'] == 'on')
                                    <a href="#paymentwall_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Paymentwall') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_toyyibpay_enabled']) && $admin_payment_setting['is_toyyibpay_enabled'] == 'on')
                                    <a href="#toyyibpay-payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Toyyibpay') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_payfast_enabled']) && $admin_payment_setting['is_payfast_enabled'] == 'on')
                                    <a href="#payfast-payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('PayFast') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_iyzipay_enabled']) && $admin_payment_setting['is_iyzipay_enabled'] == 'on')
                                    <a href="#iyzipay-payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Iyzipay') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_sspay_enabled']) && $admin_payment_setting['is_sspay_enabled'] == 'on')
                                    <a href="#sspay-payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('SSPay') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif

                                @if (isset($admin_payment_setting['is_paytab_enabled']) && $admin_payment_setting['is_paytab_enabled'] == 'on')
                                    <a href="#paytab_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Paytab') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_benefit_enabled']) && $admin_payment_setting['is_benefit_enabled'] == 'on')
                                    <a href="#benefit_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Benefit') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_cashfree_enabled']) && $admin_payment_setting['is_cashfree_enabled'] == 'on')
                                    <a href="#cashfree_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Cashfree') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_aamarpay_enabled']) && $admin_payment_setting['is_aamarpay_enabled'] == 'on')
                                    <a href="#aamarpay_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('AamarPay') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_paytr_enabled']) && $admin_payment_setting['is_paytr_enabled'] == 'on')
                                    <a href="#paytr_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('PayTR') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_yookassa_enabled']) && $admin_payment_setting['is_yookassa_enabled'] == 'on')
                                    <a href="#yookassa_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Yookassa') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_midtrans_enabled']) && $admin_payment_setting['is_midtrans_enabled'] == 'on')
                                    <a href="#midtrans_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Midtrans') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_xendit_enabled']) && $admin_payment_setting['is_xendit_enabled'] == 'on')
                                    <a href="#xendit_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Xendit') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_nepalste_enabled']) && $admin_payment_setting['is_nepalste_enabled'] == 'on')
                                    <a href="#nepalste_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Nepalste') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_paiementpro_enabled']) && $admin_payment_setting['is_paiementpro_enabled'] == 'on')
                                    <a href="#paiementpro_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Paiement Pro') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_cinetpay_enabled']) && $admin_payment_setting['is_cinetpay_enabled'] == 'on')
                                    <a href="#cinetpay_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Cinetpay') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_fedapay_enabled']) && $admin_payment_setting['is_fedapay_enabled'] == 'on')
                                    <a href="#fedapay_payment"
                                        class="list-group-item list-group-item-action border-0">{{ __('Fedapay') }}
                                        <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                    </a>
                                @endif
                                @if (isset($admin_payment_setting['is_payhere_enabled']) && $admin_payment_setting['is_payhere_enabled'] == 'on')
                                <a href="#payhere_payment"
                                    class="list-group-item list-group-item-action border-0">{{ __('PayHere') }}
                                    <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                </a>
                                @endif
                                @if (isset($admin_payment_setting['tap_payment_is_on']) && $admin_payment_setting['tap_payment_is_on'] == 'on')
                                <a href="#tap_payment"
                                    class="list-group-item list-group-item-action border-0">{{ __('Tap') }}
                                    <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                </a>
                                @endif
                                @if (isset($admin_payment_setting['authorizenet_payment_is_on']) && $admin_payment_setting['authorizenet_payment_is_on'] == 'on')
                                <a href="#authorizenet_payment"
                                    class="list-group-item list-group-item-action border-0">{{ __('AuthorizeNet') }}
                                    <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                </a>
                                @endif
                                @if (isset($admin_payment_setting['khalti_payment_is_on']) && $admin_payment_setting['khalti_payment_is_on'] == 'on')
                                <a href="#khalti_payment"
                                    class="list-group-item list-group-item-action border-0">{{ __('Khalti') }}
                                    <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                </a>
                                @endif
                                @if (isset($admin_payment_setting['easebuzz_payment_is_on']) && $admin_payment_setting['easebuzz_payment_is_on'] == 'on')
                                <a href="#easebuzz_payment"
                                    class="list-group-item list-group-item-action border-0">{{ __('Easebuzz') }}
                                    <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                </a>
                                @endif
                                @if (isset($admin_payment_setting['ozow_payment_is_enabled']) && $admin_payment_setting['ozow_payment_is_enabled'] == 'on')
                                <a href="#ozow_payment"
                                    class="list-group-item list-group-item-action border-0">{{ __('Ozow') }}
                                    <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-9">

                    {{-- Manually --}}
                    @if ($admin_payment_setting['is_manually_payment_enabled'] == 'on')
                        <div id="send_request" class="card">
                            <div class="card-header">
                                <h5>{{ __('Manually') }}</h5>
                            </div>
                            <div class="tab-pane {{ ($admin_payment_setting['is_manually_payment_enabled'] == 'on') == 'on' ? 'active' : '' }}"
                                id="send_request">
                                <div class="border p-3 rounded send-request-div">
                                    <p>{{ __('Requesting manual payment for the planned amount for the subscriptions plan.') }}
                                    </p>
                                </div>

                                <div class="col-sm-12 my-2 px-2">
                                    <div class="text-end">
                                        @if ($plan->id != 1 && $plan->id != \Auth::user()->plan)
                                            @if (\Auth::user()->requested_plan != $plan->id)
                                                <a href="{{ route('send.request', [\Illuminate\Support\Facades\Crypt::encrypt($plan->id)]) }}"
                                                    class="btn btn-primary mb-2 me-3"
                                                    data-title="{{ __('Send Request') }}" data-bs-toggle="tooltip"
                                                    title="{{ __('Send Request') }}">
                                                    <span class="btn-inner--icon">{{ __('Send Request') }}</span>
                                                </a>
                                            @else
                                                <a href="{{ route('request.cancel', \Auth::user()->id) }}"
                                                    class="btn btn-danger mb-2 me-3"
                                                    data-title="{{ __('Cancle Request') }}" data-bs-toggle="tooltip"
                                                    title="{{ __('Cancle Request') }}">
                                                    <span class="btn-inner--icon">{{ __('Cancle Request') }}</span>
                                                </a>
                                            @endif
                                        @endif
                                    </div>
                                </div>

                            </div>
                        </div>
                    @endif

                    {{-- Bank Transfer --}}
                    @if ($admin_payment_setting['is_bank_transfer_enabled'] == 'on' && !empty($admin_payment_setting['bank_details']))
                        <div id="bank_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Bank Transfer') }}</h5>
                            </div>
                            <div class="tab-pane {{ ($admin_payment_setting['is_bank_transfer_enabled'] == 'on' && !empty($admin_payment_setting['bank_details'])) == 'on' ? 'active' : '' }}"
                                id="bank_payment">
                                <form role="form" action="{{ route('plan.pay.with.bank') }}" method="post"
                                    class="require-validation" id="bank-payment-form" enctype = "multipart/form-data">
                                    @csrf
                                    <div class="border p-3 rounded bank-payment-div">
                                        <div class="row">
                                            <div class="col-6 ">
                                                <div class="custom-radio">
                                                    <label class="font-16 font-bold">{{ __('Bank Details') }} :</label>
                                                </div>
                                                <p class="mb-0 pt-1 text-sm">
                                                    {!! $admin_payment_setting['bank_details'] !!}
                                                </p>
                                            </div>
                                            <div class="col-6">
                                                {{ Form::label('payment_receipt', __('Payment Receipt'), ['class' => 'form-label']) }}
                                                <div class="choose-file form-group">
                                                    <input type="file" name="payment_receipt" id="image"
                                                        class="form-control">
                                                    <p class="upload_file"></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mt-2">
                                            <div class="col-md-12">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="bank_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="bank_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>
                                                    <div class="form-group ms-3 mt-4">
                                                        <a href="#" class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-6 ">
                                                <div class="custom-radio">
                                                    <label class="font-16 font-bold">{{ __('Plan Price') }} :</label>
                                                    {{ isset($admin_payment_setting['currency_symbol']) ? $admin_payment_setting['currency_symbol'] : '$' }}{{ $plan->price }}</small>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="custom-radio">
                                                    <label class="font-16 font-bold">{{ __('Net Amount') }} : </label>
                                                    <span
                                                        class="final-price">{{ isset($admin_payment_setting['currency_symbol']) ? $admin_payment_setting['currency_symbol'] : '$' }}{{ $plan->price }}</span>
                                                </div>
                                                (<small class="">{{ __('After coupon apply') }}</small>)
                                            </div>

                                        </div>
                                    </div>
                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <input type="submit" value="{{ __('Pay Now') }}"
                                                class="btn btn-primary mb-2 me-3">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif


                    {{-- stripe payment --}}
                    @if (
                        $admin_payment_setting['is_stripe_enabled'] == 'on' &&
                            !empty($admin_payment_setting['stripe_key']) &&
                            !empty($admin_payment_setting['stripe_secret']))
                        <div id="stripe_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Stripe') }}</h5>
                            </div>
                            <div class="tab-pane {{ ($admin_payment_setting['is_stripe_enabled'] == 'on' && !empty($admin_payment_setting['stripe_key']) && !empty($admin_payment_setting['stripe_secret'])) == 'on' ? 'active' : '' }}"
                                id="stripe_payment">
                                <form role="form" action="{{ route('stripe.post') }}" method="post"
                                    class="require-validation" id="payment-form">
                                    @csrf
                                    <div class="border p-3 rounded stripe-payment-div">
                                        <div class="row">
                                            <div class="col-sm-8">
                                                <div class="custom-radio">
                                                    <label
                                                        class="font-16 font-weight-bold">{{ __('Credit / Debit Card') }}</label>
                                                </div>
                                                <p class="mb-0 pt-1 text-sm">
                                                    {{ __('Safe money transfer using your bank account. We support Mastercard, Visa, Discover and American express.') }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="card-name-on"
                                                        class="form-label text-dark">{{ __('Name on card') }}</label>
                                                    <input type="text" name="name" id="card-name-on"
                                                        class="form-control required"
                                                        placeholder="{{ \Auth::user()->name }}">
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div id="card-element"></div>
                                                <div id="card-errors" role="alert"></div>
                                            </div>
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="stripe_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="stripe_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>
                                                    <div class="form-group ms-3 mt-4">
                                                        <a href="#" class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-12">
                                                <div class="error" style="display: none;">
                                                    <div class='alert-danger alert'>
                                                        {{ __('Please correct the errors and try again.') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <input type="submit" value="{{ __('Pay Now') }}"
                                                class="btn btn-primary mb-2 me-3">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    {{-- paypal end --}}
                    @if (
                        $admin_payment_setting['is_paypal_enabled'] == 'on' &&
                            !empty($admin_payment_setting['paypal_client_id']) &&
                            !empty($admin_payment_setting['paypal_secret_key']))
                        <div id="paypal_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Paypal') }}</h5>
                            </div>
                            <div class="tab-pane {{ ($admin_payment_setting['is_stripe_enabled'] != 'on' && $admin_payment_setting['is_paypal_enabled'] == 'on' && !empty($admin_payment_setting['paypal_client_id']) && !empty($admin_payment_setting['paypal_secret_key'])) == 'on' ? 'active' : '' }}"
                                id="paypal_payment">
                                <form class="w3-container w3-display-middle w3-card-4" method="POST" id="payment-form"
                                    action="{{ route('plan.pay.with.paypal') }}">
                                    @csrf
                                    <input type="hidden" name="plan_id"
                                        value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="paypal_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="paypal_coupon" name="coupon"
                                                            class="form-control coupon"placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="submit" value="{{ __('Pay Now') }}"
                                                class="btn btn-primary mb-2 me-3">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    {{-- Paystack --}}
                    @if (isset($admin_payment_setting['is_paystack_enabled']) && $admin_payment_setting['is_paystack_enabled'] == 'on')
                        <div id="paystack_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Paystack') }}</h5>
                            </div>
                            <div id="paystack-payment" class="tabs-card">
                                <div class="">
                                    <form class="w3-container w3-display-middle w3-card-4" method="POST"
                                        id="paystack-payment-form" action="{{ route('plan.pay.with.paystack') }}">
                                        @csrf
                                        <input type="hidden" name="plan_id"
                                            value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">

                                        <div class="border p-3 mb-3 rounded">
                                            <div class="row">
                                                <div class="col-md-12 mt-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="form-group w-100">
                                                            <label for="paystack_coupon"
                                                                class="form-label">{{ __('Coupon') }}</label>
                                                            <input type="text" id="paystack_coupon" name="coupon"
                                                                class="form-control coupon"
                                                                placeholder="{{ __('Enter Coupon Code') }}">
                                                        </div>

                                                        <div class="form-group ms-3 mt-4">
                                                            <a class="text-muted " data-bs-toggle="tooltip"
                                                                title="{{ __('Apply') }}">
                                                                <i class="ti ti-square-check btn-apply apply-coupon"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 text-right paymentwall-coupon-tr"
                                                        style="display: none">
                                                        <b>{{ __('Coupon Discount') }}</b> : <b
                                                            class="paymentwall-coupon-price"></b>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-sm-12 my-2 px-2">
                                            <div class="text-end">
                                                <input type="button" id="pay_with_paystack"
                                                    value="{{ __('Pay Now') }}" class="btn btn-primary mb-2 me-3">
                                            </div>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Flutterwave --}}
                    @if (isset($admin_payment_setting['is_flutterwave_enabled']) && $admin_payment_setting['is_flutterwave_enabled'] == 'on')
                        <div id="flutterwave_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Flutterwave') }}</h5>
                            </div>
                            <div class="tab-pane " id="flutterwave_payment">
                                <form class="w3-container w3-display-middle w3-card-4" method="POST"
                                    id="flaterwave-payment-form" action="{{ route('plan.pay.with.flaterwave') }}">
                                    @csrf
                                    <input type="hidden" name="plan_id"
                                        value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="flutterwave_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="flutterwave_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>
                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input id="pay_with_flaterwave" type="button" value="{{ __('Pay Now') }}"
                                                class="btn btn-primary mb-2 me-3">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    {{-- Razorpay --}}
                    @if (isset($admin_payment_setting['is_razorpay_enabled']) && $admin_payment_setting['is_razorpay_enabled'] == 'on')
                        <div id="razorpay_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Razorpay') }} </h5>
                            </div>
                            <div class="tab-pane " id="razorpay_payment">
                                <form class="w3-container w3-display-middle w3-card-4" method="POST"
                                    id="razorpay-payment-form" action="{{ route('plan.pay.with.razorpay') }}">
                                    @csrf
                                    <input type="hidden" name="plan_id"
                                        value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="razorpay_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="razorpay_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="button" id="pay_with_razorpay" value="{{ __('Pay Now') }}"
                                                class="btn btn-primary mb-2 me-3">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif


                    {{-- Mercado Pago --}}
                    @if (isset($admin_payment_setting['is_mercado_enabled']) && $admin_payment_setting['is_mercado_enabled'] == 'on')
                        <div id="mercado_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Mercado Pago') }}</h5>
                            </div>
                            <div class="tab-pane " id="mercado_payment">
                                <form class="w3-container w3-display-middle w3-card-4" method="POST" id="payment-form"
                                    action="{{ route('plan.pay.with.mercado') }}">
                                    @csrf
                                    <input type="hidden" name="plan_id"
                                        value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="border p-3 mb-3 rounded payment-box">
                                                    <div class="d-flex align-items-center">
                                                        <div class="form-group w-100">
                                                            <label for="mercado_coupon"
                                                                class="form-label">{{ __('Coupon') }}</label>
                                                            <input type="text" id="mercado_coupon" name="coupon"
                                                                class="form-control coupon"
                                                                placeholder="{{ __('Enter Coupon Code') }}">
                                                        </div>

                                                        <div class="form-group ms-3 mt-4">
                                                            <a class="text-muted " data-bs-toggle="tooltip"
                                                                title="{{ __('Apply') }}"><i
                                                                    class="ti ti-square-check btn-apply apply-coupon"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="submit" id="pay_with_mercado" value="{{ __('Pay Now') }}"
                                                class="btn btn-primary mb-2 me-3">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif


                    {{-- Paytm --}}
                    @if (isset($admin_payment_setting['is_paytm_enabled']) && $admin_payment_setting['is_paytm_enabled'] == 'on')
                        <div id="paytm_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Paytm') }}</h5>
                            </div>
                            <div class="tab-pane " id="paytm_payment">
                                <form role="form" action="{{ route('plan.pay.with.paytm') }}" method="post"
                                    class="require-validation" id="paytm-payment-form">
                                    @csrf
                                    <input type="hidden" name="plan_id"
                                        value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                    <input type="hidden" name="total_price" id="paytm_total_price"
                                        value="{{ $plan->price }}" class="form-control">

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="mobile_number"
                                                        class="form-label">{{ __('Mobile Number') }}</label>
                                                    <input type="text" id="mobile_number" name="mobile_number"
                                                        class="form-control coupon"
                                                        placeholder="{{ __('Enter Mobile Number') }} " required>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <div class="form-group w-100">
                                                    <label for="paytm_coupon"
                                                        class="form-label">{{ __('Coupon') }}</label>
                                                    <input type="text" id="paytm_coupon" name="coupon"
                                                        class="form-control coupon"
                                                        placeholder="{{ __('Enter Coupon Code') }}">
                                                </div>

                                                <div class="form-group ms-3 mt-4">
                                                    <a class="text-muted " data-bs-toggle="tooltip"
                                                        title="{{ __('Apply') }}"><i
                                                            class="ti ti-square-check btn-apply apply-coupon"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <button class="btn btn-primary mb-2 me-3" id="pay_with_paytm" type="submit">
                                                {{ __('Pay Now') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif


                    {{-- Mollie --}}
                    @if (isset($admin_payment_setting['is_mollie_enabled']) && $admin_payment_setting['is_mollie_enabled'] == 'on')
                        <div id="mollie_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Mollie') }}</h5>
                            </div>
                            <div class="tab-pane " id="mollie_payment">
                                <form role="form" action="{{ route('plan.pay.with.mollie') }}" method="post"
                                    class="require-validation" id="mollie-payment-form">
                                    @csrf
                                    <input type="hidden" name="plan_id"
                                        value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                    <input type="hidden" name="total_price" id="mollie_total_price"
                                        value="{{ $plan->price }}" class="form-control">

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="mollie_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="mollie_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>
                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <button class="btn btn-primary mb-2 me-3" id="pay_with_mollie"
                                                type="submit">
                                                {{ __('Pay Now') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    {{-- Skrill --}}
                    @if (isset($admin_payment_setting['is_skrill_enabled']) && $admin_payment_setting['is_skrill_enabled'] == 'on')
                        <div id="skrill_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Skrill') }}</h5>
                            </div>
                            <div class="tab-pane " id="skrill_payment">
                                <form role="form" action="{{ route('plan.pay.with.skrill') }}" method="post"
                                    class="require-validation" id="skrill-payment-form">
                                    @csrf
                                    <input type="hidden" name="id"
                                        value="{{ date('Y-m-d') }}-{{ strtotime(date('Y-m-d H:i:s')) }}-payatm">
                                    <input type="hidden" name="order_id"
                                        value="{{ str_pad(!empty($order->id) ? $order->id + 1 : 0 + 1, 4, '100', STR_PAD_LEFT) }}">
                                    @php
                                        $skrill_data = [
                                            'transaction_id' => md5(
                                                date('Y-m-d') . strtotime('Y-m-d H:i:s') . 'user_id',
                                            ),
                                            'user_id' => 'user_id',
                                            'amount' => 'amount',
                                            'currency' => 'currency',
                                        ];
                                        session()->put('skrill_data', $skrill_data);
                                    @endphp
                                    <input type="hidden" name="plan_id"
                                        value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                    <input type="hidden" name="total_price" id="skrill_total_price"
                                        value="{{ $plan->price }}" class="form-control">

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="skrill_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="skrill_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>
                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <button class="btn btn-primary mb-2 me-3" id="pay_with_skrill"
                                                type="submit">
                                                {{ __('Pay Now') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif


                    {{-- Coingate --}}
                    @if (isset($admin_payment_setting['is_coingate_enabled']) && $admin_payment_setting['is_coingate_enabled'] == 'on')
                        <div id="coingate_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Coingate') }}</h5>
                            </div>
                            <div class="tab-pane " id="coingate_payment">
                                <form role="form" action="{{ route('plan.pay.with.coingate') }}" method="post"
                                    class="require-validation" id="coingate-payment-form">
                                    @csrf
                                    <input type="hidden" name="counpon" id="coingate_coupon" value="">
                                    <input type="hidden" name="plan_id"
                                        value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                    <input type="hidden" name="total_price" id="coingate_total_price"
                                        value="{{ $plan->price }}" class="form-control">
                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="coingate_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="coingate_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>
                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <button class="btn btn-primary mb-2 me-3" id="pay_with_coingate"
                                                type="submit">
                                                {{ __('Pay Now') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif


                    {{-- Paymentwall --}}
                    @if (isset($admin_payment_setting['is_paymentwall_enabled']) && $admin_payment_setting['is_paymentwall_enabled'] == 'on')
                        <div id="paymentwall_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Paymentwall') }}</h5>
                            </div>
                            <div class="tab-pane " id="paymentwall_payment">
                                <form role="form" action="{{ route('plan.paymentwallpayment') }}" method="post"
                                    id="paymentwall-payment-form" class="w3-container w3-display-middle w3-card-4">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="paymentwall_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="paymentwall_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit"
                                                id="pay_with_paymentwall">
                                                {{ __('Pay Now') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif


                    {{-- Toyyibpay --}}
                    @if (isset($admin_payment_setting['is_toyyibpay_enabled']) && $admin_payment_setting['is_toyyibpay_enabled'] == 'on')
                        <div id="toyyibpay_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Toyyibpay') }}</h5>
                            </div>
                            <div class="tab-pane" id="toyyibpay_payment">
                                <form role="form" action="{{ route('plan.toyyibpaypayment') }}" method="post"
                                    id="toyyibpay-payment-form" class="w3-container w3-display-middle w3-card-4">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="toyyibpay_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="toyyibpay_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @endif
                    {{-- Toyyibpay end --}}

                    {{-- PayFast --}}
                    @if (isset($admin_payment_setting['is_payfast_enabled']) && $admin_payment_setting['is_payfast_enabled'] == 'on')
                        <div id="payfast_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Payfast') }}</h5>
                            </div>
                            @if (
                                $admin_payment_setting['is_payfast_enabled'] == 'on' &&
                                    !empty($admin_payment_setting['payfast_merchant_id']) &&
                                    !empty($admin_payment_setting['payfast_merchant_key']) &&
                                    !empty($admin_payment_setting['payfast_signature']) &&
                                    !empty($admin_payment_setting['payfast_mode']))
                                <div
                                    class="tab-pane {{ ($admin_payment_setting['is_payfast_enabled'] == 'on' && !empty($admin_payment_setting['payfast_merchant_id']) && !empty($admin_payment_setting['payfast_merchant_key'])) == 'on' ? 'active' : '' }}">
                                    @php
                                        $pfHost =
                                            $admin_payment_setting['payfast_mode'] == 'sandbox'
                                                ? 'sandbox.payfast.co.za'
                                                : 'www.payfast.co.za';
                                    @endphp
                                    <form role="form" action={{ 'https://' . $pfHost . '/eng/process' }}
                                        method="post" class="require-validation" id="payfast-form">

                                        <div class="border p-3 mb-3 rounded">
                                            <div class="row">
                                                <div class="col-md-12 mt-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="form-group w-100">
                                                            <label for="payfast_coupon"
                                                                class="form-label">{{ __('Coupon') }}</label>
                                                            <input type="text" id="payfast_coupon" name="coupon"
                                                                class="form-control coupon"
                                                                placeholder="{{ __('Enter Coupon Code') }}">
                                                        </div>
                                                        <div class="form-group ms-3 mt-4">
                                                            <a class="text-muted apply-coupon" data-bs-toggle="tooltip"
                                                                title="{{ __('Apply') }}"><i
                                                                    class="ti ti-square-check btn-apply apply-coupon"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                        <div id="get-payfast-inputs"></div>
                                        <div class="col-sm-12 my-2 px-2">
                                            <div class="text-end">
                                                <input type="hidden" name="plan_id" id="plan_id"
                                                    value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                                <input type="submit" value="{{ __('Pay Now') }}"
                                                    id="payfast-get-status" class="btn btn-primary mb-2 me-3">
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Iyzipay --}}
                    @if (isset($admin_payment_setting['is_iyzipay_enabled']) && $admin_payment_setting['is_iyzipay_enabled'] == 'on')
                        <div class="card" id="iyzipay-payment">
                            <div class="card-header">
                                <h5>{{ __('Iyzipay') }}</h5>
                            </div>
                            <div class="tab-pane" id="iyzipay-payment">
                                <form role="form" action="{{ route('iyzipay.payment.init') }}" method="post"
                                    class="require-validation" id="iyzipay-payment-form">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="iyzipay_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="iyzipay_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>
                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted apply-coupon" data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id" id="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <input type="submit" value="{{ __('Pay Now') }}" id="payfast-get-status"
                                                class="btn btn-primary mb-2 me-3">
                                        </div>
                                    </div>


                                </form>
                            </div>
                        </div>
                    @endif

                    {{-- SSpay --}}
                    @if (isset($admin_payment_setting['is_sspay_enabled']) && $admin_payment_setting['is_sspay_enabled'] == 'on')
                        <div id="sspay_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('SSPay') }}</h5>
                            </div>
                            <div class="tab-pane" id="sspay_payment">
                                <form role="form" action="{{ route('plan.sspaypayment') }}" method="post"
                                    id="sspay-payment-form" class="w3-container w3-display-middle w3-card-4">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="sspay_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="sspay_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @endif

                    {{-- Paytab --}}
                    @if (isset($admin_payment_setting['is_paytab_enabled']) && $admin_payment_setting['is_paytab_enabled'] == 'on')
                        <div id="paytab_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('PayTab') }}</h5>
                            </div>
                            <div class="tab-pane" id="paytab_payment">
                                <form role="form" action="{{ route('plan.pay.with.paytab') }}" method="post"
                                    id="paytab-payment-form" class="w3-container w3-display-middle w3-card-4">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="paytab_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="paytab_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted" data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @endif

                    {{-- Benefit --}}
                    @if (isset($admin_payment_setting['is_benefit_enabled']) && $admin_payment_setting['is_benefit_enabled'] == 'on')
                        <div id="benefit_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Benefit') }}</h5>
                            </div>
                            <div class="tab-pane" id="benefit_payment">
                                <form role="form" action="{{ route('plan.pay.with.benefit') }}" method="post"
                                    id="benefit-payment-form" class="w3-container w3-display-middle w3-card-4">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="benefit_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="benefit_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted" data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @endif

                    {{-- Cashfree --}}
                    @if (isset($admin_payment_setting['is_cashfree_enabled']) && $admin_payment_setting['is_cashfree_enabled'] == 'on')
                        <div id="cashfree_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Cashfree') }}</h5>
                            </div>
                            <div class="tab-pane" id="cashfree_payment">
                                <form role="form" action="{{ route('plan.pay.with.cashfree') }}" method="post"
                                    id="cashfree-payment-form" class="w3-container w3-display-middle w3-card-4">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="cashfree_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="cashfree_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted" data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @endif

                    {{-- AamarPay --}}
                    @if (isset($admin_payment_setting['is_aamarpay_enabled']) && $admin_payment_setting['is_aamarpay_enabled'] == 'on')
                        <div id="aamarpay_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('AamarPay') }}</h5>
                            </div>
                            <div class="tab-pane" id="aamarpay_payment">
                                <form role="form" action="{{ route('plan.pay.with.aamarpay') }}" method="post"
                                    id="aamarpay-payment-form" class="w3-container w3-display-middle w3-card-4">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="aamarpay_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="aamarpay_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted" data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @endif

                    {{-- PayTR --}}
                    @if (isset($admin_payment_setting['is_paytr_enabled']) && $admin_payment_setting['is_paytr_enabled'] == 'on')
                        <div id="paytr_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('PayTR') }}</h5>
                            </div>
                            <div class="tab-pane" id="paytr_payment">
                                <form role="form" action="{{ route('plan.pay.with.paytr', $plan->id) }}"
                                    method="post" id="paytr-payment-form"
                                    class="w3-container w3-display-middle w3-card-4">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="paytr_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="paytr_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted" data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @endif

                    {{-- Yookassa --}}
                    @if (isset($admin_payment_setting['is_yookassa_enabled']) && $admin_payment_setting['is_yookassa_enabled'] == 'on')
                        <div id="yookassa_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Yookassa') }}</h5>
                            </div>
                            <div class="tab-pane" id="yookassa_payment">
                                <form role="form" action="{{ route('plan.pay.with.yookassa', $plan->id) }}"
                                    method="post" id="yookassa-payment-form"
                                    class="w3-container w3-display-middle w3-card-4">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">

                                                    <div class="form-group w-100">
                                                        <label for="yookassa_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="yookassa_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted" data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @endif

                    {{-- Midtrans --}}
                    @if (isset($admin_payment_setting['is_midtrans_enabled']) && $admin_payment_setting['is_midtrans_enabled'] == 'on')
                        <div id="midtrans_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Midtrans') }}</h5>
                            </div>
                            <div class="tab-pane" id="midtrans_payment">
                                <form role="form" action="{{ route('plan.pay.with.midtrans', $plan->id) }}"
                                    method="post" id="midtrans-payment-form"
                                    class="w3-container w3-display-middle w3-card-4">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="midtrans_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="midtrans_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted" data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @endif

                    {{-- Xendit --}}
                    @if (isset($admin_payment_setting['is_xendit_enabled']) && $admin_payment_setting['is_xendit_enabled'] == 'on')
                        <div id="xendit_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Xendit') }}</h5>
                            </div>
                            <div class="tab-pane" id="xendit_payment">
                                <form role="form" action="{{ route('plan.pay.with.xendit', $plan->id) }}"
                                    method="post" id="xendit-payment-form"
                                    class="w3-container w3-display-middle w3-card-4">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="xendit_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="xendit_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted" data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @endif


                    @if (isset($admin_payment_setting['is_nepalste_enabled']) && $admin_payment_setting['is_nepalste_enabled'] == 'on')
                        <div id="nepalste_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Nepalste') }}</h5>
                            </div>
                            <div class="tab-pane" id="nepalste_payment">
                                <form role="form" action="{{ route('plan.pay.with.nepalste', $plan->id) }}"
                                    method="post" id="nepalste-payment-form"
                                    class="w3-container w3-display-middle w3-card-4">
                                    @csrf

                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="nepalste_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="nepalste_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>

                                                    <div class="form-group ms-3 mt-4">
                                                        <a class="text-muted" data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @endif

                    {{-- Paiement Pro --}}
                    @if (isset($admin_payment_setting['is_paiementpro_enabled']) && $admin_payment_setting['is_paiementpro_enabled'] == 'on')
                        <div id="paiementpro_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Paiement Pro') }}</h5>
                            </div>
                            <div class="tab-pane" id="paiementpro_payment">
                                <form role="form" action="{{ route('plan.pay.with.paiementpro', $plan->id) }}"
                                    method="post" id="paiementpro-payment-form"
                                    class="w3-container w3-display-middle w3-card-4">
                                    @csrf
                                    <div class="border p-3 rounded stripe-payment-div">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="paiementpro_mobile_number"
                                                        class="form-control-label text-dark">{{ __('Mobile Number') }}</label>
                                                    <input type="text" id="paiementpro_mobile_number"
                                                        name="mobile_number" class="form-control mobile_number"
                                                        data-from="paiementpro"
                                                        placeholder="{{ __('Enter Mobile Number') }}" required>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="paiementpro_channel"
                                                        class="form-control-label text-dark">{{ __('Channel') }}</label>
                                                    <input type="text" id="paiementpro_channel" name="channel"
                                                        class="form-control channel" data-from="paiementpro"
                                                        placeholder="{{ __('Enter Channel') }}" required>
                                                    <small class="text-danger">Example : OMCIV2 , MOMO , CARD , FLOOZ ,
                                                        PAYPAL</small>
                                                </div>
                                            </div>
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="stripe_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="stripe_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>
                                                    <div class="form-group ms-3 mt-4">
                                                        <a href="#" class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-12">
                                                <div class="error" style="display: none;">
                                                    <div class='alert-danger alert'>
                                                        {{ __('Please correct the errors and try again.') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @endif

                    {{-- Cinetpay --}}
                    @if (isset($admin_payment_setting['is_cinetpay_enabled']) && $admin_payment_setting['is_cinetpay_enabled'] == 'on')
                        <div id="cinetpay_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Cinetpay') }}</h5>
                            </div>
                            <div class="tab-pane" id="cinetpay_payment">
                                <form role="form" action="{{ route('plan.pay.with.cinetpay', $plan->id) }}"
                                    method="post" id="cinetpay-payment-form"
                                    class="w3-container w3-display-middle w3-card-4">
                                    @csrf
                                    <div class="border p-3 rounded cinetpay-payment-div">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="cinetpay_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="cinetpay_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>
                                                    <div class="form-group ms-3 mt-4">
                                                        <a href="#" class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="error" style="display: none;">
                                                    <div class='alert-danger alert'>
                                                        {{ __('Please correct the errors and try again.') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                     {{-- Fedapay --}}
                     @if (isset($admin_payment_setting['is_fedapay_enabled']) && $admin_payment_setting['is_fedapay_enabled'] == 'on')
                        <div id="fedapay_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('Fedapay') }}</h5>
                            </div>
                            <div class="tab-pane" id="fedapay_payment">
                                <form role="form" action="{{ route('plan.pay.with.fedapay', $plan->id) }}"
                                    method="post" id="fedapay-payment-form"
                                    class="w3-container w3-display-middle w3-card-4">
                                    @csrf
                                    <div class="border p-3 rounded fedapay-payment-div">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="fedapay_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="fedapay_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>
                                                    <div class="form-group ms-3 mt-4">
                                                        <a href="#" class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="error" style="display: none;">
                                                    <div class='alert-danger alert'>
                                                        {{ __('Please correct the errors and try again.') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                     @endif

                     {{-- PayHere --}}
                     @if (isset($admin_payment_setting['is_payhere_enabled']) && $admin_payment_setting['is_payhere_enabled'] == 'on')
                        <div id="payhere_payment" class="card">
                            <div class="card-header">
                                <h5>{{ __('PayHere') }}</h5>
                            </div>
                            <div class="tab-pane" id="payhere_payment">
                                <form role="form" action="{{ route('plan.pay.with.payhere', $plan->id) }}"
                                    method="post" id="payhere-payment-form"
                                    class="w3-container w3-display-middle w3-card-4">
                                    @csrf
                                    <div class="border p-3 rounded payhere-payment-div">
                                        <div class="row">
                                            <div class="col-md-12 mt-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-group w-100">
                                                        <label for="payhere_coupon"
                                                            class="form-label">{{ __('Coupon') }}</label>
                                                        <input type="text" id="payhere_coupon" name="coupon"
                                                            class="form-control coupon"
                                                            placeholder="{{ __('Enter Coupon Code') }}">
                                                    </div>
                                                    <div class="form-group ms-3 mt-4">
                                                        <a href="#" class="text-muted " data-bs-toggle="tooltip"
                                                            title="{{ __('Apply') }}"><i
                                                                class="ti ti-square-check btn-apply apply-coupon"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="error" style="display: none;">
                                                    <div class='alert-danger alert'>
                                                        {{ __('Please correct the errors and try again.') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-12 my-2 px-2">
                                        <div class="text-end">
                                            <input type="hidden" name="plan_id"
                                                value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                            <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                {{ __('Pay Now') }}
                                            </button>

                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                     @endif

                       {{-- Tap --}}
                       @if (isset($admin_payment_setting['tap_payment_is_on']) && $admin_payment_setting['tap_payment_is_on'] == 'on')
                            <div id="tap_payment" class="card">
                                <div class="card-header">
                                    <h5>{{ __('Tap') }}</h5>
                                </div>
                                <div class="tab-pane" id="tap_payment">
                                    <form role="form" action="{{ route('plan.pay.with.tap', $plan->id) }}"
                                        method="post" id="tap-payment-form"
                                        class="w3-container w3-display-middle w3-card-4">
                                        @csrf
                                        <div class="border p-3 rounded tap-payment-div">
                                            <div class="row">
                                                <div class="col-md-12 mt-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="form-group w-100">
                                                            <label for="tap_coupon"
                                                                class="form-label">{{ __('Coupon') }}</label>
                                                            <input type="text" id="tap_coupon" name="coupon"
                                                                class="form-control coupon"
                                                                placeholder="{{ __('Enter Coupon Code') }}">
                                                        </div>
                                                        <div class="form-group ms-3 mt-4">
                                                            <a href="#" class="text-muted " data-bs-toggle="tooltip"
                                                                title="{{ __('Apply') }}"><i
                                                                    class="ti ti-square-check btn-apply apply-coupon"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="error" style="display: none;">
                                                        <div class='alert-danger alert'>
                                                            {{ __('Please correct the errors and try again.') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-sm-12 my-2 px-2">
                                            <div class="text-end">
                                                <input type="hidden" name="plan_id"
                                                    value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                                <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                    {{ __('Pay Now') }}
                                                </button>

                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif

                         {{-- AuthorizeNet --}}
                        @if (isset($admin_payment_setting['authorizenet_payment_is_on']) && $admin_payment_setting['authorizenet_payment_is_on'] == 'on')
                            <div id="authorizenet_payment" class="card">
                                <div class="card-header">
                                    <h5>{{ __('AuthorizeNet') }}</h5>
                                </div>
                                <div class="tab-pane" id="authorizenet_payment">
                                    <form role="form" action="{{ route('plan.pay.with.authorizenet', $plan->id) }}"
                                        method="post" id="authorizenet-payment-form"
                                        class="w3-container w3-display-middle w3-card-4">
                                        @csrf
                                        <div class="border p-3 rounded authorizenet-payment-div">
                                            <div class="row">
                                                <div class="col-md-12 mt-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="form-group w-100">
                                                            <label for="authorizenet_coupon"
                                                                class="form-label">{{ __('Coupon') }}</label>
                                                            <input type="text" id="authorizenet_coupon" name="coupon"
                                                                class="form-control coupon"
                                                                placeholder="{{ __('Enter Coupon Code') }}">
                                                        </div>
                                                        <div class="form-group ms-3 mt-4">
                                                            <a href="#" class="text-muted " data-bs-toggle="tooltip"
                                                                title="{{ __('Apply') }}"><i
                                                                    class="ti ti-square-check btn-apply apply-coupon"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="error" style="display: none;">
                                                        <div class='alert-danger alert'>
                                                            {{ __('Please correct the errors and try again.') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-sm-12 my-2 px-2">
                                            <div class="text-end">
                                                <input type="hidden" name="plan_id"
                                                    value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                                <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                    {{ __('Pay Now') }}
                                                </button>

                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif

                         {{-- Khalti --}}
                        @if (isset($admin_payment_setting['khalti_payment_is_on']) && $admin_payment_setting['khalti_payment_is_on'] == 'on')
                            <div id="khalti_payment" class="card">
                                <div class="card-header">
                                    <h5>{{ __('Khalti') }}</h5>
                                </div>
                                <div class="tab-pane" id="khalti_payment">
                                    <form role="form" action="{{ route('plan.pay.with.khalti', $plan->id) }}"
                                        method="post" id="khalti-payment-form"
                                        class="w3-container w3-display-middle w3-card-4">
                                        @csrf
                                        <div class="border p-3 rounded khalti-payment-div">
                                            <div class="row">
                                                <div class="col-md-12 mt-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="form-group w-100">
                                                            <label for="khalti_coupon"
                                                                class="form-label">{{ __('Coupon') }}</label>
                                                            <input type="text" id="khalti_coupon" name="coupon"
                                                                class="form-control coupon"
                                                                placeholder="{{ __('Enter Coupon Code') }}">
                                                        </div>
                                                        <div class="form-group ms-3 mt-4">
                                                            <a href="#" class="text-muted " data-bs-toggle="tooltip"
                                                                title="{{ __('Apply') }}"><i
                                                                    class="ti ti-square-check btn-apply apply-coupon"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="error" style="display: none;">
                                                        <div class='alert-danger alert'>
                                                            {{ __('Please correct the errors and try again.') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-sm-12 my-2 px-2">
                                            <div class="text-end">
                                                <input type="hidden" name="plan_id" class="plan_id"
                                                    value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                                <button class="btn btn-primary mb-2 me-3 payment-btn" type="submit" id="">
                                                    {{ __('Pay Now') }}
                                                </button>

                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif

                        {{-- Easebuzz --}}
                        @if (isset($admin_payment_setting['easebuzz_payment_is_on']) && $admin_payment_setting['easebuzz_payment_is_on'] == 'on')
                           <div id="easebuzz_payment" class="card">
                               <div class="card-header">
                                   <h5>{{ __('Easebuzz') }}</h5>
                               </div>
                               <div class="tab-pane" id="easebuzz_payment">
                                   <form role="form" action="{{ route('plan.pay.with.easebuzz', $plan->id) }}"
                                       method="post" id="easebuzz-payment-form"
                                       class="w3-container w3-display-middle w3-card-4">
                                       @csrf
                                       <div class="border p-3 rounded easebuzz-payment-div">
                                           <div class="row">
                                               <div class="col-md-12 mt-4">
                                                   <div class="d-flex align-items-center">
                                                       <div class="form-group w-100">
                                                           <label for="easebuzz_coupon"
                                                               class="form-label">{{ __('Coupon') }}</label>
                                                           <input type="text" id="easebuzz_coupon" name="coupon"
                                                               class="form-control coupon"
                                                               placeholder="{{ __('Enter Coupon Code') }}">
                                                       </div>
                                                       <div class="form-group ms-3 mt-4">
                                                           <a href="#" class="text-muted " data-bs-toggle="tooltip"
                                                               title="{{ __('Apply') }}"><i
                                                                   class="ti ti-square-check btn-apply apply-coupon"></i>
                                                           </a>
                                                       </div>
                                                   </div>
                                               </div>
                                           </div>
                                           <div class="row">
                                               <div class="col-12">
                                                   <div class="error" style="display: none;">
                                                       <div class='alert-danger alert'>
                                                           {{ __('Please correct the errors and try again.') }}
                                                       </div>
                                                   </div>
                                               </div>
                                           </div>
                                       </div>

                                       <div class="col-sm-12 my-2 px-2">
                                           <div class="text-end">
                                               <input type="hidden" name="plan_id"
                                                   value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                               <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                   {{ __('Pay Now') }}
                                               </button>

                                           </div>
                                       </div>
                                   </form>
                               </div>
                           </div>
                       @endif

                        {{-- Ozow --}}
                        @if (isset($admin_payment_setting['ozow_payment_is_enabled']) && $admin_payment_setting['ozow_payment_is_enabled'] == 'on')
                            <div id="ozow_payment" class="card">
                                <div class="card-header">
                                    <h5>{{ __('Ozow') }}</h5>
                                </div>
                                <div class="tab-pane" id="ozow_payment">
                                    <form role="form" action="{{ route('plan.pay.with.ozow', $plan->id) }}"
                                        method="post" id="ozow-payment-form"
                                        class="w3-container w3-display-middle w3-card-4">
                                        @csrf
                                        <div class="border p-3 rounded ozow-payment-div">
                                            <div class="row">
                                                <div class="col-md-12 mt-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="form-group w-100">
                                                            <label for="ozow_coupon"
                                                                class="form-label">{{ __('Coupon') }}</label>
                                                            <input type="text" id="ozow_coupon" name="coupon"
                                                                class="form-control coupon"
                                                                placeholder="{{ __('Enter Coupon Code') }}">
                                                        </div>
                                                        <div class="form-group ms-3 mt-4">
                                                            <a href="#" class="text-muted " data-bs-toggle="tooltip"
                                                                title="{{ __('Apply') }}"><i
                                                                    class="ti ti-square-check btn-apply apply-coupon"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="error" style="display: none;">
                                                        <div class='alert-danger alert'>
                                                            {{ __('Please correct the errors and try again.') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-sm-12 my-2 px-2">
                                            <div class="text-end">
                                                <input type="hidden" name="plan_id"
                                                    value="{{ \Illuminate\Support\Facades\Crypt::encrypt($plan->id) }}">
                                                <button class="btn btn-primary mb-2 me-3" type="submit" id="">
                                                    {{ __('Pay Now') }}
                                                </button>

                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif




                </div>
            </div>
        </div>
    </div>
@endsection
