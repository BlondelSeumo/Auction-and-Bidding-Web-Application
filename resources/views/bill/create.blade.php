
@extends('layouts.admin')
@section('page-title')
    {{__('Bill Create')}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{route('dashboard')}}">{{__('Dashboard')}}</a></li>
    <li class="breadcrumb-item"><a href="{{route('bill.index')}}">{{__('Bill')}}</a></li>
    <li class="breadcrumb-item">{{__('Bill Create')}}</li>
@endsection
@push('script-page')
    <script src="{{asset('js/jquery-ui.min.js')}}"></script>
    <script src="{{asset('js/jquery.repeater.min.js')}}"></script>
    <script src="{{ asset('js/jquery-searchbox.js') }}"></script>
    <script>
        var selector = "body";
        if ($(selector + " .repeater").length) {
            var $dragAndDrop = $("body .repeater tbody").sortable({
                handle: '.sort-handler'
            });
            var $repeater = $(selector + ' .repeater').repeater({
                initEmpty: false,
                defaultValues: {
                    'status': 1
                },
                show: function () {
                    $(this).slideDown();
                    var file_uploads = $(this).find('input.multi');
                    if (file_uploads.length) {
                        $(this).find('input.multi').MultiFile({
                            max: 3,
                            accept: 'png|jpg|jpeg',
                            max_size: 2048
                        });
                    }

                    // for item SearchBox ( this function is  custom Js )
                    JsSearchBox();

                    $('.select2').select2();
                },
                hide: function (deleteElement) {
                    if (confirm('Are you sure you want to delete this element?')) {
                        $(this).slideUp(deleteElement);
                        $(this).remove();

                        var inputs = $(".amount");
                        var subTotal = 0;
                        for (var i = 0; i < inputs.length; i++) {
                            subTotal = parseFloat(subTotal) + parseFloat($(inputs[i]).html());
                        }
                        $('.subTotal').html(subTotal.toFixed(2));
                        $('.totalAmount').html(subTotal.toFixed(2));
                    }
                },
                ready: function (setIndexes) {
                    $dragAndDrop.on('drop', setIndexes);
                },
                isFirstItemUndeletable: true
            });
            var value = $(selector + " .repeater").attr('data-value');
            if (typeof value != 'undefined' && value.length != 0) {
                value = JSON.parse(value);
                $repeater.setList(value);
            }

        }

        $(document).on('change', '#vender', function () {
            $('#vender_detail').removeClass('d-none');
            $('#vender_detail').addClass('d-block');
            $('#vender-box').removeClass('d-block');
            $('#vender-box').addClass('d-none');
            var id = $(this).val();
            var url = $(this).data('url');
            $.ajax({
                url: url,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': jQuery('#token').val()
                },
                data: {
                    'id': id
                },
                cache: false,
                success: function (data) {
                    if (data != '') {
                        $('#vender_detail').html(data);
                    } else {
                        $('#vender-box').removeClass('d-none');
                        $('#vender-box').addClass('d-block');
                        $('#vender_detail').removeClass('d-block');
                        $('#vender_detail').addClass('d-none');
                    }
                },
            });
        });

        $(document).on('click', '#remove', function () {
            $('#vender-box').removeClass('d-none');
            $('#vender-box').addClass('d-block');
            $('#vender_detail').removeClass('d-block');
            $('#vender_detail').addClass('d-none');
        })

        $(document).on('change', '.item', function () {

            var iteams_id = $(this).val();
            var url = $(this).data('url');
            var el = $(this);


            $.ajax({
                url: url,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': jQuery('#token').val()
                },
                data: {
                    'product_id': iteams_id
                },
                cache: false,
                success: function (data) {
                    var item = JSON.parse(data);
                    // console.log(item)

                    $(el.parent().parent().find('.quantity')).val(1);
                    $(el.parent().parent().find('.price')).val(item.product.purchase_price);
                    $(el.parent().parent().parent().find('.pro_description')).val(item.product.description);

                    var taxes = '';
                    var tax = [];

                    var totalItemTaxRate = 0;
                    if (item.taxes == 0) {
                        taxes += '-';
                    } else {
                        for (var i = 0; i < item.taxes.length; i++) {
                            taxes += '<span class="badge bg-primary mt-1 mr-2">' + item.taxes[i].name + ' ' + '(' + item.taxes[i].rate + '%)' + '</span>';
                            tax.push(item.taxes[i].id);
                            totalItemTaxRate += parseFloat(item.taxes[i].rate);
                        }
                    }
                    var itemTaxPrice = parseFloat((totalItemTaxRate / 100) * (item.product.purchase_price * 1));

                    $(el.parent().parent().find('.itemTaxPrice')).val(itemTaxPrice.toFixed(2));
                    $(el.parent().parent().find('.itemTaxRate')).val(totalItemTaxRate.toFixed(2));
                    $(el.parent().parent().find('.taxes')).html(taxes);
                    $(el.parent().parent().find('.tax')).val(tax);
                    $(el.parent().parent().find('.unit')).html(item.unit);
                    $(el.parent().parent().find('.discount')).val(0);
                    // $(el.parent().parent().find('.amount')).html(item.totalAmount);


                    var inputs = $(".amount");
                    var subTotal = 0;
                    for (var i = 0; i < inputs.length; i++) {
                        subTotal = parseFloat(subTotal) + parseFloat($(inputs[i]).html());
                    }


                    var accountinputs = $(".accountamount");
                    var accountSubTotal = 0;
                    for (var i = 0; i < accountinputs.length; i++)
                    {
                        var currentInputValue = parseFloat(accountinputs[i].innerHTML);
                        if (!isNaN(currentInputValue))
                        {
                            accountSubTotal += currentInputValue;
                        }
                    }



                    var totalItemPrice = 0;
                    var priceInput = $('.price');
                    for (var j = 0; j < priceInput.length; j++) {
                        totalItemPrice += parseFloat(priceInput[j].value);

                    }

                    var totalItemTaxPrice = 0;
                    var itemTaxPriceInput = $('.itemTaxPrice');
                    for (var j = 0; j < itemTaxPriceInput.length; j++) {
                        totalItemTaxPrice += parseFloat(itemTaxPriceInput[j].value);
                        $(el.parent().parent().find('.amount')).html(parseFloat(item.totalAmount)+parseFloat(itemTaxPriceInput[j].value));
                    }

                    var totalItemDiscountPrice = 0;
                    var itemDiscountPriceInput = $('.discount');
                    for (var k = 0; k < itemDiscountPriceInput.length; k++) {

                        totalItemDiscountPrice += parseFloat(itemDiscountPriceInput[k].value);
                    }


                    $('.subTotal').html((totalItemPrice+accountSubTotal).toFixed(2));
                    $('.totalTax').html(totalItemTaxPrice.toFixed(2));
                    $('.totalAmount').html((parseFloat(totalItemPrice) - parseFloat(totalItemDiscountPrice) + parseFloat(totalItemTaxPrice)).toFixed(2));


                },
            });
        });

        $(document).on('keyup', '.quantity', function () {
            var quntityTotalTaxPrice = 0;

            var el = $(this).parent().parent().parent().parent();
            var quantity = $(this).val();
            var price = $(el.find('.price')).val();
            var discount = $(el.find('.discount')).val();
            if(discount.length <= 0)
            {
                discount = 0 ;
            }

            var totalItemPrice = (quantity * price) - discount;


            var amount = (totalItemPrice);

            var totalItemTaxRate = $(el.find('.itemTaxRate')).val();
            var itemTaxPrice = parseFloat((totalItemTaxRate / 100) * (totalItemPrice));
            $(el.find('.itemTaxPrice')).val(itemTaxPrice.toFixed(2));

            $(el.find('.amount')).html(parseFloat(itemTaxPrice)+parseFloat(amount));

            var totalItemTaxPrice = 0;
            var itemTaxPriceInput = $('.itemTaxPrice');
            for (var j = 0; j < itemTaxPriceInput.length; j++) {
                totalItemTaxPrice += parseFloat(itemTaxPriceInput[j].value);
            }


            var totalItemPrice = 0;
            var inputs_quantity = $(".quantity");
            var priceInput = $('.price');
            for (var j = 0; j < priceInput.length; j++) {
                totalItemPrice += (parseFloat(priceInput[j].value) * parseFloat(inputs_quantity[j].value));
            }


            // var totalAccount = 0;
            // var accountInput = $('.accountAmount');
            // for (var j = 0; j < accountInput.length; j++) {
            //     if(typeof accountInput[j].value != 'undefined')
            //     {
            //         var accountInputPrice = accountInput[j].value;
            //     }
            //     else {
            //        var accountInputPrice = 0;
            //     }
            //     totalAccount += (parseFloat(accountInputPrice));
            // }

            var totalAccount = 0;
            var accountInput = $('.accountAmount');

            for (var j = 0; j < accountInput.length; j++) {
                if (typeof accountInput[j].value != 'undefined') {
                    var accountInputPrice = parseFloat(accountInput[j].value);

                    if (isNaN(accountInputPrice)) {
                        totalAccount = 0;
                    } else {
                        totalAccount += accountInputPrice;
                    }
                }
            }



            var inputs = $(".amount");
            var subTotal = 0;
            for (var i = 0; i < inputs.length; i++) {
                subTotal = parseFloat(subTotal) + parseFloat($(inputs[i]).html());
            }

            console.log(totalAccount)

            var sumAmount = totalItemPrice + totalAccount;

            $('.subTotal').html((sumAmount).toFixed(2));
            $('.totalTax').html(totalItemTaxPrice.toFixed(2));
            $('.totalAmount').html((parseFloat(subTotal)+totalAccount).toFixed(2));

        })

        $(document).on('keyup change', '.price', function () {
            var el = $(this).parent().parent().parent().parent();
            var price = $(this).val();
            var quantity = $(el.find('.quantity')).val();

            var discount = $(el.find('.discount')).val();
            if(discount.length <= 0)
            {
                discount = 0 ;
            }
            var totalItemPrice = (quantity * price)-discount;

            var amount = (totalItemPrice);


            var totalItemTaxRate = $(el.find('.itemTaxRate')).val();
            var itemTaxPrice = parseFloat((totalItemTaxRate / 100) * (totalItemPrice));
            $(el.find('.itemTaxPrice')).val(itemTaxPrice.toFixed(2));

            $(el.find('.amount')).html(parseFloat(itemTaxPrice)+parseFloat(amount));

            var totalItemTaxPrice = 0;
            var itemTaxPriceInput = $('.itemTaxPrice');
            for (var j = 0; j < itemTaxPriceInput.length; j++) {
                totalItemTaxPrice += parseFloat(itemTaxPriceInput[j].value);
            }


            var totalItemPrice = 0;
            var inputs_quantity = $(".quantity");
            var priceInput = $('.price');
            for (var j = 0; j < priceInput.length; j++) {
                totalItemPrice += (parseFloat(priceInput[j].value) * parseFloat(inputs_quantity[j].value));
            }


            var totalAccount = 0;
            var accountInput = $('.accountAmount');

            for (var j = 0; j < accountInput.length; j++) {
                if (typeof accountInput[j].value != 'undefined') {
                    var accountInputPrice = parseFloat(accountInput[j].value);

                    if (isNaN(accountInputPrice)) {
                        totalAccount = 0;
                    } else {
                        totalAccount += accountInputPrice;
                    }
                }
            }

            var inputs = $(".amount");
            var subTotal = 0;
            for (var i = 0; i < inputs.length; i++) {
                subTotal = parseFloat(subTotal) + parseFloat($(inputs[i]).html());
            }


            $('.subTotal').html((totalItemPrice+totalAccount).toFixed(2));
            $('.totalTax').html(totalItemTaxPrice.toFixed(2));
            $('.totalAmount').html((parseFloat(subTotal)+totalAccount).toFixed(2));


        })

        $(document).on('keyup change', '.discount', function () {
            var el = $(this).parent().parent().parent();
            var discount = $(this).val();
            if(discount.length <= 0)
            {
                discount = 0 ;
            }

            var price = $(el.find('.price')).val();
            var quantity = $(el.find('.quantity')).val();
            var totalItemPrice = (quantity * price) - discount;


            var amount = (totalItemPrice);


            var totalItemTaxRate = $(el.find('.itemTaxRate')).val();
            var itemTaxPrice = parseFloat((totalItemTaxRate / 100) * (totalItemPrice));
            $(el.find('.itemTaxPrice')).val(itemTaxPrice.toFixed(2));

            $(el.find('.amount')).html(parseFloat(itemTaxPrice)+parseFloat(amount));

            var totalItemTaxPrice = 0;
            var itemTaxPriceInput = $('.itemTaxPrice');
            for (var j = 0; j < itemTaxPriceInput.length; j++) {
                totalItemTaxPrice += parseFloat(itemTaxPriceInput[j].value);
            }


            var totalItemPrice = 0;
            var inputs_quantity = $(".quantity");

            var priceInput = $('.price');
            for (var j = 0; j < priceInput.length; j++) {
                totalItemPrice += (parseFloat(priceInput[j].value) * parseFloat(inputs_quantity[j].value));
            }

            var inputs = $(".amount");
            var subTotal = 0;
            for (var i = 0; i < inputs.length; i++) {
                subTotal = parseFloat(subTotal) + parseFloat($(inputs[i]).html());
            }


            var totalItemDiscountPrice = 0;
            var itemDiscountPriceInput = $('.discount');
            for (var k = 0; k < itemDiscountPriceInput.length; k++) {
                totalItemDiscountPrice += parseFloat(itemDiscountPriceInput[k].value);
            }

            var totalAccount = 0;
            var accountInput = $('.accountAmount');

            for (var j = 0; j < accountInput.length; j++) {
                if (typeof accountInput[j].value != 'undefined') {
                    var accountInputPrice = parseFloat(accountInput[j].value);

                    if (isNaN(accountInputPrice)) {
                        totalAccount = 0;
                    } else {
                        totalAccount += accountInputPrice;
                    }
                }
            }


            // $('.subTotal').html(totalItemPrice.toFixed(2));
            $('.subTotal').html((totalItemPrice+totalAccount).toFixed(2));

            $('.totalTax').html(totalItemTaxPrice.toFixed(2));

            $('.totalAmount').html((parseFloat(subTotal)+totalAccount).toFixed(2));
            $('.totalDiscount').html(totalItemDiscountPrice.toFixed(2));


        })


        $(document).on('keyup change', '.accountAmount', function () {

            var el1 = $(this).parent().parent().parent().parent();
            var el = $(this).parent().parent().parent().parent().parent();

            var quantityDiv = $(el.find('.quantity'));
            var priceDiv = $(el.find('.price'));
            var discountDiv = $(el.find('.discount'));

            var itemSubTotal=0;
            for (var p = 0; p < priceDiv.length; p++) {
                var quantity=quantityDiv[p].value;
                var price=priceDiv[p].value;
                var discount=discountDiv[p].value;
                if(discount.length <= 0)
                {
                    discount = 0 ;
                }
                itemSubTotal += (quantity*price) - (discount);
            }


            // var totalItemTaxPrice = 0;
            // var itemTaxPriceInput = $('.itemTaxPrice');
            // for (var j = 0; j < itemTaxPriceInput.length; j++) {
            //
            //     totalItemTaxPrice += parseFloat(itemTaxPriceInput[j].value);
            //
            // }

            var totalItemTaxPrice = 0;
            var itemTaxPriceInput = $('.itemTaxPrice');

            for (var j = 0; j < itemTaxPriceInput.length; j++) {
                var parsedValue = parseFloat(itemTaxPriceInput[j].value);

                if (!isNaN(parsedValue)) {
                    totalItemTaxPrice += parsedValue;
                }
            }


            var amount = $(this).val();
            el1.find('.accountamount').html(amount);
            var totalAccount = 0;
            var accountInput = $('.accountAmount');
            for (var j = 0; j < accountInput.length; j++) {
                totalAccount += (parseFloat(accountInput[j].value) );
            }


            var inputs = $(".accountamount");
            var subTotal = 0;
            for (var i = 0; i < inputs.length; i++) {

                subTotal = parseFloat(subTotal) + parseFloat($(inputs[i]).html());
            }

            // console.log(subTotal)


            $('.subTotal').text((totalAccount+itemSubTotal).toFixed(2));
            $('.totalAmount').text((parseFloat((subTotal + itemSubTotal) + (totalItemTaxPrice))).toFixed(2));


        })


        var vendorId = '{{$vendorId}}';
        if (vendorId > 0) {
            $('#vender').val(vendorId).change();
        }

    </script>
    <script>

        $(document).on('change', '.item', function () {
            $('.item option').prop('hidden', false);
            $('.item :selected').each(function () {
                var id = $(this).val();
                $(".item option[value=" + id + "]").prop("hidden", true);
            });
        });

        $(document).on('click', '[data-repeater-create]', function () {
            $('.item option').prop('hidden', false);
            $('.item :selected').each(function () {
                var id = $(this).val();
                $(".item option[value=" + id + "]").prop("hidden", true);
            });
        })

        $(document).on('click', '[data-repeater-delete]', function () {
            $(".price").change();
            $(".discount").change();

            $('.item option').prop('hidden', false);
            $('.item :selected').each(function () {
                var id = $(this).val();
                $(".item option[value=" + id + "]").prop("hidden", true);
            });
        });
    </script>
@endpush
@section('content')
    <div class="row">
        {{ Form::open(array('url' => 'bill','class'=>'w-100', 'class'=>'needs-validation', 'novalidate')) }}
        <div class="col-12">
            <input type="hidden" name="_token" id="token" value="{{ csrf_token() }}">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" id="vender-box">
                                {{ Form::label('vender_id', __('Vendor'),['class'=>'form-label']) }}<x-required></x-required>
                                {{ Form::select('vender_id', $venders,$vendorId, array('class' => 'form-control select','id'=>'vender','data-url'=>route('bill.vender'),'required'=>'required')) }}
                            </div>
                            <div id="vender_detail" class="d-none">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        {{ Form::label('bill_date', __('Bill Date'),['class'=>'form-label']) }}<x-required></x-required>
                                        {{Form::date('bill_date',null,array('class'=>'form-control','required'=>'required'))}}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        {{ Form::label('due_date', __('Due Date'),['class'=>'form-label']) }}<x-required></x-required>
                                        {{Form::date('due_date',null,array('class'=>'form-control','required'=>'required'))}}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        {{ Form::label('bill_number', __('Bill Number'),['class'=>'form-label']) }}
                                        <input type="text" class="form-control" value="{{$bill_number}}" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        {{ Form::label('category_id', __('Category'),['class'=>'form-label']) }}
                                        {{ Form::select('category_id', $category,null, array('class' => 'form-control select')) }}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        {{ Form::label('order_number', __('Order Number'),['class'=>'form-label']) }}
                                        {{ Form::number('order_number', '', array('class' => 'form-control' , 'placeholder'=>__('Enter Order Number'))) }}
                                    </div>
                                </div>
                                @if(!$customFields->isEmpty())
                                    <div class="col-md-6">
                                        <div class="tab-pane fade show" id="tab-2" role="tabpanel">
                                            @include('customFields.formBuilder')
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <h5 class="d-inline-block mb-4">{{__('Product & Services')}}</h5>
            <div class="card repeater">
                <div class="item-section py-2">
                    <div class="row justify-content-between align-items-center">
                        <div class="col-md-12 d-flex align-items-center justify-content-between justify-content-md-end">
                            <div class="all-button-box me-2">
                                <a href="#" data-repeater-create="" class="btn btn-primary" data-bs-toggle="modal" data-target="#add-bank">
                                    <i class="ti ti-plus"></i> {{__('Add Item')}}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table" data-repeater-list="items" id="sortable-table">
                            <thead>
                                <tr>
                                <th width="20%">{{__('Items')}}<x-required></x-required></th>
                                <th>{{__('Quantity')}}<x-required></x-required></th>
                                <th>{{__('Price')}}<x-required></x-required></th>
                                <th>{{__('Discount')}}<x-required></x-required></th>
                                <th>{{__('Tax')}} (%)</th>
                                <th class="text-end">{{__('Amount')}}
                                    <br><small class="text-danger font-bold">{{__('after tax & discount')}}</small>
                                </th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody class="ui-sortable" data-repeater-item>
                            <tr>
                                <td width="25%" class="form-group pt-0">
                                     {{ Form::select('item', $product_services,'', array('class' => 'form-control select2 item ','data-url'=>route('bill.product'), 'required' => 'required')) }}
                                </td>
                                <td>
                                    <div class="form-group price-input input-group search-form">
                                        {{ Form::text('quantity','', array('class' => 'form-control quantity','placeholder'=>__('Qty'), 'required' => 'required')) }}
                                        <span class="unit input-group-text bg-transparent"></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-group price-input input-group search-form">
                                        {{ Form::text('price','', array('class' => 'form-control price','placeholder'=>__('Price'), 'required' => 'required')) }}
                                        <span class="input-group-text bg-transparent">{{\Auth::user()->currencySymbol()}}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-group price-input input-group search-form">
                                        {{ Form::text('discount','', array('class' => 'form-control discount','placeholder'=>__('Discount'), 'required' => 'required')) }}
                                        <span class="input-group-text bg-transparent">{{\Auth::user()->currencySymbol()}}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-group">
                                        <div class="input-group">
                                            <div class="taxes"></div>
                                            {{ Form::hidden('tax','', array('class' => 'form-control tax')) }}
                                            {{ Form::hidden('itemTaxPrice','', array('class' => 'form-control itemTaxPrice')) }}
                                            {{ Form::hidden('itemTaxRate','', array('class' => 'form-control itemTaxRate')) }}
                                        </div>
                                    </div>
                                </td>

                                <td class="text-end amount">
                                    0.00
                                </td>
                                <td>
                                    @can('delete proposal product')
                                    <div class="action-btn me-2">
                                        <a href="#" class="ti ti-trash text-white btn btn-sm repeater-action-btn bg-danger ms-2" data-repeater-delete></a>
                                    </div>
                                    @endcan
                                </td>
                            </tr>
                            <tr>

                                <td  class="form-group">
                                    {{-- {{ Form::select('chart_account_id', $chartAccounts,'', array('class' => 'form-control select2 js-searchBox')) }} --}}
                                    <select name="chart_account_id" class="form-control">
                                        @foreach ($chartAccounts as $key => $chartAccount)
                                            <option value="{{ $key }}" class="subAccount">{{ $chartAccount}}</option>
                                            @foreach ($subAccounts as $subAccount)
                                                @if ($key == $subAccount['account'])
                                                    <option value="{{ $subAccount['id'] }}" class="ms-5"> &nbsp; &nbsp;&nbsp; {{ $subAccount['name'] }}</option>
                                                @endif
                                            @endforeach
                                        @endforeach
                                    </select>
                                </td>
                                <td class="form-group">
                                    <div class="input-group">
                                        {{ Form::text('amount','', array('class' => 'form-control accountAmount','placeholder'=>__('Amount'))) }}
                                        <span class="input-group-text bg-transparent">{{\Auth::user()->currencySymbol()}}</span>
                                    </div>
                                </td>

                                <td colspan="2" class="form-group">
                                    {{ Form::textarea('description', null, ['class'=>'form-control pro_description','rows'=>'1','placeholder'=>__('Description')]) }}
                                </td>
                                <td></td>


{{--                                <td colspan="5"></td>--}}
                                <td class="text-end accountamount">
                                    0.00
                                </td>
                            </tr>

                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td></td>
                                    <td><strong>{{__('Sub Total')}} ({{\Auth::user()->currencySymbol()}})</strong></td>
                                    <td class="text-end subTotal">0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td></td>
                                    <td><strong>{{__('Discount')}} ({{\Auth::user()->currencySymbol()}})</strong></td>
                                    <td class="text-end totalDiscount">0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td></td>
                                    <td><strong>{{__('Tax')}} ({{\Auth::user()->currencySymbol()}})</strong></td>
                                    <td class="text-end totalTax">0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td class="blue-text"><strong>{{__('Total Amount')}} ({{\Auth::user()->currencySymbol()}})</strong></td>
                                <td class="blue-text text-end totalAmount">0.00</td>

                                    <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>


        <div class="modal-footer">
            <input type="button" value="{{__('Cancel')}}" onclick="location.href = '{{route("bill.index")}}';" class="btn btn-secondary me-2">
            <input type="submit" value="{{__('Create')}}" class="btn  btn-primary">
        </div>
        {{ Form::close() }}
    </div>
@endsection

