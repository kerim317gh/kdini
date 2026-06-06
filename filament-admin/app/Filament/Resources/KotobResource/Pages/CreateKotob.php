<?php

namespace App\Filament\Resources\KotobResource\Pages;

use App\Filament\Resources\KotobResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateKotob extends CreateRecord
{
    protected static string $resource = KotobResource::class;
    protected static ?string $title = 'افزودن کتاب';
}
