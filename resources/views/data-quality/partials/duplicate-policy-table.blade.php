@if($groups->isEmpty())
    <p class="muted">لا توجد {{ $label }} مكررة.</p>
@else
    <table>
        <thead>
            <tr>
                <th>{{ $label }}</th>
                <th>عدد التكرار</th>
                <th>الكتب المرتبطة</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groups as $group)
                <tr>
                    <td><strong>{{ $group['policy_number'] }}</strong></td>
                    <td><span class="badge">{{ $group['total'] }}</span></td>
                    <td>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            @foreach($group['documents'] as $document)
                                <a class="btn btn-secondary btn-sm" href="{{ route('documents.show', $document) }}">
                                    {{ $document->reference_number }}
                                </a>
                            @endforeach
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
