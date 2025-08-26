@props([
    'name',
    'options' => [],
    'value' => [], // preselected values (from model or old input)
])
<div x-data="multiSelect(
    {{ Js::from($options) }},
    '{{ $name }}',
    {{ Js::from($value) }}
)" class="relative w-full" x-init="init()"
    @cities-updated.window="updateOptions($event.detail.options)">
    <!-- Select Box -->
    <div @click="open = !open"
        class="relative border border-gray-300 dark:border-gray-600 rounded-md p-2 cursor-pointer bg-white dark:bg-gray-800">

        <!-- Selected Tags -->
        <template x-for="id in selected" :key="id">
            <span class="inline-flex items-center bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-1 mb-1">
                <span x-text="getNameById(id)"></span>
                <button @click.stop="remove(id)" class="ml-1 text-blue-500 hover:text-red-500">×</button>
            </span>
        </template>

        <!-- Hidden Inputs -->
        <template x-for="id in selected" :key="'hidden-' + id">
            <input type="hidden" :name="name" :value="id" :id="name">
        </template>

        <!-- Search Field -->
        <input x-model="query" @keydown.enter.prevent @input="filter" type="text" placeholder="Search..."
            class="w-full mt-2 outline-none bg-transparent text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500" />
    </div>

    <!-- Options Dropdown -->
    <div x-show="open" @click.outside="open = false"
        class="border border-gray-300 dark:border-gray-600 rounded-md mt-1 max-h-40 overflow-auto z-10 absolute w-full bg-white dark:bg-gray-800 shadow-lg">
        <template x-for="option in filtered" :key="option.id">
            <div @click="toggle(option.id)"
                class="px-4 py-2 text-sm cursor-pointer flex justify-between hover:bg-gray-100 dark:hover:bg-gray-700"
                :class="{ 'bg-gray-200 dark:bg-gray-700': selected.includes(String(option.id)) }">
                <span x-text="option.name" class="text-gray-900 dark:text-gray-200"></span>
                <span x-show="selected.includes(String(option.id))" class="text-blue-600 text-sm">✔</span>
            </div>
        </template>
        <div x-show="filtered.length === 0" class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">No results.</div>
    </div>
</div>
