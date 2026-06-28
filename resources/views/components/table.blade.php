@props([
    'headers' => [],
])

<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-gray-200">
        @if (count($headers))
            <thead class="bg-gray-50">
                <tr>
                    @foreach ($headers as $header)
                        <th
                            scope="col"
                            class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap"
                        >
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody class="divide-y divide-gray-100 text-sm [&>tr]:transition-colors [&>tr:hover]:bg-gray-50">
            {{ $slot }}
        </tbody>
    </table>
</div>
