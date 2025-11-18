@extends('layouts.admin')

@push('head')
    @vite(['resources/js/summernote.js'])
@endpush

@section('content')
<style>
        h1 {
            font-size: 2.25rem;
            /* 36px */
            font-weight: 700;
        }

        h2 {
            font-size: 1.875rem;
            /* 30px */
            font-weight: 600;
        }

        h3 {
            font-size: 1.5rem;
            /* 24px */
            font-weight: 600;
        }

        h4 {
            font-size: 1.25rem;
            /* 20px */
            font-weight: 500;
        }

        h5 {
            font-size: 1rem;
            /* 16px */
            font-weight: 500;
        }

        h6 {
            font-size: 0.875rem;
            /* 14px */
            font-weight: 500;
        }
    </style>
<div class="max-w-6xl mx-auto px-4 py-8">

    {{-- Flash --}}
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
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Create Campaign</h1>
            <p class="text-sm text-gray-500">
                Draft a LaLune by NE newsletter and preview it in real time.
            </p>
        </div>

        <a href="{{ route('admin.newsletter.campaigns.index') }}"
           class="text-xs text-gray-500 hover:text-black underline">
            ← Back to campaigns
        </a>
    </div>

    {{-- Split layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        {{-- LEFT: Form --}}
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 tracking-wide uppercase mb-1">
                Campaign details
            </h2>

            <form method="POST" action="{{ route('admin.newsletter.campaigns.store') }}" class="space-y-4">
                @csrf

                {{-- Name --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        Internal name
                    </label>
                    <input
                        type="text"
                        name="name"
                        id="name-input"
                        value="{{ old('name') }}"
                        class="w-full px-3 py-2 rounded-lg border text-sm
                               border-gray-300 focus:outline-none focus:ring-2 focus:ring-black focus:border-black"
                        placeholder="e.g. New Drop – Moonlight Collection"
                        required
                    />
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Subject --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        Email subject
                    </label>
                    <input
                        type="text"
                        name="subject"
                        id="subject-input"
                        value="{{ old('subject') }}"
                        class="w-full px-3 py-2 rounded-lg border text-sm
                               border-gray-300 focus:outline-none focus:ring-2 focus:ring-black focus:border-black"
                        placeholder="e.g. Meet LaLune – a new chapter in your night ritual"
                        required
                    />
                    @error('subject')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Audience --}}
                <div class="space-y-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        Audience
                    </label>

                    @php
                        $oldSegment = old('segment', 'all_subscribed');
                    @endphp

                    <div class="space-y-1 text-xs text-gray-700">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                name="segment"
                                value="all_subscribed"
                                class="text-black border-gray-300 focus:ring-black"
                                {{ $oldSegment === 'all_subscribed' ? 'checked' : '' }}
                            >
                            <span>All subscribed</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                name="segment"
                                value="only_pending"
                                class="text-black border-gray-300 focus:ring-black"
                                {{ $oldSegment === 'only_pending' ? 'checked' : '' }}
                            >
                            <span>Only pending (resend confirm)</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                name="segment"
                                value="custom_subscribers"
                                class="text-black border-gray-300 focus:ring-black"
                                {{ $oldSegment === 'custom_subscribers' ? 'checked' : '' }}
                            >
                            <span>Specific subscribers</span>
                        </label>
                    </div>

                    @error('segment')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror

                    {{-- Specific subscribers multi-select --}}
                    <div
                        id="custom-subscribers-box"
                        class="mt-2"
                        style="display: {{ $oldSegment === 'custom_subscribers' ? 'block' : 'none' }};"
                    >
                        <label class="block text-[11px] font-medium text-gray-600 mb-1">
                            Choose subscribers
                        </label>
                        <select
                            name="subscriber_ids[]"
                            multiple
                            class="w-full px-3 py-2 rounded-lg border text-sm
                                   border-gray-300 focus:outline-none focus:ring-2 focus:ring-black focus:border-black
                                   h-40"
                        >
                            @foreach($subscribers as $sub)
                                <option
                                    value="{{ $sub->id }}"
                                    @if(collect(old('subscriber_ids', []))->contains($sub->id)) selected @endif
                                >
                                    {{ $sub->email }} ({{ $sub->status }})
                                </option>
                            @endforeach
                        </select>
                        @error('subscriber_ids')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-[11px] text-gray-500">
                            Hold Ctrl (Windows) / Command (Mac) to select multiple.
                        </p>
                    </div>

                    <p class="mt-1 text-[11px] text-gray-500">
                        “All subscribed” = active newsletter list. “Specific subscribers” = send only to chosen emails.
                    </p>
                </div>

                {{-- Schedule --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        Schedule (optional)
                    </label>
                    <input
                        type="datetime-local"
                        name="scheduled_for"
                        value="{{ old('scheduled_for') }}"
                        class="w-full px-3 py-2 rounded-lg border text-sm
                               border-gray-300 focus:outline-none focus:ring-2 focus:ring-black focus:border-black"
                    />
                    @error('scheduled_for')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-[11px] text-gray-500">
                        Leave empty to start sending immediately (still spaced 5 minutes apart).
                    </p>
                </div>

                {{-- Body (Summernote) --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        Email content
                    </label>
                    <textarea
                        class="summernote-editor"
                        name="body"
                        id="body-editor"
                    >{!! old('body', '') !!}</textarea>

                    @error('body')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror

                    <p class="mt-1 text-[11px] text-gray-500">
                        Use this area to design the body of the email. The preview will update as you type.
                    </p>
                </div>

                {{-- Submit --}}
                <div class="pt-2 flex justify-end">
                    <button
                    id="save_button"
                        type="submit"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-full
                               text-sm font-semibold text-white bg-black hover:bg-gray-900 transition"
                    >
                        Save & schedule campaign
                    </button>
                </div>
            </form>
        </div>

        {{-- RIGHT: Live preview --}}
        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-5">
            <h2 class="text-sm font-semibold text-gray-800 tracking-wide uppercase mb-3">
                Live preview
            </h2>

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                {{-- “Email header” --}}
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between bg-black text-white">
                    <div>
                        <div class="text-xs uppercase tracking-[0.18em] font-semibold">
                            LaLune by NE
                        </div>
                        <div class="text-[11px] text-gray-300 mt-0.5">
                            Preview of your newsletter
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[11px] text-gray-300">
                            {{ config('mail.from.address') }}
                        </div>
                        <div class="text-[11px] text-gray-400">
                            To: subscriber@example.com
                        </div>
                    </div>
                </div>

                {{-- Subject line --}}
                <div class="px-4 py-3 border-b border-gray-100">
                    <div class="text-[11px] uppercase tracking-wide text-gray-400 mb-1">
                        Subject
                    </div>
                    <div
                        class="text-sm text-gray-900 font-medium"
                        id="subject-preview"
                    >
                        {{ old('subject') ?: 'Your subject will appear here' }}
                    </div>
                </div>

                {{-- Body --}}
                <div class="px-4 py-4 text-sm leading-relaxed text-gray-800">
                    <div
                        id="body-preview"
                        class="prose prose-sm max-w-none prose-headings:mt-3 prose-headings:mb-1
                               prose-p:mb-2 prose-a:text-black prose-a:underline"
                    >
                        {!! old('body') ?: '
                            <p>Start writing your LaLune story here. This is just a placeholder preview.</p>
                            <p>You can style headings, paragraphs, links and more using the editor on the left.</p>
                        ' !!}
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
                    <p class="text-[11px] text-gray-500">
                        You’re receiving this email because you subscribed to LaLune by NE.
                        You can unsubscribe at any time.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ----- Audience: show/hide specific subscribers box -----
        const segmentRadios = document.querySelectorAll('input[name="segment"]');
        const customBox = document.getElementById('custom-subscribers-box');
        function refreshCustomBox() {
            const selected = document.querySelector('input[name="segment"]:checked');
            if (selected && selected.value === 'custom_subscribers') {
                customBox.style.display = 'block';
            } else {
                customBox.style.display = 'none';
            }
        }

        if (segmentRadios.length && customBox) {
            segmentRadios.forEach(radio => {
                radio.addEventListener('change', refreshCustomBox);
            });
            refreshCustomBox(); // initial
        }

        // ----- Live subject preview -----
        const subjectInput = document.getElementById('subject-input');
        const subjectPreview = document.getElementById('subject-preview');

        function updateSubjectPreview() {
            if (!subjectInput || !subjectPreview) return;
            const value = subjectInput.value.trim();
            subjectPreview.textContent = value || 'Your subject will appear here';
        }

        if (subjectInput && subjectPreview) {
            subjectInput.addEventListener('input', updateSubjectPreview);
            updateSubjectPreview(); // initial
        }

        // ----- Live body preview with Summernote -----
        const bodyPreview = document.getElementById('body-preview');
        const defaultBody = `
            <p>Start writing your LaLune story here. This is just a placeholder preview.</p>
            <p>You can style headings, paragraphs, links and more using the editor on the left.</p>
        `;

        function updateBodyPreview(contents) {
            if (!bodyPreview) return;
            const html = (contents || '').trim();
            bodyPreview.innerHTML = html || contents ;
        }

        // Listen for Summernote change events (summernote.js already initializes the editor)
            $('.summernote-editor').on('summernote.change', function (we, contents) {
                console.log(contents)
                updateBodyPreview(contents);
            });
        
    });
</script>
@endsection

