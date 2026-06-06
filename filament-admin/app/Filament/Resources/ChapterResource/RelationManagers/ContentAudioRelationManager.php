<?php

namespace App\Filament\Resources\ChapterResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\Action;
use Illuminate\Support\HtmlString;

class ContentAudioRelationManager extends RelationManager
{
    protected static string $relationship = 'contentAudio';

    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $pluralLabel = 'فایل‌های صوتی';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('kotob_id')
                    ->relationship('book', 'title')
                    ->label('برای کدام کتاب')
                    ->required(),
                Forms\Components\TextInput::make('title')->label('عنوان فایل صوتی')->required(),
                Forms\Components\TextInput::make('narrator')->label('گوینده'),
                Forms\Components\TextInput::make('lang')->label('زبان (مثلا: fa)'),
                Forms\Components\TextInput::make('url')->label('لینک فایل صوتی')->url(),
                Forms\Components\TextInput::make('duration_ms')->label('مدت زمان (میلی‌ثانیه)')->numeric(),
                Forms\Components\Toggle::make('selected')->label('فایل منتخب'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان'),
                Tables\Columns\TextColumn::make('narrator')->label('گوینده'),
                Tables\Columns\TextColumn::make('lang')->label('زبان'),
                Tables\Columns\BooleanColumn::make('selected')->label('منتخب'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::class,
            ])
            ->actions([
                Tables\Actions\EditAction::class,

                Action::make('edit_timing')
                    ->label('زمان‌بندی')
                    ->icon('heroicon-o-clock')
                    ->modalWidth('4xl')
                    ->modalHeading('ویرایشگر زمان‌بندی صدا')
                    ->modalSubheading('روی کلمات کلیک و راست-کلیک کنید تا زمان شروع و پایان را ثبت نمایید.')
                    ->modalContent(fn ($record) => new HtmlString('<livewire:timing-editor :audioId="' . $record->id . '"/>'))
                    // We use an empty footer because the component has its own save button
                    ->modalActions([]),

                Tables\Actions\DeleteAction::class,
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::class,
            ]);
    }
}
