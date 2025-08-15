<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages as ResourcePages;
use App\Models\Page as PageModel;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;

class PageResource extends Resource
{
    protected static ?string $model = PageModel::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')->searchable()->wrap()->limit(60),
                TextColumn::make('page_type')->label('Type')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordUrl(fn (PageModel $record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('url')->label('URL')->copyable()->columnSpanFull(),
                TextEntry::make('page_type')->label('Type'),
                TextEntry::make('status')->label('Status'),
                TextEntry::make('meta.title')->label('Title')->placeholder('-'),
                TextEntry::make('cleaned_text')
                    ->label('Content')
                    ->copyable()
                    ->columnSpanFull()
                    ->html()
                    ->formatStateUsing(fn ($state) => nl2br(e($state))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ResourcePages\ListPages::route('/'),
            'view' => ResourcePages\ViewPage::route('/{record}'),
        ];
    }
}

