@extends('layouts.admin')

@section('page-title')
    {{ __('User Profile') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"> {{ __('POD') }}</li>
@endsection


@section('content')
<div class="row">
    <div class="col-xl-3">
        <div class="card sticky-top" style="top:30px">
            <div class="list-group list-group-flush" id="useradd-sidenav">
                <a href="#v-pills-home" class="list-group-item list-group-item-action border-0 active">
                    Create New Pod <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                </a>
            </div>
        </div>
    </div>
    <div class="col-xl-9">
        <div class="card" id="v-pills-profiler">
            <div class="card-header">
                <h5>Create New Pod</h5>
            </div>
            <div class="card-body">
                @php 
                    $skills = \App\Models\Skill::all();
                @endphp
                @include('pod.create_form')
            
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script type="text/javascript">
        document.querySelector('.create-pod-form').addEventListener('submit', async function(event) {
            event.preventDefault(); 
            
            const formData = new FormData(this); 
            try {
                const response = await axios.post('./api/pod', formData); 
                console.log(response.data); 

                this.reset();
                document.getElementById('success-message').style.display = 'block';
                setTimeout(function() {
                    document.getElementById('success-message').style.display = 'none';
                }, 5000);
            } catch (error) {
                console.error(error); 
            }
        });
    </script>
@endpush
