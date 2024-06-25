<div class="boxarchives__content">
    <h3 class="title_box">Industry based info</h3>
    <a href="{{ route('landing.pods.news.list', $podId = $podId ) }}" class="buttonViewAll">ViewAll <i class="fa fa-chevron-right">&raquo;</i></a>
    <div class="boxarchives__content__list">
        @foreach($news as $index => $post)
            <div class="boxarchives__content__list__item {{ $index < 3 ? 'visible' : 'hidden' }}">
            
            <h3 class="boxarchives__content__list__item__title">
                <a href="{{ route('landing.pods.news.show', ['podId' => $podId, 'newsId' => $post->id]) }}">
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