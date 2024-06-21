<style>
    .one-post {
        padding: 20px;
    }
    .news-image {
        width: 50%;
    }
</style>
<div class="one-post">
    <h3 class="boxarchives__content__list__item__title">{{ $post->title }}</h3>
    <div class="date">{{ $post->created_at->format('d F y') }}</div>
    @if($post->image)
    <img src="{{ asset('storage/' . $post->image) }}" class="news-image" />
    @endif
    <p class="content">
        {!! $post->content !!}
    </p>
</div>
