<?php

namespace App\Filament\Pages;

use App\Support\KdiniMetadataRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class AudioManager extends Page
{
    protected static ?string $title = 'مدیریت صوت‌ها';

    protected string $view = 'filament.pages.audio-manager';

    protected static ?int $navigationSort = 20;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-musical-note';

    protected static string | UnitEnum | null $navigationGroup = 'متادیتا';

    public string $search = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    public ?int $editingIndex = null;

    public bool $isCreating = false;

    /**
     * @var array<string, mixed>
     */
    public array $edit = [];

    public function mount(): void
    {
        $this->loadRows();
    }

    public function loadRows(): void
    {
        try {
            $this->rows = KdiniMetadataRepository::readAudio();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('خواندن فایل صوتی ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $this->rows = [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredRowsProperty(): array
    {
        $query = mb_strtolower(trim($this->search));

        $result = [];

        foreach ($this->rows as $index => $row) {
            $kotob = (string) ($row['kotob_id'] ?? '');
            $chapter = (string) ($row['chapters_id'] ?? '');
            $lang = (string) ($row['lang'] ?? '');
            $narrator = (string) ($row['narrator'] ?? '');
            $title = (string) ($row['title'] ?? '');
            $url = (string) ($row['url'] ?? '');

            if ($query !== '') {
                $haystack = mb_strtolower(implode(' ', [$kotob, $chapter, $lang, $narrator, $title, $url]));
                if (! str_contains($haystack, $query)) {
                    continue;
                }
            }

            $row['__index'] = $index;
            $result[] = $row;
        }

        return $result;
    }

    public function startEdit(int $index): void
    {
        if (! isset($this->rows[$index])) {
            return;
        }

        $row = $this->rows[$index];

        $this->isCreating = false;
        $this->editingIndex = $index;
        $this->edit = [
            'kotob_id' => (string) ($row['kotob_id'] ?? ''),
            'chapters_id' => (string) ($row['chapters_id'] ?? ''),
            'lang' => (string) ($row['lang'] ?? ''),
            'narrator' => (string) ($row['narrator'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
        ];
    }

    public function startCreate(): void
    {
        $this->isCreating = true;
        $this->editingIndex = null;
        $this->edit = [
            'kotob_id' => '',
            'chapters_id' => '',
            'lang' => 'fa',
            'narrator' => '',
            'title' => '',
            'url' => '',
        ];
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

        if (! $isCreating && ($this->editingIndex === null || ! isset($this->rows[$this->editingIndex]))) {
            return;
        }

        $row = $isCreating ? [] : $this->rows[$this->editingIndex];

        $row['kotob_id'] = $this->toIntOrNull((string) ($this->edit['kotob_id'] ?? ''));
        $row['chapters_id'] = $this->toIntOrOriginal((string) ($this->edit['chapters_id'] ?? ''));
        $row['lang'] = trim((string) ($this->edit['lang'] ?? ''));
        $row['narrator'] = trim((string) ($this->edit['narrator'] ?? ''));
        $row['title'] = trim((string) ($this->edit['title'] ?? ''));
        $row['url'] = trim((string) ($this->edit['url'] ?? ''));

        if ($isCreating) {
            $this->rows[] = $row;
        } else {
            $this->rows[$this->editingIndex] = $row;
        }

        try {
            KdiniMetadataRepository::writeAudio($this->rows);

            Notification::make()
                ->title($isCreating ? 'رکورد صوتی جدید اضافه شد' : 'ردیف صوت ذخیره شد')
                ->success()
                ->send();

            $this->cancelEdit();
            $this->loadRows();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('ذخیره با خطا مواجه شد')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function toIntOrNull(string $value): int | string | null
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return is_numeric($trimmed) ? (int) $trimmed : $trimmed;
    }

    protected function toIntOrOriginal(string $value): int | string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        return is_numeric($trimmed) ? (int) $trimmed : $trimmed;
    }
}
