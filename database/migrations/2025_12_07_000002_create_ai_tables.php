<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // AI Conversations (session tracking)
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('session_id', 64);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'session_id'], 'idx_user_session');
            $table->index('last_message_at', 'idx_last_message');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // AI Messages (conversation history)
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->json('citations')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->enum('feedback', ['positive', 'negative'])->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('conversation_id', 'idx_conversation');
            $table->foreign('conversation_id')->references('id')->on('ai_conversations')->onDelete('cascade');
        });

        // AI Index Status (tracking what's indexed in vector store)
        Schema::create('ai_index_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('entity_id');
            $table->string('entity_type', 50);
            $table->unsignedInteger('revision_id')->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->timestamp('indexed_at')->nullable();
            $table->enum('index_status', ['pending', 'indexing', 'indexed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'entity_type'], 'idx_entity');
            $table->index('index_status', 'idx_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_index_status');
    }
};

