<?php

namespace App\Filament\Resources\QuickActionResource\Pages;

use App\Filament\Resources\QuickActionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQuickAction extends EditRecord
{
    protected static string $resource = QuickActionResource::class;

    protected static ?string $title = '修改快捷菜单';

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
