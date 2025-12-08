<?php

namespace BookStack\AI\Models;

use BookStack\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AI Conversation session tracking.
 *
 * @property int $id
 * @property int $user_id
 * @property string $session_id
 * @property \Carbon\Carbon $started_at
 * @property \Carbon\Carbon|null $last_message_at
 * @property int $message_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AIConversation extends Model
{
    protected $table = 'ai_conversations';

    protected $fillable = [
        'user_id',
        'session_id',
        'started_at',
        'last_message_at',
        'message_count',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_message_at' => 'datetime',
        'message_count' => 'integer',
    ];

    /**
     * Get the user that owns this conversation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the messages in this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AIMessage::class, 'conversation_id')->orderBy('created_at');
    }

    /**
     * Get the latest message in this conversation.
     */
    public function latestMessage(): HasMany
    {
        return $this->hasMany(AIMessage::class, 'conversation_id')->latest('created_at')->limit(1);
    }

    /**
     * Increment the message count.
     */
    public function incrementMessageCount(int $count = 1): void
    {
        $this->increment('message_count', $count);
        $this->update(['last_message_at' => now()]);
    }
}

