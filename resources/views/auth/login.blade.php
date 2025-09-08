<x-guest-layout>
    <section
        class="relative min-h-screen flex items-center justify-center px-4 py-8
                   bg-cover bg-center md:bg-fixed"
        style="background-image:url('{{ asset('images/about-hero.png') }}')">
            <div
            class="relative w-full max-w-sm mx-auto rounded-2xl overflow-hidden
                bg-black/30 backdrop-blur-lg ring-1 ring-white/10 shadow-xl
                max-h-[85vh]">
            <div class="p-5 sm:p-6">
                {{-- Brand (smaller) --}}
                <div class="flex flex-col items-center gap-2 mb-4">
                    <img src="{{ asset('images/logo.jpeg') }}" alt="LaLune by NE" class="h-10 rounded-full">
                    <h1 class="text-xl font-semibold text-white tracking-wide">Welcome back</h1>
                </div>

                {{-- Session Status --}}
                <x-auth-session-status class="mb-3 text-sm text-green-200" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf

                    {{-- Email --}}
                    <div>
                        <label for="email"
                            class="block text-sm font-medium text-white/90 mb-1">{{ __('Email') }}</label>
                        <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus
                            autocomplete="username"
                            class="w-full h-10 rounded-lg bg-white/85 focus:bg-white
                     border border-white/30 text-gray-900 placeholder:text-gray-500
                     focus:ring-2 focus:ring-black focus:border-black" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1 text-red-200" />
                    </div>

                    {{-- Password --}}
                    <div>
                        <label for="password"
                            class="block text-sm font-medium text-white/90 mb-1">{{ __('Password') }}</label>
                        <x-text-input id="password" type="password" name="password" required
                            autocomplete="current-password"
                            class="w-full h-10 rounded-lg bg-white/85 focus:bg-white
                     border border-white/30 text-gray-900 placeholder:text-gray-500
                     focus:ring-2 focus:ring-black focus:border-black" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1 text-red-200" />
                    </div>

                    {{-- Remember + Forgot (tight) --}}
                    <div class="flex items-center justify-between">
                        <label for="remember_me" class="inline-flex items-center gap-2">
                            <input id="remember_me" type="checkbox" name="remember"
                                class="rounded border-white/30 bg-transparent text-black
                            focus:ring-black focus:ring-offset-0">
                            <span class="text-sm text-white/80">{{ __('Remember me') }}</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}"
                                class="text-xs text-white/80 hover:text-white underline underline-offset-4">
                                {{ __('Forgot?') }}
                            </a>
                        @endif
                    </div>

                    {{-- Submit (smaller) --}}
                    <button type="submit"
                        class="w-full inline-flex items-center justify-center rounded-full
                   bg-gradient-to-r from-black via-neutral-700 to-black
                   text-white font-medium px-5 py-2.5
                   bg-[length:200%_100%] bg-left hover:bg-right transition-all">
                        {{ __('Log in') }}
                    </button>
                </form>
            </div>
        </div>
    </section>
</x-guest-layout>
