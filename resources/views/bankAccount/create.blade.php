{{ Form::open(array('url' => 'bank-account', 'class'=>'needs-validation', 'novalidate')) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6">
            {{ Form::label('chart_account_id', __('Account'),['class'=>'form-label']) }}<x-required></x-required>
            {{-- {{ Form::select('chart_account_id', $chart_accounts,'', array('class' => 'form-control select','required'=>'required')) }} --}}
            <select name="chart_account_id" class="form-control" required="required">
                @foreach ($chartAccounts as $key => $chartAccount)
                    <option value="{{ $key }}" class="subAccount">{{ $chartAccount }}</option>
                    @foreach ($subAccounts as $subAccount)
                        @if ($key == $subAccount['account'])
                            <option value="{{ $subAccount['id'] }}" class="ms-5"> &nbsp; &nbsp;&nbsp; {{ $subAccount['code_name'] }}</option>
                        @endif
                    @endforeach
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('holder_name', __('Bank Holder Name'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::text('holder_name', '', array('class' => 'form-control','required'=>'required' , 'placeholder'=>__('Enter Bank Holder Name'))) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('bank_name', __('Bank Name'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::text('bank_name', '', array('class' => 'form-control','required'=>'required' , 'placeholder' => __('Enter Bank Name'))) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('account_number', __('Account Number'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::text('account_number', '', array('class' => 'form-control','required'=>'required' , 'placeholder' => __('Enter Account Number'))) }}
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('opening_balance', __('Opening Balance'),['class'=>'form-label']) }}
            {{ Form::number('opening_balance', '', array('class' => 'form-control','step'=>'0.01' , 'placeholder'=>__('Enter Opening Balance'))) }}
        </div>
        <div class="form-group col-md-6">
            {{-- {{ Form::label('contact_number', __('Contact Number'),['class'=>'form-label']) }}
            {{ Form::text('contact_number', '', array('class' => 'form-control' , 'placeholder'=>__('Enter Contact Number'))) }} --}}
            <x-mobile label="{{__('Contact Number')}}" name="contact_number" value="{{old('contact_number')}}" placeholder="Enter Contact Number"></x-mobile>
        </div>
        <div class="form-group col-md-12">
            {{ Form::label('bank_address', __('Bank Address'),['class'=>'form-label']) }}
            {{ Form::textarea('bank_address', '', array('class' => 'form-control','rows'=>3 , 'placeholder' => __('Enter Bank Address'))) }}
        </div>
        @if(!$customFields->isEmpty())
            <div class="col-md-12">
                <div class="tab-pane fade show" id="tab-2" role="tabpanel">
                    @include('customFields.formBuilder')
                </div>
            </div>
        @endif

    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn  btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Create')}}" class="btn  btn-primary">
</div>
{{ Form::close() }}
