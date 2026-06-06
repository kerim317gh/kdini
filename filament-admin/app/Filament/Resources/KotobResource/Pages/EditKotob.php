<?php

namespace App\Filament\Resources\KotobResource\Pages;

use App\Filament\Resources\KotobResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKotob extends EditRecord
{
    protected static string $resource = KotobResource::class;
    protected static ?string $title = 'ویرایش کتاب';
}
