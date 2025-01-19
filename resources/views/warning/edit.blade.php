{{Form::model($warning,array('route' => array('warning.update', $warning->id), 'method' => 'PUT', 'class'=>'needs-validation', 'novalidate')) }}
<div class="modal-body">
    {{-- start for ai module--}}
    @php
        $plan= \App\Models\Utility::getChatGPTSettings();
    @endphp
    @if($plan->chatgpt == 1)
    <div class="text-end">
        <a href="#" data-size="md" class="btn  btn-primary btn-icon btn-sm" data-ajax-popup-over="true" data-url="{{ route('generate',['warning']) }}"
           data-bs-placement="top" data-title="{{ __('Generate content with AI') }}">
            <i class="fas fa-robot"></i> <span>{{__('Generate with AI')}}</span>
        </a>
    </div>
    @endif
    {{-- end for ai module--}}

     <div class="row">
        @if(\Auth::user()->type != 'Employee')
            <div class="form-group col-md-6 col-lg-6">
                {{ Form::label('warning_by', __('Warning By'),['class'=>'form-label'])}}
                {{ Form::select('warning_by', $employees,null, array('class' => 'form-control select','required'=>'required')) }}
            </div>
        @endif
        <div class="form-group col-lg-6 col-md-6">
            {{Form::label('warning_to',__('Warning To'),['class'=>'form-label'])}}
            {{Form::select('warning_to',$employees,null,array('class'=>'form-control select','required'=>'required'))}}
        </div>
        <div class="form-group col-lg-6 col-md-6">
            {{Form::label('subject',__('Subject'),['class'=>'form-label'])}}
            {{Form::text('subject',null,array('class'=>'form-control','required'=>'required', 'placeholder'=>__('Enter Subject')))}}
        </div>
        <div class="form-group col-lg-6 col-md-6">
            {{Form::label('warning_date',__('Warning Date'),['class'=>'form-label'])}}
            {{Form::date('warning_date',null,array('class'=>'form-control ','required'=>'required'))}}
        </div>
        <div class="form-group col-lg-12">
            {{Form::label('description',__('Description'),['class'=>'form-label'])}}
            {{Form::textarea('description',null,array('class'=>'form-control','placeholder'=>__('Enter Description')))}}
        </div>

    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn  btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Update')}}" class="btn  btn-primary">
</div>
   {{Form::close()}}

