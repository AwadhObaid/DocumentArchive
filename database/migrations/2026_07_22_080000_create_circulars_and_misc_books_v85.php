<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('archive_categories')) {
            Schema::create('archive_categories', function (Blueprint $table) {
                $table->id();
                $table->string('module', 50);
                $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained('archive_categories')
                    ->nullOnDelete();
                $table->string('name');
                $table->string('code', 100);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['module', 'code']);
                $table->index(['module', 'is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('circular_counters')) {
            Schema::create('circular_counters', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('year')->unique();
                $table->unsignedBigInteger('start_number');
                $table->integer('last_sequence')->default(-1);
                $table->string('last_circular_number')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circulars')) {
            Schema::create('circulars', function (Blueprint $table) {
                $table->id();
                $table->string('circular_number')->unique();
                $table->unsignedSmallInteger('circular_year');
                $table->unsignedInteger('circular_sequence')->default(0);
                $table->string('original_number')->nullable();
                $table->date('circular_date');
                $table->text('subject');
                $table->foreignId('category_id')
                    ->nullable()
                    ->constrained('archive_categories')
                    ->nullOnDelete();
                $table->string('issuing_entity')->nullable();
                $table->string('scope')->nullable();
                $table->date('effective_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('status')->default('active');
                $table->string('workflow_status')->default('draft');
                $table->text('workflow_note')->nullable();
                $table->foreignId('workflow_submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('workflow_submitted_at')->nullable();
                $table->foreignId('workflow_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('workflow_reviewed_at')->nullable();
                $table->foreignId('workflow_finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('workflow_finalized_at')->nullable();
                $table->string('confidentiality')->default('normal');
                $table->string('priority')->default('normal');
                $table->text('keywords')->nullable();
                $table->text('notes')->nullable();
                $table->longText('search_text')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();

                $table->index(['circular_year', 'circular_sequence']);
                $table->index(['status', 'workflow_status']);
                $table->index(['circular_date', 'category_id']);
            });
        }

        if (! Schema::hasTable('circular_attachments')) {
            Schema::create('circular_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('circular_id')->constrained('circulars')->cascadeOnDelete();
                $table->unsignedInteger('version_no')->default(1);
                $table->boolean('is_main')->default(true);
                $table->string('original_name');
                $table->string('file_name');
                $table->text('file_path');
                $table->string('disk')->default('local');
                $table->string('extension', 30)->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->char('sha256', 64)->nullable();
                $table->text('source_path')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('deletion_reason')->nullable();
                $table->foreignId('replaces_attachment_id')->nullable()->constrained('circular_attachments')->nullOnDelete();
                $table->foreignId('replaced_by_attachment_id')->nullable()->constrained('circular_attachments')->nullOnDelete();
                $table->text('replacement_reason')->nullable();
                $table->timestamp('replaced_at')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index(['circular_id', 'version_no']);
                $table->index('sha256');
            });
        }

        if (! Schema::hasTable('misc_book_counters')) {
            Schema::create('misc_book_counters', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('year')->unique();
                $table->unsignedBigInteger('start_number');
                $table->integer('last_sequence')->default(-1);
                $table->string('last_misc_book_number')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('misc_books')) {
            Schema::create('misc_books', function (Blueprint $table) {
                $table->id();
                $table->string('misc_number')->unique();
                $table->unsignedSmallInteger('misc_year');
                $table->unsignedInteger('misc_sequence')->default(0);
                $table->string('original_number')->nullable();
                $table->date('book_date');
                $table->text('subject');
                $table->foreignId('category_id')
                    ->nullable()
                    ->constrained('archive_categories')
                    ->nullOnDelete();
                $table->string('correspondence_direction')->default('incoming');
                $table->string('nature')->default('unspecified');
                $table->string('sender')->nullable();
                $table->string('receiver')->nullable();
                $table->string('employee_name')->nullable();
                $table->string('authority_name')->nullable();
                $table->string('status')->default('active');
                $table->string('workflow_status')->default('draft');
                $table->text('workflow_note')->nullable();
                $table->foreignId('workflow_submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('workflow_submitted_at')->nullable();
                $table->foreignId('workflow_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('workflow_reviewed_at')->nullable();
                $table->foreignId('workflow_finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('workflow_finalized_at')->nullable();
                $table->string('confidentiality')->default('normal');
                $table->string('priority')->default('normal');
                $table->text('keywords')->nullable();
                $table->text('notes')->nullable();
                $table->longText('search_text')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();

                $table->index(['misc_year', 'misc_sequence']);
                $table->index(['status', 'workflow_status']);
                $table->index(['book_date', 'category_id']);
                $table->index(['correspondence_direction', 'nature']);
            });
        }

        if (! Schema::hasTable('misc_book_attachments')) {
            Schema::create('misc_book_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('misc_book_id')->constrained('misc_books')->cascadeOnDelete();
                $table->unsignedInteger('version_no')->default(1);
                $table->boolean('is_main')->default(true);
                $table->string('original_name');
                $table->string('file_name');
                $table->text('file_path');
                $table->string('disk')->default('local');
                $table->string('extension', 30)->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->char('sha256', 64)->nullable();
                $table->text('source_path')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('deletion_reason')->nullable();
                $table->foreignId('replaces_attachment_id')->nullable()->constrained('misc_book_attachments')->nullOnDelete();
                $table->foreignId('replaced_by_attachment_id')->nullable()->constrained('misc_book_attachments')->nullOnDelete();
                $table->text('replacement_reason')->nullable();
                $table->timestamp('replaced_at')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index(['misc_book_id', 'version_no']);
                $table->index('sha256');
            });
        }

        $now = now();

        $categories = [
            ['module' => 'circular', 'parent_code' => null, 'name' => 'إداري', 'code' => 'administrative', 'sort_order' => 10],
            ['module' => 'circular', 'parent_code' => null, 'name' => 'مالي', 'code' => 'financial', 'sort_order' => 20],
            ['module' => 'circular', 'parent_code' => null, 'name' => 'موارد بشرية', 'code' => 'human-resources', 'sort_order' => 30],
            ['module' => 'circular', 'parent_code' => null, 'name' => 'أمني', 'code' => 'security', 'sort_order' => 40],
            ['module' => 'circular', 'parent_code' => null, 'name' => 'تشغيلي', 'code' => 'operational', 'sort_order' => 50],
            ['module' => 'circular', 'parent_code' => null, 'name' => 'أخرى', 'code' => 'other', 'sort_order' => 90],

            ['module' => 'misc_book', 'parent_code' => null, 'name' => 'كتب الموظفين', 'code' => 'employee-books', 'sort_order' => 10],
            ['module' => 'misc_book', 'parent_code' => null, 'name' => 'كتب الهيئات', 'code' => 'authority-books', 'sort_order' => 20],
            ['module' => 'misc_book', 'parent_code' => null, 'name' => 'مخاطبات أخرى', 'code' => 'other-correspondence', 'sort_order' => 30],
        ];

        foreach ($categories as $category) {
            DB::table('archive_categories')->updateOrInsert(
                ['module' => $category['module'], 'code' => $category['code']],
                [
                    'parent_id' => null,
                    'name' => $category['name'],
                    'is_active' => true,
                    'sort_order' => $category['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $otherParentId = DB::table('archive_categories')
            ->where('module', 'misc_book')
            ->where('code', 'other-correspondence')
            ->value('id');

        foreach ([
            ['name' => 'مخاطبات عسكرية', 'code' => 'military', 'sort_order' => 10],
            ['name' => 'مخاطبات مدنية', 'code' => 'civil', 'sort_order' => 20],
        ] as $child) {
            DB::table('archive_categories')->updateOrInsert(
                ['module' => 'misc_book', 'code' => $child['code']],
                [
                    'parent_id' => $otherParentId,
                    'name' => $child['name'],
                    'is_active' => true,
                    'sort_order' => $child['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('misc_book_attachments');
        Schema::dropIfExists('misc_books');
        Schema::dropIfExists('misc_book_counters');
        Schema::dropIfExists('circular_attachments');
        Schema::dropIfExists('circulars');
        Schema::dropIfExists('circular_counters');
        Schema::dropIfExists('archive_categories');
    }
};
