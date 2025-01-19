    {{Form::model($plan, array('route' => array('plans.update', $plan->id), 'method' => 'PUT', 'enctype' => "multipart/form-data", 'class'=>'needs-validation', 'novalidate')) }}
    <div class="modal-body">
        {{-- start for ai module--}}
        @php
            $settings = \App\Models\Utility::settings();
        @endphp
        @if(!empty($settings['chat_gpt_key']))
        <div class="text-end">
            <a href="#" data-size="md" class="btn  btn-primary btn-icon btn-sm" data-ajax-popup-over="true" data-url="{{ route('generate',['plan']) }}"
               data-bs-placement="top" data-title="{{ __('Generate content with AI') }}">
                <i class="fas fa-robot"></i> <span>{{__('Generate with AI')}}</span>
            </a>
        </div>
        @endif
        {{-- end for ai module--}}

    <div class="row">
        <div class="form-group col-md-6">
            {{Form::label('name',__('Name'),['class'=>'form-label'])}}<x-required></x-required>
            {{Form::text('name',null,array('class'=>'form-control font-style','placeholder'=>__('Enter Plan Name'),'required'=>'required'))}}
        </div>
        @if($plan->price > 0)
            <div class="form-group col-md-6">
                {{Form::label('price',__('Price'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::number('price',null,array('class'=>'form-control','placeholder'=>__('Enter Plan Price'),'required'=>'required' ,'step' => '0.01'))}}
            </div>
            <div class="form-group col-md-6">
                {{ Form::label('duration', __('Duration'),['class'=>'form-label']) }}<x-required></x-required>
                {!! Form::select('duration', $arrDuration, null,array('class' => 'form-control select','required'=>'required')) !!}
            </div>
        @endif
        <div class="form-group col-md-6">
            {{Form::label('max_users',__('Maximum Users'),['class'=>'form-label'])}}<x-required></x-required>
            {{Form::number('max_users',null,array('class'=>'form-control','required'=>'required', 'placeholder' => __('Enter Maximum Users')))}}
            <span class="small">{{__('Note: "-1" for Unlimited')}}</span>
        </div>
        <div class="form-group col-md-6">
            {{Form::label('max_customers',__('Maximum Customers'),['class'=>'form-label'])}}<x-required></x-required>
            {{Form::number('max_customers',null,array('class'=>'form-control','required'=>'required', 'placeholder' => __('Enter Maximum Customers')))}}
            <span class="small">{{__('Note: "-1" for Unlimited')}}</span>
        </div>
        <div class="form-group col-md-6">
            {{Form::label('max_venders',__('Maximum Venders'),['class'=>'form-label'])}}<x-required></x-required>
            {{Form::number('max_venders',null,array('class'=>'form-control','required'=>'required', 'placeholder' => __('Enter Maximum Vendors')))}}
            <span class="small">{{__('Note: "-1" for Unlimited')}}</span>
        </div>
        <div class="form-group col-md-6">
            {{Form::label('max_clients',__('Maximum Clients'),['class'=>'form-label'])}}<x-required></x-required>
            {{Form::number('max_clients',null,array('class'=>'form-control','required'=>'required', 'placeholder' => __('Enter Maximum Clients')))}}
            <span class="small">{{__('Note: "-1" for Unlimited')}}</span>
        </div>
        <div class="form-group col-md-6">
            {{ Form::label('storage_limit', __('Storage limit'), ['class' => 'form-label']) }}<x-required></x-required>
            <div class="input-group">
                {{ Form::number('storage_limit', null,array('class'=>'form-control','required'=>'required', 'placeholder' => __('Maximum Storage Limit'))) }}
                <div class="input-group-append">
                <span class="input-group-text"
                      id="basic-addon2">{{__('MB')}}</span>
                </div>
            </div>
            {{-- <span class="small">{{__('Note: upload size ( In MB)')}}</span> --}}
        </div>


        <div class="form-group col-md-12">
            {{ Form::label('description', __('Description'),['class'=>'form-label']) }}
            {!! Form::textarea('description', null, ['class'=>'form-control','rows'=>'2', 'placeholder' => __('Enter Description')]) !!}
        </div>
        @if($plan->price > 0)
        <div class="col-md-6">
            <label class="form-check-label" for="trial"></label>
            <div class="form-group">
                <label for="trial" class="form-label">{{ __('Trial is enable(on/off)') }}</label>
                <div class="form-check form-switch custom-switch-v1 float-end">
                    <input type="checkbox" name="trial" class="form-check-input input-primary pointer" value="1" id="trial"  {{ $plan['trial'] == 1 ? 'checked="checked"' : '' }}>
                    <label class="form-check-label" for="trial"></label>
                </div>
            </div>
        </div>
        <div class="col-md-6 ">
            <div class="form-group plan_div  {{ $plan['trial'] == 1 ? 'd-block' : 'd-none' }}">
                {{ Form::label('trial_days', __('Trial Days'), ['class' => 'form-label']) }}
                {{ Form::number('trial_days',null, ['class' => 'form-control trial_days','placeholder' => __('Enter Trial days'),'step' => '1','min'=>'1']) }}
            </div>
        </div>
        @endif
        <div class="form-group col-md-3 mt-2">
            <div class="form-check form-switch ">
                <input type="checkbox" class="form-check-input" name="enable_crm" id="enable_crm" {{ $plan['crm'] == 1 ? 'checked="checked"' : '' }}>
                <label class="custom-control-label form-label" for="enable_crm">{{__('CRM')}}</label>
            </div>
        </div>
        <div class="form-group col-md-3 mt-2">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="enable_project" id="enable_project" {{ $plan['project'] == 1 ? 'checked="checked"' : '' }}>
                <label class="custom-control-label form-label" for="enable_project">{{__('Project')}}</label>
            </div>
        </div>
        <div class="form-group col-md-3 mt-2">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="enable_hrm" id="enable_hrm" {{ $plan['hrm'] == 1 ? 'checked="checked"' : '' }}>
                <label class="custom-control-label form-label" for="enable_hrm">{{__('HRM')}}</label>
            </div>
        </div>
        <div class="form-group col-md-3 mt-2">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="enable_account" id="enable_account" {{ $plan['account'] == 1 ? 'checked="checked"' : '' }}>
                <label class="custom-control-label form-label" for="enable_account">{{__('Account')}}</label>
            </div>
        </div>
        <div class="form-group col-md-3">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="enable_pos" id="enable_pos" {{ $plan['pos'] == 1 ? 'checked="checked"' : '' }}>
                <label class="custom-control-label form-label" for="enable_pos">{{__('POS')}}</label>
            </div>
        </div>
        <div class="form-group col-md-3">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="enable_chatgpt" id="enable_chatgpt" {{ $plan['chatgpt'] == 1 ? 'checked="checked"' : '' }}>
                <label class="custom-control-label form-label" for="enable_chatgpt">{{__('Chat GPT')}}</label>
            </div>
        </div>

    </div>
    </div>

    <div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn  btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Update')}}" class="btn  btn-primary">
</div>
    {{ Form::close() }}

