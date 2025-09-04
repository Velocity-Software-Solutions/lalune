@extends('layouts.admin')

@section('title', 'Shipping Options')

@section('content')
    <div
        class="h-full max-h-full p-5 mx-3 overflow-scroll bg-white rounded-md shadow-md dark:bg-gray-800 scroll scroll-m-0 custom-scroll">
        <!-- Page header -->
        <div class="flex items-center justify-between gap-3 mb-5">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white">Shipping Options</h1>

            <!-- Quick Add Button -->
            <button x-data @click="$dispatch('open-add')"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-semibold text-gray-800 dark:text-white">
                <span class="material-icons text-base">add</span>
                Add Option
            </button>
        </div>

        <!-- Filters (optional simple search by country/name) -->
        <div x-data="{ q: '' }" class="mb-4">
            <div class="relative ">
                <input x-model="q" type="text" placeholder="Search by name or country…"
                    class="w-full md:w-96 form-input pl-10 rounded">
                <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">search</span>
            </div>
            <script>
                // lightweight client filter (optional)
                document.addEventListener('alpine:init', () => {
                    Alpine.data('cardFilter', () => ({
                        query: '',
                        matches(text) {
                            if (!this.query) return true;
                            return text.toLowerCase().includes(this.query.toLowerCase());
                        }
                    }))
                })
            </script>
        </div>

        <!-- Cards Grid -->
        <div class="grid gap-4 md:gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {{-- Create “ghost” card that triggers modal --}}
            <button x-data @click="$dispatch('open-add')"
                class="group border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-2xl p-6 flex items-center justify-center hover:border-gray-400 dark:hover:border-gray-600 hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition">
                <div class="text-center">
                    <div class="mx-auto mb-2 w-10 h-10 rounded-full grid place-content-center bg-gray-100 dark:bg-gray-700">
                        <span class="material-icons">add</span>
                    </div>
                    <div class="font-semibold">Add Shipping Option</div>
                    <div class="text-xs text-gray-500">Quick create</div>
                </div>
            </button>

            @foreach ($shippingOptions as $option)
                <div x-data="{
                    open: false,
                    ...shippingForm({
                        id: {{ $option->id }},
                        name: @js($option->name),
                        name_ar: @js($option->name_ar),
                        price: @js($option->price),
                        delivery_time: @js($option->delivery_time),
                        description: @js($option->description),
                        country: @js($option->country),
                        cities: @js($option->cities ?? [])
                    })
                }"
                    class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden shadow-sm">

                    <!-- Compact view -->
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white" x-text="name"></h3>
                                    <span
                                        class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300"
                                        x-text="country || '—'"></span>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-300"
                                    x-text="description || 'No description'"></p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-800 dark:text-white">Price</div>
                                <div class="text-xl font-bold text-gray-800 dark:text-white"
                                    x-text="Number(price || 0).toFixed(2)"></div>
                                <div class="text-xs text-gray-800 dark:text-white" x-text="delivery_time || '—'"></div>
                            </div>
                        </div>

                        <!-- Cities badges -->
                        <div class="mt-3 flex flex-wrap gap-1.5 max-h-24 overflow-auto scrollbar-thin">
                            <template x-if="displayCities().length === 0">
                                <span class="text-xs text-gray-400">No cities selected</span>
                            </template>
                            <template x-for="c in displayCities()" :key="c">
                                <span
                                    class="text-[11px] px-2 py-1 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-200"
                                    x-text="c"></span>
                            </template>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="px-4 pb-4 flex items-center justify-between">
                        <button @click="open=!open"
                            class="text-sm inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-800 dark:text-white">
                            <span class="material-icons text-base">edit</span> Edit
                        </button>

                        <form action="{{ route('admin.shipping-options.destroy', $option->id) }}" method="POST"
                            onsubmit="return confirm('Delete this option?');">
                            @csrf @method('DELETE')
                            <button
                                class="transition duration-150 text-sm inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-700 dark:hover:bg-red-900/20">
                                <span class="material-icons text-base">delete</span> Delete
                            </button>
                        </form>
                    </div>

                    <!-- Edit Drawer -->
                    <div x-show="open" x-collapse class="border-t border-gray-200 dark:border-gray-700 p-4">
                        <form :action="updateAction" method="POST" class="space-y-3">
                            @csrf @method('PUT')
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Name</span>
                                    <input name="name" x-model="name" class="form-input w-full">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Arabic Name</span>
                                    <input name="name_ar" x-model="name_ar" class="form-input w-full">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Price</span>
                                    <input name="price" x-model="price" type="number" step="0.01"
                                        class="form-input w-full">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Delivery Time</span>
                                    <input name="delivery_time" x-model="delivery_time" class="form-input w-full">
                                </label>
                                <label class="sm:col-span-2 block">
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Description</span>
                                    <input name="description" x-model="description" class="form-input w-full">
                                </label>

                                <label class="block">
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Country</span>
                                    <select name="country" x-model="country" @change="onCountryChange()"
                                        class="form-select w-full">
                                        <option value="">Select Country</option>
                                        <template x-for="(list, name) in countriesMap" :key="name">
                                            <option :value="name" x-text="name" :selected="country === name">
                                            </option>
                                        </template>
                                    </select>
                                </label>

                                <!-- Cities chooser with search -->
                                <div class="sm:col-span-1">
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Cities</span>
                                    <div x-data="{ q: '' }"
                                        class="mt-1 rounded-xl border border-gray-200 dark:border-gray-700">
                                        <div class="p-2 border-b border-gray-200 dark:border-gray-700">
                                            <input x-model="q" type="text" placeholder="Filter cities…"
                                                class="form-input w-full">
                                        </div>
                                        <div class="max-h-40 overflow-auto p-2 space-y-1">
                                            <template x-for="c in filterCities(country, q)" :key="c">
                                                <label class="flex items-center gap-2 text-sm">
                                                    <input type="checkbox" class="rounded text-blue-600"
                                                        :value="c" x-model="cities">
                                                    <span x-text="c"></span>
                                                </label>
                                            </template>
                                            <template x-if="filterCities(country, q).length===0">
                                                <div class="text-xs text-gray-400">No results</div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Hidden inputs for cities[] -->
                                    <template x-for="c in cities" :key="'edit-hidden-' + c">
                                        <input type="hidden" name="cities[]" :value="c">
                                    </template>
                                </div>
                            </div>

                            <div class="pt-2 flex justify-end">
                                <button
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-600 hover:bg-gray-700 text-white">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $shippingOptions->links() }}
        </div>
    </div>

    <!-- Add Modal -->
    <div x-data="shippingForm()" x-on:open-add.window="open = true" x-show="open" x-transition.opacity
        class="fixed inset-0 z-40 bg-black/40 p-4">
        <div @click.outside="open=false"
            class="mx-auto max-w-2xl rounded-2xl bg-white dark:bg-gray-800 shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Add Shipping Option</h2>
                <button @click="open=false"
                    class="flex p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-800 dark:text-white">
                    <span class="material-icons">close</span>
                </button>
            </div>

            <form action="{{ route('admin.shipping-options.store') }}" method="POST" class="p-5 space-y-3">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="block">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Name</span>
                        <input name="name" x-model="name" class="form-input w-full">
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Arabic Name</span>
                        <input name="name_ar" x-model="name_ar" class="form-input w-full">
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Price</span>
                        <input name="price" x-model="price" type="number" step="0.01" class="form-input w-full">
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Delivery Time</span>
                        <input name="delivery_time" x-model="delivery_time" class="form-input w-full">
                    </label>
                    <label class="sm:col-span-2 block">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Description</span>
                        <input name="description" x-model="description" class="form-input w-full">
                    </label>

                    <label class="block">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Country</span>
                        <select name="country" x-model="country" @change="onCountryChange()" class="form-select w-full">
                            <option value="">Select Country</option>
                            <template x-for="(list, name) in countriesMap" :key="name">
                                <option :value="name" x-text="name"></option>
                            </template>
                        </select>
                    </label>

                    <div class="sm:col-span-1">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Cities</span>
                        <div x-data="{ q: '' }" class="mt-1 rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="p-2 border-b border-gray-200 dark:border-gray-700">
                                <input x-model="q" type="text" placeholder="Filter cities…"
                                    class="form-input w-full">
                            </div>
                            <div class="max-h-40 overflow-auto p-2 space-y-1">
                                <template x-for="c in filterCities(country, q)" :key="c">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" class="rounded text-blue-600" :value="c"
                                            x-model="cities">
                                        <span x-text="c"></span>
                                    </label>
                                </template>
                                <template x-if="filterCities(country, q).length===0">
                                    <div class="text-xs text-gray-400">Pick a country to list cities</div>
                                </template>
                            </div>
                        </div>
                        <!-- Hidden inputs -->
                        <template x-for="c in cities" :key="'new-hidden-' + c">
                            <input type="hidden" name="cities[]" :value="c">
                        </template>
                    </div>
                </div>

                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="open=false"
                        class="px-4 py-2 rounded-xl text-gray-800 dark:text-white border border-gray-200 dark:border-gray-700">Cancel</button>
                    <button class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white">Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Alpine helpers -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('shippingForm', (payload = {}) => ({
                // UI
                open: false,

                // Fields
                id: payload.id || null,
                name: payload.name || '',
                name_ar: payload.name_ar || '',
                price: payload.price || '',
                delivery_time: payload.delivery_time || '',
                description: payload.description || '',
                country: payload.country || '',
                // Normalize incoming cities:
                // accepts ['Dubai', ...] or [{city:'Dubai'}, ...]
                cities: Array.isArray(payload.cities) ?
                    payload.cities.map(c => typeof c === 'string' ? c : (c?.city ?? c)) : [],

                // Routes
                get updateAction() {
                    return this.id ? "{{ url('/admin/shipping-options') }}/" + this.id : "#";
                },

                // Data
                countriesMap: {
                    'US': [
                        'Alabama',
                        'Alaska',
                        'Arizona',
                        'Arkansas',
                        'California',
                        'Colorado',
                        'Connecticut',
                        'Delaware',
                        'Florida',
                        'Georgia',
                        'Hawaii',
                        'Idaho',
                        'Illinois',
                        'Indiana',
                        'Iowa',
                        'Kansas',
                        'Kentucky',
                        'Louisiana',
                        'Maine',
                        'Maryland',
                        'Massachusetts',
                        'Michigan',
                        'Minnesota',
                        'Mississippi',
                        'Missouri',
                        'Montana',
                        'Nebraska',
                        'Nevada',
                        'New Hampshire',
                        'New Jersey',
                        'New Mexico',
                        'New York',
                        'North Carolina',
                        'North Dakota',
                        'Ohio',
                        'Oklahoma',
                        'Oregon',
                        'Pennsylvania',
                        'Rhode Island',
                        'South Carolina',
                        'South Dakota',
                        'Tennessee',
                        'Texas',
                        'Utah',
                        'Vermont',
                        'Virginia',
                        'Washington',
                        'West Virginia',
                        'Wisconsin',
                        'Wyoming'
                    ],
                    'Canada': [
                        'Alberta',
                        'British Columbia',
                        'Manitoba',
                        'New Brunswick',
                        'Newfoundland and Labrador',
                        'Northwest Territories',
                        'Nova Scotia',
                        'Nunavut',
                        'Ontario',
                        'Prince Edward Island',
                        'Quebec',
                        'Saskatchewan',
                        'Yukon'
                    ]
                },


                // Methods
                onCountryChange() {
                    const allowed = new Set(this.countriesMap[this.country] || []);
                    this.cities = this.cities.filter(c => allowed.has(c));
                },
                filterCities(country, q) {
                    const list = this.countriesMap[country] || [];
                    if (!q) return list;
                    q = q.toLowerCase();
                    return list.filter(c => c.toLowerCase().includes(q));
                },
                displayCities() {
                    return this.cities.slice().sort((a, b) => a.localeCompare(b));
                }
            }))
        })
    </script>
@endsection
