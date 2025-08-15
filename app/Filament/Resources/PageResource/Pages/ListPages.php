<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\BulkActionsHelp;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            BulkActionsHelp::class,
        ];
    }
}
