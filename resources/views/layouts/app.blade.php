<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Lalune By NE') }}</title>
    <link rel="canonical" href="{{ url()->current() }}" />
    <link rel="canonical" href="https://lalunebyne.com/products" />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montaga&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/logo.jpeg') }}">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    @vite(['resources/css/app.css', 'resources/css/mousecursor.css', 'resources/js/app.js', 'resources/js/magiccursor.js'])
    @stack('head')

</head>

<body class="text-gray-900 bg-gray-50">
    <header x-data="{ mobileOpen: false, stagger(i) { return `transition-delay:${i*60}ms` } }" class="top-0 z-50 shadow bg-black m-6 rounded-3xl">
        <div class="flex items-center justify-between px-[5%] py-2 mx-auto max-w-7xl">

            {{-- Left: Logo --}}
            <div class="flex items-center gap-3">
                <a href="/" class="text-2xl font-bold">
                    <img src="{{ asset('images/logo-horizontal.jpg') }}" alt="{{ __('messages.logo_alt') }}"
                        class="h-[80px] lg:h-[90px] xl:h-[100px]">
                </a>
            </div>

            {{-- Desktop Nav --}}
            <nav class="hidden md:flex gap-6 items-center text-gray-100">
                <a href="/" class="nav-item hover:text-gray-200"><span
                        class="text-2xl material-icons">home</span></a>

                <a href="{{ route('cart.index') }}" class="nav-item hover:text-gray-200 relative">
                    <span class="text-2xl material-icons">shopping_cart</span>
                    @if (session('cart'))
                        <span
                            class="absolute -top-1 -right-1 bg-gray-200 text-black text-[10px] rounded-full w-4 h-4 inline-flex items-center justify-center font-semibold">
                            {{ count(session('cart')) }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('collections') }}" class="nav-item hover:text-gray-200">Collections</a>
                <a href="{{ route('about-us') }}" class="nav-item hover:text-gray-200">About Us</a>

                {{-- Policies dropdown (desktop) --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open=!open" @keydown.escape.window="open=false" @click.away="open=false"
                        :aria-expanded="open" aria-haspopup="true"
                        class="flex items-center gap-2 hover:text-white px-3 py-2 rounded-xl focus:outline-none focus:ring-2 focus:ring-white/30">
                        <span>Policies</span>
                        <span class="material-icons text-base transition-transform duration-200"
                            :class="open ? 'rotate-180' : ''">expand_more</span>
                    </button>

                    <div x-show="open" x-transition class="absolute right-0 mt-3 w-64 origin-top-right z-50"
                        style="display:none;">
                        <div
                            class="rounded-2xl bg-white/95 backdrop-blur-md shadow-2xl ring-1 ring-black/5 overflow-hidden">
                            <div class="h-0.5 w-full bg-gradient-to-r from-neutral-900 via-neutral-600 to-neutral-900">
                            </div>
                            <div class="py-2">
                                <a href="{{ route('return-policy') }}"
                                    class="flex items-center gap-3 px-4 py-3 text-gray-800 hover:bg-black/5">
                                    <span class="material-icons text-neutral-700">u_turn_left</span>
                                    <div>
                                        <div class="font-medium">Return Policy</div>
                                        <div class="text-xs text-gray-500">Refund/credit (0–7d), credit (8–14d)</div>
                                    </div>
                                </a>
                                <a href="{{ route('privacy-policy') }}"
                                    class="flex items-center gap-3 px-4 py-3 text-gray-800 hover:bg-black/5">
                                    <span class="material-icons text-neutral-700">shield</span>
                                    <div>
                                        <div class="font-medium">Privacy Policy</div>
                                    </div>
                                </a>
                                <a href="{{ route('terms-conditions') }}"
                                    class="flex items-center gap-3 px-4 py-3 text-gray-800 hover:bg-black/5">
                                    <span class="material-icons text-neutral-700">gavel</span>
                                    <div>
                                        <div class="font-medium">Terms &amp; Conditions</div>
                                    </div>
                                </a>
                            </div>
                            <div
                                class="px-4 py-2 bg-gradient-to-r from-black via-neutral-700 to-black text-white text-xs">
                                Shop with confidence ✨</div>
                        </div>
                    </div>
                </div>

                @auth
                    <a href="{{ route('admin.dashboard') }}"
                        class="hover:text-gray-200">{{ __('messages.nav_dashboard') }}</a>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="hover:text-gray-200">{{ __('messages.nav_logout') }}</button>
                    </form>
                @endauth
            </nav>

            {{-- Mobile: Hamburger --}}
            <button @click="mobileOpen=true"
                class="md:hidden text-white p-2 rounded-lg focus:ring-2 focus:ring-white/30">
                <span class="material-icons text-3xl">menu</span>
            </button>
        </div>

        {{-- Mobile Fullscreen Overlay Menu --}}
        <div x-show="mobileOpen" x-transition.opacity class="fixed inset-0 z-[60] bg-black/95 text-white md:hidden"
            style="display:none;" @keydown.escape.window="mobileOpen=false">
            <div class="absolute top-0 left-0 right-0 p-5 flex items-center justify-between">
                <img src="{{ asset('images/logo-horizontal.jpg') }}" alt="{{ __('messages.logo_alt') }}"
                    class="h-10">
                <button @click="mobileOpen=false"
                    class="p-2 focus:outline-none focus:ring-2 focus:ring-white/30 rounded-lg">
                    <span class="material-icons text-3xl">close</span>
                </button>
            </div>

            <nav class="pt-24 px-8 space-y-2">
                {{-- Each item drops from top with stagger --}}
                <a href="/" @click="mobileOpen=false" class="block text-xl py-3 border-b border-white/10"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="-translate-y-6 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100" :style="stagger(0)">
                    Home
                </a>

                <a href="{{ route('collections') }}" @click="mobileOpen=false"
                    class="block text-xl py-3 border-b border-white/10"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="-translate-y-6 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100" :style="stagger(1)">
                    Collections
                </a>

                <a href="{{ route('about-us') }}" @click="mobileOpen=false"
                    class="block text-xl py-3 border-b border-white/10"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="-translate-y-6 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100" :style="stagger(2)">
                    About Us
                </a>

                {{-- Cart with badge --}}
                <a href="{{ route('cart.index') }}" @click="mobileOpen=false"
                    class="relative block text-xl py-3 border-b border-white/10"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="-translate-y-6 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100" :style="stagger(3)">
                    Cart
                    @if (session('cart'))
                        <span
                            class="ml-2 inline-flex items-center justify-center text-xs bg-white text-black rounded-full px-2 h-5 align-middle">
                            {{ count(session('cart')) }}
                        </span>
                    @endif
                </a>

                {{-- Policies group --}}
                <div class="pt-2" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="-translate-y-6 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100" :style="stagger(4)">
                    <div class="text-sm uppercase tracking-wider text-white/60 mb-1">Policies</div>
                    <div class="grid">
                        <a href="{{ route('return-policy') }}" @click="mobileOpen=false"
                            class="py-2 text-lg hover:text-white/90">Return Policy</a>
                        <a href="{{ route('privacy-policy') }}" @click="mobileOpen=false"
                            class="py-2 text-lg hover:text-white/90">Privacy Policy</a>
                        <a href="{{ route('terms-conditions') }}" @click="mobileOpen=false"
                            class="py-2 text-lg hover:text-white/90">Terms &amp; Conditions</a>
                    </div>
                </div>

                @auth
                    <div class="pt-2" x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="-translate-y-6 opacity-0"
                        x-transition:enter-end="translate-y-0 opacity-100" :style="stagger(5)">
                        <div class="grid">
                            <a href="{{ route('admin.dashboard') }}" @click="mobileOpen=false"
                                class="py-2 text-lg hover:text-white/90">{{ __('messages.nav_dashboard') }}</a>
                            <form action="{{ route('logout') }}" method="POST" class="py-2">
                                @csrf
                                <button type="submit"
                                    class="text-left w-full text-lg hover:text-white/90">{{ __('messages.nav_logout') }}</button>
                            </form>
                        </div>
                    </div>
                @endauth

                {{-- CTA at bottom --}}
                <div class="pt-4" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="-translate-y-6 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100" :style="stagger(6)">
                    <a href="{{ route('collections') }}" @click="mobileOpen=false"
                        class="inline-flex items-center justify-center w-full rounded-full
                  bg-gradient-to-r from-white/90 via-white to-white/90 text-black
                  px-6 py-3 font-medium bg-[length:200%_100%] bg-left hover:bg-right transition-all">
                        Shop Now
                    </a>
                </div>
            </nav>
        </div>
    </header>
    @if (session('success'))
        <div
            class="flex justify-center items-center px-4 py-2 mx-6 rounded bg-green-100 text-green-800 border border-green-200">
            {{ session('success') }}
        </div>
    @endif
    <main class="py-4">
        @yield('content')
    </main>

    <footer class="py-8 mt-10 bg-gray-50 border-t">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                {{-- Left: Newsletter --}}
                <div class="text-left">
                    <h3 class="text-sm font-semibold text-gray-800">
                        Stay updated
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 max-w-sm">
                        Subscribe for exclusive updates, new collections, and promo codes.
                    </p>

                    <form method="POST" action="{{ route('newsletter.subscribe') }}"
                        class="mt-3 flex flex-col sm:flex-row gap-2 sm:gap-3">
                        @csrf
                        <div class="hidden" aria-hidden="true">
                            <label>Leave this field empty</label>
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <input type="hidden" name="hp_time" :value="hpTime">
                        <input type="hidden" name="source" value="footer">
                        <input type="email" name="email" required placeholder="Enter your email"
                            class="w-full sm:w-64 px-3 py-2 rounded-xl text-xs sm:text-sm
                               border border-gray-300 bg-white
                               placeholder-gray-400
                               focus:outline-none focus:ring-2 focus:ring-black focus:border-black" />
                        @error('email')
                            <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                        @enderror
                        <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 rounded-xl
                               text-xs sm:text-sm font-semibold text-white
                               bg-gradient-to-r from-black via-neutral-700 to-black
                               bg-[length:200%_100%] bg-left hover:bg-right
                               transition-all duration-500">
                            Subscribe
                        </button>
                    </form>

                    <p class="mt-1 text-[10px] text-gray-400">
                        By subscribing, you agree to receive marketing emails. You can unsubscribe at any time.
                    </p>
                </div>

                {{-- Right: Copyright --}}
                <div class="text-center md:text-right text-gray-500 text-xs sm:text-sm">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Al Khinjar Al Dhahbi') }}.
                    {{ __('messages.footer_rights') }}
                </div>
            </div>
        </div>
    </footer>

</body>

</html>
