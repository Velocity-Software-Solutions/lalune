{{-- resources/views/admin/newsletter/subscribers/show.blade.php --}}
@extends('layouts.admin')

@section('content')
<div class=" mx-auto px-4 py-8 space-y-6">

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="rounded-lg bg-emerald-50 text-emerald-800 px-4 py-2 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg bg-red-50 text-red-800 px-4 py-2 text-sm">
            {{ session('error') }}
        </div>
    @endif

    @if (session('info'))
        <div class="rounded-lg bg-blue-50 text-blue-800 px-4 py-2 text-sm">
            {{ session('info') }}
        </div>
    @endif

    {{-- Breadcrumb / back --}}
    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('admin.newsletter.subscribers.index') }}"
           class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-black">
            <span class="text-lg leading-none">←</span>
            <span>Back to subscribers</span>
        </a>
    </div>

    @php
        $statusColor = match ($subscriber->status) {
            'pending'      => 'bg-amber-100 text-amber-800 border border-amber-200',
            'subscribed'   => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
            'unsubscribed' => 'bg-gray-100 text-gray-700 border border-gray-200',
            'bounced'      => 'bg-red-100 text-red-800 border border-red-200',
            default        => 'bg-gray-100 text-gray-700 border border-gray-200',
        };
    @endphp

    {{-- Header card --}}
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm px-5 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-lg md:text-xl font-semibold text-gray-900">
                Subscriber
            </h1>
            <p class="text-xs text-gray-500">
                LaLune by NE newsletter subscriber details.
            </p>

            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                <span class="font-mono text-gray-900">{{ $subscriber->email }}</span>

                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusColor }}">
                    {{ ucfirst($subscriber->status) }}
                </span>

                @if($subscriber->source)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-50 text-gray-600 border border-gray-200">
                        Source: {{ ucfirst($subscriber->source) }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex flex-wrap items-center gap-2 justify-end">
            {{-- Resend confirm (only pending) --}}
            @if ($subscriber->status === 'pending')
                <form
                    method="POST"
                    action="{{ route('admin.newsletter.subscribers.resend-confirm', $subscriber) }}"
                    onsubmit="return confirm('Resend confirmation email to {{ $subscriber->email }}?');"
                >
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold
                               text-amber-900 bg-amber-100 hover:bg-amber-200 border border-amber-200 transition"
                    >
                        Resend confirmation
                    </button>
                </form>
            @endif

            {{-- Unsubscribe --}}
            @if ($subscriber->status === 'subscribed')
                <form
                    method="POST"
                    action="{{ route('admin.newsletter.subscribers.unsubscribe', $subscriber) }}"
                    onsubmit="return confirm('Unsubscribe {{ $subscriber->email }}?');"
                >
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold
                               text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 transition"
                    >
                        Unsubscribe
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Two-column layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Profile / meta card --}}
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-800 mb-4 tracking-wide uppercase">
                Profile
            </h2>

            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Email</dt>
                    <dd class="text-gray-900 font-mono text-xs md:text-sm text-right break-all">
                        {{ $subscriber->email }}
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Status</dt>
                    <dd class="text-gray-900 text-right">
                        {{ ucfirst($subscriber->status) }}
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Source</dt>
                    <dd class="text-gray-900 text-right">
                        {{ $subscriber->source ?? '—' }}
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Created</dt>
                    <dd class="text-gray-900 text-right">
                        {{ optional($subscriber->created_at)->format('Y-m-d H:i') ?? '—' }}
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Last updated</dt>
                    <dd class="text-gray-900 text-right">
                        {{ optional($subscriber->updated_at)->format('Y-m-d H:i') ?? '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Timeline / activity card --}}
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-800 mb-4 tracking-wide uppercase">
                Activity
            </h2>

            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Subscribed at</dt>
                    <dd class="text-gray-900 text-right">
                        {{ optional($subscriber->subscribed_at)->format('Y-m-d H:i') ?? '—' }}
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Confirmed at</dt>
                    <dd class="text-gray-900 text-right">
                        {{ optional($subscriber->confirmed_at)->format('Y-m-d H:i') ?? '—' }}
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Unsubscribed at</dt>
                    <dd class="text-gray-900 text-right">
                        {{ optional($subscriber->unsubscribed_at)->format('Y-m-d H:i') ?? '—' }}
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Last opened</dt>
                    <dd class="text-gray-900 text-right">
                        {{ optional($subscriber->last_opened_at)->format('Y-m-d H:i') ?? '—' }}
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500">Last clicked</dt>
                    <dd class="text-gray-900 text-right">
                        {{ optional($subscriber->last_clicked_at)->format('Y-m-d H:i') ?? '—' }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Placeholder for future: campaign history --}}
    {{-- 
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-3 tracking-wide uppercase">
            Email history
        </h2>
        <p class="text-xs text-gray-500">
            Later, you can show campaigns sent to this subscriber here.
        </p>
    </div>
    --}}
</div>
@endsection
