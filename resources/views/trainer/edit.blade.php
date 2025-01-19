{{Form::model($trainer,array('route' => array('trainer.update', $trainer->id), 'method' => 'PUT', 'class'=>'needs-validation', 'novalidate')) }}
<div class="modal-body">
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {{Form::label('branch',__('Branch'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::select('branch',$branches,null,array('class'=>'form-control select','required'=>'required'))}}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{Form::label('firstname',__('First Name'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::text('firstname',null,array('class'=>'form-control','required'=>'required', 'placeholder'=>__('Enter First Name')))}}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{Form::label('lastname',__('Last Name'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::text('lastname',null,array('class'=>'form-control','required'=>'required', 'placeholder'=>__('Enter Last Name')))}}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{-- {{Form::label('contact',__('Contact'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::text('contact',null,array('class'=>'form-control','required'=>'required'))}} --}}
                <x-mobile label="{{__('Contact')}}" name="contact" value="{{$trainer->contact}}" required placeholder="{{__('Enter Contact')}}"></x-mobile>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{Form::label('email',__('Email'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::text('email',null,array('class'=>'form-control','required'=>'required', 'placeholder'=>__('Enter email')))}}
            </div>
        </div>
        <div class="form-group col-lg-12">
            {{Form::label('expertise',__('Expertise'),['class'=>'form-label'])}}
            {{Form::textarea('expertise',null,array('class'=>'form-control','placeholder'=>__('Enter Expertise')))}}
        </div>
        <div class="form-group col-lg-12">
            {{Form::label('address',__('Address'),['class'=>'form-label'])}}
            {{Form::textarea('address',null,array('class'=>'form-control','placeholder'=>__('Enter Address')))}}
        </div>

    </div>
</div>

    <div class="modal-footer">
        <input type="button" value="{{__('Cancel')}}" class="btn  btn-secondary" data-bs-dismiss="modal">
        <input type="submit" value="{{__('Update')}}" class="btn  btn-primary">
    </div>
{{Form::close()}}
