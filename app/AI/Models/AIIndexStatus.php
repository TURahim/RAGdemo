<?php

namespace BookStack\AI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI Index Status for tracking indexed entities in vector store.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $entity_type
 * @property int|null $revision_id
 * @property int $chunk_count
 * @property \Carbon\Carbon|null $indexed_at
 * @property string $index_status
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AIIndexStatus extends Model
{
    protected $table = 'ai_index_status';

    protected $fillable = [
        'entity_id',
        'entity_type',
        'revision_id',
        'chunk_count',
        'indexed_at',
        'index_status',
        'error_message',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'revision_id' => 'integer',
        'chunk_count' => 'integer',
        'indexed_at' => 'datetime',
    ];

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_INDEXING = 'indexing';
    public const STATUS_INDEXED = 'indexed';
    public const STATUS_FAILED = 'failed';

    /**
     * Check if this entity is successfully indexed.
     */
    public function isIndexed(): bool
    {
        return $this->index_status === self::STATUS_INDEXED;
    }

    /**
     * Check if indexing is in progress.
     */
    public function isIndexing(): bool
    {
        return $this->index_status === self::STATUS_INDEXING;
    }

    /**
     * Check if indexing failed.
     */
    public function hasFailed(): bool
    {
        return $this->index_status === self::STATUS_FAILED;
    }

    /**
     * Mark as indexing started.
     */
    public function markIndexing(): void
    {
        $this->update([
            'index_status' => self::STATUS_INDEXING,
            'error_message' => null,
        ]);
    }

    /**
     * Mark as successfully indexed.
     */
    public function markIndexed(int $chunkCount, ?int $revisionId = null): void
    {
        $this->update([
            'index_status' => self::STATUS_INDEXED,
            'chunk_count' => $chunkCount,
            'revision_id' => $revisionId,
            'indexed_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark as failed with error message.
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'index_status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Find or create status for an entity.
     */
    public static function forEntity(int $entityId, string $entityType): self
    {
        return static::firstOrCreate(
            ['entity_id' => $entityId, 'entity_type' => $entityType],
            ['index_status' => self::STATUS_PENDING]
        );
    }
}

