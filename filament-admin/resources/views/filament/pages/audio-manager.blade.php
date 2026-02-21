<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-bold">فایل‌های صوتی ({{ count($rows) }})</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::input.wrapper class="min-w-64">
                        <x-filament::input wire:model.live.debounce.300ms="search" type="text" placeholder="جستجو در narrator، title، url..." />
                    </x-filament::input.wrapper>
                    <x-filament::button color="gray" wire:click="loadRows">بارگذاری مجدد</x-filament::button>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 text-right text-xs dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-3 font-bold">#</th>
                            <th class="px-3 py-3 font-bold">kotob_id</th>
                            <th class="px-3 py-3 font-bold">chapters_id</th>
                            <th class="px-3 py-3 font-bold">lang</th>
                            <th class="px-3 py-3 font-bold">narrator</th>
                            <th class="px-3 py-3 font-bold">title</th>
                            <th class="px-3 py-3 font-bold">url</th>
                            <th class="px-3 py-3 font-bold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->filteredRows as $row)
                            <tr class="hover:bg-primary-50/40 dark:hover:bg-gray-800/70">
                                <td class="px-3 py-3">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3">{{ $row['kotob_id'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['chapters_id'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['lang'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['narrator'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['title'] ?? '' }}</td>
                                <td class="px-3 py-3" dir="ltr">
                                    @if(! empty($row['url']))
                                        <a class="text-primary-600 hover:underline" href="{{ $row['url'] }}" target="_blank" rel="noreferrer">
                                            {{ \Illuminate\Support\Str::limit($row['url'], 48) }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <x-filament::button size="xs" color="gray" wire:click="startEdit({{ $row['__index'] }})">ویرایش</x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-sm text-gray-500">داده‌ای پیدا نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($editingIndex !== null)
            <section class="rounded-2xl border border-primary-200 bg-primary-50/40 p-5 shadow-sm dark:border-primary-500/30 dark:bg-gray-900">
                <h3 class="text-sm font-bold">ویرایش ردیف {{ $editingIndex + 1 }}</h3>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="edit.kotob_id" type="text" placeholder="kotob_id" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="edit.chapters_id" type="text" placeholder="chapters_id" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="edit.lang" type="text" placeholder="lang" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="edit.narrator" type="text" placeholder="narrator" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper class="lg:col-span-2">
                        <x-filament::input wire:model="edit.title" type="text" placeholder="title" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper class="lg:col-span-2">
                        <x-filament::input wire:model="edit.url" type="text" placeholder="url" dir="ltr" />
                    </x-filament::input.wrapper>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button color="primary" wire:click="saveEdit">ذخیره تغییرات</x-filament::button>
                    <x-filament::button color="gray" wire:click="cancelEdit">انصراف</x-filament::button>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
