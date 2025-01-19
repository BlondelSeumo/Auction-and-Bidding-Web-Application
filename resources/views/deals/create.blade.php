{{ Form::open(array('url' => 'deals', 'class'=>'needs-validation', 'novalidate')) }}
<div class="modal-body">
    {{-- start for ai module--}}
    @php
        $plan= \App\Models\Utility::getChatGPTSettings();
    @endphp
    @if($plan->chatgpt == 1)
    <div class="text-end">
        <a href="#" data-size="md" class="btn  btn-primary btn-icon btn-sm" data-ajax-popup-over="true" data-url="{{ route('generate',['deal']) }}"
           data-bs-placement="top" data-title="{{ __('Generate content with AI') }}">
            <i class="fas fa-robot"></i> <span>{{__('Generate with AI')}}</span>
        </a>
    </div>
    @endif
    {{-- end for ai module--}}
    <div class="row">
        <div class="col-6 form-group">
            {{ Form::label('name', __('Deal Name'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::text('name', null, array('class' => 'form-control','required'=>'required', 'placeholder'=>__('Enter Deal Name'))) }}
        </div>
        <div class="col-6 form-group">
            {{-- {{ Form::label('phone', __('Phone'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::text('phone', null, array('class' => 'form-control','required'=>'required')) }} --}}
            <x-mobile label="{{__('Phone')}}" name="phone" value="{{old('phone')}}" required placeholder="Enter Phone"></x-mobile>
        </div>
        <div class="col-6 form-group">
            {{ Form::label('price', __('Price'),['class'=>'form-label']) }}
            {{ Form::number('price', 0, array('class' => 'form-control','min'=>0, 'placeholder'=>__('Enter Price'))) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('clients', __('Clients'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::select('clients[]', $clients,null, array('class' => 'form-control select2','multiple'=>'','id'=>'choices-multiple1','required'=>'required', 'placeholder'=>__('Select Client'))) }}
            @if(count($clients) <= 0 && Auth::user()->type == 'Owner')
                <div class="text-muted text-xs">
                    {{__('Please create new clients')}} <a href="{{route('clients.index')}}">{{__('here')}}</a>.
                </div>
            @endif
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn  btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Create')}}" class="btn  btn-primary">
</div>
{{Form::close()}}
