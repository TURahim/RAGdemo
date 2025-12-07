<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SOP Approval Workflow Migration
 * 
 * Adds approval status tracking to page_revisions table.
 * This enables revision-based approval (not page-level) for compliance.
 * 
 * Status flow: draft → in_review → approved (or rejected back to draft)
 * 
 * Note: BookStack 2025 uses unified `entities` + `entity_page_data` tables
 * instead of the old `pages` table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add approval fields to page_revisions
        Schema::table('page_revisions', function (Blueprint $table) {
            // Approval status: draft (default), in_review, approved, rejected
            $table->string('status', 20)->default('draft')->after('summary');
            
            // Who approved this revision
            $table->unsignedInteger('approved_by')->nullable()->after('status');
            
            // When was it approved
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            
            // Optional: rejection reason / review notes
            $table->text('review_notes')->nullable()->after('approved_at');
            
            // Index for filtering by status
            $table->index('status');
            
            // Foreign key to users table (soft reference - don't cascade delete)
            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // Add approval tracking to entity_page_data (replaces old pages table)
        Schema::table('entity_page_data', function (Blueprint $table) {
            // Points to the currently approved revision (null = no approved version)
            $table->unsignedInteger('approved_revision_id')->nullable()->after('editor');
            
            // Review scheduling
            $table->date('next_review_date')->nullable()->after('approved_revision_id');
            $table->unsignedSmallInteger('review_interval_days')->nullable()->after('next_review_date');
            
            // Foreign key to page_revisions (soft reference)
            $table->foreign('approved_revision_id')
                ->references('id')
                ->on('page_revisions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_page_data', function (Blueprint $table) {
            $table->dropForeign(['approved_revision_id']);
            $table->dropColumn(['approved_revision_id', 'next_review_date', 'review_interval_days']);
        });

        Schema::table('page_revisions', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'approved_by', 'approved_at', 'review_notes']);
        });
    }
};

