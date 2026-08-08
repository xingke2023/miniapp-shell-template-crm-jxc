<?php

namespace App\Filament\Resources\MenuTemplateResource\RelationManagers;

use App\Filament\Forms\Components\IconPickerField;
use App\Filament\Resources\QuickActionResource;
use App\Models\QuickAction;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class QuickActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'quickActions';

    protected static ?string $title = '菜单按钮';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('action_type')
                    ->label('类型')
                    ->options([
                        'prompt'        => '发文字给 AI',
                        'web'           => 'AI 摘要+网页',
                        'open'          => '打开网页（带 token）',
                        'external_open' => '外部网站（带 token）',
                        'menu'          => '弹出子菜单',
                        'shortcuts'     => '展开快捷按钮到聊天区',
                        'route'         => '小程序原生页',
                        'external'      => '外部网站（不带 token）',
                    ])
                    ->default('prompt')
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('label')->label('按钮文字')->required()->maxLength(50),

                Forms\Components\TextInput::make('key')->label('Key')->required()->maxLength(50),

                Forms\Components\TextInput::make('badge')->label('角标')->maxLength(20)->placeholder('热门'),

                Forms\Components\TextInput::make('sort_order')->label('排序')->numeric()->default(0),

                Forms\Components\Toggle::make('enabled')->label('启用')->default(true)->inline(),

                Forms\Components\Toggle::make('admin_only')->label('仅管理员可见')->inline(),

                Forms\Components\Toggle::make('show_in_chat')
                    ->label('聊天区显示')
                    ->inline()
                    ->visible(fn (Get $get) => $get('action_type') === 'menu'),

                Forms\Components\Textarea::make('prompt')
                    ->label('发给 AI 的文字')->rows(2)->columnSpanFull()
                    ->visible(fn (Get $get) => in_array($get('action_type'), ['prompt', 'web']))
                    ->required(fn (Get $get) => in_array($get('action_type'), ['prompt', 'web'])),

                Forms\Components\TextInput::make('target_path')
                    ->label('路径 / 网址')->maxLength(200)->columnSpanFull()
                    ->visible(fn (Get $get) => in_array($get('action_type'), ['web', 'open', 'external_open', 'route', 'external']))
                    ->required(fn (Get $get) => in_array($get('action_type'), ['web', 'open', 'external_open', 'route', 'external'])),

                Forms\Components\TextInput::make('target_title')
                    ->label('网页标题')->maxLength(50)
                    ->visible(fn (Get $get) => in_array($get('action_type'), ['web', 'open', 'external_open'])),

                Forms\Components\TextInput::make('web_label')
                    ->label('「打开完整页」按钮文字')->maxLength(50)->placeholder('📦 打开完整库存页')
                    ->visible(fn (Get $get) => $get('action_type') === 'web'),

            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->label('按钮文字'),
                Tables\Columns\TextColumn::make('action_type')
                    ->label('类型')
                    ->badge()
                    ->formatStateUsing(fn ($state) => [
                        'prompt'        => '文字',
                        'web'           => 'AI+网页',
                        'open'          => '打开网页',
                        'external_open' => '外部网站',
                        'menu'          => '子菜单',
                        'route'         => '原生页',
                        'external'      => '外链',
                    ][$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'menu' => 'warning',
                        'open', 'external_open', 'external' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('子项数')
                    ->counts('items')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\IconColumn::make('enabled')->label('启用')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['industry'] = $this->getOwnerRecord()->industry;

                        return $data;
                    }),
            ])
            ->actions([
                Actions\Action::make('edit')
                    ->label('编辑')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (QuickAction $record) => QuickActionResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(false),
                Actions\DeleteAction::make(),
            ]);
    }
}
