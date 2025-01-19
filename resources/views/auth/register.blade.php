@extends('layouts.auth')
@section('page-title')
    {{ __('Register') }}
@endsection
@php
    $settings = Utility::settings();
    $logo = \App\Models\Utility::get_file('uploads/logo');
    $setting = \Modules\LandingPage\Entities\LandingPageSetting::settings();

@endphp
@push('custom-scripts')
    @if ($settings['recaptcha_module'] == 'on')
        {!! NoCaptcha::renderJs() !!}
    @endif
@endpush
@if ($settings['cust_darklayout'] == 'on')
    <style>
        .g-recaptcha {
            filter: invert(1) hue-rotate(180deg) !important;
        }
    </style>
@endif
@php
    $languages = App\Models\Utility::languages();
@endphp
@section('language-bar')
    <div class="lang-dropdown-only-desk">
        <li class="dropdown dash-h-item drp-language">
            <a class="dash-head-link dropdown-toggle btn" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="drp-text"> {{ $languages[$lang] }}
                </span>
            </a>
            <div class="dropdown-menu dash-h-dropdown dropdown-menu-end">
                @foreach ($languages as $code => $language)
                    <a href="{{ route('register', [$ref, $code]) }}" tabindex="0" class="dropdown-item ">
                        <span>{{ Str::ucfirst($language) }}</span>
                    </a>
                @endforeach
            </div>
        </li>
    </div>
@endsection

@section('content')
    <div class="card-body">
        <div>
            <h2 class="mb-3 f-w-600">{{ __('Register') }}</h2>
        </div>
        <form method="POST" action="{{ route('register.store', ['plan' => $plan]) }}" class='needs-validation' novalidate>
            @if (session('status'))
                <div class="mb-4 font-medium text-lg text-green-600 text-danger">
                    {{ __('Email SMTP settings does not configured so please contact to your site admin.') }}
                </div>
            @endif
            @csrf
            <div class="">
                <div class="form-group mb-3">
                    <label for="name" class="form-label">{{ __('Name') }}</label>
                    <input id="name" type="text" class="form-control @error('name') is-invalid @enderror"
                        name="name" value="{{ old('name') }}" autocomplete="name" autofocus
                        placeholder="{{ __('Enter Name') }}" required="required">
                    @error('name')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                <div class="form-group mb-3">
                    <label for="email" class="form-label">{{ __('Email') }}</label>
                    <input class="form-control @error('email') is-invalid @enderror" id="email" type="email"
                        name="email" value="{{ old('email') }}" autocomplete="email" autofocus
                        placeholder="{{ __('Enter Email') }}" required="required">
                    @error('email')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                    {{-- <div class="invalid-feedback">
                        {{ __('Please fill in your email') }}
                    </div> --}}
                </div>
                <div class="form-group mb-3">
                    <label for="password" class="form-label">{{ __('Password') }}</label>
                    <input id="password" type="password" data-indicator="pwindicator"
                        class="form-control pwstrength @error('password') is-invalid @enderror" name="password"
                        autocomplete="new-password" placeholder="{{ __('Enter Password') }}" required="required">
                    @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                    <div id="pwindicator" class="pwindicator">
                        <div class="bar"></div>
                        <div class="label"></div>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label for="password_confirmation" class="form-label">{{ __('Password Confirmation') }}</label>
                    <input id="password_confirmation" type="password" data-indicator="password_confirmation"
                        class="form-control pwstrength @error('password_confirmation') is-invalid @enderror"
                        name="password_confirmation" autocomplete="new-password"
                        placeholder="{{ __('Enter Confirm Password') }}" required="required">
                    @error('password_confirmation')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                    <div id="password_confirmation" class="pwindicator">
                        <div class="bar"></div>
                        <div class="label"></div>
                    </div>
                </div>
                <div class="form-check custom-checkbox">
                    <input type="checkbox" class="form-check-input" id="termsCheckbox" name="terms" required="required">
                    <label class="form-check-label text-sm" for="termsCheckbox">{{ __('I agree to the ') }}
                        @if (is_array(json_decode($setting['menubar_page'])) || is_object(json_decode($setting['menubar_page'])))
                            @foreach (json_decode($setting['menubar_page']) as $key => $value)
                                @if (in_array($value->menubar_page_name, ['Terms and Conditions']) && isset($value->template_name))
                                    <a href="{{ $value->template_name == 'page_content' ? route('custom.page', $value->page_slug) : $value->page_url }}"
                                        target="_blank">{{ $value->menubar_page_name }}</a>
                                @endif
                            @endforeach
                            {{ __('and the ') }}
                            @foreach (json_decode($setting['menubar_page']) as $key => $value)
                                @if (in_array($value->menubar_page_name, ['Privacy Policy']) && isset($value->template_name))
                                    <a href="{{ $value->template_name == 'page_content' ? route('custom.page', $value->page_slug) : $value->page_url }}"
                                        target="_blank">{{ $value->menubar_page_name }}</a>
                                @endif
                            @endforeach
                        @endif
                    </label>
                </div>
                    @if ($settings['recaptcha_module'] == 'on')
                    @if (isset($settings['google_recaptcha_version']) && $settings['google_recaptcha_version'] == 'v2-checkbox')
                        <div class="form-group col-lg-12 col-md-12 mt-3">
                            {!! NoCaptcha::display() !!}
                            @error('g-recaptcha-response')
                                <span class="small text-danger" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    @else
                        <div class="form-group col-lg-12 col-md-12 mt-3">
                            <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response" class="form-control">
                            @error('g-recaptcha-response')
                                <span class="error small text-danger" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    @endif
                @endif

                <div class="d-grid">
                    <input type="hidden" name="ref_code" value="{{ $ref }}">
                    <button type="submit" class="btn btn-primary btn-block mt-2">{{ __('Register') }}</button>
                </div>

            </div>
            <p class="my-4 text-center">{{ __('Already have an account?') }} <a href="{{ route('login', $lang) }}"
                    class="text-primary">{{ __('Login') }}</a></p>
        </form>

    </div>
@endsection
@if (isset($settings['recaptcha_module']) && $settings['recaptcha_module'] == 'on')
    @if (isset($settings['google_recaptcha_version']) && $settings['google_recaptcha_version'] == 'v2-checkbox')
        {!! NoCaptcha::renderJs() !!}
    @else
        <script src="https://www.google.com/recaptcha/api.js?render={{ $settings['google_recaptcha_key'] }}"></script>
        <script>
            $(document).ready(function() {
                grecaptcha.ready(function() {
                    grecaptcha.execute('{{ $settings['google_recaptcha_key'] }}', {
                        action: 'submit'
                    }).then(function(token) {
                        $('#g-recaptcha-response').val(token);
                    });
                });
            });
        </script>
    @endif
@endif
