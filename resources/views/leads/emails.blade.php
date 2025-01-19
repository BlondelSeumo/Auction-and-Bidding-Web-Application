{{ Form::open(array('route' => ['leads.emails.store',$lead->id], 'class'=>'needs-validation', 'novalidate')) }}
<div class="modal-body">
    <div class="row">
        <div class="col-6 form-group">
            {{ Form::label('to', __('Mail To'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::email('to', null, array('class' => 'form-control','required'=>'required', 'placeholder'=>__('Enter Mail To'))) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('subject', __('Subject'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::text('subject', null, array('class' => 'form-control','required'=>'required', 'placeholder'=>__('Enter Subject'))) }}
        </div>
        <div class="col-12 form-group">
            {{ Form::label('description', __('Description'),['class'=>'form-label']) }}
        {{ Form::textarea('description', null, array('class' => 'summernote-simple', 'placeholder'=>__('Enter Description'))) }}
        </div>
        <script>
            $('#emails-summernote').summernote();
        </script>

    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Create')}}" class="btn btn-primary">
</div>

{{Form::close()}}
