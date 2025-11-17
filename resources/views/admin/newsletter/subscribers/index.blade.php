    <!-- Knowing is not enough; we must apply. Being willing is not enough; we must do. - Leonardo da Vinci -->
    @extends('layouts.admin')

    @section('content')
        <div class="max-w-6xl mx-auto px-4 py-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Newsletter Subscribers</h1>
                    <p class="text-sm text-gray-500">
                        Manage LaLune by NE newsletter subscribers, filters, and actions.
                    </p>
                </div>

                {{-- Add subscriber form (uses existing public subscribe route) --}}
                <form method="POST" action="{{ route('newsletter.subscribe') }}"
                    class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                    @csrf
                    <input type="email" name="email" required placeholder="Add subscriber email…"
                        class="w-full sm:w-64 px-3 py-2 rounded-lg border text-sm
                       border-gray-300 focus:outline-none focus:ring-2 focus:ring-black focus:border-black" />
                    <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-lg
                       text-sm font-semibold text-white
                       bg-black hover:bg-gray-900 transition">
                        Add Subscriber
                    </button>
                </form>
            </div>

            {{-- Filters --}}
            <form method="GET" class="mb-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                {{-- Search --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Search (email)</label>
                    <input type="text" name="q" value="{{ $search }}" placeholder="example@email.com"
                        class="w-full px-3 py-2 rounded-lg border text-sm
                       border-gray-300 focus:outline-none focus:ring-2 focus:ring-black focus:border-black" />
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select name="status"
                        class="w-full px-3 py-2 rounded-lg border text-sm
                       border-gray-300 focus:outline-none focus:ring-2 focus:ring-black focus:border-black">
                        <option value="">All</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Source --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Source</label>
                    <select name="source"
                        class="w-full px-3 py-2 rounded-lg border text-sm
                       border-gray-300 focus:outline-none focus:ring-2 focus:ring-black focus:border-black">
                        <option value="">All</option>
                        @foreach ($sourceOptions as $value => $label)
                            <option value="{{ $value }}" @selected($source === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Date from --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Subscribed from</label>
                    <input type="date" name="from" value="{{ $from }}"
                        class="w-full px-3 py-2 rounded-lg border text-sm
                       border-gray-300 focus:outline-none focus:ring-2 focus:ring-black focus:border-black" />
                </div>

                {{-- Date to  --}}
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Subscribed to</label>
                        <input type="date" name="to" value="{{ $to }}"
                            class="w-full px-3 py-2 rounded-lg border text-sm
                           border-gray-300 focus:outline-none focus:ring-2 focus:ring-black focus:border-black" />
                    </div>

                </div>
                {{-- Submit --}}
                <div class="flex items-end">
                    <button type="submit"
                        class="px-3 py-2 rounded-lg text-sm font-semibold text-white bg-black hover:bg-gray-900 transition">
                        Filter
                    </button>
                </div>
            </form>

            {{-- Subscribers table --}}
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-gray-700">Email</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-700">Status</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-700">Source</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-700">Subscribed at</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-700">Last opened</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-700">Last clicked</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($subscribers as $subscriber)
                            <tr>
                                <td class="px-4 py-2 text-gray-900">
                                    {{ $subscriber->email }}
                                </td>

                                {{-- Status badge --}}
                                <td class="px-4 py-2">
                                    @php
                                        $statusColor = match ($subscriber->status) {
                                            'pending' => 'bg-amber-100 text-amber-800',
                                            'subscribed' => 'bg-emerald-100 text-emerald-800',
                                            'unsubscribed' => 'bg-gray-200 text-gray-800',
                                            'bounced' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-700',
                                        };
                                    @endphp
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusColor }}">
                                        {{ ucfirst($subscriber->status) }}
                                    </span>
                                </td>

                                <td class="px-4 py-2 text-gray-700">
                                    {{ $subscriber->source ?? '-' }}
                                </td>

                                <td class="px-4 py-2 text-gray-700">
                                    {{ optional($subscriber->subscribed_at)->format('Y-m-d H:i') ?? '-' }}
                                </td>

                                <td class="px-4 py-2 text-gray-700">
                                    {{ optional($subscriber->last_opened_at)->format('Y-m-d H:i') ?? '-' }}
                                </td>

                                <td class="px-4 py-2 text-gray-700">
                                    {{ optional($subscriber->last_clicked_at)->format('Y-m-d H:i') ?? '-' }}
                                </td>

                                <td class="px-4 py-2 text-right text-xs">
                                    <div class="inline-flex items-center gap-2">
                                        {{-- View (for later subscriber detail page) --}}
                                        <a href="{{ route('admin.newsletter.subscribers.show', $subscriber) }}"
                                            class="text-gray-600 hover:text-black underline">
                                            View
                                        </a>

                                        {{-- Resend confirm (only pending) --}}
                                        @if ($subscriber->status === 'pending')
                                            <form method="POST"
                                                action="{{ route('admin.newsletter.subscribers.resend-confirm', $subscriber) }}"
                                                onsubmit="return confirm('Resend confirmation email to {{ $subscriber->email }}?');">
                                                @csrf
                                                <button type="submit"
                                                    class="text-amber-700 hover:text-amber-900 underline">
                                                    Resend confirm
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Unsubscribe --}}
                                        @if ($subscriber->status === 'subscribed')
                                            <form method="POST"
                                                action="{{ route('admin.newsletter.subscribers.unsubscribe', $subscriber) }}"
                                                onsubmit="return confirm('Unsubscribe {{ $subscriber->email }}?');">
                                                @csrf
                                                <button type="submit" class="text-red-600 hover:text-red-800 underline">
                                                    Unsubscribe
                                                </button>
                                            </form>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                    No subscribers found with the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $subscribers->links() }}
            </div>
        </div>
    @endsection
