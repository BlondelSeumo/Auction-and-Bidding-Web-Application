@php
    $path =\App\Models\Utility::get_file('uploads/order');
@endphp
{{ Form::open(['route' => ['order.changestatus',$order->id],'method' => 'post']) }}
<div class="modal-body">
    <div class="row">
        <div class="col-12">
            <table class="table modal-table">
                <tr role="row">
                    {{--                    @dd($order)--}}
                    <th>{{__('Order Id')}}</th>
                    <td>{{$order->order_id}}</td>
                </tr>

                <tr>
                    <th>{{__('Plan Name')}}</th>
                    <td>{{$order->plan_name}}</td>
                </tr>
                <tr>
                    <th>{{__('Plan Price')}}</th>
                    <td>{{$order->price}}</td>
                </tr>
                <tr>
                    <th>{{__('Payment Type')}}</th>
                    <td>{{$order->payment_type}}</td>
                </tr>
                <tr>
                    <th>{{__('Payment Status')}}</th>
                    <td>{{$order->payment_status}}</td>
                </tr>
                <tr>
                    <th>{{__('Bank Details')}}</th>
                    <td>{!! $admin_payment_setting['bank_details'] !!}</td>
                </tr>
                @if(!empty( $order->receipt))
                    <tr>
                        <th>{{__('Payment Receipt')}}</th>
                        <td>
                            <a  class="action-btn bg-primary ms-2 btn btn-sm align-items-center" href="{{ $path . '/' . $order->receipt }}" download=""  data-bs-toggle="tooltip" title="{{__('Download')}}" target="_blank">
                                <i class="ti ti-download text-white"></i>
                            </a>
                        </td>
                    </tr>
                @endif
                <input type="hidden" value="{{ $order->id }}" name="order_id">
            </table>
        </div>
    </div>

</div>
<div class="modal-footer">
    <input type="submit" value="{{__('Approval')}}" class="btn btn-secondary" data-bs-dismiss="modal" name="status">
    <input type="submit" value="{{__('Reject')}}" class="btn btn-danger" name="status">
</div>
{{Form::close()}}
