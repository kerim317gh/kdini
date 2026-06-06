<?php

namespace App\Filament\Resources\KotobResource\Pages;

use App\Filament\Resources\KotobResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKotobs extends ListRecords
{
    protected static string $resource = KotobResource::class;
    protected static ?string $title = 'فهرست کتاب‌ها';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('افزودن کتاب جدید'),
        ];
    }
}
