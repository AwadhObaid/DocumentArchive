<div class="dq-panel">
    <div class="dq-panel-header">
        <div>
            <h3>{{ $title }}</h3>
            <small>{{ $subtitle }}</small>
        </div>
        <span class="dq-pill">{{ $items->count() }}</span>
    </div>

    @if($items->isEmpty())
        <div class="dq-empty">{{ $empty }}</div>
    @else
        <div class="dq-table-wrap">
            <table class="dq-table">
                <thead>
                    <tr>
                        <th>رقم
 البوليصة</th>
                        <th>عدد التكرار</th>
                        <th>الكتب الم
رتبطة</th>
                        <th class="no-print">نسخ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td><span class="dq-pill light">{{ $item['policy'] }}</span></td>
                            <td><span class="dq-pill">{{ $item['count'] }}</span></td>
                            <td>
                                @foreach($item['documents'] as $document)
                                    <a class="dq-pill" href="{{ route('documents.show', $document) }}" title="{{ $document->subject ?? $document->title ?? '' }}">
                                        {{ $document->reference_number }}
                                    </a>
                                @endforeach
                            </td>
                            <td class="no-print">
                                <button class="dq-copy" type="button" onclick="copyDQValue(@js($item['policy']))">نسخ الرقم
</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>