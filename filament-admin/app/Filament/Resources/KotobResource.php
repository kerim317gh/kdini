<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KotobResource\Pages;
use App\Models\Kotob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class KotobResource extends Resource
{
    protected static ?string $model = Kotob::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'مدیریت کتاب‌ها';
    protected static ?string $pluralModelLabel = 'کتاب‌ها';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('عنوان کتاب')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('توضیحات')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('current_version')
                    ->label('ورژن فعلی'),
                Forms\Components\TextInput::make('latest_version')
                    ->label('آخرین ورژن'),
                Forms\Components\TextInput::make('sql_download_url')
                    ->label('آدرس دانلود SQL')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('cover_image_url')
                    ->label('آدرس تصویر جلد')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_default')
                    ->label('کتاب پیش‌فرض')
                    ->default(false),
                Forms\Components\Toggle::make('is_downloaded')
                    ->label('دانلود شده')
                    ->default(false),
                Forms\Components\TextInput::make('status')
                    ->label('وضعیت'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
                Tables\Columns\TextColumn::make('current_version')->label('ورژن'),
                Tables\Columns\IconColumn::make('is_default')->label('پیش‌فرض')->boolean(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListKotobs::route('/'),
            'create' => Pages\CreateKotob::route('/create'),
            'edit' => Pages\EditKotob::route('/{record}/edit'),
        ];
    }
}
