<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SoundResource\Pages;
use App\Models\Sound;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Card;

class SoundResource extends Resource
{
    protected static ?string $model = Sound::class;

    protected static ?string $navigationIcon = 'heroicon-o-musical-note';
    protected static ?string $navigationLabel = 'مدیریت صداها';
    protected static ?string $pluralModelLabel = 'صداها';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make()->schema([
                    Forms\Components\Select::make('chapter_id')
                        ->relationship('chapter', 'title')
                        ->label('فصل مربوطه')
                        ->searchable()
                        ->required(),
                    Forms\Components\FileUpload::make('url')
                        ->label('فایل صوتی اصلی (پیش‌فرض)')
                        ->disk('public')
                        ->directory('sounds')
                        ->preserveFilenames(),
                    Forms\Components\FileUpload::make('url_fa')
                        ->label('فایل صوتی فارسی')
                        ->disk('public')
                        ->directory('sounds/fa')
                        ->preserveFilenames(),
                    Forms\Components\FileUpload::make('url_en')
                        ->label('فایل صوتی انگلیسی')
                        ->disk('public')
                        ->directory('sounds/en')
                        ->preserveFilenames(),
                    Forms\Components\FileUpload::make('url_tr')
                        ->label('فایل صوتی ترکی استانبولی')
                        ->disk('public')
                        ->directory('sounds/tr')
                        ->preserveFilenames(),
                    Forms\Components\FileUpload::make('url_ru')
                        ->label('فایل صوتی روسی')
                        ->disk('public')
                        ->directory('sounds/ru')
                        ->preserveFilenames(),
                    Forms\Components\FileUpload::make('url_tk')
                        ->label('فایل صوتی ترکمنی')
                        ->disk('public')
                        ->directory('sounds/tk')
                        ->preserveFilenames(),
                ]),
                Card::make()->schema([
                    Repeater::make('soundTimes')
                        ->relationship()
                        ->label('زمان‌بندی متن و صوت')
                        ->schema([
                            TextInput::make('text_part')->label('بخش متن')->required(),
                            TextInput::make('start_time')->label('زمان شروع (فرمت: 00:00)')->required(),
                            TextInput::make('end_time')->label('زمان پایان (فرمت: 00:00)')->required(),
                        ])
                        ->columns(3)
                        ->addActionLabel('افزودن زمان‌بندی جدید')
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('chapter.title')->label('فصل مربوطه')->searchable(),
                Tables\Columns\TextColumn::make('url')->label('فایل اصلی'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSounds::route('/'),
            'create' => Pages\CreateSound::route('/create'),
            'edit' => Pages\EditSound::route('/{record}/edit'),
        ];
    }
}
