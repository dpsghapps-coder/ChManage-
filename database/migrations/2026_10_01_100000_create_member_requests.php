<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests members make from the portal: a change to their details (applied only when approved), a transfer, an issue,
 * a course, a sacrament, the bus or a facility. What was asked is kept as `details` (the answers) or, for a change to
 * the member's record, as `changes` (field => from/to).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('member_id');
            $table->string('type', 30);
            $table->string('status', 20)->default('submitted');
            $table->json('details')->nullable();
            $table->json('changes')->nullable();
            $table->text('member_note')->nullable();
            $table->text('response')->nullable()->comment('The reply shown to the member');
            $table->unsignedInteger('handled_by')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('handled_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_requests');
    }
};
