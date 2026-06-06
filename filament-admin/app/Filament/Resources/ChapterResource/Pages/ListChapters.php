<?php

namespace App\Filament\Resources\ChapterResource\Pages;

use App\Filament\Resources\ChapterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChapters extends ListRecords
{
    protected static string $resource = ChapterResource::class;
    protected static ?string $title = 'فهرست فصل‌ها';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('افزودن فصل جدید'),
        ];
    }
}
