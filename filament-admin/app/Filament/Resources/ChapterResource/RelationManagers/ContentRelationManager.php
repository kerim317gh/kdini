<?php

namespace App\Filament\Resources\ChapterResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContentRelationManager extends RelationManager
{
    protected static string $relationship = 'content';

    protected static ?string $recordTitleAttribute = 'id';
    protected static ?string $pluralLabel = 'محتوا و ترجمه‌ها';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\RichEditor::make('text')->label('متن اصلی (عربی)')->required(),
                Forms\Components\RichEditor::make('text_fa')->label('ترجمه فارسی'),
                Forms\Components\RichEditor::make('text_en')->label('ترجمه انگلیسی'),
                Forms\Components\RichEditor::make('text_tr')->label('ترجمه ترکی'),
                Forms\Components\RichEditor::make('text_ru')->label('ترجمه روسی'),
                Forms\Components\RichEditor::make('text_turkmen')->label('ترجمه ترکمنی'),
                Forms\Components\Select::make('kotob_id')
                    ->relationship('book', 'title')
                    ->label('برای کدام کتاب')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                // Display a snippet of the text
                Tables\Columns\TextColumn::make('text')->label('متن اصلی')->limit(50)->html(),
                Tables\Columns\TextColumn::make('book.title')->label('کتاب'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::class,
            ])
            ->actions([
                Tables\Actions\EditAction::class,
                Tables\Actions\DeleteAction::class,
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::class,
            ]);
    }
}
