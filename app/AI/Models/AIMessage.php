<?php

namespace BookStack\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI Message in a conversation.
 *
 * @property int $id
 * @property int $conversation_id
 * @property string $role
 * @property string $content
 * @property array|null $citations
 * @property float|null $confidence
 * @property string|null $feedback
 * @property int|null $latency_ms
 * @property \Carbon\Carbon $created_at
 */
class AIMessage extends Model
{
    protected $table = 'ai_messages';

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'citations',
        'confidence',
        'feedback',
        'latency_ms',
    ];

    protected $casts = [
        'citations' => 'array',
        'confidence' => 'float',
        'latency_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AIConversation::class, 'conversation_id');
    }

    /**
     * Check if this is a user message.
     */
    public function isUserMessage(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if this is an assistant message.
     */
    public function isAssistantMessage(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Check if this message has citations.
     */
    public function hasCitations(): bool
    {
        return !empty($this->citations);
    }

    /**
     * Set positive feedback.
     */
    public function markPositive(): void
    {
        $this->update(['feedback' => 'positive']);
    }

    /**
     * Set negative feedback.
     */
    public function markNegative(): void
    {
        $this->update(['feedback' => 'negative']);
    }
}

