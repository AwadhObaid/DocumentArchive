@php($trashed = $trashed ?? false)
<div class="dq-panel">
    <div class="dq-panel-header">
        <div>
            <h3>{{ $title }}</h3>
            <small>{{ $subtitle }}</small>
        </div>
        <span class="dq-pill">{{ $documents->count() }}</span>
    </div>

    @if($documents->isEmpty())
        <div class="dq-empty">{{ $empty }}</div>
    @else
        <div class="dq-table-wrap">
            <table class="dq-table">
                <thead>
                    <tr>
                        <th>رقمالكتاب</th>
                        <th>التاريخ</th>
                        <th>الموضوع</th>
                        <th>البوليصة الرئيسية</th>
                        <th>البوليصة الفرعية</th>
                        <th class="no-print">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td>
                                @if(!$trashed)
                                    <a class="dq-doc-link" href="{{ route('documents.show', $document) }}">{{ $document->reference_number }}</a>
                                @else
                                    <span class="dq-pill light">{{ $document->reference_number }}</span>
                                @endif
                            </td>
                            <td>{{ optional($document->reference_date)->format('Y-m-d') ?? $document->reference_date }}</td>
                            <td>{{ $document->subject ?? $document->title ?? '-' }}</td>
                            <td>{{ $document->main_policy_number ?: '-' }}</td>
                            <td>{{ $document->sub_policy_number ?: '-' }}</td>
                            <td class="no-print">
                                @if(!$trashed)
                                    <a class="dq-btn secondary" href="{{ route('documents.show', $document) }}">عرض</a>
                                @else
                                    <a class="dq-btn secondary" href="{{ route('trash.index') }}">السلة</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>