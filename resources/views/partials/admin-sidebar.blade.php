{{-- Mobile overlay (click to close) --}}
<div
  x-show="$store.ui.sidebarOpen"
  x-transition.opacity
  class="fixed inset-0 z-30 bg-black/30 md:hidden"
  @click="$store.ui.closeSidebar()"
  aria-hidden="true">
</div>

<aside
  id="admin-sidebar"
  class="
    fixed z-40 inset-y-0 left-0
    w-60 md:w-72
    bg-white dark:bg-gray-800
    border-r border-gray-200 dark:border-gray-700
    m-0 md:m-4 md:rounded-lg
    overflow-y-auto custom-scrollbar scrollbar-hide
    transform transition-transform duration-300 ease-in-out
  "
  :class="$store.ui.sidebarOpen ? 'translate-x-0' : '-translate-x-[120%]'"
  x-trap.noscroll.inert="$store.ui.sidebarOpen"  {{-- optional: requires Alpine v3.13+ --}}
>
  <div class="p-4">
    {{-- your existing sidebar content (logo, user block, nav) --}}
    <div class="flex flex-col items-center justify-center text-black dark:text-white">
      <img src="{{ asset('images/logo.jpeg') }}" alt="Antiques Shop" class="rounded-full w-[70px] h-[70px] mb-4">
      <p class="text-xl font-semibold ">{{ auth()->user()->name }}</p>
      <p class="text-sm text-gray-700 dark:text-gray-300 font-regular">{{ auth()->user()->email }}</p>

      <form method="POST" action="{{ route('logout') }}" class="w-full">
        @csrf
        <button type="submit"
          class="flex items-center justify-center w-full gap-2 p-2 mt-5 mb-5 border-2 border-gray-600 rounded-md hover:bg-gray-600 hover:text-white">
          <span class="material-icons">logout</span> Log Out
        </button>
      </form>
    </div>

    <ul class=" space-y-3 dark:text-[#ffffff] text-lg">


        <li>
            <button
                class="flex items-center w-full gap-2 p-3 toggle rounded-xl hover:bg-gray-300 dark:hover:bg-gray-500 group">
                <span
                    class="text-2xl text-gray-400 transition duration-75 material-icons dark:text-gray-600 group-hover:text-gray-900 dark:group-hover:text-white">
                    build
                </span>
                <span class="flex-1 ml-3 text-left whitespace-nowrap">Setup </span>
                <span
                    class="text-black transition duration-100 material-icons justify-self-end material-symbols-filled dark:text-white">
                    keyboard_arrow_down
                </span>
            </button>
            <ul class="py-1 space-y-1 dropdown">
                <a href="{{ route('admin.orders.index') }}" class="w-full">
                    <li
                        class="flex items-center w-full gap-2 p-2 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-500 group">
                        <span
                            class="text-2xl text-gray-400 transition duration-75 material-icons dark:text-gray-600 group-hover:text-gray-900 dark:group-hover:text-white">
                            list_alt</span>
                        <span class="flex-1 ml-3 text-left whitespace-nowrap">Orders</span>

                    </li>
                </a>
      

    
                <a href="{{ route('admin.products.index') }}" class="w-full">
                    <li
                        class="flex items-center w-full gap-2 p-2 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-500 group">
                        <span
                            class="text-2xl text-gray-400 transition duration-75 material-icons dark:text-gray-600 group-hover:text-gray-900 dark:group-hover:text-white">
                            shopping_bag</span>
                        <span class="flex-1 ml-3 text-left whitespace-nowrap">Products </span>
                    </li>
                </a>
            
                <a href="{{ route('admin.categories.index') }}" class="w-full">
                    <li
                        class="flex items-center w-full gap-2 p-2 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-500 group">
                        <span
                            class="text-2xl text-gray-400 transition duration-75 material-icons dark:text-gray-600 group-hover:text-gray-900 dark:group-hover:text-white">
                            style</span>
                        <span class="flex-1 ml-3 text-left whitespace-nowrap">Categories </span>
                    </li>
                </a>
         
                         <a href="{{ route('admin.collections.index') }}" class="w-full">
                    <li
                        class="flex items-center w-full gap-2 p-2 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-500 group">
                        <span
                            class="text-2xl text-gray-400 transition duration-75 material-icons dark:text-gray-600 group-hover:text-gray-900 dark:group-hover:text-white">
                            grid_view</span>
                        <span class="flex-1 ml-3 text-left whitespace-nowrap">Collections </span>
                    </li>
                </a>

            <a href="{{ route('admin.promo-codes.index') }}" class="w-full">
                <li
                    class="flex items-center w-full gap-2 p-2 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-500 group">
                    <span
                        class="text-2xl text-gray-400 transition duration-75 material-icons dark:text-gray-600 group-hover:text-gray-900 dark:group-hover:text-white">
                        redeem </span>
                    <span class="flex-1 ml-3 text-left whitespace-nowrap">Promo Codes</span>
                </li>
            </a>
      
            {{-- <a href="{{ route('admin.shipping-options.index') }}" class="w-full">
                <li
                    class="flex items-center w-full gap-2 p-2 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-500 group">
                    <span
                        class="text-2xl text-gray-400 transition duration-75 material-icons dark:text-gray-600 group-hover:text-gray-900 dark:group-hover:text-white">
                        local_shipping </span>
                    <span class="flex-1 ml-3 text-left whitespace-nowrap">Shipping Options</span>
                </li>
            </a>
        --}}
            {{-- <a href="" class="w-full">
                <li
                    class="flex items-center w-full gap-2 p-2 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-500 group">
                    <span
                        class="text-2xl text-gray-400 transition duration-75 material-icons dark:text-gray-600 group-hover:text-gray-900 dark:group-hover:text-white">
                        people</span>
                    <span class="flex-1 ml-3 text-left whitespace-nowrap">Users</span>
                </li>
            </a> --}}
        </ul>
        </li>
    </ul>
  </div>
</aside>

