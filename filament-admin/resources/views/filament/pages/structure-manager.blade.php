<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-bold">مدیریت ساختار</h3>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button color="gray" wire:click="switchSection('categories')" :outlined="$section !== 'categories'">دسته‌بندی‌ها</x-filament::button>
                    <x-filament::button color="gray" wire:click="switchSection('chapters')" :outlined="$section !== 'chapters'">فصل‌ها</x-filament::button>
                    <x-filament::button color="gray" wire:click="loadStructure">بارگذاری مجدد</x-filament::button>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-filament::input.wrapper class="min-w-72">
                    <x-filament::input wire:model.live.debounce.300ms="search" type="text" placeholder="جستجو در ردیف‌ها..." />
                </x-filament::input.wrapper>
                <span class="text-xs text-gray-500">مجموع بخش فعلی: {{ count($this->filteredRows) }}</span>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 text-right text-xs dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        @if($section === 'categories')
                            <tr>
                                <th class="px-3 py-3 font-bold">#</th>
                                <th class="px-3 py-3 font-bold">id</th>
                                <th class="px-3 py-3 font-bold">title</th>
                                <th class="px-3 py-3 font-bold">sort_order</th>
                                <th class="px-3 py-3 font-bold">icon</th>
                                <th class="px-3 py-3 font-bold">عملیات</th>
                            </tr>
                        @else
                            <tr>
                                <th class="px-3 py-3 font-bold">#</th>
                                <th class="px-3 py-3 font-bold">id</th>
                                <th class="px-3 py-3 font-bold">category_id</th>
                                <th class="px-3 py-3 font-bold">parent_id</th>
                                <th class="px-3 py-3 font-bold">title</th>
                                <th class="px-3 py-3 font-bold">icon</th>
                                <th class="px-3 py-3 font-bold">عملیات</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->filteredRows as $row)
                            <tr class="hover:bg-primary-50/40 dark:hover:bg-gray-800/70">
                                <td class="px-3 py-3">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3">{{ $row['id'] ?? '' }}</td>

                                @if($section === 'categories')
                                    <td class="px-3 py-3">{{ $row['title'] ?? '' }}</td>
                                    <td class="px-3 py-3">{{ $row['sort_order'] ?? '' }}</td>
                                    <td class="px-3 py-3">{{ $row['icon'] ?? '' }}</td>
                                @else
                                    <td class="px-3 py-3">{{ $row['category_id'] ?? '' }}</td>
                                    <td class="px-3 py-3">{{ $row['parent_id'] ?? '' }}</td>
                                    <td class="px-3 py-3">{{ $row['title'] ?? '' }}</td>
                                    <td class="px-3 py-3">{{ $row['icon'] ?? '' }}</td>
                                @endif

                                <td class="px-3 py-3">
                                    <x-filament::button size="xs" color="gray" wire:click="startEdit({{ $row['__index'] }})">ویرایش</x-filament::button>
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
                <h3 class="text-sm font-bold">ویرایش {{ $section === 'categories' ? 'دسته‌بندی' : 'فصل' }} ردیف {{ $editingIndex + 1 }}</h3>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="edit.id" type="text" placeholder="id" />
                    </x-filament::input.wrapper>

                    @if($section === 'categories')
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.sort_order" type="text" placeholder="sort_order" />
                        </x-filament::input.wrapper>
                    @else
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.category_id" type="text" placeholder="category_id" />
                        </x-filament::input.wrapper>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.parent_id" type="text" placeholder="parent_id" />
                        </x-filament::input.wrapper>
                    @endif

                    <x-filament::input.wrapper class="lg:col-span-2">
                        <x-filament::input wire:model="edit.title" type="text" placeholder="title" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper class="lg:col-span-2">
                        <x-filament::input wire:model="edit.icon" type="text" placeholder="icon" />
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
