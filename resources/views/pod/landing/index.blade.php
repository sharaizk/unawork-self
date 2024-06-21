@extends('layouts.landing')


@section('page-title')
    {{ __('POD') }}
@endsection

@section('content')
<link rel="stylesheet" href="http://unawork.net/styles/scss/outer-pods.css">
<section class="section__titlepage">
    <div class="container-master">
        <h1 class="section__titlepage--headertitle"><span>{{ $pod->title }}</span></h1>
    </div>
</section>
<section class="section__tasksmeeting">
    <div class="container-master">
        <div class="row-master">            
            <div class="boxarchives col-12 col-xl-6">
                @include('pod.landing.news', ['news' => $news, 'podId' => $pod->id])
                
            </div>
            <div class="members col-12 col-xl-6">
                @include('pod.landing.members', ['users' => $pod->users])
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
@endsection



@endpush
