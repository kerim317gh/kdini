<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Filament\Resources\BookResource\RelationManagers;
use App\Models\Book;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;

class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'کتاب‌ها';
    protected static ?string $pluralLabel = 'کتاب‌ها';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')->label('عنوان')->required(),
                Forms\Components\Textarea::make('description')->label('توضیحات'),
                Forms\Components\TextInput::make('status')->label('وضعیت'),
                Forms\Components\TextInput::make('current_version')->label('نسخه فعلی'),
                Forms\Components\TextInput::make('latest_version')->label('آخرین نسخه'),
                Forms\Components\TextInput::make('sql_download_url')->label('لینک دانلود SQL'),
                Forms\Components\TextInput::make('cover_image_url')->label('لینک کاور'),
                Forms\Components\Toggle::make('is_default')->label('کتاب پیش‌فرض'),
                Forms\Components\Toggle::make('is_downloaded')->label('دانلود شده'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('شناسه')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
                Tables\Columns\TextColumn::make('status')->label('وضعیت'),
                Tables\Columns\TextColumn::make('current_version')->label('نسخه'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::class,
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
