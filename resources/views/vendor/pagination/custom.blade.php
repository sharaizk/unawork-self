@if ($paginator->hasPages())
    <nav class="custom-pagination">
        <ul class="pagination d-flex justify-content-center">
            @if ($paginator->onFirstPage())
                <!-- <li class="page-item disabled"><span class="page-link">Prev</span></li> -->
            @else
                <li class="page-item"><a class="page-link direction" href="{{ $paginator->previousPageUrl() }}" rel="prev">Prev</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link direction" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a></li>
            @endif
        </ul>
    </nav>
@endif
