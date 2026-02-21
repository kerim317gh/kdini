<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-bold">کتاب‌ها ({{ count($books) }})</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::input.wrapper class="min-w-64">
                        <x-filament::input wire:model.live.debounce.300ms="search" type="text" placeholder="جستجو در عنوان، نسخه، لینک..." />
                    </x-filament::input.wrapper>
                    <x-filament::button color="gray" wire:click="loadBooks">بارگذاری مجدد</x-filament::button>
                    <x-filament::button color="success" wire:click="normalizeSqlUrls">اصلاح لینک‌های kotob</x-filament::button>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 text-right text-xs dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-3 font-bold">#</th>
                            <th class="px-3 py-3 font-bold">ID</th>
                            <th class="px-3 py-3 font-bold">عنوان</th>
                            <th class="px-3 py-3 font-bold">نسخه</th>
                            <th class="px-3 py-3 font-bold">وضعیت</th>
                            <th class="px-3 py-3 font-bold">لینک</th>
                            <th class="px-3 py-3 font-bold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->filteredRows as $row)
                            @php
                                $rowIndex = $row['__index'];
                                $url = null;
                                foreach (['sql_download_url', 'download_url', 'url'] as $urlKey) {
                                    if (!empty($row[$urlKey])) {
                                        $url = (string) $row[$urlKey];
                                        break;
                                    }
                                }
                            @endphp
                            <tr class="hover:bg-primary-50/40 dark:hover:bg-gray-800/70">
                                <td class="px-3 py-3">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3">{{ $row['id'] ?? '' }}</td>
                                <td class="px-3 py-3 font-semibold">{{ $row['title'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['version'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['status'] ?? '' }}</td>
                                <td class="px-3 py-3" dir="ltr">
                                    @if($url)
                                        <a class="text-primary-600 hover:underline" href="{{ $url }}" target="_blank" rel="noreferrer">
                                            {{ \Illuminate\Support\Str::limit($url, 56) }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <x-filament::button size="xs" color="gray" wire:click="startEdit({{ $rowIndex }})">ویرایش</x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-sm text-gray-500">داده‌ای پیدا نشد.</td>
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
                        <x-filament::input wire:model="edit.id" type="text" placeholder="id" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="edit.version" type="text" placeholder="version" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper class="lg:col-span-2">
                        <x-filament::input wire:model="edit.title" type="text" placeholder="title" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper class="lg:col-span-2">
                        <textarea wire:model="edit.description" rows="4" placeholder="description" class="fi-input block w-full rounded-lg border-none bg-transparent text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-500 dark:text-white dark:ring-white/20"></textarea>
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="edit.is_default" type="text" placeholder="is_default" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="edit.is_downloaded_on_device" type="text" placeholder="is_downloaded_on_device" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="edit.status" type="text" placeholder="status" />
                    </x-filament::input.wrapper>

                    <div></div>

                    <x-filament::input.wrapper class="lg:col-span-2">
                        <x-filament::input wire:model="edit.sql_download_url" type="text" placeholder="sql_download_url" dir="ltr" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper class="lg:col-span-2">
                        <x-filament::input wire:model="edit.download_url" type="text" placeholder="download_url" dir="ltr" />
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
