<?php

namespace App\Filament\Pages;

use App\Support\KdiniMetadataRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use JsonException;
use UnitEnum;

class JsonFilesManager extends Page
{
    protected static ?string $title = 'مدیریت فایل‌های JSON';

    protected string $view = 'filament.pages.json-files-manager';

    protected static ?int $navigationSort = 34;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-code-bracket-square';

    protected static string | UnitEnum | null $navigationGroup = 'متادیتا';

    public string $search = '';

    public string $selectedRelativePath = 'json/structure_metadata.json';

    public string $jsonContent = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $availableFiles = [];

    public function mount(): void
    {
        $this->loadAvailableFiles();
        $this->ensureSelectedFileExists();
        $this->loadSelectedFile();
    }

    public function loadAvailableFiles(): void
    {
        $directory = KdiniMetadataRepository::safePath('json');
        File::ensureDirectoryExists($directory);

        $rows = [];

        foreach (File::files($directory) as $file) {
            if (mb_strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            $name = $file->getFilename();
            $rows[] = [
                'relative' => 'json/' . $name,
                'name' => $name,
                'size_bytes' => $file->getSize(),
                'size_human' => $this->formatBytes((int) $file->getSize()),
                'modified_ts' => $file->getMTime(),
                'modified' => date('Y-m-d H:i', $file->getMTime()),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) $a['name'], (string) $b['name']);
        });

        $this->availableFiles = $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredFilesProperty(): array
    {
        $query = mb_strtolower(trim($this->search));

        if ($query === '') {
            return $this->availableFiles;
        }

        return array_values(array_filter($this->availableFiles, static function (array $row) use ($query): bool {
            $haystack = mb_strtolower(
                implode(' ', [
                    (string) ($row['name'] ?? ''),
                    (string) ($row['relative'] ?? ''),
                ])
            );

            return str_contains($haystack, $query);
        }));
    }

    public function selectFile(string $relativePath): void
    {
        $relativePath = $this->normalizeJsonRelativePath($relativePath);
        if (! $this->isKnownJsonFile($relativePath)) {
            Notification::make()
                ->title('فایل انتخاب‌شده معتبر نیست')
                ->danger()
                ->send();

            return;
        }

        $this->selectedRelativePath = $relativePath;
        $this->loadSelectedFile();
    }

    public function reloadSelectedFile(): void
    {
        $this->loadSelectedFile();
    }

    public function saveSelectedFile(): void
    {
        $relativePath = $this->normalizeJsonRelativePath($this->selectedRelativePath);
        if (! $this->isKnownJsonFile($relativePath)) {
            Notification::make()
                ->title('فایل انتخاب‌شده معتبر نیست')
                ->danger()
                ->send();

            return;
        }

        try {
            $decoded = json_decode($this->jsonContent, true, 512, JSON_THROW_ON_ERROR);
            $normalizedJson = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Notification::make()
                ->title('JSON نامعتبر است')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $path = KdiniMetadataRepository::safePath($relativePath);
        File::put($path, $normalizedJson . PHP_EOL);

        $this->jsonContent = $normalizedJson . PHP_EOL;
        $this->loadAvailableFiles();

        Notification::make()
            ->title('فایل JSON ذخیره شد')
            ->success()
            ->send();
    }

    protected function loadSelectedFile(): void
    {
        $relativePath = $this->normalizeJsonRelativePath($this->selectedRelativePath);
        $path = KdiniMetadataRepository::safePath($relativePath);

        if (! File::exists($path)) {
            $this->jsonContent = '';

            Notification::make()
                ->title('فایل JSON پیدا نشد')
                ->body($relativePath)
                ->danger()
                ->send();

            return;
        }

        $this->selectedRelativePath = $relativePath;
        $this->jsonContent = (string) File::get($path);
    }

    protected function ensureSelectedFileExists(): void
    {
        $target = $this->normalizeJsonRelativePath($this->selectedRelativePath);
        if ($this->isKnownJsonFile($target)) {
            $this->selectedRelativePath = $target;

            return;
        }

        if ($this->availableFiles === []) {
            $this->selectedRelativePath = 'json/structure_metadata.json';

            return;
        }

        $this->selectedRelativePath = (string) ($this->availableFiles[0]['relative'] ?? 'json/structure_metadata.json');
    }

    protected function isKnownJsonFile(string $relativePath): bool
    {
        foreach ($this->availableFiles as $row) {
            if (($row['relative'] ?? '') === $relativePath) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeJsonRelativePath(string $relativePath): string
    {
        $normalized = ltrim(str_replace('\\', '/', trim($relativePath)), '/');

        if (! str_starts_with($normalized, 'json/')) {
            $normalized = 'json/' . basename($normalized);
        }

        if (! str_ends_with(mb_strtolower($normalized), '.json')) {
            $normalized .= '.json';
        }

        return $normalized;
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
