<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('book_attachment_companies')) {
            Schema::create('book_attachment_companies', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code')->nullable()->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
                $table->index('sort_order');
            });
        }

        if (! Schema::hasTable('book_attachment_operations')) {
            Schema::create('book_attachment_operations', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code')->nullable()->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
                $table->index('sort_order');
            });
        }

        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (! Schema::hasColumn('documents', 'attachment_company_name')) {
                    $table->string('attachment_company_name')->nullable()->after('book_subject_id');
                }

                if (! Schema::hasColumn('documents', 'attachment_category_name')) {
                    $table->string('attachment_category_name')->nullable()->after('attachment_company_name');
                }
            });
        }

        if (Schema::hasTable('documents')) {
            try {
                Schema::table('documents', function (Blueprint $table) {
                    if (Schema::hasColumn('documents', 'attachment_company_name')) {
                        $table->index('attachment_company_name', 'documents_attachment_company_name_index');
                    }

                    if (Schema::hasColumn('documents', 'attachment_category_name')) {
                        $table->index('attachment_category_name', 'documents_attachment_category_name_index');
                    }
                });
            } catch (\Throwable $e) {
                // Indexes may already exist on some local copies. Do not block the migration.
            }
        }

        if (Schema::hasTable('document_attachments')) {
            Schema::table('document_attachments', function (Blueprint $table) {
                if (! Schema::hasColumn('document_attachments', 'classification_company_name')) {
                    $table->string('classification_company_name')->nullable()->after('disk');
                }

                if (! Schema::hasColumn('document_attachments', 'classification_operation_name')) {
                    $table->string('classification_operation_name')->nullable()->after('classification_company_name');
                }

                if (! Schema::hasColumn('document_attachments', 'classification_year')) {
                    $table->unsignedInteger('classification_year')->nullable()->after('classification_operation_name');
                }

                if (! Schema::hasColumn('document_attachments', 'classification_folder')) {
                    $table->string('classification_folder')->nullable()->after('classification_year');
                }
            });
        }

        $this->seedDefaults();
    }

    public function down(): void
    {
        if (Schema::hasTable('document_attachments')) {
            Schema::table('document_attachments', function (Blueprint $table) {
                foreach (['classification_folder', 'classification_year', 'classification_operation_name', 'classification_company_name'] as $column) {
                    if (Schema::hasColumn('document_attachments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('documents')) {
            try {
                Schema::table('documents', function (Blueprint $table) {
                    $table->dropIndex('documents_attachment_company_name_index');
                    $table->dropIndex('documents_attachment_category_name_index');
                });
            } catch (\Throwable $e) {
                // Ignore missing indexes.
            }

            Schema::table('documents', function (Blueprint $table) {
                foreach (['attachment_category_name', 'attachment_company_name'] as $column) {
                    if (Schema::hasColumn('documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('book_attachment_operations');
        Schema::dropIfExists('book_attachment_companies');
    }

    private function seedDefaults(): void
    {
        $now = now();

        $companies = [
            'DHL EXPRESS',
            'DHL GLOBAL',
            'EXPEDITORS INTERNATIONAL',
            'FEDEX EXPRESS',
            'UPS',
            'TNT',
            'BWL SHIPPING COMPANY',
            'AL Rashed International Shipping',
            'GULF AGENCY COMPANY',
            'FRONTLINE LOGISTICS',
            'GLOBAL FREIGHT SYSTEMS CO.W.L.L',
            'كونا ناجل',
            'الجمارك العامة',
            'الموانئ الشمالية',
            'الموانئ الجنوبية شعيبة',
        ];

        foreach ($companies as $index => $name) {
            DB::table('book_attachment_companies')->updateOrInsert(
                ['name' => $name],
                ['sort_order' => ($index + 1) * 10, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $operations = [
            'إفراج جمركي',
            'تصدير شحنة',
            'شحن بري',
            'استلام مباشر',
            'استلام وتسليم بوالص الشحن',
            'تعديل بوليصة شحن',
            'تفويض وإستلام شحنة',
            'حقيبة برفقة راكب',
        ];

        foreach ($operations as $index => $name) {
            DB::table('book_attachment_operations')->updateOrInsert(
                ['name' => $name],
                ['sort_order' => ($index + 1) * 10, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }
};
