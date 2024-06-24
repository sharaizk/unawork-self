@extends('layouts.landing')

@section('body-class', 'tpl-pods-list')

@section('content')
<main>
    <section class="section__titlepage">
        <div class="container-master">
            <h1 class="section__titlepage--headertitle"><span>Pods</span></h1>
        </div>
    </section>
    <div class="container-master">

    <section class="section__layout section_pod">
            <div class="container-master pods__title">
                <h2>Pods of Interest </h2>
            </div>
            <div class="container-master">
                <div class="row-master">
                    @foreach($pods as $pod)
                        @include('pod.landing.pod_card', ['pod' => $pod])
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</main>

@endsection

@section('scripts')

@endsection