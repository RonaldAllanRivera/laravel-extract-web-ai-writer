<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class BulkActionsHelp extends Widget
{
    protected string $view = 'filament.widgets.bulk-actions-help';

    // Put this near the top of the header area
    protected static ?int $sort = -10;
}
