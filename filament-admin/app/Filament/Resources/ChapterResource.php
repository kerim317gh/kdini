<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChapterResource\Pages;
use App\Models\Chapter;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Forms\Components\Select;
use App\Filament\Resources\ChapterResource\RelationManagers\ContentRelationManager;
use App\Filament\Resources\ChapterResource\RelationManagers\ContentAudioRelationManager;

class ChapterResource extends Resource
{
    protected static ?string $model = Chapter::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'فصل‌ها';
    protected static ?string $pluralLabel = 'فصل‌ها';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('category_id')
                    ->relationship('category', 'title')
                    ->label('دسته‌بندی')
                    ->required(),
                Select::make('parent_id')
                    ->relationship('parent', 'title')
                    ->label('فصل والد'),
                Forms\Components\TextInput::make('title')->label('عنوان اصلی')->required(),
                Forms\Components\TextInput::make('title_fa')->label('عنوان فارسی'),
                Forms\Components\TextInput::make('title_en')->label('عنوان انگلیسی'),
                Forms\Components\TextInput::make('title_tr')->label('عنوان ترکی'),
                Forms\Components\TextInput::make('title_ru')->label('عنوان روسی'),
                Forms\Components\TextInput::make('title_tk')->label('عنوان ترکمنی'),
                Forms\Components\TextInput::make('icon')->label('آیکون'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('شناسه')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
                Tables\Columns\TextColumn::make('category.title')->label('دسته‌بندی'),
                Tables\Columns\TextColumn::make('parent.title')->label('فصل والد'),
            ])
            ->filters([
                // You can add a filter to show only top-level chapters
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
            ContentRelationManager::class,
            ContentAudioRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChapters::route('/'),
            'create' => Pages\CreateChapter::route('/create'),
            'edit' => Pages\EditChapter::route('/{record}/edit'),
        ];
    }
}
