<?php

namespace BookStack\AI\Jobs;

use BookStack\AI\Models\AIIndexStatus;
use BookStack\AI\Services\ChunkingService;
use BookStack\Entities\Models\Page;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Queue job for indexing an entity into the vector store.
 */
class IndexEntityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    public function __construct(
        private int $entityId,
        private string $entityType = 'page'
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(ChunkingService $chunker): void
    {
        if (!config('ai.enabled')) {
            Log::info("AI features disabled, skipping index job for {$this->entityType}:{$this->entityId}");
            return;
        }

        // Get or create status record
        $status = AIIndexStatus::forEntity($this->entityId, $this->entityType);
        $status->markIndexing();

        try {
            $this->processEntity($chunker, $status);
        } catch (Exception $e) {
            $status->markFailed($e->getMessage());
            Log::error("AI index job failed for {$this->entityType}:{$this->entityId}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process the entity for indexing.
     */
    private function processEntity(ChunkingService $chunker, AIIndexStatus $status): void
    {
        // Currently only pages are supported
        if ($this->entityType !== 'page') {
            $status->markFailed("Unsupported entity type: {$this->entityType}");
            return;
        }

        $page = Page::with(['book', 'approvedRevision'])->find($this->entityId);

        if (!$page) {
            $status->markFailed('Page not found');
            return;
        }

        // Check for approved revision
        if (!$page->hasApprovedRevision()) {
            $status->update([
                'index_status' => AIIndexStatus::STATUS_PENDING,
                'error_message' => 'No approved revision - waiting for approval',
            ]);
            Log::info("Page {$this->entityId} has no approved revision, skipping indexing");
            return;
        }

        // Chunk the content
        $chunks = $chunker->chunkPage($page);

        if (empty($chunks)) {
            $status->markIndexed(0, $page->approved_revision_id);
            Log::info("Page {$this->entityId} has no content to index");
            return;
        }

        // Send to RAG service for embedding and storage
        $this->sendToRagService($chunks, $status, $page);
    }

    /**
     * Send chunks to the RAG service for embedding and vector storage.
     */
    private function sendToRagService(array $chunks, AIIndexStatus $status, Page $page): void
    {
        $ragServiceUrl = config('ai.rag_service.url');
        $timeout = config('ai.rag_service.timeout', 60);

        $response = Http::timeout($timeout)->post("{$ragServiceUrl}/index", [
            'entity_id' => $this->entityId,
            'entity_type' => $this->entityType,
            'chunks' => $chunks,
        ]);

        if (!$response->successful()) {
            throw new Exception('RAG service indexing failed: ' . $response->body());
        }

        $status->markIndexed(count($chunks), $page->approved_revision_id);

        Log::info("Successfully indexed {$this->entityType}:{$this->entityId}", [
            'chunk_count' => count($chunks),
            'revision_id' => $page->approved_revision_id,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error("AI IndexEntityJob permanently failed for {$this->entityType}:{$this->entityId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}

