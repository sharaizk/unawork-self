@extends('layouts.guest')
@section('page-title')
    {{ __('Copylink') }}
@endsection

{{-- @section('language-bar')
    <a href="#" class="btn-primary">
        <select name="language" id="language" class=" btn-primary btn "
            onchange="this.options[this.selectedIndex].value && (window.location = this.options[this.selectedIndex].value);">
            @foreach (App\Models\Utility::languages() as $langCode => $language)
                <option class="login_lang" @if ($lang == $langCode) selected @endif
                    value="{{ route('verification.notice', $langCode) }}">{{ ucfirst($language) }}
                </option>
            @endforeach
        </select>
    </a>
@endsection --}}
<link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome.css') }}">
@section('content')
    <div class="card-body">
        <div class="mb-3">
            <h2 class="h3">{{ __('Password required') }}</h2>
            <h6>{{ __('This document is password-protected. Please enter a password.') }}</h6>

        </div>

        <span class="clearfix"></span>
        <form method="POST"
            action="{{ route('projects.link', [$slug, \Illuminate\Support\Facades\Crypt::encrypt($projectID)]) }}">
            @csrf
            <div class="form-group mb-2">
                <label class="form-control-label">{{ __('Password') }}</label>
                <div class="input-group input-group-merge">
                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                        name="password" required autocomplete="new-password">
                    <div class="input-group-append">
                        <span class="input-group-text">
                            <a href="#" data-toggle="password-text" data-target="#password">
                                <i class="fas fa-eye-slash" id="togglePassword"></i>
                            </a>
                        </span>
                    </div>
                    @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <span class="btn-inner--text">{{ __('Save') }}</span>

                </button>
            </div>
        </form>
    </div>
@endsection

@push('custom-scripts')
    <script src="{{ asset('assets/custom/libs/jquery/dist/jquery.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $("#form_data").submit(function(e) {
                $("#login_button").attr("disabled", true);
                return true;
            });
        });
    </script>
    @if (env('RECAPTCHA_MODULE') == 'on')
        {!! NoCaptcha::renderJs() !!}
    @endif

    <script>
        $(document).ready(function() {
            $("#togglePassword").click(function() {
                const password = $("#password");
                const passwordValue = password.val();
                console.log(passwordValue);

                const type = password.attr("type") === "password" ? "text" : "password";
                password.attr("type", type);

                // toggle the icon
                $(this).toggleClass("fa-eye fa-eye-slash");
            });
        });
    </script>
@endpush
