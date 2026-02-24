<?php

namespace App\Filament\Pages;

use App\Support\KdiniMetadataRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use PDO;
use Throwable;
use UnitEnum;

class StructureHierarchyManager extends Page
{
    protected static ?string $title = 'مرورگر یکپارچه ساختار';

    protected string $view = 'filament.pages.structure-hierarchy-manager';

    protected static ?int $navigationSort = 32;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | UnitEnum | null $navigationGroup = 'متادیتا';

    public string $search = '';

    public string $activeCategoryKey = '';

    public string $activeChapterKey = '';

    public string $selectedNodeKey = '';

    public string $selectedSource = '';

    public string $selectedSection = '';

    public int $selectedIndex = -1;

    /**
     * @var array<string, mixed>
     */
    public array $edit = [];

    /**
     * @var array<string, mixed>
     */
    public array $baseStructure = [
        'schema_version' => 1,
        'data_version' => 1,
        'categories' => [],
        'chapters' => [],
    ];

    /**
     * @var array<string, mixed>
     */
    public array $structure = [
        'schema' => 1,
        'data_version' => '',
        'categories' => [],
        'chapters' => [],
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $dbCategories = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $dbChapters = [];

    public bool $dbAvailable = false;

    public string $dbPath = '';

    public function mount(): void
    {
        $this->loadAll();
    }

    public function loadAll(): void
    {
        $this->loadBaseStructure();
        $this->loadStructureMetadata();
        $this->loadReferenceDatabaseRows();
        $this->normalizeSelections();
    }

    protected function loadBaseStructure(): void
    {
        try {
            $payload = KdiniMetadataRepository::readJson('json/base_structure.json');
            if (! is_array($payload)) {
                throw new \RuntimeException('ساختار base_structure.json باید آبجکت باشد.');
            }

            $payload['categories'] = is_array($payload['categories'] ?? null)
                ? array_values(array_filter($payload['categories'], 'is_array'))
                : [];

            $payload['chapters'] = is_array($payload['chapters'] ?? null)
                ? array_values(array_filter($payload['chapters'], 'is_array'))
                : [];

            $this->baseStructure = $payload;
        } catch (Throwable $exception) {
            Notification::make()
                ->title('خواندن base_structure.json ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $this->baseStructure = [
                'schema_version' => 1,
                'data_version' => 1,
                'categories' => [],
                'chapters' => [],
            ];
        }
    }

    protected function loadStructureMetadata(): void
    {
        try {
            $this->structure = KdiniMetadataRepository::readStructure();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('خواندن structure_metadata.json ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $this->structure = [
                'schema' => 1,
                'data_version' => '',
                'categories' => [],
                'chapters' => [],
            ];
        }
    }

    protected function loadReferenceDatabaseRows(): void
    {
        $this->dbAvailable = false;
        $this->dbPath = '';
        $this->dbCategories = [];
        $this->dbChapters = [];

        $path = $this->resolveReferenceDatabasePath();
        if ($path === null) {
            return;
        }

        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $catQuery = $pdo->query('SELECT id, title, sort_order, icon FROM categories ORDER BY sort_order, id');
            $chapterQuery = $pdo->query('SELECT id, title, parent_id, category_id, icon, title_fa, title_en, title_tr, title_ru, title_tk FROM chapters ORDER BY category_id, parent_id, id');

            if ($catQuery === false || $chapterQuery === false) {
                throw new \RuntimeException('خواندن جدول‌های categories/chapters از دیتابیس ممکن نشد.');
            }

            $this->dbCategories = array_values(array_filter($catQuery->fetchAll(PDO::FETCH_ASSOC), 'is_array'));
            $this->dbChapters = array_values(array_filter($chapterQuery->fetchAll(PDO::FETCH_ASSOC), 'is_array'));
            $this->dbAvailable = true;
            $this->dbPath = $path;
        } catch (Throwable $exception) {
            Notification::make()
                ->title('اتصال به دیتابیس مادر ناموفق بود')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    protected function normalizeSelections(): void
    {
        if ($this->selectedNodeKey !== '' && $this->findNodeByKey($this->selectedNodeKey) === null) {
            $this->selectedNodeKey = '';
            $this->selectedSource = '';
            $this->selectedSection = '';
            $this->selectedIndex = -1;
            $this->edit = [];
        }

        if ($this->activeCategoryKey !== '' && $this->findNodeByKey($this->activeCategoryKey, 'categories') === null) {
            $this->activeCategoryKey = '';
        }

        if ($this->activeChapterKey !== '' && $this->findNodeByKey($this->activeChapterKey, 'chapters') === null) {
            $this->activeChapterKey = '';
        }
    }

    protected function resolveReferenceDatabasePath(): ?string
    {
        $repoRoot = KdiniMetadataRepository::repoRoot();
        $repoParent = dirname($repoRoot);

        $candidates = [
            trim((string) env('KDINI_REFERENCE_DB_PATH', '')),
            $repoRoot . '/assets/books.db',
            $repoRoot . '/kdini/assets/books.db',
            $repoParent . '/kdini/assets/books.db',
            $repoParent . '/kdini/kdini/assets/books.db',
            '/Users/kerim/Documents/kdini/kdini/assets/books.db',
        ];

        foreach ($candidates as $candidate) {
            $path = trim((string) $candidate);
            if ($path !== '' && File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function sourceRows(string $source, string $section): array
    {
        if (! in_array($section, ['categories', 'chapters'], true)) {
            return [];
        }

        if ($source === 'base') {
            $rows = $this->baseStructure[$section] ?? [];

            return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
        }

        if ($source === 'structure') {
            $rows = $this->structure[$section] ?? [];

            return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
        }

        if ($source === 'db') {
            return $section === 'categories' ? $this->dbCategories : $this->dbChapters;
        }

        return [];
    }

    public function sourceLabel(string $source): string
    {
        return match ($source) {
            'db' => 'دیتابیس مادر',
            'base' => 'base_structure',
            'structure' => 'structure_metadata',
            default => 'نامشخص',
        };
    }

    protected function sourcePriority(string $source): int
    {
        return match ($source) {
            'db' => 0,
            'base' => 1,
            'structure' => 2,
            default => 9,
        };
    }

    protected function makeNodeKey(string $source, string $section, int $index): string
    {
        return $source . '|' . $section . '|' . $index;
    }

    /**
     * @return array{source: string, section: string, index: int}|null
     */
    protected function parseNodeKey(string $key): ?array
    {
        $parts = explode('|', $key);
        if (count($parts) !== 3) {
            return null;
        }

        [$source, $section, $indexRaw] = $parts;
        if (! in_array($source, ['db', 'base', 'structure'], true)) {
            return null;
        }

        if (! in_array($section, ['categories', 'chapters'], true)) {
            return null;
        }

        if (! is_numeric($indexRaw)) {
            return null;
        }

        return [
            'source' => $source,
            'section' => $section,
            'index' => (int) $indexRaw,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function normalizeNode(string $source, string $section, int $index, array $row): array
    {
        $node = [
            '__key' => $this->makeNodeKey($source, $section, $index),
            '__source' => $source,
            '__source_label' => $this->sourceLabel($source),
            '__section' => $section,
            '__index' => $index,
            '__raw' => $row,
            'id' => (string) ($row['id'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'icon' => (string) ($row['icon'] ?? ''),
        ];

        if ($section === 'categories') {
            $node['sort_order'] = (string) ($row['sort_order'] ?? '');

            return $node;
        }

        $node['category_id'] = (string) ($row['category_id'] ?? '');
        $node['parent_id'] = (string) ($row['parent_id'] ?? '');
        $node['title_fa'] = (string) ($row['title_fa'] ?? '');
        $node['title_en'] = (string) ($row['title_en'] ?? '');
        $node['title_tr'] = (string) ($row['title_tr'] ?? '');
        $node['title_ru'] = (string) ($row['title_ru'] ?? '');
        $node['title_tk'] = (string) ($row['title_tk'] ?? '');

        return $node;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findNodeByKey(string $key, ?string $expectedSection = null): ?array
    {
        $parsed = $this->parseNodeKey($key);
        if ($parsed === null) {
            return null;
        }

        if ($expectedSection !== null && $parsed['section'] !== $expectedSection) {
            return null;
        }

        $rows = $this->sourceRows($parsed['source'], $parsed['section']);
        if (! isset($rows[$parsed['index']]) || ! is_array($rows[$parsed['index']])) {
            return null;
        }

        return $this->normalizeNode($parsed['source'], $parsed['section'], $parsed['index'], $rows[$parsed['index']]);
    }

    protected function normalizeScalar(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCombinedCategoriesProperty(): array
    {
        $query = mb_strtolower(trim($this->search));
        $rows = [];

        foreach (['db', 'base', 'structure'] as $source) {
            foreach ($this->sourceRows($source, 'categories') as $index => $row) {
                $node = $this->normalizeNode($source, 'categories', (int) $index, $row);

                $haystack = mb_strtolower(
                    $this->normalizeScalar($node['id'])
                    . ' '
                    . $this->normalizeScalar($node['title'])
                    . ' '
                    . $this->sourceLabel($source)
                );

                if ($query !== '' && ! str_contains($haystack, $query)) {
                    continue;
                }

                $rows[] = $node;
            }
        }

        usort($rows, function (array $a, array $b): int {
            $idA = $this->normalizeScalar($a['id'] ?? '');
            $idB = $this->normalizeScalar($b['id'] ?? '');

            if (is_numeric($idA) && is_numeric($idB) && (int) $idA !== (int) $idB) {
                return (int) $idA <=> (int) $idB;
            }

            if ($idA !== $idB) {
                return strcmp($idA, $idB);
            }

            $sourceOrder = $this->sourcePriority((string) ($a['__source'] ?? '')) <=> $this->sourcePriority((string) ($b['__source'] ?? ''));
            if ($sourceOrder !== 0) {
                return $sourceOrder;
            }

            return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCombinedChaptersProperty(): array
    {
        $rows = [];

        foreach (['db', 'base', 'structure'] as $source) {
            foreach ($this->sourceRows($source, 'chapters') as $index => $row) {
                $rows[] = $this->normalizeNode($source, 'chapters', (int) $index, $row);
            }
        }

        usort($rows, function (array $a, array $b): int {
            $catA = $this->normalizeScalar($a['category_id'] ?? '0');
            $catB = $this->normalizeScalar($b['category_id'] ?? '0');
            if (is_numeric($catA) && is_numeric($catB) && (int) $catA !== (int) $catB) {
                return (int) $catA <=> (int) $catB;
            }

            $parentA = $this->normalizeScalar($a['parent_id'] ?? '0');
            $parentB = $this->normalizeScalar($b['parent_id'] ?? '0');
            if (is_numeric($parentA) && is_numeric($parentB) && (int) $parentA !== (int) $parentB) {
                return (int) $parentA <=> (int) $parentB;
            }

            $idA = $this->normalizeScalar($a['id'] ?? '');
            $idB = $this->normalizeScalar($b['id'] ?? '');
            if (is_numeric($idA) && is_numeric($idB) && (int) $idA !== (int) $idB) {
                return (int) $idA <=> (int) $idB;
            }

            return strcmp($idA, $idB);
        });

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActiveCategoryProperty(): ?array
    {
        if ($this->activeCategoryKey === '') {
            return null;
        }

        return $this->findNodeByKey($this->activeCategoryKey, 'categories');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActiveChapterProperty(): ?array
    {
        if ($this->activeChapterKey === '') {
            return null;
        }

        return $this->findNodeByKey($this->activeChapterKey, 'chapters');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTopLevelChaptersProperty(): array
    {
        $category = $this->activeCategory;
        if ($category === null) {
            return [];
        }

        $categoryId = $this->normalizeScalar($category['id'] ?? '');
        $source = (string) ($category['__source'] ?? '');
        if ($categoryId === '') {
            return [];
        }

        $rows = [];
        foreach ($this->combinedChapters as $chapter) {
            if ((string) ($chapter['__source'] ?? '') !== $source) {
                continue;
            }

            if ($this->normalizeScalar($chapter['category_id'] ?? '') !== $categoryId) {
                continue;
            }

            $parentId = $this->normalizeScalar($chapter['parent_id'] ?? '0');
            if ($parentId !== '' && $parentId !== '0') {
                continue;
            }

            $rows[] = $chapter;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSubchaptersProperty(): array
    {
        $activeChapter = $this->activeChapter;
        if ($activeChapter === null) {
            return [];
        }

        $parentId = $this->normalizeScalar($activeChapter['id'] ?? '');
        $categoryId = $this->normalizeScalar($activeChapter['category_id'] ?? '');
        $source = (string) ($activeChapter['__source'] ?? '');

        if ($parentId === '' || $categoryId === '') {
            return [];
        }

        $rows = [];
        foreach ($this->combinedChapters as $chapter) {
            if ((string) ($chapter['__source'] ?? '') !== $source) {
                continue;
            }

            if ($this->normalizeScalar($chapter['category_id'] ?? '') !== $categoryId) {
                continue;
            }

            if ($this->normalizeScalar($chapter['parent_id'] ?? '') !== $parentId) {
                continue;
            }

            $rows[] = $chapter;
        }

        return $rows;
    }

    public function countTopLevelChaptersForCategory(mixed $categoryId, ?string $source = null): int
    {
        $categoryId = $this->normalizeScalar($categoryId);
        if ($categoryId === '') {
            return 0;
        }

        $count = 0;
        foreach ($this->combinedChapters as $chapter) {
            if ($source !== null && (string) ($chapter['__source'] ?? '') !== $source) {
                continue;
            }

            if ($this->normalizeScalar($chapter['category_id'] ?? '') !== $categoryId) {
                continue;
            }

            $parentId = $this->normalizeScalar($chapter['parent_id'] ?? '0');
            if ($parentId === '' || $parentId === '0') {
                $count++;
            }
        }

        return $count;
    }

    public function selectCategory(string $key): void
    {
        $node = $this->findNodeByKey($key, 'categories');
        if ($node === null) {
            return;
        }

        $this->activeCategoryKey = $key;
        $this->activeChapterKey = '';
        $this->applySelectedNode($node);
    }

    public function selectChapter(string $key): void
    {
        $node = $this->findNodeByKey($key, 'chapters');
        if ($node === null) {
            return;
        }

        $this->activeChapterKey = $key;

        $categoryKey = $this->findCategoryKeyByIdInSource(
            $this->normalizeScalar($node['category_id'] ?? ''),
            (string) ($node['__source'] ?? '')
        );
        if ($categoryKey !== null) {
            $this->activeCategoryKey = $categoryKey;
        }

        $this->applySelectedNode($node);
    }

    /**
     * @param array<string, mixed> $node
     */
    protected function applySelectedNode(array $node): void
    {
        $this->selectedNodeKey = (string) ($node['__key'] ?? '');
        $this->selectedSource = (string) ($node['__source'] ?? '');
        $this->selectedSection = (string) ($node['__section'] ?? '');
        $this->selectedIndex = (int) ($node['__index'] ?? -1);

        if ($this->selectedSection === 'categories') {
            $this->edit = [
                'id' => (string) ($node['id'] ?? ''),
                'title' => (string) ($node['title'] ?? ''),
                'sort_order' => (string) ($node['sort_order'] ?? ''),
                'icon' => (string) ($node['icon'] ?? ''),
            ];

            return;
        }

        $this->edit = [
            'id' => (string) ($node['id'] ?? ''),
            'category_id' => (string) ($node['category_id'] ?? ''),
            'parent_id' => (string) ($node['parent_id'] ?? '0'),
            'title' => (string) ($node['title'] ?? ''),
            'icon' => (string) ($node['icon'] ?? ''),
            'title_fa' => (string) ($node['title_fa'] ?? ''),
            'title_en' => (string) ($node['title_en'] ?? ''),
            'title_tr' => (string) ($node['title_tr'] ?? ''),
            'title_ru' => (string) ($node['title_ru'] ?? ''),
            'title_tk' => (string) ($node['title_tk'] ?? ''),
        ];
    }

    protected function findCategoryKeyById(string $categoryId): ?string
    {
        return $this->findCategoryKeyByIdInSource($categoryId);
    }

    protected function findCategoryKeyByIdInSource(string $categoryId, ?string $source = null): ?string
    {
        if ($categoryId === '') {
            return null;
        }

        foreach ($this->combinedCategories as $category) {
            if ($source !== null && (string) ($category['__source'] ?? '') !== $source) {
                continue;
            }

            if ($this->normalizeScalar($category['id'] ?? '') === $categoryId) {
                return (string) ($category['__key'] ?? '');
            }
        }

        return null;
    }

    protected function categoryExists(string $categoryId, ?string $source = null): bool
    {
        if ($categoryId === '') {
            return false;
        }

        return $this->findCategoryKeyByIdInSource($categoryId, $source) !== null;
    }

    protected function chapterExists(string $chapterId, ?string $source = null): bool
    {
        if ($chapterId === '') {
            return false;
        }

        foreach ($this->combinedChapters as $chapter) {
            if ($source !== null && (string) ($chapter['__source'] ?? '') !== $source) {
                continue;
            }

            if ($this->normalizeScalar($chapter['id'] ?? '') === $chapterId) {
                return true;
            }
        }

        return false;
    }

    protected function findDuplicateNode(string $section, string $id, string $excludeKey, ?string $source = null): ?array
    {
        $pool = $section === 'categories' ? $this->combinedCategories : $this->combinedChapters;

        foreach ($pool as $node) {
            if ($source !== null && (string) ($node['__source'] ?? '') !== $source) {
                continue;
            }

            if ($this->normalizeScalar($node['id'] ?? '') !== $id) {
                continue;
            }

            if ((string) ($node['__key'] ?? '') === $excludeKey) {
                continue;
            }

            return $node;
        }

        return null;
    }

    public function nextSuggestedId(string $section, ?string $source = null): int
    {
        $max = 0;
        $pool = $section === 'categories' ? $this->combinedCategories : $this->combinedChapters;
        $source = $source ?: ($this->selectedSource !== '' ? $this->selectedSource : null);

        foreach ($pool as $node) {
            if ($source !== null && (string) ($node['__source'] ?? '') !== $source) {
                continue;
            }

            $id = $this->normalizeScalar($node['id'] ?? '');
            if (is_numeric($id)) {
                $max = max($max, (int) $id);
            }
        }

        return $max + 1;
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
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    protected function buildUpdatedRow(array $node): array
    {
        $updated = is_array($node['__raw'] ?? null) ? $node['__raw'] : [];

        if ($this->selectedSection === 'categories') {
            $updated['id'] = $this->toIntOrOriginal((string) ($this->edit['id'] ?? ''));
            $updated['title'] = trim((string) ($this->edit['title'] ?? ''));
            $updated['sort_order'] = $this->toIntOrOriginal((string) ($this->edit['sort_order'] ?? ''));

            $icon = trim((string) ($this->edit['icon'] ?? ''));
            $updated['icon'] = $icon === '' ? null : $icon;

            return $updated;
        }

        $updated['id'] = $this->toIntOrOriginal((string) ($this->edit['id'] ?? ''));
        $updated['category_id'] = $this->toIntOrOriginal((string) ($this->edit['category_id'] ?? ''));
        $updated['parent_id'] = $this->toIntOrOriginal((string) ($this->edit['parent_id'] ?? ''));
        $updated['title'] = trim((string) ($this->edit['title'] ?? ''));

        $icon = trim((string) ($this->edit['icon'] ?? ''));
        $updated['icon'] = $icon === '' ? null : $icon;

        foreach (['title_fa', 'title_en', 'title_tr', 'title_ru', 'title_tk'] as $field) {
            $input = trim((string) ($this->edit[$field] ?? ''));
            if ($this->selectedSource === 'db' || $this->selectedSource === 'base' || array_key_exists($field, $updated) || $input !== '') {
                $updated[$field] = $input;
            }
        }

        return $updated;
    }

    public function saveSelected(): void
    {
        $node = $this->findNodeByKey($this->selectedNodeKey, $this->selectedSection !== '' ? $this->selectedSection : null);
        if ($node === null) {
            Notification::make()
                ->title('ابتدا یک مورد را انتخاب کن')
                ->warning()
                ->send();

            return;
        }

        $updated = $this->buildUpdatedRow($node);

        $id = $this->normalizeScalar($updated['id'] ?? '');
        if ($id === '') {
            Notification::make()
                ->title('شناسه (id) خالی است')
                ->danger()
                ->send();

            return;
        }

        $title = $this->normalizeScalar($updated['title'] ?? '');
        if ($title === '') {
            Notification::make()
                ->title('عنوان (title) خالی است')
                ->danger()
                ->send();

            return;
        }

        $duplicate = $this->findDuplicateNode($this->selectedSection, $id, $this->selectedNodeKey, $this->selectedSource);
        if ($duplicate !== null) {
            Notification::make()
                ->title('این id قبلا استفاده شده است')
                ->body('منبع: ' . $this->sourceLabel((string) ($duplicate['__source'] ?? '')) . ' | عنوان: ' . (string) ($duplicate['title'] ?? ''))
                ->danger()
                ->send();

            return;
        }

        if ($this->selectedSection === 'chapters') {
            $categoryId = $this->normalizeScalar($updated['category_id'] ?? '');
            if (! $this->categoryExists($categoryId, $this->selectedSource)) {
                Notification::make()
                    ->title('category_id معتبر نیست')
                    ->body('ابتدا دسته‌بندی معتبر انتخاب کن.')
                    ->danger()
                    ->send();

                return;
            }

            $parentId = $this->normalizeScalar($updated['parent_id'] ?? '0');
            if ($parentId !== '' && $parentId !== '0') {
                if ($parentId === $id) {
                    Notification::make()
                        ->title('parent_id نمی‌تواند برابر id باشد')
                        ->danger()
                        ->send();

                    return;
                }

                if (! $this->chapterExists($parentId, $this->selectedSource)) {
                    Notification::make()
                        ->title('parent_id معتبر نیست')
                        ->body('فصل والد با این شناسه پیدا نشد.')
                        ->danger()
                        ->send();

                    return;
                }
            }
        }

        $source = (string) ($node['__source'] ?? '');
        $section = (string) ($node['__section'] ?? '');
        $index = (int) ($node['__index'] ?? -1);

        try {
            if ($source === 'base') {
                if (! isset($this->baseStructure[$section][$index])) {
                    throw new \RuntimeException('ردیف مورد نظر در base_structure پیدا نشد.');
                }

                $this->baseStructure[$section][$index] = $updated;
                KdiniMetadataRepository::writeJson('json/base_structure.json', $this->baseStructure);
            } elseif ($source === 'structure') {
                if (! isset($this->structure[$section][$index])) {
                    throw new \RuntimeException('ردیف مورد نظر در structure_metadata پیدا نشد.');
                }

                $this->structure[$section][$index] = $updated;
                KdiniMetadataRepository::writeStructure($this->structure);
            } elseif ($source === 'db') {
                $this->updateDatabaseRow($section, $node, $updated);
            } else {
                throw new \RuntimeException('منبع ذخیره‌سازی نامعتبر است.');
            }

            $newId = $this->normalizeScalar($updated['id'] ?? '');
            $categoryId = $this->normalizeScalar($updated['category_id'] ?? '');

            $this->loadAll();
            $this->restoreSelection($source, $section, $newId, $categoryId);

            Notification::make()
                ->title('تغییرات ذخیره شد')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('ذخیره ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param array<string, mixed> $originalNode
     * @param array<string, mixed> $updated
     */
    protected function updateDatabaseRow(string $section, array $originalNode, array $updated): void
    {
        if (! $this->dbAvailable || $this->dbPath === '') {
            throw new \RuntimeException('دیتابیس مادر در دسترس نیست.');
        }

        $originalId = $this->toIntOrOriginal((string) ($originalNode['id'] ?? ''));
        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($section === 'categories') {
            $stmt = $pdo->prepare('UPDATE categories SET id = :id, title = :title, sort_order = :sort_order, icon = :icon WHERE id = :original_id');
            $stmt->execute([
                ':id' => $updated['id'] ?? null,
                ':title' => $updated['title'] ?? null,
                ':sort_order' => $updated['sort_order'] ?? null,
                ':icon' => $updated['icon'] ?? null,
                ':original_id' => $originalId,
            ]);

            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE chapters SET
                id = :id,
                title = :title,
                parent_id = :parent_id,
                category_id = :category_id,
                icon = :icon,
                title_fa = :title_fa,
                title_en = :title_en,
                title_tr = :title_tr,
                title_ru = :title_ru,
                title_tk = :title_tk
             WHERE id = :original_id'
        );

        $stmt->execute([
            ':id' => $updated['id'] ?? null,
            ':title' => $updated['title'] ?? null,
            ':parent_id' => $updated['parent_id'] ?? null,
            ':category_id' => $updated['category_id'] ?? null,
            ':icon' => $updated['icon'] ?? null,
            ':title_fa' => $updated['title_fa'] ?? null,
            ':title_en' => $updated['title_en'] ?? null,
            ':title_tr' => $updated['title_tr'] ?? null,
            ':title_ru' => $updated['title_ru'] ?? null,
            ':title_tk' => $updated['title_tk'] ?? null,
            ':original_id' => $originalId,
        ]);
    }

    protected function restoreSelection(string $source, string $section, string $id, string $categoryId): void
    {
        $pool = $section === 'categories' ? $this->combinedCategories : $this->combinedChapters;

        foreach ($pool as $node) {
            if ((string) ($node['__source'] ?? '') !== $source) {
                continue;
            }

            if ((string) ($node['__section'] ?? '') !== $section) {
                continue;
            }

            if ($this->normalizeScalar($node['id'] ?? '') !== $id) {
                continue;
            }

            $this->applySelectedNode($node);

            if ($section === 'categories') {
                $this->activeCategoryKey = (string) ($node['__key'] ?? '');
                $this->activeChapterKey = '';
            } else {
                $this->activeChapterKey = (string) ($node['__key'] ?? '');

                $categoryKey = $this->findCategoryKeyByIdInSource($categoryId, $source);
                if ($categoryKey !== null) {
                    $this->activeCategoryKey = $categoryKey;
                }
            }

            return;
        }
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getCategoryOptionsProperty(): array
    {
        $options = [];
        $source = $this->selectedSource !== '' ? $this->selectedSource : null;

        foreach ($this->combinedCategories as $category) {
            if ($source !== null && (string) ($category['__source'] ?? '') !== $source) {
                continue;
            }

            $id = $this->normalizeScalar($category['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $label = trim((string) ($category['title'] ?? ''));
            if ($label === '') {
                $label = 'بدون عنوان';
            }

            $options[] = [
                'value' => $id,
                'label' => $id . ' - ' . $label . ' [' . (string) ($category['__source_label'] ?? '') . ']',
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getParentChapterOptionsProperty(): array
    {
        $options = [
            ['value' => '0', 'label' => 'بدون والد (سطح اول)'],
        ];

        if ($this->selectedSection !== 'chapters') {
            return $options;
        }

        $selectedId = $this->normalizeScalar($this->edit['id'] ?? '');
        $categoryId = $this->normalizeScalar($this->edit['category_id'] ?? '');
        $source = $this->selectedSource !== '' ? $this->selectedSource : null;

        foreach ($this->combinedChapters as $chapter) {
            if ($source !== null && (string) ($chapter['__source'] ?? '') !== $source) {
                continue;
            }

            $chapterId = $this->normalizeScalar($chapter['id'] ?? '');
            if ($chapterId === '' || $chapterId === $selectedId) {
                continue;
            }

            if ($categoryId !== '' && $this->normalizeScalar($chapter['category_id'] ?? '') !== $categoryId) {
                continue;
            }

            $title = trim((string) ($chapter['title'] ?? ''));
            if ($title === '') {
                $title = 'بدون عنوان';
            }

            $options[] = [
                'value' => $chapterId,
                'label' => $chapterId . ' - ' . $title . ' [' . (string) ($chapter['__source_label'] ?? '') . ']',
            ];
        }

        return $options;
    }
}
