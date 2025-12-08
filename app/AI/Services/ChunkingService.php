<?php

namespace BookStack\AI\Services;

use BookStack\Entities\Models\Page;

/**
 * Service for chunking document content for vector embedding.
 */
class ChunkingService
{
    private int $chunkSize;
    private int $chunkOverlap;

    public function __construct()
    {
        $this->chunkSize = config('ai.chunking.chunk_size', 500);
        $this->chunkOverlap = config('ai.chunking.chunk_overlap', 50);
    }

    /**
     * Chunk a page into smaller pieces for embedding.
     *
     * @return array<int, array{text: string, metadata: array}>
     */
    public function chunkPage(Page $page): array
    {
        $cleanText = $this->extractCleanText($page);
        $chunks = $this->splitIntoChunks($cleanText);

        return array_map(function ($chunk, $index) use ($page) {
            return [
                'text' => $chunk,
                'metadata' => $this->buildMetadata($page, $index),
            ];
        }, $chunks, array_keys($chunks));
    }

    /**
     * Extract clean text from a page, stripping HTML.
     */
    private function extractCleanText(Page $page): string
    {
        // Strip HTML tags
        $text = strip_tags($page->html);

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Prepend the title for context
        return "# {$page->name}\n\n" . trim($text);
    }

    /**
     * Split text into overlapping chunks based on token estimates.
     *
     * @return array<int, string>
     */
    private function splitIntoChunks(string $text): array
    {
        // If text is empty, return empty array
        if (empty(trim($text))) {
            return [];
        }

        $words = explode(' ', $text);
        $chunks = [];
        $currentChunk = [];
        $tokenCount = 0;

        foreach ($words as $word) {
            // Estimate tokens (roughly 4 characters per token)
            $wordTokens = (int) ceil(strlen($word) / 4);

            // Check if adding this word exceeds chunk size
            if ($tokenCount + $wordTokens > $this->chunkSize && !empty($currentChunk)) {
                // Save current chunk
                $chunks[] = implode(' ', $currentChunk);

                // Keep overlap words for next chunk
                $overlapWordCount = (int) ($this->chunkOverlap / 4);
                $overlapWords = array_slice($currentChunk, -$overlapWordCount);
                $currentChunk = $overlapWords;
                $tokenCount = (int) ceil(strlen(implode(' ', $currentChunk)) / 4);
            }

            $currentChunk[] = $word;
            $tokenCount += $wordTokens;
        }

        // Add the final chunk
        if (!empty($currentChunk)) {
            $chunks[] = implode(' ', $currentChunk);
        }

        return $chunks;
    }

    /**
     * Build metadata for a chunk.
     * Pinecone requires all metadata values to be non-null.
     */
    private function buildMetadata(Page $page, int $chunkIndex): array
    {
        $book = $page->book;
        $shelf = $book?->shelves()->first();

        // Get the approved revision if available
        $approvedRevision = null;
        if ($page->approved_revision_id) {
            $approvedRevision = $page->approvedRevision;
        }

        $metadata = [
            'entity_id' => $page->id,
            'entity_type' => 'page',
            'title' => $page->name,
            'department' => $shelf?->name ?? 'General',
            'chunk_index' => $chunkIndex,
            'updated_at' => $page->updated_at->toISOString(),
        ];

        // Only add optional fields if they have values (Pinecone rejects nulls)
        if ($book?->id) {
            $metadata['book_id'] = $book->id;
        }
        if ($shelf?->id) {
            $metadata['shelf_id'] = $shelf->id;
        }
        if ($approvedRevision?->id) {
            $metadata['revision_id'] = $approvedRevision->id;
        }
        if ($approvedRevision?->approved_at) {
            $metadata['approved_at'] = $approvedRevision->approved_at->toISOString();
        }

        return $metadata;
    }

    /**
     * Get the estimated token count for a text.
     */
    public function estimateTokens(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }
}

