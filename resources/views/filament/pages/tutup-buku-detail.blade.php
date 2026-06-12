@php
    $summary = $meta["summary"] ?? [];
    $total = 0;
    foreach ($summary as $val) {
        $total += $val["archived"] ?? 0;
    }
@endphp

<div class="space-y-3">
    @isset($meta["started_at"])
        <div class="text-sm">
            <span class="font-semibold text-gray-500">Mulai:</span>
            <span class="text-gray-700">{{ Carbon\Carbon::parse($meta["started_at"])->format("d/m/Y H:i:s") }}</span>
        </div>
    @endisset

    @isset($meta["completed_at"])
        <div class="text-sm">
            <span class="font-semibold text-gray-500">Selesai:</span>
            <span class="text-gray-700">{{ Carbon\Carbon::parse($meta["completed_at"])->format("d/m/Y H:i:s") }}</span>
        </div>
    @endisset

    @if($summary)
        <div class="border-t border-gray-200 pt-3">
            <p class="text-sm font-semibold text-gray-700 mb-2">Ringkasan Arsip:</p>
            <table class="w-full text-sm">
                @foreach($summary as $type => $val)
                    <tr>
                        <td class="py-0.5 text-gray-600">{{ str($type)->replace("_"," ")->title() }}:</td>
                        <td class="py-0.5 text-right font-medium">{{ $val["archived"] ?? 0 }}</td>
                    </tr>
                @endforeach
                <tr class="border-t border-gray-200">
                    <td class="py-0.5 font-bold text-gray-800">Total</td>
                    <td class="py-0.5 text-right font-bold">{{ $total }}</td>
                </tr>
            </table>
        </div>
    @endif

    @isset($meta["stok_snapshot"])
        <div class="border-t border-gray-200 pt-3 text-sm">
            <span class="font-semibold text-gray-700">Stok Snapshot:</span>
            <span class="text-gray-600">{{ count($meta["stok_snapshot"]) }} item</span>
        </div>
    @endisset
</div>
