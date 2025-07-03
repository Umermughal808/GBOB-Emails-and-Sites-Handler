<?php

namespace App\Filament\Resources\OrderTypeResource\Pages;

use App\Filament\Resources\OrderTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrderTypes extends ListRecords
{
    protected static string $resource = OrderTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
