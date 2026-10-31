@php
    $page = $paginator->currentPage();
    $totalPages = $paginator->lastPage();
    $nearby = config('leaveConfig.pagination_nearby_pages');
    $pageNumbers = [1];
    for ($number = max(2, $page - $nearby); $number <= min($totalPages - 1, $page + $nearby); $number++) {
        $pageNumbers[] = $number;
    }
    if ($totalPages > 1) {
        $pageNumbers[] = $totalPages;
    }
    $previousNumber = 0;
@endphp
<nav class="leave-pagination">
    @if ($paginator->previousPageUrl() !== null)
        <a href="{{ $paginator->previousPageUrl() }}" class="leave-page-previous">← Previous</a>
    @else
        <span class="leave-page-previous leave-page-disabled">← Previous</span>
    @endif

    @foreach ($pageNumbers as $number)
        @if ($number > $previousNumber + 1)
            <span class="leave-page-gap">…</span>
        @endif
        @if ($number === $page)
            <span class="leave-page-current">{{ $number }}</span>
        @else
            <a href="{{ $paginator->url($number) }}">{{ $number }}</a>
        @endif
        @php $previousNumber = $number; @endphp
    @endforeach

    @if ($paginator->nextPageUrl() !== null)
        <a href="{{ $paginator->nextPageUrl() }}" class="leave-page-next">Next →</a>
    @else
        <span class="leave-page-next leave-page-disabled">Next →</span>
    @endif
</nav>
