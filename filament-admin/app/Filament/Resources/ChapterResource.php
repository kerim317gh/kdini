<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChapterResource\Pages;
use App\Models\Chapter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ChapterResource extends Resource
{
    protected static ?string $model = Chapter::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'مدیریت فصل‌ها';
    protected static ?string $pluralModelLabel = 'فصل‌ها';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('اطلاعات اصلی')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('عنوان اصلی')
                            ->required(),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'title')
                            ->label('دسته‌بندی')
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('parent_id')
                            ->relationship('parent', 'title')
                            ->label('فصل والد')
                            ->searchable(),
                        Forms\Components\TextInput::make('icon')
                            ->label('آیکن'),
                    ])->columns(2),

                Forms\Components\Section::make('ترجمه‌ها')
                    ->schema([
                        Forms\Components\TextInput::make('title_fa')->label('عنوان فارسی'),
                        Forms\Components\TextInput::make('title_en')->label('عنوان انگلیسی'),
                        Forms\Components\TextInput::make('title_tr')->label('عنوان ترکی استانبولی'),
                        Forms\Components\TextInput::make('title_ru')->label('عنوان روسی'),
                        Forms\Components\TextInput::make('title_tk')->label('عنوان ترکمنی'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
                Tables\Columns\TextColumn::make('category.title')->label('دسته‌بندی')->searchable(),
                Tables\Columns\TextColumn::make('parent.title')->label('فصل والد')->searchable(),
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
            'index' => Pages\ListChapters::route('/'),
            'create' => Pages\CreateChapter::route('/create'),
            'edit' => Pages\EditChapter::route('/{record}/edit'),
        ];
    }
}
