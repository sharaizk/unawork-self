@extends('layouts.admin')

@section('page-title')
    {{ __('POD') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.index') }}">{{ __('Pod') }}</a></li>
    <li class="breadcrumb-item"> {{ __('Pod " '.$pod->title.' " Manage') }}</li>
@endsection


@section('content')
<div class="row">
    <div class="col-xl-2">
        @include('pod.manage.nav', ['podId' => $pod->id])
    </div>
    <div class="col-xl-10">
        @yield('content.inside')
    </div>
</div>

@endsection

@push('scripts')
@section('scripts')
@endsection
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        $(document).on('shown.bs.modal', function (e) {
            const form = document.querySelector('.invite-user-form');
            const inviteUserButton = document.getElementById('inviteUserButton');
            const podId = {{ $pod->id }};

            inviteUserButton.addEventListener('click', async function(event) {
                event.preventDefault();

                const formData = new FormData(form);

                try {
                    const response = await axios.post('{{ route('pods.user.exists', ['podId' => $pod->id]) }}', formData);
                    console.log(response);

                    form.reset();
                    document.getElementById('success-message').style.display = 'block';
                    setTimeout(function() {
                        location.reload(); 
                    }, 3000);
                } catch (error) {
                    console.log(error);

                    form.reset();
                    document.getElementById('failed-message').style.display = 'block';
                    
                    setTimeout(function() {
                        location.reload(); 
                    }, 3000);
                }
            });
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    const removeUserLinks = document.querySelectorAll('.remove-user');

    removeUserLinks.forEach(link => {
        link.addEventListener('click', async function(event) {
            event.preventDefault();
            const url = this.getAttribute('data-url');
            try {
                const response = await axios.delete(url);
                location.reload(); 
            } catch (error) {
                console.error('An error occurred:', error);
            }
        });
    });
});
</script>


@endpush