@extends('layouts.auth')
@php
  //  $logo=asset(Storage::url('uploads/logo/'));
    $logo=\App\Models\Utility::get_file('uploads/logo');
 $company_logo=Utility::getValByName('company_logo');
@endphp
@section('page-title')
    {{__('Forgot Password')}}
@endsection

@php
    $languages = App\Models\Utility::languages();
@endphp
{{-- @section('language-bar')
    <div class="lang-dropdown-only-desk">
        <li class="dropdown dash-h-item drp-language">
            <a class="dash-head-link dropdown-toggle btn" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="drp-text"> {{ $languages[$lang] }}
                </span>
            </a>
            <div class="dropdown-menu dash-h-dropdown dropdown-menu-end">
                @foreach($languages as $code => $language)
                <a href="{{ route('password.reset',$code) }}"tabindex="0"
                class="dropdown-item ">
                <span>{{ Str::ucfirst($language) }}</span>
            </a>
                @endforeach
            </div>
        </li>
    </div>
@endsection --}}

@section('content')
    <div class="card-body">
        <div>
            <h2 class="mb-3 f-w-600">{{ __('Reset Password') }}</h2>
            {{-- <p>{{ __('Sign in by entering the information below?') }} </p> --}}
        </div>
    {{Form::open(array('route'=>'password.update','method'=>'post','id'=>'loginForm'))}}
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    <div class="">
        <div class="form-group mb-3">
            {{Form::label('email',__('E-Mail Address'),['class'=>'form-label'])}}
            {{Form::text('email',null,array('class'=>'form-control' , 'placeholder'=>__('Enter email')))}}
            @error('email')
            <span class="invalid-email text-danger" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
            @enderror
        </div>
        <div class="form-group mb-3">
            {{Form::label('password',__('Password'),['class'=>'form-label'])}}
            {{Form::password('password',array('class'=>'form-control' , 'placeholder'=>__('Enter Password')))}}
            @error('password')
            <span class="invalid-password text-danger" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
            @enderror
        </div>
        <div class="form-group mb-3">
            {{Form::label('password_confirmation',__('Password Confirmation'),['class'=>'form-label'])}}
            {{Form::password('password_confirmation',array('class'=>'form-control' , 'placeholder'=>__('Enter Confirm Password')))}}
            @error('password_confirmation')
            <span class="invalid-password_confirmation text-danger" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
            @enderror
        </div>
        <div class="d-grid">
            {{Form::submit(__('Reset'),array('class'=>'btn btn-primary btn-block mt-2','id'=>'resetBtn'))}}
        </div>

    </div>

    {{Form::close()}}
</div>

@endsection
