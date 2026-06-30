@if($rows->isEmpty())
    <div class="empty">لا توجد نتائج.</div>
@else
    <table>
        <thead>
            <tr>
                <th style="width: 28%;">رقم البوليصة</th>
                <th style="width: 18%;">عدد التكرار</th>
                <th>الكتب المرتبطة</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td><span class="pill">{{ $row->policy_number }}</span></td>
                    <td><span class="pill">{{ $row->repeat_count }}</span></td>
                    <td>
                        @foreach(explode(',', $row->linked_references ?? '') as $reference)
                            @if(trim($reference) !== '')
                                <span class="pill">{{ trim($reference) }}</span>
                            @endif
                        @endforeach
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
