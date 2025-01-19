<!DOCTYPE html>
@php
    $settings = \App\Models\Utility::settingsById($invoice->created_by);
@endphp

<html lang="en" dir="{{ $settings['SITE_RTL'] == 'on' ? 'rtl' : '' }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>
            {{ \App\Models\Utility::invoiceNumberFormat($settings , $invoice->invoice_id) }}
            |
            
        </title>
        <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap"
            rel="stylesheet">

        @if ($settings['SITE_RTL'] == 'on')
            <link rel="stylesheet" href="{{ asset('css/template-rtl.css') }}">
        @else
            <link rel="stylesheet" href="{{ asset('css/template.css') }}">
        @endif
        <style type="text/css">
            :root {
                --theme-color:{{ $color }};
                --white: #ffffff;
                --black: #000000;
            }
            /*  */

                .hg-pdf table tr {
                page-break-inside: avoid;           
                }

        </style>
    </head>
    <body>
        <div class="invoice-preview-main no-padding-table" id="boxes">
            <div class="invoice-header" style="background-color: var(--theme-color); color: {{ $font_color }};">

                <header>
                    <div class="navbar d-flex justify-content-between align-items-center" style="margin-right: 21px;">
                        <div class="logo">
                            <div class="view-qrcode" style="margin-left: 0; margin-right: 0;">
                                <img src="{{ $img }}" alt="">
                            </div>
                        </div>
                        <div class="text-center" style="margin: auto;"><h1>{{__('INVOICE')}}</h1></div>
                    </div>
                </header>
                <div class="table-responsive">
                    <table class="vertical-align-top">
                        <tbody>
                        <tr>
                            <td>
                                <p>
                                    @if($settings['company_name']){{$settings['company_name']}}@endif<br>
                                    @if($settings['mail_from_address']){{$settings['mail_from_address']}}@endif<br><br>
                                    @if($settings['company_address']){{$settings['company_address']}}@endif
                                    @if($settings['company_city']) <br> {{$settings['company_city']}}, @endif
                                    @if($settings['company_state']){{$settings['company_state']}}@endif
                                    @if($settings['company_zipcode']) - {{$settings['company_zipcode']}}@endif
                                    @if($settings['company_country']) <br>{{$settings['company_country']}}@endif
                                    @if($settings['company_telephone']){{$settings['company_telephone']}}@endif<br>
                                    @if(!empty($settings['registration_number'])){{__('Registration Number')}} : {{$settings['registration_number']}} @endif<br>
                                    @if($settings['vat_gst_number_switch'] == 'on')
                                        @if(!empty($settings['tax_type']) && !empty($settings['vat_number'])){{$settings['tax_type'].' '. __('Number')}} : {{$settings['vat_number']}} <br>@endif
                                    @endif
                                </p>
                            </td>
                            <td>
                                <table class="no-space" style="">
                                    <tbody>
                                    <tr>
                                        <td>{{__('Number')}}:</td>
                                        <td class="text-right">{{Utility::invoiceNumberFormat($settings,$invoice->invoice_id)}}</td>
                                    </tr>
                                    <tr>
                                        <td>{{__('Issue Date')}}:</td>
                                        <td class="text-right">{{Utility::dateFormat($settings,$invoice->issue_date)}}</td>
                                    </tr>
            
                                    <tr>
                                        <td><b>{{__('Due Date:')}}</b></td>
                                        <td class="text-right">{{Utility::dateFormat($settings,$invoice->due_date)}}</td>
                                    </tr>
                                    @if(!empty($customFields) && count($invoice->customField)>0)
                                        @foreach($customFields as $field)
                                            <tr>
                                                <td>{{$field->name}} :</td>
                                                <td> {{!empty($invoice->customField)?$invoice->customField[$field->id]:'-'}}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                    <tr>
                                        <td colspan="2">
                                            <div class="view-qrcode">
                                                {!! DNS2D::getBarcodeHTML(route('invoice.link.copy',\Crypt::encrypt($invoice->invoice_id)), "QRCODE",2,2) !!}
                                            </div>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="invoice-body">
                <div class="table-responsive" style="margin-bottom: 30px;">
                <table class="vertical-align-top">
                    <tbody>
                        <tr>
                            <td>
                                <strong style="margin-bottom: 10px;">{{__('Bill To')}}:</strong>
                                @if(!empty($customer->billing_name))
                                    <p>
                                        {{!empty($customer->billing_name)?$customer->billing_name:''}}<br>
                                        {{!empty($customer->billing_address)?$customer->billing_address:''}}<br>
                                        {{!empty($customer->billing_city)?$customer->billing_city:'' .', '}}<br>
                                        {{!empty($customer->billing_state)?$customer->billing_state:'',', '}},
                                        {{!empty($customer->billing_zip)?$customer->billing_zip:''}}<br>
                                        {{!empty($customer->billing_country)?$customer->billing_country:''}}<br>
                                        {{!empty($customer->billing_phone)?$customer->billing_phone:''}}<br>
                                    </p>
                                @else
                                    -
                                @endif
                            </td>
            
                            @if($settings['shipping_display']=='on')
                                <td class="text-right">
                                    <strong style="margin-bottom: 10px;">{{__('Ship To')}}:</strong>
                                    @if(!empty($customer->shipping_name))
                                    <p>
                                        {{!empty($customer->shipping_name)?$customer->shipping_name:''}}<br>
                                        {{!empty($customer->shipping_address)?$customer->shipping_address:''}}<br>
                                        {{!empty($customer->shipping_city)?$customer->shipping_city:'' . ', '}}<br>
                                        {{!empty($customer->shipping_state)?$customer->shipping_state:'' .', '}},
                                        {{!empty($customer->shipping_zip)?$customer->shipping_zip:''}}<br>
                                        {{!empty($customer->shipping_country)?$customer->shipping_country:''}}<br>
                                        {{!empty($customer->shipping_phone)?$customer->shipping_phone:''}}<br>
                                    </p>
                                    @else
                                        -
                                    @endif
                                </td>
                            @endif
                        </tr>
                    </tbody>
                </table>
            </div>

                <h3 style="margin-bottom: 10px;padding: 0 15px;">{{__('Service Description')}}</h3>
                <div class="table-responsive" style="margin-bottom: 30px;">
                    <table class="border">
                        <thead style="background-color: var(--theme-color);color: {{ $font_color }};">
                            <tr>
                                <th>{{__('Item')}}</th>
                                <th>{{__('Description')}}</th>
                                <th>{{__('Quantity')}}</th>
                                <th>{{__('Rate')}}</th>
                                <th>{{__('Discount')}}</th>
                                <th>{{__('Tax')}} (%)</th>
                                <th>{{__('Price')}} <small>after tax & discount</small></th>
                            </tr>
                        </thead>

                        @if(isset($invoice->itemData) && count($invoice->itemData) > 0)
                        @foreach($invoice->itemData as $key => $item)
                        <tr>
                            <td>{{$item->name}}</td>
                            @php
                            $unitName = App\Models\ProductServiceUnit::find($item->unit);
                            @endphp
                            @if(!empty($item->description))
                                <td>{{$item->description}}</td>
                            @endif
                            <td>{{$item->quantity}} {{ ($unitName != null) ?  '('. $unitName->name .')' : ''}}</td>
                            <td>{{Utility::priceFormat($settings,$item->price)}}</td>
                            <td>{{($item->discount!=0)?Utility::priceFormat($settings,$item->discount):'-'}}</td>
                            @php
                                $itemtax = 0;
                            @endphp
                            <td>
                                @if(!empty($item->itemTax))
                                    @foreach($item->itemTax as $taxes)
                                        @php
                                            $itemtax += $taxes['tax_price'];
                                        @endphp
                                        <p>{{$taxes['name']}} ({{$taxes['rate']}}) {{$taxes['price']}}</p>
                                    @endforeach
                                @else
                                    <span>-</span>
                                @endif
                            </td>
                            <td>{{Utility::priceFormat($settings,$item->price * $item->quantity -  $item->discount + $itemtax)}}</td>
                        </tr>
                        @endforeach
                    @else
                    @endif
                        <tr>
                            <td>{{ __('Total') }}</td>
                            <td></td>
                            <td>{{ $invoice->totalQuantity }}</td>
                            <td>{{Utility::priceFormat($settings,$invoice->totalRate)}}</td>
                            <td>{{Utility::priceFormat($settings,$invoice->totalDiscount)}}</td>
                            <td>{{Utility::priceFormat($settings,$invoice->totalTaxPrice) }}</td>
                            <td>{{Utility::priceFormat($settings,$invoice->getSubTotal())}}</td>
                        </tr>
                    </table>
                </div>

                <h3 style="margin-bottom: 10px;padding: 0 15px;">{{__('Total Amounts')}}</h3>
                <div class="table-responsive hg-pdf">
                    <table class="border invoice-summary" style="min-width: 400px;">
                        <tr>
                            <td>{{__('Subtotal')}}:</td>
                            <td>{{Utility::priceFormat($settings,$invoice->getSubTotal())}}</td>
                        </tr>
                        @if($invoice->getTotalDiscount())
                        <tr>
                            <td>{{__('Discount')}}:</td>
                            <td>{{Utility::priceFormat($settings,$invoice->getTotalDiscount())}}</td>
                        </tr>
                        @endif
                        @if(!empty($invoice->taxesData))
                            @foreach($invoice->taxesData as $taxName => $taxPrice)
                            <tr>
                                <td>{{$taxName}} :</td>
                                <td>{{ Utility::priceFormat($settings,$taxPrice)  }}</td>
                            </tr>
                            @endforeach
                        @endif
                        <tr>
                            <td>{{__('Total')}}:</td>
                            <td>{{Utility::priceFormat($settings,$invoice->getSubTotal()-$invoice->getTotalDiscount()+$invoice->getTotalTax())}}</td>
                        </tr>
                        <tr>
                            <td>{{__('Paid')}}:</td>
                            <td>{{Utility::priceFormat($settings,($invoice->getTotal()-$invoice->getDue())-($invoice->invoiceTotalCreditNote()))}}</td>
                        </tr>
                        <tr>
                            <td>{{__('Credit Note')}}:</td>
                            <td>{{Utility::priceFormat($settings,($invoice->invoiceTotalCreditNote()))}}</td>
                        </tr>
                        <tr>
                            <td>{{__('Due Amount')}}:</td>
                            <td>{{Utility::priceFormat($settings,$invoice->getDue())}}</td>
                        </tr>
                    </table>
                </div>
                <div class="invoice-footer">
                    <p> {{ $settings['footer_title'] }} <br>
                        {{ $settings['footer_notes'] }} </p>
                </div>
            </div>
        </div>
        @if (!isset($preview))
            @include('invoice.script');
        @endif
    </body>
</html>
