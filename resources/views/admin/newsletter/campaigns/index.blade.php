    <!-- It is quality rather than quantity that matters. - Lucius Annaeus Seneca -->
@extends('layouts.admin')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-800 px-4 py-2 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 text-red-800 px-4 py-2 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Newsletter Campaigns</h1>
            <p class="text-sm text-gray-500">
                Manage email blasts for LaLune by NE – drops, promos, and updates.
            </p>
        </div>

        <a href="{{ route('admin.newsletter.campaigns.create') }}"
           class="inline-flex items-center justify-center px-4 py-2 rounded-full
                  text-sm font-semibold text-white bg-black hover:bg-gray-900 transition">
            + New Campaign
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-5 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
        {{-- Search --}}
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="Name or subject…"
                class="w-full px-3 py-2 rounded-lg border text-sm
                       border-gray-300 focus:outline-none focus:ring-2 focus:ring-black focus:border-black"
            />
        </div>

        {{-- Status --}}
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
            <select
                name="status"
                class="w-full px-3 py-2 rounded-lg border text-sm
                       border-gray-300 focus:outline-none focus:ring-2 focus:ring-black focus:border-black"
            >
                <option value="">All</option>
                @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-end">
            <button
                type="submit"
                class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-black hover:bg-gray-900 transition w-full sm:w-auto"
            >
                Filter
            </button>
        </div>
    </form>

    {{-- Campaigns table --}}
    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Name</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Subject</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Status</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Scheduled for</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Sent at</th>
                    <th class="px-4 py-2 text-right font-semibold text-gray-700">Recipients</th>
                    <th class="px-4 py-2 text-right font-semibold text-gray-700">Open / Click</th>
                    <th class="px-4 py-2 text-right font-semibold text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($campaigns as $campaign)
                    @php
                        $statusColor = match ($campaign->status) {
                            'draft'     => 'bg-gray-100 text-gray-800 border border-gray-200',
                            'scheduled' => 'bg-amber-100 text-amber-800 border border-amber-200',
                            'sending'   => 'bg-blue-100 text-blue-800 border border-blue-200',
                            'sent'      => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
                            default     => 'bg-gray-100 text-gray-700 border border-gray-200',
                        };

                        $total    = $campaign->total_sends ?? 0;
                        $opens    = $campaign->opens_count ?? 0;
                        $clicks   = $campaign->clicks_count ?? 0;
                        $openRate = $total > 0 ? round(($opens / $total) * 100) : 0;
                        $clickRate = $total > 0 ? round(($clicks / $total) * 100) : 0;
                    @endphp
                    <tr>
                        <td class="px-4 py-2 text-gray-900">
                            <div class="font-medium">{{ $campaign->name }}</div>
                            <div class="text-xs text-gray-500">
                                ID: {{ $campaign->id }}
                            </div>
                        </td>

                        <td class="px-4 py-2 text-gray-700">
                            {{ $campaign->subject }}
                        </td>

                        <td class="px-4 py-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusColor }}">
                                {{ ucfirst($campaign->status) }}
                            </span>
                        </td>

                        <td class="px-4 py-2 text-gray-700">
                            {{ optional($campaign->scheduled_for)->format('Y-m-d H:i') ?? '—' }}
                        </td>

                        <td class="px-4 py-2 text-gray-700">
                            {{ optional($campaign->sent_at)->format('Y-m-d H:i') ?? '—' }}
                        </td>

                        <td class="px-4 py-2 text-right text-gray-900">
                            {{ $total }}
                        </td>

                        <td class="px-4 py-2 text-right text-gray-700">
                            @if($total > 0)
                                <span class="inline-block text-xs">
                                    {{ $openRate }}% open<br>
                                    {{ $clickRate }}% click
                                </span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>

                        <td class="px-4 py-2 text-right text-xs">
                            <div class="inline-flex items-center gap-2">
                                <a
                                    href="{{ route('admin.newsletter.campaigns.edit', $campaign) }}"
                                    class="text-gray-600 hover:text-black underline"
                                >
                                    Edit
                                </a>

                                {{-- Later: preview, send, etc. --}}
                                {{-- <a href="{{ route('admin.newsletter.campaigns.preview', $campaign) }}" class="text-gray-600 hover:text-black underline">
                                    Preview
                                </a> --}}
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                            No campaigns found yet. Create your first LaLune campaign above.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $campaigns->links() }}
    </div>
</div>
@endsection
