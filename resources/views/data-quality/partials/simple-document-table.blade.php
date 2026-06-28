@if($documents->isEmpty())
    <p class="muted">لا توجد نتائج.</p>
@else
    <table>
        <thead>
            <tr>
                <th>رقمالكتاب</th>
                <th>الموضوع</th>
                <th>إجراء</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documents as $document)
                <tr>
                    <td>{{ $document->reference_number }}</td>
                    <td>{{ $document->subject ?: $document->title }}</td>
                    <td><a class="btn btn-secondary btn-sm" href="{{ route('documents.show', $document) }}">عرض</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
