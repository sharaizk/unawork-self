@extends('layouts.landing')


@section('title')
    {{ __('Pod') }}
@endsection

@section('content')
<style>
    .pod-join {
        background: #022D4D;
        color: #ffffff;
        padding: 5px 20px;
        border-radius: 5px;
        margin: 10px auto;
        display: inline-block;
        width: 25rem;
    }
    .pod-join :hover {
        background: white;
        color: #022D4D;
        border: 1px solid #022D4D;
    }

</style>
<link rel="stylesheet" href="{{ asset('assets/css/landing/outer-pods.css') }}">
<section class="header__search-pods"></section>
<section class="section__titlepage">
    <div class="container">
        <div class="">
            <h1 class="section__titlepage--headertitle "><span>{{ $pod->title }}</span></h1>
            <p class="pods__subtitle">The best opportunities for work at your fingertips. Whether you are looking for a side hustle, second income, or new career growth, UnaWork offers layers of opportunities to hire and get hired.</p>
            @if (Auth::user())
                @if (!in_array($pod->id, Auth::user()->pods()->pluck('pods.id')->toArray())  )
                    <form class="join-form col-3" data-pod-id="{{ $pod->id }}">
                        <button type="submit" class="pod-join btn btn-primary">Join this pod</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

</section>
<section class="section__tasksmeeting">
    <div class="container">
        <div class="pod__wrapper">
            <div class="boxarchives">
                @include('pod.landing.news', ['news' => $news, 'podId' => $pod->id])

            </div>
            <div class="members">
                @include('pod.landing.members', ['users' => $pod->users->sortByDesc('created_at')])
            </div>
        </div>
    </div>
</section>
<!--
<section class="section__statistics__archives">
        <div class="container-master">
            <div class="row-master">

                <div class="notes col-12 col-xl-6">

                </div>

                <div class="notes col-12 col-xl-6">

                </div>

            </div>
    </div>
</section> -->


    @endsection

@push('scripts')
@section('scripts')
<script type="text/javascript">
    $(document).ready(function(){
    $('.section__general_info .buttonViewAll').on('click', function(e){
        e.preventDefault();
        $('.members__info_list--item').css('display', '');
        $(this).hide();
    });
});
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('.join-form');

        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                event.preventDefault();

                const podId = this.getAttribute('data-pod-id');

                fetch(`{{ ENV('APP_URL') }}api/pods/${podId}/requests`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    const messageContainer = document.getElementById('message-container');
                    if (data.status === 'success') {
                        messageContainer.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                        form.style.display = 'none';
                    } else {
                        messageContainer.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    const messageContainer = document.getElementById('message-container');
                    messageContainer.innerHTML = `<div class="alert alert-danger">An error occurred: ${error.message}</div>`;
                });
            });
        });
    });
</script>
@endsection



@endpush
