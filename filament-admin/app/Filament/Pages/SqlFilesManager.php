<?php

namespace App\Filament\Pages;

use App\Support\KdiniMetadataRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
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
    public array $previewRows = [];

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
        $this->sqlContent = $sql;
        $this->editBookId = $this->detectDeleteBookId($sql);
        $this->detectedInsertCount = $this->detectInsertCount($sql);
        $this->previewRows = $this->extractPreviewRows($sql);
    }

    public function cancelEditFile(): void
    {
        $this->editingRelativePath = null;
        $this->editFileName = '';
        $this->editBookId = '';
        $this->sqlContent = '';
        $this->detectedInsertCount = 0;
        $this->previewRows = [];
    }

    public function saveSqlFile(): void
    {
        if ($this->editingRelativePath === null) {
            return;
        }

        $content = trim($this->sqlContent);
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
        $this->sqlContent = $content;
        $this->editBookId = $this->detectDeleteBookId($content);
        $this->detectedInsertCount = $this->detectInsertCount($content);
        $this->previewRows = $this->extractPreviewRows($content);

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
        if (preg_match('/DELETE\s+FROM\s+content\s+WHERE\s+kotob_id\s*=\s*(-?\d+)/i', $sql, $matches) === 1) {
            return (string) $matches[1];
        }

        return '';
    }

    protected function detectInsertCount(string $sql): int
    {
        return preg_match_all('/\bINSERT\s+INTO\s+content\b/i', $sql) ?: 0;
    }

    /**
     * @return array<int, array{chapter_id:string, preview:string}>
     */
    protected function extractPreviewRows(string $sql): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];
        $rows = [];

        $collecting = false;
        $statement = '';

        foreach ($lines as $line) {
            if (! $collecting && preg_match('/^\s*INSERT\s+INTO\s+content\b/i', $line) === 1) {
                $collecting = true;
                $statement = $line;
            } elseif ($collecting) {
                $statement .= "\n" . $line;
            }

            if ($collecting && str_contains($line, ';')) {
                $chapterId = '';
                if (preg_match('/VALUES\s*\(\s*(-?\d+)/i', $statement, $m) === 1) {
                    $chapterId = (string) $m[1];
                }

                $preview = preg_replace('/\s+/u', ' ', trim($statement)) ?: '';
                if (mb_strlen($preview) > 140) {
                    $preview = mb_substr($preview, 0, 140) . '...';
                }

                $rows[] = [
                    'chapter_id' => $chapterId,
                    'preview' => $preview,
                ];

                if (count($rows) >= 20) {
                    break;
                }

                $collecting = false;
                $statement = '';
            }
        }

        return $rows;
    }

    protected function applyBookIdToDeleteStatement(string $sql, int $bookId): string
    {
        $targetLine = "DELETE FROM content WHERE kotob_id = {$bookId};";

        if (preg_match('/DELETE\s+FROM\s+content\s+WHERE\s+kotob_id\s*=\s*-?\d+\s*;/i', $sql) === 1) {
            return preg_replace('/DELETE\s+FROM\s+content\s+WHERE\s+kotob_id\s*=\s*-?\d+\s*;/i', $targetLine, $sql, 1) ?: $sql;
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
