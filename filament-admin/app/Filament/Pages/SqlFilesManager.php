<?php

namespace App\Filament\Pages;

use App\Support\KdiniMetadataRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use PDO;
use Throwable;
use UnitEnum;

class SqlFilesManager extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'مدیریت فایل‌های SQL';

    protected string $view = 'filament.pages.sql-files-manager';

    protected static ?int $navigationSort = 35;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static string | UnitEnum | null $navigationGroup = 'متادیتا';

    public string $search = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $files = [];

    public ?string $editingRelativePath = null;

    public string $editFileName = '';

    public string $editBookId = '';

    public string $sqlContent = '';

    public int $detectedInsertCount = 0;

    /**
     * @var array<int, array<string, string>>
     */
    public array $contentRows = [];

    public ?int $selectedRowIndex = null;

    public string $selectedRowField = 'text_fa';

    public string $selectedRowText = '';

    public string $sqlRowsError = '';

    public bool $showRawSqlEditor = false;

    public bool $rowsDirty = false;

    public string $detectedDeleteStatement = '';

    /**
     * @var array<int, string>
     */
    public array $contentColumns = [];

    /**
     * @var array<string, string>
     */
    public array $contentColumnTypes = [];

    /**
     * @var array<int, string>
     */
    public array $contentTextFields = ['text', 'text_fa', 'text_turkmen'];

    /**
     * @var TemporaryUploadedFile|string|null
     */
    public $uploadedSqlFile = null;

    public function mount(): void
    {
        $this->loadFiles();
    }

    public function loadFiles(): void
    {
        $directory = KdiniMetadataRepository::safePath('kotob');
        File::ensureDirectoryExists($directory);

        $rows = [];

        foreach (File::files($directory) as $file) {
            $name = $file->getFilename();
            if (! $this->isAllowedSqlFileName($name)) {
                continue;
            }

            $relative = 'kotob/' . $name;
            $stats = $this->parseSqlStats($file->getRealPath(), $name);

            $rows[] = [
                'relative' => $relative,
                'name' => $name,
                'type' => $stats['type'],
                'book_id' => $stats['book_id'],
                'insert_count' => $stats['insert_count'],
                'editable' => $stats['editable'],
                'size_bytes' => $file->getSize(),
                'size_human' => $this->formatBytes((int) $file->getSize()),
                'modified_ts' => $file->getMTime(),
                'modified' => date('Y-m-d H:i', $file->getMTime()),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return ($b['modified_ts'] <=> $a['modified_ts']) ?: strcmp((string) $a['name'], (string) $b['name']);
        });

        $this->files = $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredFilesProperty(): array
    {
        $query = mb_strtolower(trim($this->search));

        if ($query === '') {
            return $this->files;
        }

        return array_values(array_filter($this->files, static function (array $row) use ($query): bool {
            $haystack = mb_strtolower(
                implode(' ', [
                    (string) ($row['name'] ?? ''),
                    (string) ($row['type'] ?? ''),
                    (string) ($row['book_id'] ?? ''),
                    (string) ($row['insert_count'] ?? ''),
                ])
            );

            return str_contains($haystack, $query);
        }));
    }

    public function startEditFile(string $relativePath): void
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        $path = KdiniMetadataRepository::safePath($relativePath);

        if (! File::exists($path)) {
            Notification::make()
                ->title('فایل پیدا نشد')
                ->danger()
                ->send();

            return;
        }

        $name = basename($path);
        if (! str_ends_with(mb_strtolower($name), '.sql')) {
            Notification::make()
                ->title('ویرایش متنی فقط برای فایل .sql فعال است')
                ->body('برای فایل‌های sql.gz ابتدا خروجی sql معمولی بساز.')
                ->warning()
                ->send();

            return;
        }

        $sql = File::get($path);

        $this->editingRelativePath = $relativePath;
        $this->editFileName = $name;
        // Keep large SQL text out of Livewire payload unless raw editor is explicitly opened.
        $this->sqlContent = '';
        $this->editBookId = $this->detectDeleteBookId($sql);
        $this->detectedInsertCount = $this->detectInsertCount($sql);
        $this->rowsDirty = false;
        $this->detectedDeleteStatement = $this->detectDeleteStatementForContent($sql);
        $this->loadContentRowsFromSql($sql);
    }

    public function cancelEditFile(): void
    {
        $this->editingRelativePath = null;
        $this->editFileName = '';
        $this->editBookId = '';
        $this->sqlContent = '';
        $this->detectedInsertCount = 0;
        $this->contentRows = [];
        $this->selectedRowIndex = null;
        $this->selectedRowField = 'text_fa';
        $this->selectedRowText = '';
        $this->sqlRowsError = '';
        $this->showRawSqlEditor = false;
        $this->rowsDirty = false;
        $this->detectedDeleteStatement = '';
        $this->contentColumns = [];
        $this->contentColumnTypes = [];
        $this->contentTextFields = ['text', 'text_fa', 'text_turkmen'];
    }

    public function saveSqlFile(): void
    {
        if ($this->editingRelativePath === null) {
            return;
        }

        $content = $this->resolveSqlContentForSave();
        if ($content === '') {
            Notification::make()
                ->title('محتوای SQL خالی است')
                ->danger()
                ->send();

            return;
        }

        $safeName = $this->sanitizeUploadFileName($this->editFileName !== '' ? $this->editFileName : 'book.sql');
        if (! str_ends_with(mb_strtolower($safeName), '.sql')) {
            Notification::make()
                ->title('نام فایل باید با .sql تمام شود')
                ->danger()
                ->send();

            return;
        }

        if (trim($this->editBookId) !== '' && is_numeric(trim($this->editBookId))) {
            $content = $this->applyBookIdToDeleteStatement($content, (int) trim($this->editBookId));
        }

        if (! str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        $oldRelative = $this->normalizeRelativePath($this->editingRelativePath);
        $newRelative = 'kotob/' . $safeName;

        $oldPath = KdiniMetadataRepository::safePath($oldRelative);
        $newPath = KdiniMetadataRepository::safePath($newRelative);

        if ($newPath !== $oldPath && File::exists($newPath)) {
            Notification::make()
                ->title('نام فایل تکراری است')
                ->body('یک نام جدید انتخاب کن.')
                ->danger()
                ->send();

            return;
        }

        File::put($oldPath, $content);

        if ($newPath !== $oldPath) {
            File::move($oldPath, $newPath);
        }

        $this->editingRelativePath = $newRelative;
        $this->editFileName = $safeName;
        $this->sqlContent = $this->showRawSqlEditor ? $content : '';
        $this->editBookId = $this->detectDeleteBookId($content);
        $this->detectedInsertCount = $this->detectInsertCount($content);
        $this->rowsDirty = false;
        $this->detectedDeleteStatement = $this->detectDeleteStatementForContent($content);
        $this->loadContentRowsFromSql($content);

        $this->loadFiles();

        Notification::make()
            ->title('فایل SQL ذخیره شد')
            ->success()
            ->send();
    }

    public function uploadNewSqlFile(): void
    {
        if (! $this->uploadedSqlFile instanceof TemporaryUploadedFile) {
            Notification::make()
                ->title('هیچ فایلی انتخاب نشده است')
                ->warning()
                ->send();

            return;
        }

        $originalName = $this->uploadedSqlFile->getClientOriginalName();
        if (! $this->isAllowedSqlFileName($originalName)) {
            Notification::make()
                ->title('فرمت فایل مجاز نیست')
                ->body('فقط sql / sql.gz / db / gz مجاز است.')
                ->danger()
                ->send();

            return;
        }

        $safeName = $this->sanitizeUploadFileName($originalName);
        $relative = 'kotob/' . $safeName;
        $path = KdiniMetadataRepository::safePath($relative);

        if (File::exists($path)) {
            $safeName = $this->appendTimestampToFileName($safeName);
            $relative = 'kotob/' . $safeName;
            $path = KdiniMetadataRepository::safePath($relative);
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, File::get($this->uploadedSqlFile->getRealPath()));

        $this->uploadedSqlFile = null;
        $this->loadFiles();

        Notification::make()
            ->title('فایل SQL آپلود شد')
            ->body("مسیر: {$relative}")
            ->success()
            ->send();
    }

    public function deleteSqlFile(string $relativePath): void
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        $path = KdiniMetadataRepository::safePath($relativePath);

        if (! File::exists($path)) {
            Notification::make()
                ->title('فایل پیدا نشد')
                ->body($relativePath)
                ->warning()
                ->send();

            $this->loadFiles();

            return;
        }

        try {
            if ($this->editingRelativePath !== null && $this->normalizeRelativePath($this->editingRelativePath) === $relativePath) {
                $this->cancelEditFile();
            }

            File::delete($path);
            $this->loadFiles();

            Notification::make()
                ->title('فایل SQL حذف شد')
                ->body($relativePath)
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('حذف فایل ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function selectContentRow(int $rowIndex, string $field = ''): void
    {
        if (! isset($this->contentRows[$rowIndex])) {
            return;
        }

        $resolvedField = $field !== ''
            ? $this->normalizeTextField($field)
            : $this->getPreferredTextField($this->contentRows[$rowIndex]);

        $this->selectedRowIndex = $rowIndex;
        $this->selectedRowField = $resolvedField;
        $this->selectedRowText = (string) ($this->contentRows[$rowIndex][$resolvedField] ?? '');
    }

    public function changeSelectedRowField(string $field): void
    {
        if ($this->selectedRowIndex === null) {
            return;
        }

        $this->selectContentRow($this->selectedRowIndex, $field);
    }

    public function saveSelectedRowText(): void
    {
        if ($this->selectedRowIndex === null || ! isset($this->contentRows[$this->selectedRowIndex])) {
            Notification::make()
                ->title('ابتدا یک ردیف را انتخاب کن')
                ->warning()
                ->send();

            return;
        }

        $field = $this->normalizeTextField($this->selectedRowField);

        $this->contentRows[$this->selectedRowIndex][$field] = $this->selectedRowText;

        $bookId = $this->resolveBookIdFromRows($this->contentRows);
        if ($bookId !== 0 && in_array('kotob_id', $this->contentColumns, true)) {
            foreach ($this->contentRows as &$row) {
                $row['kotob_id'] = (string) $bookId;
            }
            unset($row);

            $this->editBookId = (string) $bookId;
        }

        if ($this->showRawSqlEditor) {
            $this->sqlContent = $this->buildSqlContentFromRows($this->contentRows, $bookId, $this->detectedDeleteStatement);
        }

        $this->rowsDirty = true;
        $this->detectedInsertCount = count($this->contentRows);

        Notification::make()
            ->title('متن ردیف به‌روز شد')
            ->body('برای ثبت نهایی روی دیسک، دکمه «ذخیره فایل SQL» را بزن.')
            ->success()
            ->send();
    }

    public function reloadTableFromSqlContent(): void
    {
        if (trim($this->sqlContent) === '') {
            Notification::make()
                ->title('ابتدا SQL خام را وارد کن')
                ->warning()
                ->send();

            return;
        }

        $lastRowIndex = $this->selectedRowIndex;
        $lastField = $this->selectedRowField;
        $this->detectedDeleteStatement = $this->detectDeleteStatementForContent($this->sqlContent);

        $this->loadContentRowsFromSql($this->sqlContent);
        $this->rowsDirty = true;

        if ($lastRowIndex !== null && isset($this->contentRows[$lastRowIndex])) {
            $this->selectContentRow($lastRowIndex, $lastField);
        }

        if ($this->sqlRowsError !== '') {
            Notification::make()
                ->title('بازخوانی جدول با خطا انجام شد')
                ->body($this->sqlRowsError)
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('جدول از SQL دوباره ساخته شد')
            ->success()
            ->send();
    }

    public function toggleRawSqlEditor(): void
    {
        $nextState = ! $this->showRawSqlEditor;

        if ($nextState && trim($this->sqlContent) === '') {
            $this->sqlContent = $this->resolveSqlContentForRawEditor();
            $this->detectedDeleteStatement = $this->detectDeleteStatementForContent($this->sqlContent);
        }

        $this->showRawSqlEditor = $nextState;
    }

    public function hydrateContentRows(mixed $value = null): void
    {
        if (! is_array($value)) {
            $this->contentRows = [];
        }
    }

    /**
     * @return array{type:string, book_id:string, insert_count:int, editable:bool}
     */
    protected function parseSqlStats(string $path, string $name): array
    {
        $lower = mb_strtolower($name);

        if (! str_ends_with($lower, '.sql')) {
            return [
                'type' => str_ends_with($lower, '.sql.gz') ? 'sql.gz' : pathinfo($name, PATHINFO_EXTENSION),
                'book_id' => '',
                'insert_count' => 0,
                'editable' => false,
            ];
        }

        $content = File::get($path);

        return [
            'type' => 'sql',
            'book_id' => $this->detectDeleteBookId($content),
            'insert_count' => $this->detectInsertCount($content),
            'editable' => true,
        ];
    }

    protected function detectDeleteBookId(string $sql): string
    {
        if (preg_match('/DELETE\s+FROM\s+(?:"content"|`content`|\[content\]|content)\s+WHERE\s+kotob_id\s*=\s*(-?\d+)/i', $sql, $matches) === 1) {
            return (string) $matches[1];
        }

        return '';
    }

    protected function detectInsertCount(string $sql): int
    {
        return preg_match_all('/\bINSERT\s+INTO\s+(?:"content"|`content`|\[content\]|content)\b/i', $sql) ?: 0;
    }

    protected function detectDeleteStatementForContent(string $sql): string
    {
        $statements = $this->splitSqlStatements($sql);

        foreach ($statements as $statement) {
            $trimmed = trim($statement);

            if (preg_match('/^DELETE\s+FROM\s+(?:"content"|`content`|\[content\]|content)\b/i', $trimmed) !== 1) {
                continue;
            }

            if (! str_ends_with($trimmed, ';')) {
                $trimmed .= ';';
            }

            return $trimmed;
        }

        return '';
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function parseContentRows(string $sql): array
    {
        $this->sqlRowsError = '';
        $this->contentColumns = [];
        $this->contentColumnTypes = [];
        $this->contentTextFields = ['text', 'text_fa', 'text_turkmen'];

        $statements = $this->extractContentSqlStatements($sql);
        if ($statements === []) {
            return [];
        }

        [$columnTypes, $columns] = $this->resolveColumnTypesForStatements($statements);
        if ($columns === []) {
            return [];
        }

        try {
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $columnDefinitions = [];
            foreach ($columns as $column) {
                $columnDefinitions[] = sprintf(
                    '%s %s',
                    $this->quoteIdentifier($column),
                    $this->normalizeSQLiteType($columnTypes[$column] ?? 'TEXT')
                );
            }

            $pdo->exec(sprintf('CREATE TABLE content (%s)', implode(', ', $columnDefinitions)));

            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }

            $selectColumns = implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns));
            $query = $pdo->query("SELECT rowid AS row_index, {$selectColumns} FROM content ORDER BY rowid ASC");
            $records = $query !== false ? $query->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            if (mb_strlen($message) > 160) {
                $message = mb_substr($message, 0, 160) . '...';
            }

            $this->sqlRowsError = $message !== ''
                ? "ساختار فایل برای حالت جدولی قابل‌خواندن نبود: {$message}"
                : 'ساختار فایل برای حالت جدولی قابل‌خواندن نبود. از حالت کد خام استفاده کن یا فایل SQL را بررسی کن.';

            return [];
        }

        $rows = [];

        foreach ($records as $record) {
            $row = [
                'row_index' => (string) ($record['row_index'] ?? ''),
            ];

            foreach ($columns as $column) {
                $value = $record[$column] ?? null;
                $row[$column] = $value === null ? '' : (string) $value;
            }

            $rows[] = $row;
        }

        $this->contentColumns = $columns;
        $this->contentColumnTypes = $columnTypes;
        $this->contentTextFields = $this->detectTextFieldsFromColumns($columns, $columnTypes);

        return $rows;
    }

    protected function loadContentRowsFromSql(string $sql): void
    {
        $this->contentRows = $this->parseContentRows($sql);

        if ($this->contentRows === []) {
            $this->selectedRowIndex = null;
            $this->selectedRowField = $this->contentTextFields[0] ?? 'text_fa';
            $this->selectedRowText = '';

            return;
        }

        $this->detectedInsertCount = count($this->contentRows);
        $this->selectContentRow(0);
    }

    /**
     * @return array<int, string>
     */
    protected function extractContentSqlStatements(string $sql): array
    {
        $statements = $this->splitSqlStatements($sql);
        $contentStatements = [];

        foreach ($statements as $statement) {
            $trimmed = trim($statement);
            if ($trimmed === '') {
                continue;
            }

            if (
                preg_match('/^DELETE\s+FROM\s+(?:"content"|`content`|\[content\]|content)\b/i', $trimmed) !== 1
                && preg_match('/^INSERT\s+INTO\s+(?:"content"|`content`|\[content\]|content)\b/i', $trimmed) !== 1
            ) {
                continue;
            }

            if (! str_ends_with($trimmed, ';')) {
                $trimmed .= ';';
            }

            $contentStatements[] = $trimmed;
        }

        return $contentStatements;
    }

    /**
     * @return array<int, string>
     */
    protected function splitSqlStatements(string $sql): array
    {
        $len = strlen($sql);
        $buffer = '';
        $statements = [];
        $inSingleQuote = false;

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];
            $buffer .= $char;

            if ($char === "'") {
                if ($inSingleQuote && $i + 1 < $len && $sql[$i + 1] === "'") {
                    $buffer .= "'";
                    $i++;
                    continue;
                }

                $inSingleQuote = ! $inSingleQuote;
                continue;
            }

            if (! $inSingleQuote && $char === ';') {
                $statements[] = $buffer;
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    /**
     * @param array<int, string> $statements
     * @return array{0: array<string, string>, 1: array<int, string>}
     */
    protected function resolveColumnTypesForStatements(array $statements): array
    {
        $columnTypes = $this->getReferenceContentColumnTypes();

        foreach ($statements as $statement) {
            if (preg_match('/^INSERT\s+INTO\s+(?:"content"|`content`|\[content\]|content)\b/i', trim($statement)) !== 1) {
                continue;
            }

            foreach ($this->parseInsertColumnNames($statement) as $column) {
                if ($column === '') {
                    continue;
                }

                if (! array_key_exists($column, $columnTypes)) {
                    $columnTypes[$column] = str_ends_with($column, '_id') ? 'INTEGER' : 'TEXT';
                }
            }
        }

        if ($columnTypes === []) {
            $columnTypes = [
                'chapters_id' => 'INTEGER',
                'kotob_id' => 'INTEGER',
                'text' => 'TEXT',
                'text_fa' => 'TEXT',
                'text_turkmen' => 'TEXT',
            ];
        }

        $columns = array_keys($columnTypes);
        $priority = [
            'chapters_id' => 1,
            'kotob_id' => 2,
            'text' => 10,
            'text_fa' => 11,
            'text_turkmen' => 12,
            'text_en' => 13,
            'text_tr' => 14,
            'text_ru' => 15,
        ];

        usort($columns, static function (string $a, string $b) use ($priority): int {
            $orderA = $priority[$a] ?? 1000;
            $orderB = $priority[$b] ?? 1000;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return strcmp($a, $b);
        });

        return [$columnTypes, $columns];
    }

    /**
     * @return array<string, string>
     */
    protected function getReferenceContentColumnTypes(): array
    {
        $fallback = [
            'chapters_id' => 'INTEGER',
            'kotob_id' => 'INTEGER',
            'text' => 'TEXT',
            'text_fa' => 'TEXT',
            'text_turkmen' => 'TEXT',
            'text_en' => 'TEXT',
            'text_tr' => 'TEXT',
            'text_ru' => 'TEXT',
        ];

        foreach ($this->resolveReferenceDatabasePaths() as $path) {
            if (! File::exists($path)) {
                continue;
            }

            try {
                $pdo = new PDO('sqlite:' . $path);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $query = $pdo->query('PRAGMA table_info(content)');

                if ($query === false) {
                    continue;
                }

                $rows = $query->fetchAll(PDO::FETCH_ASSOC);
                if ($rows === []) {
                    continue;
                }

                $resolved = [];
                foreach ($rows as $row) {
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $resolved[$name] = $this->normalizeSQLiteType((string) ($row['type'] ?? 'TEXT'));
                }

                if ($resolved !== []) {
                    return $resolved;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $fallback;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveReferenceDatabasePaths(): array
    {
        $repoRoot = KdiniMetadataRepository::repoRoot();
        $repoParent = dirname($repoRoot);

        $candidates = [
            trim((string) env('KDINI_REFERENCE_DB_PATH', '')),
            $repoRoot . '/assets/books.db',
            $repoRoot . '/kdini/assets/books.db',
            $repoParent . '/kdini/assets/books.db',
            $repoParent . '/kdini/kdini/assets/books.db',
        ];

        $paths = [];
        foreach ($candidates as $candidate) {
            $trimmed = trim((string) $candidate);
            if ($trimmed === '') {
                continue;
            }

            if (! in_array($trimmed, $paths, true)) {
                $paths[] = $trimmed;
            }
        }

        return $paths;
    }

    protected function normalizeSQLiteType(string $type): string
    {
        $upper = strtoupper(trim($type));

        if ($upper === '') {
            return 'TEXT';
        }

        if (str_contains($upper, 'INT')) {
            return 'INTEGER';
        }

        if (str_contains($upper, 'REAL') || str_contains($upper, 'FLOA') || str_contains($upper, 'DOUB')) {
            return 'REAL';
        }

        return 'TEXT';
    }

    /**
     * @return array<int, string>
     */
    protected function parseInsertColumnNames(string $statement): array
    {
        if (
            preg_match(
                '/^INSERT\s+INTO\s+(?:"content"|`content`|\[content\]|content)\s*\((.*?)\)\s*VALUES/isu',
                trim($statement),
                $matches
            ) !== 1
        ) {
            return [];
        }

        $rawColumns = (string) ($matches[1] ?? '');
        if ($rawColumns === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/u', $rawColumns) ?: [];
        $columns = [];

        foreach ($parts as $part) {
            $name = trim($part);
            $name = trim($name, " \t\n\r\0\x0B`\"[]");

            if ($name === '') {
                continue;
            }

            $columns[] = $name;
        }

        return array_values(array_unique($columns));
    }

    protected function quoteIdentifier(string $column): string
    {
        return '"' . str_replace('"', '""', $column) . '"';
    }

    protected function isNumericColumn(string $column): bool
    {
        $type = strtoupper((string) ($this->contentColumnTypes[$column] ?? ''));

        if ($type !== '' && (str_contains($type, 'INT') || str_contains($type, 'REAL'))) {
            return true;
        }

        return str_ends_with($column, '_id') || $column === 'id';
    }

    /**
     * @param array<int, string> $columns
     * @param array<string, string> $columnTypes
     * @return array<int, string>
     */
    protected function detectTextFieldsFromColumns(array $columns, array $columnTypes): array
    {
        $textFields = [];
        $preferred = ['text', 'text_fa', 'text_turkmen', 'text_en', 'text_tr', 'text_ru'];

        foreach ($preferred as $field) {
            if (in_array($field, $columns, true)) {
                $textFields[] = $field;
            }
        }

        foreach ($columns as $column) {
            $type = strtoupper((string) ($columnTypes[$column] ?? 'TEXT'));
            $isTextLike = str_starts_with($column, 'text') || $type === 'TEXT';

            if (! $isTextLike) {
                continue;
            }

            if (in_array($column, ['row_index'], true)) {
                continue;
            }

            if (! in_array($column, $textFields, true)) {
                $textFields[] = $column;
            }
        }

        return $textFields !== [] ? $textFields : ['text_fa'];
    }

    protected function applyBookIdToDeleteStatement(string $sql, int $bookId): string
    {
        $targetLine = "DELETE FROM content WHERE kotob_id = {$bookId};";

        if (preg_match('/DELETE\s+FROM\s+(?:"content"|`content`|\[content\]|content)\s+WHERE\s+kotob_id\s*=\s*-?\d+\s*;/i', $sql) === 1) {
            return preg_replace('/DELETE\s+FROM\s+(?:"content"|`content`|\[content\]|content)\s+WHERE\s+kotob_id\s*=\s*-?\d+\s*;/i', $targetLine, $sql, 1) ?: $sql;
        }

        $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];

        $inserted = false;
        $newLines = [];

        foreach ($lines as $line) {
            $newLines[] = $line;

            if (! $inserted && preg_match('/^\s*BEGIN\s+TRANSACTION\b/i', $line) === 1) {
                $newLines[] = $targetLine;
                $inserted = true;
            }
        }

        if (! $inserted) {
            array_unshift($newLines, $targetLine);
        }

        return implode("\n", $newLines);
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    protected function buildSqlContentFromRows(array $rows, int $bookId, string $deleteStatement = ''): string
    {
        $insertColumns = $this->contentColumns !== [] ? $this->contentColumns : ['chapters_id', 'kotob_id', 'text', 'text_fa', 'text_turkmen'];
        $deleteLine = $this->resolveDeleteStatementForBuild($rows, $bookId, $deleteStatement);

        $lines = [
            'BEGIN TRANSACTION;',
            $deleteLine,
            '',
        ];

        foreach ($rows as $row) {
            $values = [];
            foreach ($insertColumns as $column) {
                $value = (string) ($row[$column] ?? '');

                if ($column === 'kotob_id' && $bookId !== 0) {
                    $value = (string) $bookId;
                }

                $values[] = $this->formatSqlValueForColumn($column, $value);
            }

            $lines[] = sprintf(
                'INSERT INTO content (%s) VALUES (%s);',
                implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $insertColumns)),
                implode(', ', $values)
            );
        }

        $lines[] = '';
        $lines[] = 'COMMIT;';

        return implode("\n", $lines);
    }

    protected function quoteSqlString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    protected function resolveDeleteStatementForBuild(array $rows, int $bookId, string $detectedDeleteStatement): string
    {
        $manualBookId = trim($this->editBookId);
        if ($manualBookId !== '' && is_numeric($manualBookId)) {
            return 'DELETE FROM content WHERE kotob_id = ' . (int) $manualBookId . ';';
        }

        if ($bookId !== 0) {
            return 'DELETE FROM content WHERE kotob_id = ' . $bookId . ';';
        }

        $trimmedDelete = trim($detectedDeleteStatement);
        if ($trimmedDelete !== '') {
            if (! str_ends_with($trimmedDelete, ';')) {
                $trimmedDelete .= ';';
            }

            return $trimmedDelete;
        }

        $chapterIds = [];
        foreach ($rows as $row) {
            $candidate = trim((string) ($row['chapters_id'] ?? ''));
            if ($candidate === '' || ! is_numeric($candidate)) {
                continue;
            }

            $chapterIds[] = (int) $candidate;
        }

        $chapterIds = array_values(array_unique($chapterIds));

        if ($chapterIds !== []) {
            return 'DELETE FROM content WHERE chapters_id IN (' . implode(', ', $chapterIds) . ');';
        }

        return 'DELETE FROM content WHERE 1 = 0;';
    }

    protected function formatSqlValueForColumn(string $column, string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 'NULL';
        }

        if ($this->isNumericColumn($column) && is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (string) ((float) $trimmed) : (string) ((int) $trimmed);
        }

        return $this->quoteSqlString($value);
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    protected function resolveBookIdFromRows(array $rows): int
    {
        $manual = trim($this->editBookId);
        if ($manual !== '' && is_numeric($manual)) {
            return (int) $manual;
        }

        foreach ($rows as $row) {
            $candidate = trim((string) ($row['kotob_id'] ?? ''));
            if ($candidate !== '' && is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        $sqlSource = trim($this->sqlContent) !== '' ? $this->sqlContent : $this->readCurrentSqlFromDisk();
        $fromSqlDelete = $this->detectDeleteBookId($sqlSource);
        if ($fromSqlDelete !== '' && is_numeric($fromSqlDelete)) {
            return (int) $fromSqlDelete;
        }

        return 0;
    }

    protected function resolveSqlContentForSave(): string
    {
        $rawSql = trim($this->sqlContent);

        if ($rawSql !== '' && ($this->showRawSqlEditor || $this->contentRows === [])) {
            return $rawSql;
        }

        if ($this->contentRows !== []) {
            $bookId = $this->resolveBookIdFromRows($this->contentRows);

            return trim($this->buildSqlContentFromRows($this->contentRows, $bookId, $this->detectedDeleteStatement));
        }

        if ($rawSql !== '') {
            return $rawSql;
        }

        return trim($this->readCurrentSqlFromDisk());
    }

    protected function resolveSqlContentForRawEditor(): string
    {
        if ($this->contentRows !== []) {
            $bookId = $this->resolveBookIdFromRows($this->contentRows);

            return $this->buildSqlContentFromRows($this->contentRows, $bookId, $this->detectedDeleteStatement);
        }

        return $this->readCurrentSqlFromDisk();
    }

    protected function readCurrentSqlFromDisk(): string
    {
        if ($this->editingRelativePath === null) {
            return '';
        }

        $path = KdiniMetadataRepository::safePath($this->normalizeRelativePath($this->editingRelativePath));
        if (! File::exists($path)) {
            return '';
        }

        return (string) File::get($path);
    }

    protected function getPreferredTextField(array $row): string
    {
        foreach ($this->getAllowedTextFields() as $field) {
            if (trim((string) ($row[$field] ?? '')) !== '') {
                return $field;
            }
        }

        return $this->getAllowedTextFields()[0] ?? 'text_fa';
    }

    protected function normalizeTextField(string $field): string
    {
        if (in_array($field, $this->getAllowedTextFields(), true)) {
            return $field;
        }

        return $this->getAllowedTextFields()[0] ?? 'text_fa';
    }

    /**
     * @return array<int, string>
     */
    protected function getAllowedTextFields(): array
    {
        return $this->contentTextFields !== [] ? $this->contentTextFields : ['text', 'text_fa', 'text_turkmen'];
    }

    public function labelForTextField(string $field): string
    {
        return match ($field) {
            'text' => 'متن عربی',
            'text_fa' => 'ترجمه فارسی',
            'text_turkmen' => 'متن ترکمنی',
            'text_en' => 'متن انگلیسی',
            'text_tr' => 'متن ترکی',
            'text_ru' => 'متن روسی',
            default => $field,
        };
    }

    protected function normalizeRelativePath(string $relativePath): string
    {
        $normalized = ltrim(str_replace('\\', '/', trim($relativePath)), '/');

        if (! str_starts_with($normalized, 'kotob/')) {
            $normalized = 'kotob/' . basename($normalized);
        }

        return $normalized;
    }

    protected function isAllowedSqlFileName(string $name): bool
    {
        $lower = mb_strtolower(trim($name));

        return str_ends_with($lower, '.sql')
            || str_ends_with($lower, '.sql.gz')
            || str_ends_with($lower, '.db')
            || str_ends_with($lower, '.gz');
    }

    protected function sanitizeUploadFileName(string $originalName): string
    {
        $trimmed = trim($originalName);
        $lower = mb_strtolower($trimmed);

        $ext = '';
        $stem = $trimmed;

        if (str_ends_with($lower, '.sql.gz')) {
            $ext = '.sql.gz';
            $stem = substr($trimmed, 0, -7);
        } else {
            $ext = '.' . pathinfo($trimmed, PATHINFO_EXTENSION);
            $stem = pathinfo($trimmed, PATHINFO_FILENAME);
        }

        $stem = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $stem) ?: '';
        $stem = trim($stem, '._-');

        if ($stem === '') {
            $stem = 'book_' . date('Ymd_His');
        }

        return $stem . $ext;
    }

    protected function appendTimestampToFileName(string $fileName): string
    {
        $lower = mb_strtolower($fileName);
        $stamp = date('Ymd_His');

        if (str_ends_with($lower, '.sql.gz')) {
            $stem = substr($fileName, 0, -7);

            return "{$stem}_{$stamp}.sql.gz";
        }

        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $stem = pathinfo($fileName, PATHINFO_FILENAME);

        if ($ext === '') {
            return "{$stem}_{$stamp}";
        }

        return "{$stem}_{$stamp}.{$ext}";
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return number_format($value, 1) . ' ' . $units[$unitIndex];
    }
}
