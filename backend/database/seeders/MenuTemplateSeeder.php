<?php

namespace Database\Seeders;

use App\Models\MenuTemplate;
use App\Models\QuickAction;
use Illuminate\Database\Seeder;

class MenuTemplateSeeder extends Seeder
{
    /**
     * 为每个行业确保有一条「默认模版」(生效)，并把该行业未归模版的按钮挂进去。
     * 幂等：默认模版已存在则复用；只补挂 menu_template_id 为空的按钮。
     * 须在 QuickActionSeeder 之后运行（按钮先建好，再归集到模版）。
     */
    /** @var array<string, string> */
    private const INDUSTRY_NAMES = [
        'fresh'         => '生鲜门店',
        'restaurant'    => '餐饮快餐',
        'apparel'       => '服装零售',
        'convenience'   => '便利超市',
        'beauty'        => '美业服务',
        'manufacturing' => '制造业',
        'auto_repair'   => '汽修门店',
        'insurance'     => '保险',
        'finance'       => '金融',
        'ecommerce'     => '电商',
        'cross_border'  => '跨境电商',
    ];

    public function run(): void
    {
        $slugs = QuickAction::query()
            ->whereNotNull('industry')
            ->distinct()
            ->pluck('industry');

        foreach ($slugs as $slug) {
            $templateName = (self::INDUSTRY_NAMES[$slug] ?? $slug) . '模版';

            $template = MenuTemplate::query()->firstOrCreate(
                ['industry' => $slug],
                ['name' => $templateName, 'is_active' => true, 'sort_order' => 0],
            );

            // 已存在但名称还是默认值时，补更新
            if ($template->name === '默认模版') {
                $template->update(['name' => $templateName]);
            }

            // 该行业还没有任何生效模版时，把默认模版设为生效
            $hasActive = MenuTemplate::query()
                ->where('industry', $slug)
                ->where('is_active', true)
                ->exists();
            if (! $hasActive) {
                $template->update(['is_active' => true]);
            }

            // 该行业「未归模版」的按钮挂到默认模版
            QuickAction::query()
                ->where('industry', $slug)
                ->whereNull('menu_template_id')
                ->update(['menu_template_id' => $template->id]);
        }
    }
}
