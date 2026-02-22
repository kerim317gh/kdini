<?php

namespace App\Filament\Pages;

use App\Support\KdiniMetadataRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class BaseStructureManager extends Page
{
    protected static ?string $title = 'ساختار پایه';

    protected string $view = 'filament.pages.base-structure-manager';

    protected static ?int $navigationSort = 31;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string | UnitEnum | null $navigationGroup = 'متادیتا';

    public string $section = 'categories';

    public string $search = '';

    /**
     * @var array<string, mixed>
     */
    public array $structure = [
        'schema_version' => 1,
        'data_version' => 1,
        'categories' => [],
        'chapters' => [],
    ];

    public string $schemaVersion = '1';

    public string $dataVersion = '1';

    public ?int $editingIndex = null;

    public bool $isCreating = false;

    /**
     * @var array<string, mixed>
     */
    public array $edit = [];

    public function mount(): void
    {
        $this->loadStructure();
    }

    public function loadStructure(): void
    {
        try {
            $payload = KdiniMetadataRepository::readJson('json/base_structure.json');
            if (! is_array($payload)) {
                throw new \RuntimeException('ساختار فایل باید آبجکت باشد.');
            }

            $payload['categories'] = is_array($payload['categories'] ?? null)
                ? array_values(array_filter($payload['categories'], 'is_array'))
                : [];

            $payload['chapters'] = is_array($payload['chapters'] ?? null)
                ? array_values(array_filter($payload['chapters'], 'is_array'))
                : [];

            $this->structure = $payload;
            $this->schemaVersion = (string) ($payload['schema_version'] ?? 1);
            $this->dataVersion = (string) ($payload['data_version'] ?? 1);
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('خواندن base_structure.json ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $this->structure = [
                'schema_version' => 1,
                'data_version' => 1,
                'categories' => [],
                'chapters' => [],
            ];
            $this->schemaVersion = '1';
            $this->dataVersion = '1';
        }
    }

    public function saveMetadata(): void
    {
        $this->structure['schema_version'] = $this->toIntOrOriginal($this->schemaVersion);
        $this->structure['data_version'] = $this->toIntOrOriginal($this->dataVersion);

        $this->persistStructure('مشخصات فایل ذخیره شد');
    }

    public function switchSection(string $section): void
    {
        $this->section = in_array($section, ['categories', 'chapters'], true) ? $section : 'categories';
        $this->cancelEdit();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function currentRows(): array
    {
        $rows = $this->structure[$this->section] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredRowsProperty(): array
    {
        $query = mb_strtolower(trim($this->search));
        $result = [];

        $rows = $this->section === 'chapters'
            ? $this->prepareChapterRows($this->currentRows())
            : $this->currentRows();

        foreach ($rows as $index => $row) {
            $haystack = mb_strtolower(implode(' ', array_map(static fn ($value): string => (string) $value, $row)));
            if ($query !== '' && ! str_contains($haystack, $query)) {
                continue;
            }

            $row['__index'] = $row['__index'] ?? $index;
            $result[] = $row;
        }

        return $result;
    }

    public function startEdit(int $index): void
    {
        $rows = $this->currentRows();

        if (! isset($rows[$index])) {
            return;
        }

        $row = $rows[$index];

        $this->isCreating = false;
        $this->editingIndex = $index;

        if ($this->section === 'categories') {
            $this->edit = [
                'id' => (string) ($row['id'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'sort_order' => (string) ($row['sort_order'] ?? ''),
                'icon' => (string) ($row['icon'] ?? ''),
            ];

            return;
        }

        $this->edit = [
            'id' => (string) ($row['id'] ?? ''),
            'category_id' => (string) ($row['category_id'] ?? ''),
            'parent_id' => (string) ($row['parent_id'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'title_fa' => (string) ($row['title_fa'] ?? ''),
            'title_en' => (string) ($row['title_en'] ?? ''),
            'title_tr' => (string) ($row['title_tr'] ?? ''),
            'title_ru' => (string) ($row['title_ru'] ?? ''),
            'title_tk' => (string) ($row['title_tk'] ?? ''),
            'icon' => (string) ($row['icon'] ?? ''),
        ];
    }

    public function startCreate(): void
    {
        $this->isCreating = true;
        $this->editingIndex = null;

        if ($this->section === 'categories') {
            $this->edit = [
                'id' => '',
                'title' => '',
                'sort_order' => (string) (count($this->currentRows()) + 1),
                'icon' => '',
            ];

            return;
        }

        $this->edit = [
            'id' => '',
            'category_id' => '',
            'parent_id' => '0',
            'title' => '',
            'title_fa' => '',
            'title_en' => '',
            'title_tr' => '',
            'title_ru' => '',
            'title_tk' => '',
            'icon' => '',
        ];
    }

    public function startCreateChild(int $parentId, int $categoryId): void
    {
        $this->section = 'chapters';
        $this->isCreating = true;
        $this->editingIndex = null;
        $this->edit = [
            'id' => '',
            'category_id' => (string) $categoryId,
            'parent_id' => (string) $parentId,
            'title' => '',
            'title_fa' => '',
            'title_en' => '',
            'title_tr' => '',
            'title_ru' => '',
            'title_tk' => '',
            'icon' => '',
        ];
    }

    public function removeRow(int $index): void
    {
        $rows = $this->currentRows();
        if (! isset($rows[$index])) {
            return;
        }

        unset($rows[$index]);
        $this->structure[$this->section] = array_values($rows);
        $this->persistStructure('ردیف حذف شد');

        if ($this->editingIndex === $index) {
            $this->cancelEdit();
        }
    }

    public function cancelEdit(): void
    {
        $this->isCreating = false;
        $this->editingIndex = null;
        $this->edit = [];
    }

    public function saveEdit(): void
    {
        $isCreating = $this->isCreating;

        if (! $isCreating && $this->editingIndex === null) {
            return;
        }

        $rows = $this->currentRows();
        if (! $isCreating && ! isset($rows[$this->editingIndex])) {
            return;
        }

        $row = $isCreating ? [] : $rows[$this->editingIndex];

        if ($this->section === 'categories') {
            $row['id'] = $this->toIntOrOriginal((string) ($this->edit['id'] ?? ''));
            $row['title'] = trim((string) ($this->edit['title'] ?? ''));
            $row['sort_order'] = $this->toIntOrOriginal((string) ($this->edit['sort_order'] ?? ''));
            $icon = trim((string) ($this->edit['icon'] ?? ''));
            $row['icon'] = $icon === '' ? null : $icon;
        } else {
            $row['id'] = $this->toIntOrOriginal((string) ($this->edit['id'] ?? ''));
            $row['category_id'] = $this->toIntOrOriginal((string) ($this->edit['category_id'] ?? ''));
            $row['parent_id'] = $this->toIntOrOriginal((string) ($this->edit['parent_id'] ?? ''));
            $row['title'] = trim((string) ($this->edit['title'] ?? ''));
            $row['title_fa'] = trim((string) ($this->edit['title_fa'] ?? ''));
            $row['title_en'] = trim((string) ($this->edit['title_en'] ?? ''));
            $row['title_tr'] = trim((string) ($this->edit['title_tr'] ?? ''));
            $row['title_ru'] = trim((string) ($this->edit['title_ru'] ?? ''));
            $row['title_tk'] = trim((string) ($this->edit['title_tk'] ?? ''));
            $icon = trim((string) ($this->edit['icon'] ?? ''));
            $row['icon'] = $icon === '' ? null : $icon;
        }

        if ($isCreating) {
            $rows[] = $row;
        } else {
            $rows[$this->editingIndex] = $row;
        }

        $this->structure[$this->section] = array_values($rows);
        $this->persistStructure($isCreating ? 'ردیف جدید اضافه شد' : 'تغییرات ذخیره شد');
        $this->cancelEdit();
    }

    protected function persistStructure(string $successTitle): void
    {
        try {
            $this->structure['categories'] = array_values(array_filter($this->structure['categories'] ?? [], 'is_array'));
            $this->structure['chapters'] = array_values(array_filter($this->structure['chapters'] ?? [], 'is_array'));
            KdiniMetadataRepository::writeJson('json/base_structure.json', $this->structure);

            Notification::make()
                ->title($successTitle)
                ->success()
                ->send();

            $this->loadStructure();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('ذخیره base_structure.json ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function toIntOrOriginal(string $value): int | string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        return is_numeric($trimmed) ? (int) $trimmed : $trimmed;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function prepareChapterRows(array $rows): array
    {
        $nodes = [];
        $children = [];
        $idToIndex = [];

        foreach ($rows as $index => $row) {
            $id = $this->toIntOrNull($row['id'] ?? null);
            $parentId = $this->toIntOrNull($row['parent_id'] ?? null) ?? 0;
            $categoryId = $this->toIntOrNull($row['category_id'] ?? null) ?? 0;

            $nodes[$index] = [
                'id' => $id,
                'parent_id' => $parentId,
                'category_id' => $categoryId,
            ];

            if ($id !== null) {
                $idToIndex[$id] = $index;
            }
        }

        foreach ($nodes as $index => $meta) {
            $parent = $meta['parent_id'] ?? 0;
            $children[$parent][] = $index;
        }

        foreach ($children as &$indices) {
            usort($indices, function (int $a, int $b) use ($nodes): int {
                $aCat = $nodes[$a]['category_id'] ?? 0;
                $bCat = $nodes[$b]['category_id'] ?? 0;
                if ($aCat !== $bCat) {
                    return $aCat <=> $bCat;
                }

                $aId = $nodes[$a]['id'] ?? 0;
                $bId = $nodes[$b]['id'] ?? 0;

                return $aId <=> $bId;
            });
        }
        unset($indices);

        $rootIndices = [];
        foreach ($nodes as $index => $meta) {
            $parent = $meta['parent_id'] ?? 0;
            if ($parent <= 0 || ! isset($idToIndex[$parent])) {
                $rootIndices[] = $index;
            }
        }

        usort($rootIndices, function (int $a, int $b) use ($nodes): int {
            $aCat = $nodes[$a]['category_id'] ?? 0;
            $bCat = $nodes[$b]['category_id'] ?? 0;
            if ($aCat !== $bCat) {
                return $aCat <=> $bCat;
            }

            $aId = $nodes[$a]['id'] ?? 0;
            $bId = $nodes[$b]['id'] ?? 0;

            return $aId <=> $bId;
        });

        $visited = [];
        $ordered = [];

        $walk = function (int $index, int $depth) use (&$walk, &$ordered, &$visited, $rows, $nodes, $children): void {
            if (isset($visited[$index])) {
                return;
            }

            $visited[$index] = true;
            $row = $rows[$index];
            $title = (string) ($row['title'] ?? '');
            $prefix = str_repeat('— ', max(0, min($depth, 8)));

            $row['__index'] = $index;
            $row['__depth'] = $depth;
            $row['__title_display'] = $prefix . $title;
            $ordered[] = $row;

            $id = $nodes[$index]['id'] ?? null;
            if ($id !== null && isset($children[$id])) {
                foreach ($children[$id] as $childIndex) {
                    $walk($childIndex, $depth + 1);
                }
            }
        };

        foreach ($rootIndices as $rootIndex) {
            $walk($rootIndex, 0);
        }

        foreach (array_keys($rows) as $index) {
            $walk((int) $index, 0);
        }

        return $ordered;
    }

    protected function toIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric((string) $value) ? (int) $value : null;
    }
}
