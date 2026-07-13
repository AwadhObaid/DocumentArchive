@php
    $smartChartSets = $smartChartSets ?? [];
    $summaryClass = $summaryClass ?? 'smart-chart-summary-table';
    $maxRows = $maxRows ?? 8;
@endphp

@if(!empty($smartChartSets))
    <div class="{{ $summaryClass }}">
        @foreach($smartChartSets as $groupTitle => $rows)
            @php
                $rows = is_array($rows) ? array_values(array_filter($rows, fn($item) => is_array($item) && (int) ($item['total'] ?? 0) >= 0)) : [];
                $rows = array_slice($rows, 0, $maxRows);
                $maxValue = 1;
                foreach ($rows as $row) {
                    $maxValue = max($maxValue, (int) ($row['total'] ?? 0));
                }
            @endphp

            @if($rows !== [])
                <div class="smart-chart-summary-group">
                    <h4>{{ $groupTitle }}</h4>
                    <table class="smart-summary-table">
                        <thead>
                            <tr>
                                <th>البند</th>
                                <th>العدد</th>
                                <th>المؤشر</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                @php
                                    $value = (int) ($row['total'] ?? 0);
                                    $percent = min(100, max(0, round(($value / $maxValue) * 100)));
                                @endphp
                                <tr>
                                    <td>{{ $row['label'] ?? ($row['day'] ?? 'غير محدد') }}</td>
                                    <td>{{ $value }}</td>
                                    <td>
                                        <div class="smart-summary-bar">
                                            <span style="width: {{ $percent }}%"></span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach
    </div>
@endif
