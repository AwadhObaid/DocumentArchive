<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FormLinksSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('form_links')) {
            return;
        }

        foreach ($this->links() as $link) {
            $existingId = DB::table('form_links')
                ->where('url', $link['url'])
                ->value('id');

            $payload = [
                'title' => $link['title'],
                'url' => $link['url'],
                'category' => $link['category'],
                'description' => $link['description'],
                'icon' => $link['icon'],
                'sort_order' => $link['sort_order'],
                'is_active' => true,
                'opens_new_tab' => true,
                'updated_at' => now(),
            ];

            if ($existingId) {
                DB::table('form_links')
                    ->where('id', $existingId)
                    ->update($payload);

                continue;
            }

            DB::table('form_links')->insert($payload + [
                'created_at' => now(),
            ]);
        }
    }

    private function links(): array
    {
        return [
            [
                'title' => 'نموذج استمارة كشف حضور وانصراف للموظفين العاملين بالمواقع الميدانية الثابتة وغير الثابتة',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/231000-40.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '📋',
                'sort_order' => 10,
            ],
            [
                'title' => 'نموذج طلب إستقالة / تقاعد',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/231000-06.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '📄',
                'sort_order' => 20,
            ],
            [
                'title' => 'طلب ترشيح لوظيفة إشرافية',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/230000-13.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '🎖️',
                'sort_order' => 30,
            ],
            [
                'title' => 'نموذج نقل وندب داخلي للهيئة الإدارية والمالية',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/231000-27.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '🔁',
                'sort_order' => 40,
            ],
            [
                'title' => 'نموذج إجازة وإقرار عودة',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/230000-12.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '🗓️',
                'sort_order' => 50,
            ],
            [
                'title' => 'نموذج طلب معاملة',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/230000-37.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '🧾',
                'sort_order' => 60,
            ],
            [
                'title' => 'نموذج طلب هوية',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/231000-12.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '🪪',
                'sort_order' => 70,
            ],
            [
                'title' => 'نموذج استمارة صرف بدلات',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/230000-43.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '💰',
                'sort_order' => 80,
            ],
            [
                'title' => 'تصريح خروج',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/231000-08%20.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '🚪',
                'sort_order' => 90,
            ],
            [
                'title' => 'إخلاء طرف عهدة شخصية / تنظيمية',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/240000-23.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '✅',
                'sort_order' => 100,
            ],
            [
                'title' => 'براءة ذمة لموظفي العقود الخاصة',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/232000-08.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '🧾',
                'sort_order' => 110,
            ],
            [
                'title' => 'نموذج علاج موظف',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/230000-41.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '🏥',
                'sort_order' => 120,
            ],
            [
                'title' => 'إشعار إجازة طارئة',
                'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/231000-26.htm',
                'category' => 'نماذج وزارة الدفاع',
                'description' => 'رابط نموذج رسمي من موقع وزارة الدفاع.',
                'icon' => '⚠️',
                'sort_order' => 130,
            ],
        ];
    }
}
