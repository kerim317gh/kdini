<?php

namespace App\Filament\Resources\SoundResource\Pages;

use App\Filament\Resources\SoundResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSound extends CreateRecord
{
    protected static string $resource = SoundResource::class;
    protected static ?string $title = 'افزودن صدا';
}
