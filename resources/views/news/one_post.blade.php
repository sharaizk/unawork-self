@extends('layouts.landing')


@section('page-title')
    {{ __('POD') }}
@endsection

@section('content')

<style>
    .one-post {
        padding: 20px;
    }
    .news-image {
        width: 50%;
    }
</style>

<link rel="stylesheet" href="{{ asset('assets/css/landing/outer-pods.css') }}">



<section class="section__titlepage">
    <div class="container-master">
        <h1 class="section__titlepage--headertitle"><span>  {{ $pod->title }} &raquo; Post #{{ $post->id }}</span></h1>
    </div>
</section>
<section class="section__tasksmeeting">
    <div class="container-master">
        <div class="row-master">            
            <div class="boxarchives col-12 col-xl-12">

                <div class="card" style="width: 38rem;">
                    @if($post->image)
                        <img src="{{ asset('storage/' . $post->image) }}" class="card-img-top" alt="...">
                    @endif
                    <div class="card-body">
                        <h1 class="section__titlepage--headertitle">
                            <span>  {{ $post->title }}</span>
                        </h1>
                        <div class="date">{{ $post->created_at->format('d F y') }}</div>

                        <p class="card-text">
                        {!! $post->content !!}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


@endsection

@push('scripts')
@section('scripts')
@endsection



@endpush