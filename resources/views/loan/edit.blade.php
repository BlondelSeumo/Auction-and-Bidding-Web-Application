{{Form::model($loan,array('route' => array('loan.update', $loan->id), 'method' => 'PUT', 'class'=>'needs-validation', 'novalidate')) }}
<div class="modal-body">
    <div class="card-body p-0">
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    {{ Form::label('title', __('Title')) }}<x-required></x-required>
                    {{ Form::text('title',null, array('class' => 'form-control ','required'=>'required', 'placeholder'=>__('Enter Title'))) }}
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {{ Form::label('loan_option', __('Loan Options')) }}<x-required></x-required>
                    {{ Form::select('loan_option',$loan_options,null, array('class' => 'form-control select','required'=>'required')) }}
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {{ Form::label('type', __('Type'), ['class' => 'form-label']) }}<x-required></x-required>
                    {{ Form::select('type', $loans, null, ['class' => 'form-control select amount_type', 'required' => 'required']) }}
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {{ Form::label('amount', __('Loan Amount'),['class'=>'form-label amount_label']) }}<x-required></x-required>
                    {{ Form::number('amount',null, array('class' => 'form-control ','required'=>'required', 'placeholder'=>__('Enter Amount'))) }}
                </div>
            </div>
{{--            <div class="col-md-6">--}}
{{--                <div class="form-group">--}}
{{--                    {{ Form::label('start_date', __('Start Date')) }}--}}
{{--                    {{ Form::date('start_date',null, array('class' => 'form-control ','required'=>'required')) }}--}}
{{--                </div>--}}
{{--            </div>--}}
{{--            <div class="col-md-6">--}}
{{--                <div class="form-group">--}}
{{--                    {{ Form::label('end_date', __('End Date')) }}--}}
{{--                    {{ Form::date('end_date',null, array('class' => 'form-control ','required'=>'required')) }}--}}
{{--                </div>--}}
{{--            </div>--}}
            <div class="col-md-12">
                <div class="form-group">
                    {{ Form::label('reason', __('Reason')) }}<x-required></x-required>
                    {{ Form::textarea('reason',null, array('class' => 'form-control ','required'=>'required','rows' => 3, 'placeholder'=>__('Enter Reason'))) }}
                </div>
            </div>
        </div>

    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn  btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Update')}}" class="btn  btn-primary">
</div>
{{Form::close()}}
