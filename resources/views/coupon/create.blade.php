{{ Form::open(array('url' => 'coupons','method' =>'post', 'class'=>'needs-validation', 'novalidate')) }}
<div class="modal-body">
    {{-- start for ai module--}}
    @php
        $settings = \App\Models\Utility::settings();
    @endphp
    @if(!empty($settings['chat_gpt_key']))
    <div class="text-end">
        <a href="#" data-size="md" class="btn  btn-primary btn-icon btn-sm" data-ajax-popup-over="true" data-url="{{ route('generate',['coupon']) }}"
           data-bs-placement="top" data-title="{{ __('Generate content with AI') }}">
            <i class="fas fa-robot"></i> <span>{{__('Generate with AI')}}</span>
        </a>
    </div>
    @endif
    {{-- end for ai module--}}
    <div class="row">
        <div class="form-group col-md-12">
            {{Form::label('name',__('Name'),['class'=>'form-label'])}}<x-required></x-required>
            {{Form::text('name',null,array('class'=>'form-control font-style','required'=>'required', 'placeholder' => __('Enter Name')))}}
        </div>

        <div class="form-group col-md-6">
            {{Form::label('discount',__('Discount'),['class'=>'form-label'])}}<x-required></x-required>
            {{Form::number('discount',null,array('class'=>'form-control','required'=>'required','step'=>'0.01', 'placeholder' => __('Enter Discount')))}}
            <span class="small">{{__('Note: Discount in Percentage')}}</span>
        </div>
        <div class="form-group col-md-6">
            {{Form::label('limit',__('Limit'),['class'=>'form-label'])}}<x-required></x-required>
            {{Form::number('limit',null,array('class'=>'form-control','required'=>'required', 'placeholder' => __('Enter Limit')))}}
        </div>


        <div class="form-group col-md-12">
            {{Form::label('code',__('Code'),['class'=>'form-label'])}}<x-required></x-required>
            <div class="d-flex radio-check">
                <div class="form-check form-check-inline form-group col-md-6">
                    <input type="radio" id="manual_code" value="manual" name="icon-input" class="form-check-input code" checked="checked">
                    <label class="custom-control-label " for="manual_code">{{__('Manual')}}</label>
                </div>
                <div class="form-check form-check-inline form-group col-md-6">
                    <input type="radio" id="auto_code" value="auto" name="icon-input" class="form-check-input code">
                    <label class="custom-control-label" for="auto_code">{{__('Auto Generate')}}</label>
                </div>
            </div>
        </div>

        <div class="form-group col-md-12 d-block" id="manual">
            <input class="form-control font-uppercase" name="manualCode" type="text" id="manual-code" placeholder="{{ __('Enter Code') }}">
        </div>
        <div class="form-group col-md-12 d-none" id="auto">
            <div class="row">
                <div class="col-md-10">
                    <input class="form-control" name="autoCode" type="text" id="auto-code" placeholder="{{ __('Enter Code') }}">
                </div>
                <div class="col-md-2 ">
                    <a href="#" class="btn btn-primary" id="code-generate"><i class="ti ti-history"></i></a>
                </div>
            </div>
        </div>

    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn  btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Create')}}" class="btn  btn-primary">
</div>
{{ Form::close() }}
