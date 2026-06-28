<table>
    <thead>
        <tr>
            <th style="width: 16%;">رقم
 الكتاب</th>
            <th style="width: 14%;">التاريخ</th>
            <th>الم
وضوع</th>
            <th style="width: 18%;">البوليصة الرئيسية</th>
            <th style="width: 18%;">البوليصة الفرعية</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                <td><span class="pill">{{ $row->reference_number ?? '—' }}</span></td>
                <td>{{ isset($row->reference_date) ? $fmtDate($row->reference_date) : '—' }}</td>
                <td>{{ $row->subject ?? $row->title ?? '—' }}</td>
                <td>{{ $row->main_policy_number ?? '—' }}</td>
                <td>{{ $row->sub_policy_number ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
