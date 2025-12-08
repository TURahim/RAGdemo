<?php

namespace BookStack\Console\Commands;

use BookStack\AI\Jobs\IndexEntityJob;
use BookStack\AI\Models\AIIndexStatus;
use BookStack\Entities\Models\Page;
use Illuminate\Console\Command;

/**
 * CLI command for indexing content for AI search.
 */
class AIIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:index
        {--all : Reindex all approved pages}
        {--entity= : Index specific entity (format: page:123)}
        {--status : Show indexing status}
        {--dry-run : Show what would be indexed without indexing}';

    /**
     * The console command description.
     */
    protected $description = 'Index content for AI search';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('ai.enabled')) {
            $this->error('AI features are disabled. Set AI_ENABLED=true in your .env file');
            return 1;
        }

        if ($this->option('status')) {
            return $this->showStatus();
        }

        if ($this->option('entity')) {
            return $this->indexSingle($this->option('entity'));
        }

        if ($this->option('all')) {
            return $this->indexAll();
        }

        $this->showUsage();
        return 0;
    }

    /**
     * Show usage information.
     */
    private function showUsage(): void
    {
        $this->info('AI Content Indexing Command');
        $this->line('');
        $this->line('Usage:');
        $this->line('  php artisan ai:index --all            Index all approved pages');
        $this->line('  php artisan ai:index --entity=page:123  Index a specific page');
        $this->line('  php artisan ai:index --status         Show indexing status');
        $this->line('  php artisan ai:index --dry-run --all  Preview what would be indexed');
    }

    /**
     * Index a single entity.
     */
    private function indexSingle(string $entitySpec): int
    {
        $parts = explode(':', $entitySpec);
        if (count($parts) !== 2) {
            $this->error('Invalid entity format. Use: page:123');
            return 1;
        }

        [$type, $id] = $parts;

        if ($type !== 'page') {
            $this->error("Unsupported entity type: {$type}. Only 'page' is currently supported.");
            return 1;
        }

        $page = Page::find($id);
        if (!$page) {
            $this->error("Page {$id} not found");
            return 1;
        }

        if (!$page->hasApprovedRevision()) {
            $this->warn("Page '{$page->name}' has no approved revision");
            if (!$this->confirm('Index anyway? (may have no content)')) {
                return 0;
            }
        }

        if ($this->option('dry-run')) {
            $this->info("Would index: {$page->name} (ID: {$page->id})");
            return 0;
        }

        IndexEntityJob::dispatch((int) $id, 'page');
        $this->info("Dispatched index job for: {$page->name}");
        return 0;
    }

    /**
     * Index all approved pages.
     */
    private function indexAll(): int
    {
        // Get all pages with approved revisions
        $pages = Page::whereNotNull('approved_revision_id')->get();

        $this->info("Found {$pages->count()} pages with approved revisions");

        if ($pages->isEmpty()) {
            $this->warn('No pages with approved revisions found.');
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Name', 'Book', 'Last Updated'],
                $pages->map(fn ($p) => [
                    $p->id,
                    $p->name,
                    $p->book?->name ?? 'N/A',
                    $p->updated_at->format('Y-m-d H:i'),
                ])
            );
            return 0;
        }

        if (!$this->confirm("Dispatch indexing jobs for {$pages->count()} pages?")) {
            return 0;
        }

        $bar = $this->output->createProgressBar($pages->count());
        $bar->start();

        foreach ($pages as $page) {
            IndexEntityJob::dispatch($page->id, 'page');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('All indexing jobs dispatched to queue');
        $this->line('Run queue worker to process: php artisan queue:work');

        return 0;
    }

    /**
     * Show indexing status.
     */
    private function showStatus(): int
    {
        $statuses = AIIndexStatus::orderBy('updated_at', 'desc')->limit(50)->get();

        if ($statuses->isEmpty()) {
            $this->info('No indexing records found.');
            return 0;
        }

        // Summary stats
        $counts = AIIndexStatus::selectRaw('index_status, COUNT(*) as count')
            ->groupBy('index_status')
            ->pluck('count', 'index_status')
            ->toArray();

        $this->info('Indexing Status Summary:');
        $this->line('  Indexed: ' . ($counts['indexed'] ?? 0));
        $this->line('  Pending: ' . ($counts['pending'] ?? 0));
        $this->line('  Indexing: ' . ($counts['indexing'] ?? 0));
        $this->line('  Failed: ' . ($counts['failed'] ?? 0));
        $this->newLine();

        // Recent records
        $this->info('Recent Index Records:');
        $this->table(
            ['Entity', 'Status', 'Chunks', 'Indexed At', 'Error'],
            $statuses->map(fn ($s) => [
                "{$s->entity_type}:{$s->entity_id}",
                $s->index_status,
                $s->chunk_count,
                $s->indexed_at?->format('Y-m-d H:i') ?? '-',
                $s->error_message ? substr($s->error_message, 0, 40) . '...' : '-',
            ])
        );

        return 0;
    }
}

