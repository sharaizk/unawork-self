@extends('layouts.landing')


@section('page-title')
    {{ __('POD') }}
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/landing/outer-pods.css') }}">
<section class="section__titlepage">
    <div class="container-master">
        <h1 class="section__titlepage--headertitle"><span>{{ $pod->title }}</span></h1>
    </div>
</section>
<section class="section__tasksmeeting">
    <div class="container-master">
        <div class="row-master">  
            <div class="boxarchives col-12 col-xl-12">
                <div class="boxarchives__content">
                    <h3 class="title_box">News:</h3>
                    <div class="boxarchives__content__list">
                        @foreach($news as $index => $post)
                            <div class="boxarchives__content__list__item ">
                            
                            <h3 class="boxarchives__content__list__item__title">
                                <a href="{{ route('landing.pods.news.show', ['podId' => $pod->id, 'newsId' => $post->id]) }}">
                                    <i class="ti ti-pencil"></i> 
                                    <span>{{ $post->title }}</span>
                                </a>
                            </h3>
                                <div class="date">{{ $post->created_at->format('d F y') }}</div>
                                
                                <p class="content">
                                    {{ $post->short_content }}
                                </p>
                            </div>
                        @endforeach
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
