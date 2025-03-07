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

    .see-more-btn {
            font-size: 15px;
            font-weight: 400!important;
            text-decoration: underline!important;
            display: none;
            background: none;
            border: none;
            color: #022D4D;
            font-weight: bold;
            cursor: pointer;
            padding: 5px 0;
        }

    @media (max-width: 768px) {
        .see-more-btn {
            display: inline;
        }

        .pods__subtitle {
            position: relative;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            max-height: 4.5em; 
            transition: max-height 0.3s ease-in-out;
        }

        .pods__subtitle.expanded {
            -webkit-line-clamp: unset;
            max-height: none;
        }
    }

</style>
<link rel="stylesheet" href="{{ asset('assets/css/landing/outer-pods.css') }}">
<section class="header__search-pods"></section>
<section class="section__titlepage">
    <div class="container">
        <div class="">
            <h1 class="section__titlepage--headertitle "><span>{{ $pod->title }}</span></h1>
            <p class="pods__subtitle">{{ $pod->description ?? '' }}</p>
            <button class="see-more-btn" style="display: none">See More</button>
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
    $('.buttonViewAll-mobile').on('click', function(e){
        console.log('wwww')
        e.preventDefault();
        $('.members__info_list--item').css('display', '');
        $(this).hide();
    });
});
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const subtitle = document.querySelector(".pods__subtitle");
        const btn = document.querySelector(".see-more-btn");

        if (subtitle && btn) {
            const lineHeight = parseFloat(getComputedStyle(subtitle).lineHeight);
            const maxHeight = lineHeight * 3; 

            if (subtitle.scrollHeight > maxHeight) {
                btn.style.display = "inline"; 
            }
            btn.addEventListener("click", function () {
                subtitle.classList.toggle("expanded");
                btn.innerText = subtitle.classList.contains("expanded") ? "See Less" : "See More";
            });
        }

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
