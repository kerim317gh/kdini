<?php

namespace App\Filament\Resources\SoundResource\Pages;

use App\Filament\Resources\SoundResource;
use Filament\Resources\Pages\EditRecord;

class EditSound extends EditRecord
{
    protected static string $resource = SoundResource::class;
    protected static ?string $title = 'ویرایش صدا';
}
