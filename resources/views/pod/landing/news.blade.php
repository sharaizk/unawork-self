<div class="boxarchives__content">
    <h3 class="title_box">Industry based info</h3>
    <!-- <a href="#" class="buttonViewAll">ViewAll <i class="fa fa-chevron-right"></i></a> -->
    <div class="boxarchives__content__list">
        @foreach($news as $post)
            <div class="boxarchives__content__list__item">
            
            <h3 class="boxarchives__content__list__item__title">
                <a href="#" class="dropdown-item" data-ajax-popup="true" data-size="lg" 
                    data-title="Open post no {{ $post->id }}" 
                    data-url="{{ route('landing.pods.news.show', ['podId' => $podId, 'newsId' => $post->id]) }}""
                    data-title="{{ __('Edit this post') }}">
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



<script>
  $(document).ready(function(){
    $('#exampleModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var modal = $(this);
      
      $.ajax({
        url: "{{ route('landing.pods.news.show', ['podId' => $podId, 'newsId' => $post->id]) }}",
        method: "GET",
        success: function(response){
          modal.find('.modal-body').html(response);
        }
      });
    });
  });
</script>