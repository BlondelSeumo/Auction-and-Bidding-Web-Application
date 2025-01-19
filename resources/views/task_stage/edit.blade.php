{{ Form::model($taskStage, array('route' => array('project-task-stages.update', $taskStage->id), 'method' => 'PUT','class'=>'needs-validation', 'novalidate')) }}
<div class="modal-body">
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {{Form::label('name',__('Project Task Stage Name'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::text('name',null,array('class'=>'form-control', 'placeholder'=>__('Enter Project Stage Name'), 'required' => 'required'))}}
            </div>
        </div>
        <div class="form-group col-12">
            {{ Form::label('color', __('Color'),['class'=>'form-label']) }}<x-required></x-required>
            <input class="jscolor form-control " value="{{ $taskStage->color }}" name="color" id="color" required>
            <small class="small">{{ __('For chart representation') }}</small>
        </div>

    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn  btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Update')}}" class="btn  btn-primary">
</div>
{{Form::close()}}

