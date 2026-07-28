@props([
    'month',
    'hasData' => false,
    'isTotal' => false,
    'lead' => 0,
    'ql' => 0,
    'pitching' => 0,
    'closing' => 0,
    'nilaiClosing' => 0,
    'leadToQl' => null,
    'qlToPitch' => null,
    'pitchToClosing' => null,
    'overall' => null,
    'status' => null,
])

<tr @class(['border-b border-base-300', 'border-t-2 font-semibold text-base-content' => $isTotal])>
    <td class="{{ $isTotal ? '' : 'font-medium text-base-content' }}">{{ $month }}</td>

    @if (! $hasData)
        <td colspan="9" class="text-base-content/30 italic">belum ada data</td>
    @else
        <td>{{ number_format($lead) }}</td>
        <td>{{ number_format($ql) }}</td>
        <td>{{ number_format($pitching) }}</td>
        <td>{{ number_format($closing) }}</td>
        <td><x-currency-abbr :value="$nilaiClosing" /></td>
        <td>{{ $leadToQl !== null ? number_format($leadToQl, 1).'%' : '-' }}</td>
        <td>{{ $qlToPitch !== null ? number_format($qlToPitch, 1).'%' : '-' }}</td>
        <td>{{ $pitchToClosing !== null ? number_format($pitchToClosing, 1).'%' : '-' }}</td>
        <td>
            @if ($status)
                <span class="badge badge-sm {{ $status['class'] }}">{{ $status['label'] }}</span>
            @elseif ($overall !== null)
                {{ number_format($overall, 1) }}%
            @else
                -
            @endif
        </td>
    @endif
</tr>
