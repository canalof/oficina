@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3 px-4 py-3 text-sm text-suave" aria-label="Paginação">
        <span>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}</span>
        <span class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm opacity-40">Anterior</span>
            @else
                <a class="btn btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
            @endif
            @if ($paginator->hasMorePages())
                <a class="btn btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima</a>
            @else
                <span class="btn btn-sm opacity-40">Próxima</span>
            @endif
        </span>
    </nav>
@endif
