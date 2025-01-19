{{ Form::model($employee, array('route' => array('employee.salary.update', $employee->id), 'method' => 'POST', 'class'=>'needs-validation', 'novalidate')) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            {{ Form::label('salary_type', __('Payslip Type'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::select('salary_type',$payslip_type,null, array('class' => 'form-control select','required'=>'required')) }}
        </div>
        <div class="form-group col-md-12">
            {{ Form::label('salary', __('Salary'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::number('salary',null, array('class' => 'form-control ','required'=>'required', 'placeholder'=>__('Enter Salary'))) }}
        </div>
        <div class="form-group col-md-12">
            {{ Form::label('account', __('Bank Account'),['class'=>'form-label']) }}<x-required></x-required>
            {{ Form::select('account',$account,null, array('class' => 'form-control select','required'=>'required')) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn  btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Save Change')}}" class="btn  btn-primary">
</div>
{{ Form::close() }}
