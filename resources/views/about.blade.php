@extends('layouts.app')

@push('head')
    <!-- Optional: nice cursive headline (remove if you don't want it) -->
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
@endpush

@section('content')
    <section class="relative mx-6 my-8 rounded-3xl overflow-hidden"
        style="background-image:url('{{ asset('images/about-hero.png') }}'); background-size:cover; background-position:center; background-attachment: fixed;">

        <!-- subtle dark veil for readability -->
        <div class="absolute inset-0 bg-black/10"></div>

        <!-- content container -->
        <div class="relative px-6 sm:px-10 lg:px-14 py-14 lg:py-20">
            <div class="sm:max-w-[36rem] md:max-w-[20rem] lg:max-w-[42rem] xl:max-w-[54rem] pr-0 lg:pr-40">
                <h1 class="text-5xl md:text-6xl lg:text-7xl font-bold text-white drop-shadow-sm mb-5">
                    About <span style="font-family:'Dancing Script', cursive;">LaLune By NE</span>
                </h1>

                <p
                    class="text-base md:text-lg leading-relaxed text-white/90 bg-black rounded-xl p-4 md:p-5 backdrop-blur-sm">
                    At La lune by NE, we believe clothing is more than what you wear – it’s how you feel. Our brand was born
                    from a love for simple, feminine, and timeless style that embraces modesty without compromising on
                    elegance.
                    Every piece is thoughtfully designed and made locally in Ontario, using sustainable materials and
                    ethical production practices. We create in limited quantities, so each garment feels special, unique,
                    and made just for you.
                    Our collections are designed to be versatile: soft knits, clean lines, and interchangeable staples that
                    fit seamlessly into your everyday life, while also offering beautiful statement pieces for special
                    occasions.
                    La lune by NE is more than a clothing line – it’s a celebration of slow fashion, conscious choices, and
                    women who find beauty in simplicity.
                    Welcome to our world – where comfort, elegance, and sustainability meet under the moonlight.
                </p>
            </div>

            <!-- CONTACT BUBBLE -->
            <div
                class="hidden md:flex items-center justify-center
             absolute bottom-[30%] translate-y-1/2
             right-[-5%]
             w-[420px] h-[420px] lg:w-[500px] lg:h-[500px]
             rounded-full shadow-2xl ring-1 ring-white/40
             bg-white/40 backdrop-blur-md p-4">
                <form method="POST" action="{{ route('contact.submit') }}" class="w-[78%] text-center">
                    @csrf
                    <h2 class="text-2xl font-semibold text-gray-900 mb-4">Contact Us</h2>
                    <div class="space-y-3">
                        <input type="text" name="name" required placeholder="Your name"
                            class="w-full rounded-full border border-gray-300 bg-white/70 focus:bg-white px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-black/50">
                        <input type="email" name="email" required placeholder="Email address"
                            class="w-full rounded-full border border-gray-300 bg-white/70 focus:bg-white px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-black/50">
                        <textarea name="message" rows="3" required placeholder="Message"
                            class="w-full rounded-2xl border border-gray-300 bg-white/70 focus:bg-white px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-black/50"></textarea>
                    </div>
                    <button type="submit"
                        class="mt-5 inline-flex items-center justify-center w-full rounded-full
                       bg-gradient-to-r from-black via-neutral-700 to-black text-white
                       px-5 py-2.5 bg-[length:200%_100%] bg-left hover:bg-right transition-all duration-200">
                        Send Message
                    </button>
                </form>
            </div>
        </div>
    </section>

    {{-- Mobile fallback: bubble below the hero as a card --}}
    <section class="md:hidden px-6 mt-4 mb-10">
        <div class="rounded-3xl bg-white shadow-lg ring-1 ring-black/5 p-5">
            <h2 class="text-2xl font-semibold text-gray-900 mb-4 text-center">Contact Us</h2>
            <form method="POST" action="{{ route('contact.submit') }}" class="space-y-3">
                @csrf
                <input type="text" name="name" required placeholder="Your name"
                    class="w-full rounded-full border border-gray-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-black/50">
                <input type="email" name="email" required placeholder="Email address"
                    class="w-full rounded-full border border-gray-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-black/50">
                <textarea name="message" rows="3" required placeholder="Message"
                    class="w-full rounded-2xl border border-gray-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-black/50"></textarea>
                <button type="submit"
                    class="w-full rounded-full bg-black text-white px-5 py-2.5 hover:bg-black/90 transition">
                    Send Message
                </button>
            </form>
        </div>
    </section>
@endsection
