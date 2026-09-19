<?php

use App\Enums\AttachmentKind;
use App\Enums\ReviewDecision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The discussion thread. Internal notes are separate from what the requester sees.
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['request_id', 'created_at']);
        });

        // Files are never replaced or deleted: a new upload of the same name becomes the next version.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('comments')->restrictOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('original_name', 255);
            $table->string('stored_path', 255)->unique(); // never derived from user input
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_internal')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['request_id', 'original_name']);
        });

        // One row per time the work is submitted for review, so every revision round stays on record.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->unsignedSmallInteger('round');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('decision', 20)->default(ReviewDecision::Pending->value);
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unique(['request_id', 'round']);
        });

        $kinds = implode(',', array_map(fn ($v) => "'{$v}'", AttachmentKind::values()));
        $decisions = implode(',', array_map(fn ($v) => "'{$v}'", ReviewDecision::values()));
        DB::statement("ALTER TABLE attachments ADD CONSTRAINT attachments_kind_check CHECK (kind IN ({$kinds}))");
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_decision_check CHECK (decision IN ({$decisions}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('comments');
    }
};
