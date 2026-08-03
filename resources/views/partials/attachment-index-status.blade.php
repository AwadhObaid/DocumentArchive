@php
    $eligible = (int) ($record->pdf_index_eligible ?? 0);
    $indexed = (int) ($record->pdf_index_indexed ?? 0);
    $unindexed = (int) ($record->pdf_index_unindexed ?? 0);
    $attention = (int) ($record->pdf_index_attention ?? 0);
    $isComplete = $eligible > 0 && $indexed >= $eligible;
    $canIndexAttachments = $canIndexAttachments ?? auth()->user()?->hasPermission('pdf_search.index');
    $recordIsTrashed = method_exists($record, 'trashed') && $record->trashed();
    $sourceFilter = match($sourceType) {
        'document' => 'documents',
        'memo' => 'memos',
        'circular' => 'circulars',
        default => 'misc_books',
    };
    $recordSearchToken = match($sourceType) {
        'document' => $record->reference_number ?? $record->id,
        'memo' => $record->memo_number ?? $record->id,
        'circular' => $record->circular_number ?? $record->id,
        default => $record->misc_number ?? $record->id,
    };
@endphp

<div class="attachment-index-summary">
    @if($eligible === 0)
        <span class="pdf-status-pill pdf-status-muted">لا يوجد PDF</span>
    @elseif($isComplete)
        <span class="pdf-status-pill pdf-status-success">مفهرس {{ $indexed }}/{{ $eligible }}</span>
    @elseif($unindexed > 0)
        <span class="pdf-status-pill pdf-status-info">غير مفهرس {{ $unindexed }}</span>
        @if($indexed > 0)
            <small>المفهرس: {{ $indexed }} من {{ $eligible }}</small>
        @endif
    @else
        <span class="pdf-status-pill pdf-status-warning">يحتاج مراجعة {{ $attention }}</span>
        <small>المفهرس: {{ $indexed }} من {{ $eligible }}</small>
    @endif

    @if($canIndexAttachments && ! $recordIsTrashed && $eligible > 0)
        <div class="attachment-index-actions no-print">
            <form method="POST" action="{{ route('pdf-search.index-record', [$sourceType, $record->id]) }}">
                @csrf
                @if($isComplete)
                    <input type="hidden" name="force" value="1">
                @endif
                <button type="submit" class="btn btn-sm {{ $isComplete ? 'btn-secondary' : 'btn-primary' }} attachment-index-mini-btn">
                    {{ $isComplete ? 'إعادة الفهرسة' : 'فهرسة' }}
                </button>
            </form>
            <a class="btn btn-sm btn-light attachment-index-mini-btn" href="{{ route('pdf-search.index', ['source' => $sourceFilter, 'q' => $recordSearchToken]) }}">التفاصيل</a>
        </div>
    @endif
</div>
