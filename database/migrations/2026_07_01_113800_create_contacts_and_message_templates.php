<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contacts')) {
            Schema::create('contacts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('organization')->nullable();
                $table->string('contact_person')->nullable();
                $table->string('email')->nullable();
                $table->string('whatsapp_number', 40)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('type')->default('external');
                $table->text('notes')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['is_active', 'type']);
                $table->index('email');
                $table->index('whatsapp_number');
            });
        }

        if (!Schema::hasTable('message_templates')) {
            Schema::create('message_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('channel')->default('both');
                $table->string('subject_template')->nullable();
                $table->longText('body_template');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['channel', 'is_active']);
                $table->index('is_default');
            });
        }

        if (Schema::hasTable('email_messages')) {
            Schema::table('email_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('email_messages', 'contact_id')) {
                    $table->foreignId('contact_id')->nullable()->after('created_by')->constrained('contacts')->nullOnDelete();
                }

                if (!Schema::hasColumn('email_messages', 'message_template_id')) {
                    $table->foreignId('message_template_id')->nullable()->after('contact_id')->constrained('message_templates')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('whatsapp_messages', 'contact_id')) {
                    $table->foreignId('contact_id')->nullable()->after('created_by')->constrained('contacts')->nullOnDelete();
                }

                if (!Schema::hasColumn('whatsapp_messages', 'message_template_id')) {
                    $table->foreignId('message_template_id')->nullable()->after('contact_id')->constrained('message_templates')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('message_templates') && DB::table('message_templates')->count() === 0) {
            DB::table('message_templates')->insert([
                [
                    'name' => 'قالب رسمي لإرسال كتاب',
                    'channel' => 'both',
                    'subject_template' => 'إرسال كتاب رقم {document_number} - {subject}',
                    'body_template' => "السلام عليكم ورحمة الله وبركاته،\n\nنرفق/نرسل لكم بيانات الكتاب التالي:\n\nرقم الكتاب: {document_number}\nتاريخ الكتاب: {document_date}\nالموضوع: {subject}\nالإدارة: {department}\nنوع الكتاب: {document_type}\nالمرسل: {sender}\nالمستلم: {receiver}\n\nعدد المرفقات المسجلة في النظام: {attachments_count}\n\nمع التحية،\n{department_name}",
                    'sort_order' => 10,
                    'is_default' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'قالب طلب متابعة',
                    'channel' => 'both',
                    'subject_template' => 'متابعة بشأن الكتاب رقم {document_number}',
                    'body_template' => "السلام عليكم ورحمة الله وبركاته،\n\nنأمل منكم التكرم بمراجعة الكتاب التالي وإفادتنا بما يلزم:\n\nرقم الكتاب: {document_number}\nالتاريخ: {document_date}\nالموضوع: {subject}\n\nمع التحية،",
                    'sort_order' => 20,
                    'is_default' => false,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'قالب واتساب مختصر',
                    'channel' => 'whatsapp',
                    'subject_template' => null,
                    'body_template' => "السلام عليكم\n\nنرسل لكم بيانات الكتاب:\nرقم الكتاب: {document_number}\nالتاريخ: {document_date}\nالموضوع: {subject}\n\nمع التحية.",
                    'sort_order' => 30,
                    'is_default' => false,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                if (Schema::hasColumn('whatsapp_messages', 'message_template_id')) {
                    $table->dropConstrainedForeignId('message_template_id');
                }
                if (Schema::hasColumn('whatsapp_messages', 'contact_id')) {
                    $table->dropConstrainedForeignId('contact_id');
                }
            });
        }

        if (Schema::hasTable('email_messages')) {
            Schema::table('email_messages', function (Blueprint $table) {
                if (Schema::hasColumn('email_messages', 'message_template_id')) {
                    $table->dropConstrainedForeignId('message_template_id');
                }
                if (Schema::hasColumn('email_messages', 'contact_id')) {
                    $table->dropConstrainedForeignId('contact_id');
                }
            });
        }

        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('contacts');
    }
};
