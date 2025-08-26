<header class="flex w-[96%] justify-end items-center p-2 justify-self-end text-sm m-3 ml-6 not-has-[nav]:hidden">
    @if (Route::has('login'))
        <nav class="flex items-center justify-between w-full gap-4">
            @auth
                @if (auth()->user()->hasVerifiedEmail())
                    <div class="flex gap-4">
                        <button type="button"
                            class="transition duration-150 inline-flex items-center justify-center rounded-md text-gray-700 dark:bg-gray-700 dark:text-white hover:bg-white dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300 p-2"
                            @click="$store.ui.toggleSidebar()" :aria-expanded="$store.ui.sidebarOpen"
                            aria-controls="admin-sidebar" aria-label="Toggle sidebar">
                            <span class="material-icons">menu</span>
                        </button>
                        <a href="{{ route('admin.orders.index') }}" class="w-full">
                            <span
                                class="p-2 text-gray-400 transition duration-75 border-2 border-gray-400 border-solid rounded-full material-icons dark:border-gray-600 dark:text-gray-600 hover:text-gray-900 dark:hover:text-white hover:border-gray-900 dark:hover:border-white">
                                receipt_long</span>
                        </a>
                        <a href="{{ route('admin.products.index') }}" class="w-full">
                            <span
                                class="p-2 text-gray-400 transition duration-75 border-2 border-gray-400 border-solid rounded-full material-icons dark:border-gray-600 dark:text-gray-600 hover:text-gray-900 dark:hover:text-white hover:border-gray-900 dark:hover:border-white">
                                shopping_bag</span>
                        </a>
                        <a href="{{ route('admin.categories.index') }}" class="w-full">
                            <span
                                class="p-2 text-gray-400 transition duration-75 border-2 border-gray-400 border-solid rounded-full material-icons dark:border-gray-600 dark:text-gray-600 hover:text-gray-900 dark:hover:text-white hover:border-gray-900 dark:hover:border-white">
                                category</span>
                        </a>
                    </div>
                    <a href="{{ route(auth()->user()->type == 1 ? 'admin.dashboard' : 'employer.dashboard') }}"
                        class="inline-block px-5 py-1.5 text-black dark:text-white border border-black hover:border-gray-700 dark:border-gray-400 dark:hover:border-white hover:bg-gray-400 transition duration-200 rounded-sm text-sm leading-normal">
                        Dashboard
                    </a>
                @endif
            @else
                <div class="flex justify-end w-full gap-4">
                    <a href="{{ route('welcome') }}"
                        class="inline-block px-5 py-1.5 text-[#EDEDEC] border border-white hover:border-[#19140035] rounded-sm text-sm leading-normal">
                        Home
                    </a>
                    <a href="{{ route('login') }}"
                        class="inline-block px-5 py-1.5 text-[#EDEDEC] border border-white hover:border-[#19140035] rounded-sm text-sm leading-normal">
                        Log in
                    </a>

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                            class="inline-block px-5 py-1.5 text-[#EDEDEC] border border-white hover:border-[#19140035] rounded-sm text-sm leading-normal">
                            Register
                        </a>
                    @endif
                </div>
            @endauth

        </nav>
    @endif

</header>
