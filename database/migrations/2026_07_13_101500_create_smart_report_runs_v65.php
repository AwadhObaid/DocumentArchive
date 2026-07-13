<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('smart_report_runs')) {
            Schema::create('smart_report_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('date_from')->nullable();
                $table->date('date_to')->nullable();
                $table->string('report_type', 80);
                $table->string('title')->nullable();
                $table->string('status', 30)->default('completed');
                $table->string('model', 100)->nullable();
                $table->longText('prompt')->nullable();
                $table->json('payload_json')->nullable();
                $table->longText('result_text')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['report_type', 'created_at']);
                $table->index(['date_from', 'date_to']);
                $table->index('status');
            });
        }

        if (Schema::hasTable('settings')) {
            $now = now();

            $defaults = [
                [
                    'key' => 'smart_reports_enabled',
                    'value' => '0',
                    'group' => 'smart_reports',
                    'type' => 'boolean',
                    'description' => 'تفعيل التقارير الذكية عبر Gemini API.',
                ],
                [
                    'key' => 'smart_reports_gemini_api_key',
                    'value' => '',
                    'group' => 'smart_reports',
                    'type' => 'password',
                    'description' => 'Gemini API Key محفوظ بشكل مشفر عند إدخاله من الإعدادات.',
                ],
                [
                    'key' => 'smart_reports_gemini_model',
                    'value' => 'gemini-3.5-flash',
                    'group' => 'smart_reports',
                    'type' => 'text',
                    'description' => 'موديل Gemini المستخدم في توليد التقارير الذكية.',
                ],
                [
                    'key' => 'smart_reports_include_titles',
                    'value' => '0',
                    'group' => 'smart_reports',
                    'type' => 'boolean',
                    'description' => 'إرسال عناوين ومواضيع عينة من الكتب إلى Gemini. يفضل تركه غير مفعل للبيانات الحساسة.',
                ],
            ];

            foreach ($defaults as $setting) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $setting['key']],
                    array_merge($setting, [
                        'updated_at' => $now,
                        'created_at' => $now,
                    ])
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_report_runs');

        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->whereIn('key', [
                    'smart_reports_enabled',
                    'smart_reports_gemini_api_key',
                    'smart_reports_gemini_model',
                    'smart_reports_include_titles',
                ])
                ->delete();
        }
    }
};
