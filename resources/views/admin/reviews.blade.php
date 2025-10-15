@extends('layouts.admin')
<!-- Smile, breathe, and go slowly. - Thich Nhat Hanh -->
@section('title', 'Reviews')

@section('content')
    @php
        $badge = function ($status) {
            return match ($status) {
                'approved' => 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300',
                'rejected' => 'bg-rose-100 text-rose-800 ring-1 ring-rose-300',
                'pending' => 'bg-amber-100 text-amber-800 ring-1 ring-amber-300',
                default => 'bg-gray-100 text-gray-800 ring-1 ring-gray-300',
            };
        };
    @endphp

    <div class="p-4 bg-white rounded-2xl shadow" x-data="{ showFull: false, full: { author: '', email: '', product: '', date: '', rating: 0, image: '', comment: '' } }">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Product Reviews</h1>

            <form method="GET" class="flex items-center gap-2">
                <input type="text" name="q" value="{{ $q }}"
                    placeholder="Search name, email, comment, product…"
                    class="w-64 max-w-[60vw] rounded-md border-gray-300 text-sm focus:border-black focus:ring-black">
                <select name="status" class="rounded-md border-gray-300 text-sm focus:border-black focus:ring-black">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'approved', 'rejected'] as $opt)
                        <option value="{{ $opt }}" @selected($status === $opt)>{{ ucfirst($opt) }}</option>
                    @endforeach
                </select>
                <button class="px-3 py-2 rounded-md bg-black text-white text-sm">Filter</button>
            </form>
        </div>

        @if (session('success'))
            <div class="mt-3 rounded-md bg-green-50 text-green-800 px-3 py-2 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-gray-600 bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left w-40">Created</th>
                        <th class="px-3 py-2 text-left">Product</th>
                        <th class="px-3 py-2 text-left w-28">Rating</th>
                        <th class="px-3 py-2 text-left w-56">Author</th>
                        <th class="px-3 py-2 text-left">Comment</th>
                        <th class="px-3 py-2 text-left w-20">Image</th>
                        <th class="px-3 py-2 text-left w-28">Status</th>
                        <th class="px-3 py-2 text-right w-48">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($reviews as $rev)
                        @php
                            $r = max(0, min(5, (float) ($rev->rating ?? 0)));
                            $full = (int) floor($r);
                            $half = $r - $full >= 0.5 ? 1 : 0;
                            $empty = 5 - $full - $half;
                            $img = $rev->image_path ? asset('storage/' . $rev->image_path) : null;

                            $author = $rev->author_name ?? (optional($rev->user)->name ?? 'Anonymous');
                            $email = $rev->author_email ?? (optional($rev->user)->email ?? null);
                        @endphp
                        <tr class="align-top">
                            <td class="px-3 py-2 text-gray-600">
                                <div>{{ optional($rev->created_at)->format('Y-m-d H:i') }}</div>
                                <div class="text-xs text-gray-400">{{ optional($rev->created_at)->diffForHumans() }}</div>
                            </td>

                            <td class="px-3 py-2">
                                @if ($rev->product)
                                    <a href="{{ route('products.show', $rev->product_id) }}"
                                        class="text-black hover:underline">
                                        {{ $rev->product->name }}
                                    </a>
                                @else
                                    <span class="text-gray-500">—</span>
                                @endif
                            </td>

                            <td class="px-3 py-2">
                                <div class="inline-flex items-center gap-0.5 text-amber-500"
                                    aria-label="{{ $r }} out of 5">
                                    @for ($i = 0; $i < $full; $i++)
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 16 16">
                                            <rect x="1" y="1" width="14" height="14" rx="2"
                                                fill="currentColor" />
                                        </svg>
                                    @endfor
                                    @if ($half)
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 16 16">
                                            <rect x="1" y="1" width="7" height="14" rx="2"
                                                fill="currentColor" />
                                            <rect x="1" y="1" width="14" height="14" rx="2" fill="none"
                                                stroke="currentColor" stroke-width="1.3" />
                                        </svg>
                                    @endif
                                    @for ($i = 0; $i < $empty; $i++)
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 16 16">
                                            <rect x="1" y="1" width="14" height="14" rx="2" fill="none"
                                                stroke="currentColor" stroke-width="1.3" />
                                        </svg>
                                    @endfor
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ number_format($r, 1) }}/5</div>
                            </td>

                            <td class="px-3 py-2">
                                <div class="text-gray-900">{{ $author }}</div>
                                @if ($email)
                                    <div class="text-xs text-gray-600">{{ $email }}</div>
                                @endif
                            </td>

<td class="px-3 py-2">
  @if ($rev->comment)
    <div class="relative max-w-lg">
      <div class="max-h-24 overflow-y-auto pr-1 text-gray-800 text-[13px] leading-snug">
        {{ $rev->comment }}
      </div>
      <!-- soft fades (match table bg) -->
      <div class="pointer-events-none absolute inset-x-0 bottom-0 h-4 bg-gradient-to-t from-white to-transparent"></div>
    </div>

    <button type="button"
            class="mt-1 text-xs text-black/80 hover:underline"
            @click="
              showFull = true;
              full = {
                author: @js($author),
                email: @js($email),
                product: @js(optional($rev->product)->name ?? '—'),
                date: @js(optional($rev->created_at)->format('Y-m-d H:i') ?? ''),
                rating: Number({{ (float) ($rev->rating ?? 0) }}),
                image: @js($img),
                comment: @js($rev->comment),
              }
            ">
      View full →
    </button>
  @else
    <span class="text-gray-400">—</span>
  @endif
</td>


                            <td class="px-3 py-2">
                                @if ($img)
                                    <a href="{{ $img }}" target="_blank">
                                        <img src="{{ $img }}" alt=""
                                            class="w-12 h-12 object-cover rounded ring-1 ring-gray-200">
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            <td class="px-3 py-2">
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $badge($rev->status) }}">
                                    {{ ucfirst($rev->status ?? 'pending') }}
                                </span>
                            </td>

                            <td class="px-3 py-2 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    @if ($rev->status !== 'approved')
                                        <form method="POST" action="{{ route('admin.reviews.approve', $rev) }}">
                                            @csrf @method('PATCH')
                                            <button
                                                class="px-2.5 py-1.5 rounded-md bg-emerald-600 text-white text-xs hover:bg-emerald-700">
                                                Approve
                                            </button>
                                        </form>
                                    @endif

                                    @if ($rev->status !== 'rejected')
                                        <form method="POST" action="{{ route('admin.reviews.reject', $rev) }}">
                                            @csrf @method('PATCH')
                                            <button
                                                class="px-2.5 py-1.5 rounded-md bg-rose-600 text-white text-xs hover:bg-rose-700">
                                                Reject
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('admin.reviews.destroy', $rev) }}"
                                        onsubmit="return confirm('Delete this review?');">
                                        @csrf @method('DELETE')
                                        <button class="px-2.5 py-1.5 rounded-md border text-xs hover:bg-gray-50">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-gray-600">No reviews found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $reviews->links() }}
        </div>
        <!-- Full Review Modal -->
<div x-cloak x-show="showFull" x-transition
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
     @keydown.escape.window="showFull=false" role="dialog" aria-modal="true">
  <div class="w-full max-w-2xl rounded-2xl bg-white p-5 shadow-xl">
    <div class="flex items-start justify-between gap-4">
      <div class="min-w-0">
        <div class="text-base font-semibold text-black" x-text="full.author || 'Anonymous'"></div>
        <div class="text-xs text-gray-600" x-show="full.email" x-text="full.email"></div>
        <div class="text-xs text-gray-500 mt-0.5">
          <span x-text="full.product"></span>
          <span x-show="full.product && full.date"> • </span>
          <span x-text="full.date"></span>
        </div>

        <!-- Stars: safe pattern (no template in SVG) -->
        <div class="mt-1 inline-flex items-center gap-0.5 text-amber-500"
             :aria-label="`${full.rating} out of 5`">
          <template x-for="i in 5" :key="'star_'+i">
            <svg class="w-4 h-4" viewBox="0 0 16 16" aria-hidden="true">
              <!-- outline -->
              <rect x="1" y="1" width="14" height="14" rx="2"
                    fill="none" stroke="currentColor" stroke-width="1.3"></rect>
              <!-- fill: 14 full, 7 half, 0 empty -->
              <rect x="1" y="1"
                    :width="(() => {
                      const r = Number(full.rating) || 0;
                      const f = Math.floor(r);
                      const half = (r - f) >= 0.5;
                      if (i <= f) return 14;
                      if (i === f + 1 && half) return 7;
                      return 0;
                    })()"
                    height="14" rx="2" fill="currentColor"></rect>
            </svg>
          </template>
          <span class="ml-2 text-xs text-gray-600" x-text="`${Number(full.rating || 0).toFixed(1)}/5`"></span>
        </div>
      </div>

      <button class="text-2xl leading-none -mr-1" @click="showFull=false">&times;</button>
    </div>

    <div class="mt-3 space-y-3">
      <template x-if="full.image">
        <img :src="full.image" alt="Review image" class="w-full h-52 object-cover rounded-md ring-1 ring-gray-200">
      </template>

      <div class="relative">
        <div class="max-h-80 overflow-y-auto pr-2 text-[14px] leading-relaxed text-gray-900 whitespace-pre-line"
             x-text="full.comment"></div>
      </div>
    </div>

    <div class="mt-4 flex items-center justify-end">
      <button class="px-3 py-2 rounded-md border text-sm" @click="showFull=false">Close</button>
    </div>
  </div>
</div>

    </div>
    
@endsection
