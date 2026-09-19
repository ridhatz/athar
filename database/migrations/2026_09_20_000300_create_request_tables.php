<?php

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Atomic sequences (request numbers). Locked row-by-row inside the creating transaction.
        Schema::create('counters', function (Blueprint $table) {
            $table->string('name', 50)->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        DB::table('counters')->insert(['name' => 'request_number', 'value' => 0]);

        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 20)->unique(); // ATH-000001, never changes
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('request_categories')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->string('priority', 10)->default(RequestPriority::Normal->value);
            $table->timestamp('deadline');
            $table->string('status', 20)->default(RequestStatus::New->value);
            // Where BLOCKED resumes to. Only set while status = BLOCKED.
            $table->string('blocked_from_status', 20)->nullable();
            $table->foreignId('assigned_team_id')->nullable()->constrained('teams')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); // archival only; there is no delete endpoint

            $table->index('status');
            $table->index('priority');
            $table->index('deadline');
            $table->index(['assigned_team_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
            $table->index(['requester_id', 'status']);
            $table->index('category_id');
            $table->index('department_id');
        });

        $statuses = $this->quoted(RequestStatus::values());
        $priorities = $this->quoted(RequestPriority::values());
        DB::statement("ALTER TABLE requests ADD CONSTRAINT requests_status_check CHECK (status IN ({$statuses}))");
        DB::statement("ALTER TABLE requests ADD CONSTRAINT requests_priority_check CHECK (priority IN ({$priorities}))");

        // Assignment history. Rows are closed (ended_at), never overwritten.
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            // 1 while current, NULL once closed. The unique index therefore allows at most
            // one current assignment per request, enforced by the database itself.
            $table->boolean('is_current')->nullable();
            $table->unique(['request_id', 'is_current']);
            $table->index('user_id');
            $table->index('team_id');
        });

        // Append-only audit trail. See the next migration for the database-level guard.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete(); // null = system
            $table->string('action', 40);
            $table->string('field', 40)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_internal')->default(false); // requesters never see internal rows
            $table->timestamp('created_at')->useCurrent();

            $table->index(['request_id', 'created_at']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('requests');
        Schema::dropIfExists('counters');
    }

    /** @param array<int, string> $values */
    private function quoted(array $values): string
    {
        return implode(',', array_map(fn (string $v) => "'{$v}'", $values));
    }
};
