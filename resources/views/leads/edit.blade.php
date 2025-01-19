{{ Form::model($lead, array('route' => array('leads.update', $lead->id), 'method' => 'PUT', 'class'=>'needs-validation', 'novalidate')) }}
<div class="modal-body">
    {{-- start for ai module--}}
    @php
        $plan= \App\Models\Utility::getChatGPTSettings();
    @endphp
    @if($plan->chatgpt == 1)
    <div class="text-end">
        <a href="#" data-size="md" class="btn  btn-primary btn-icon btn-sm" data-ajax-popup-over="true" data-url="{{ route('generate',['lead']) }}"
           data-bs-placement="top" data-title="{{ __('Generate content with AI') }}">
            <i class="fas fa-robot"></i> <span>{{__('Generate with AI')}}</span>
        </a>
    </div>
    @endif
    {{-- end for ai module--}}
    <div class="row">
        <div class="col-6 form-group">
            {{ Form::label('subject', __('Subject'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::text('subject', null, array('class' => 'form-control','required'=>'required', 'placeholder'=>__('Enter Subject'))) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('user_id', __('User'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::select('user_id', $users,null, array('class' => 'form-control select','required'=>'required')) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('name', __('Name'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::text('name', null, array('class' => 'form-control','required'=>'required', 'placeholder'=>__('Enter Name'))) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('email', __('Email'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::email('email', null, array('class' => 'form-control','required'=>'required', 'placeholder'=>__('Enter Email'))) }}
        </div>
        <div class="col-6 form-group">
            {{-- {{ Form::label('phone', __('Phone'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::text('phone', null, array('class' => 'form-control','required'=>'required')) }} --}}
            <x-mobile label="{{__('Phone')}}" name="phone" value="{{$lead->phone}}" required placeholder="Enter Phone"></x-mobile>
        </div>
        <div class="col-6 form-group">
            {{ Form::label('pipeline_id', __('Pipeline'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::select('pipeline_id', $pipelines,null, array('class' => 'form-control select','required'=>'required')) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('stage_id', __('Stage'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::select('stage_id', [''=>__('Select Stage')],null, array('class' => 'form-control select','required'=>'required')) }}
        </div>
        <div class="col-12 form-group">
            {{ Form::label('sources', __('Sources'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::select('sources[]', $sources,null, array('class' => 'form-control select2','id'=>'choices-multiple2','multiple'=>'', 'required' => 'required', 'placeholder'=>__('Select Source'))) }}
        </div>
        <div class="col-12 form-group">
            {{ Form::label('products', __('Products'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::select('products[]', $products,null, array('class' => 'form-control select2','id'=>'choices-multiple1','multiple'=>'', 'required' => 'required', 'placeholder'=>__('Select Product'))) }}
        </div>
        <div class="col-12 form-group">
            {{ Form::label('notes', __('Notes'),['class'=>'form-label']) }}
            {{ Form::textarea('notes',null, array('class' => 'summernote-simple')) }}
        </div>
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn  btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Update')}}" class="btn  btn-primary">
</div>

{{Form::close()}}



<script>
    var stage_id = '{{$lead->stage_id}}';

    $(document).ready(function () {
        var pipeline_id = $('[name=pipeline_id]').val();
        getStages(pipeline_id);
    });

    $(document).on("change", "#commonModal select[name=pipeline_id]", function () {
        var currVal = $(this).val();
        console.log('current val ', currVal);
        getStages(currVal);
    });

    function getStages(id) {
        $.ajax({
            url: '{{route('leads.json')}}',
            data: {pipeline_id: id, _token: $('meta[name="csrf-token"]').attr('content')},
            type: 'POST',
            success: function (data) {
                var stage_cnt = Object.keys(data).length;
                $("#stage_id").empty();
                if (stage_cnt > 0) {
                    $.each(data, function (key, data1) {
                        var select = '';
                        if (key == '{{ $lead->stage_id }}') {
                            select = 'selected';
                        }
                        $("#stage_id").append('<option value="' + key + '" ' + select + '>' + data1 + '</option>');
                    });
                }
                $("#stage_id").val(stage_id);
                $('#stage_id').select2({
                    placeholder: "{{__('Select Stage')}}"
                });
            }
        })
    }
</script>
