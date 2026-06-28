@if(empty($rows))
    <div class="empty">لا توجد بيانات.</div>
@else
    <table class="mini-table">
        <thead>
            <tr>
                <th>{{ $firstColumn }}</th>
                <th style="width: 24%;">العدد</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['name'] ?? 'غير محدد' }}</td>
                    <td><span class="pill">{{ number_format($row['total'] ?? 0) }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
