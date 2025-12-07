<?php

namespace BookStack\Console\Commands;

use BookStack\Permissions\PermissionConsistencyService;
use Illuminate\Console\Command;

class AuditPermissionConsistencyCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bookstack:audit-permissions
                            {--fix : Automatically repair any inconsistencies found}
                            {--rebuild : Perform a full permission rebuild (heavy operation)}
                            {--dry-run : Show what would be fixed without making changes}
                            {--stats : Show permission statistics only}';

    /**
     * The console command description.
     */
    protected $description = 'Audit and optionally repair permission consistency across all entities';

    public function __construct(
        protected PermissionConsistencyService $consistencyService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Permission Consistency Audit');
        $this->line('=============================');
        $this->newLine();

        // Show statistics
        $stats = $this->consistencyService->getStatistics();
        $this->showStatistics($stats);

        // If only stats requested, stop here
        if ($this->option('stats')) {
            return 0;
        }

        // Full rebuild mode
        if ($this->option('rebuild')) {
            return $this->performFullRebuild();
        }

        // Audit mode
        if (!$stats['is_healthy']) {
            $this->newLine();
            $this->warn('⚠️  Permission inconsistencies detected!');
            $this->showIssueDetails($stats);

            if ($this->option('fix') && !$this->option('dry-run')) {
                return $this->performRepair();
            }

            if ($this->option('dry-run')) {
                $this->newLine();
                $this->info('Dry run mode - showing what would be fixed:');
                $this->showRepairPreview($stats);
                return 0;
            }

            $this->newLine();
            $this->info('Run with --fix to automatically repair issues, or --rebuild for a full rebuild.');
        } else {
            $this->newLine();
            $this->info('✅ Permission system is healthy. No issues found.');
        }

        return 0;
    }

    /**
     * Display permission statistics.
     */
    protected function showStatistics(array $stats): void
    {
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Entities', number_format($stats['total_entities'])],
                ['Total Roles', $stats['roles_count']],
                ['Expected Permissions', number_format($stats['expected_permissions'])],
                ['Actual Permissions', number_format($stats['total_permissions'])],
                ['Entities Without Permissions', $stats['entities_without_permissions']],
                ['Orphaned Permissions', $stats['orphaned_permissions']],
                ['Health Status', $stats['is_healthy'] ? '✅ Healthy' : '⚠️ Issues Found'],
            ]
        );
    }

    /**
     * Show details about the issues found.
     */
    protected function showIssueDetails(array $stats): void
    {
        if ($stats['entities_without_permissions'] > 0) {
            $this->newLine();
            $this->error("  {$stats['entities_without_permissions']} entities are missing permission entries");
            
            $orphanedEntities = $this->consistencyService->findEntitiesWithoutPermissions();
            if ($orphanedEntities->count() <= 20) {
                $this->line('  Affected entities:');
                foreach ($orphanedEntities as $entity) {
                    $this->line("    - [{$entity->getMorphClass()}] {$entity->name} (ID: {$entity->id})");
                }
            }
        }

        if ($stats['orphaned_permissions'] > 0) {
            $this->newLine();
            $this->error("  {$stats['orphaned_permissions']} orphaned permission records (for deleted entities)");
            if (!empty($stats['orphaned_permissions_by_type'])) {
                foreach ($stats['orphaned_permissions_by_type'] as $type => $count) {
                    $this->line("    - {$type}: {$count}");
                }
            }
        }
    }

    /**
     * Show a preview of what repairs would be made.
     */
    protected function showRepairPreview(array $stats): void
    {
        if ($stats['entities_without_permissions'] > 0) {
            $orphanedEntities = $this->consistencyService->findEntitiesWithoutPermissions();
            $this->line("Would rebuild permissions for {$orphanedEntities->count()} entities:");
            foreach ($orphanedEntities->take(10) as $entity) {
                $this->line("  - [{$entity->getMorphClass()}] {$entity->name}");
            }
            if ($orphanedEntities->count() > 10) {
                $this->line("  ... and " . ($orphanedEntities->count() - 10) . " more");
            }
        }

        if ($stats['orphaned_permissions'] > 0) {
            $this->line("Would delete {$stats['orphaned_permissions']} orphaned permission records");
        }
    }

    /**
     * Perform the repair operation.
     */
    protected function performRepair(): int
    {
        $this->newLine();
        $this->info('Starting permission repair...');

        $startTime = microtime(true);
        $repairedCount = $this->consistencyService->repairAll();
        $duration = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info("✅ Repair completed in {$duration}s");
        $this->line("  - Entities repaired: {$repairedCount}");

        // Show updated stats
        $newStats = $this->consistencyService->getStatistics();
        if ($newStats['is_healthy']) {
            $this->info('  - Permission system is now healthy');
        } else {
            $this->warn('  - Some issues may remain. Consider running --rebuild for a full refresh.');
        }

        return 0;
    }

    /**
     * Perform a full permission rebuild.
     */
    protected function performFullRebuild(): int
    {
        if (!$this->option('dry-run')) {
            $this->warn('⚠️  Full permission rebuild is a heavy operation.');
            $this->warn('   This will truncate and rebuild all permission entries.');
            
            if (!$this->confirm('Are you sure you want to proceed?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        } else {
            $this->info('Dry run mode - would perform full permission rebuild');
            return 0;
        }

        $this->newLine();
        $this->info('Starting full permission rebuild...');
        $this->line('This may take a while for large databases.');

        $startTime = microtime(true);
        $this->consistencyService->rebuildAll();
        $duration = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info("✅ Full rebuild completed in {$duration}s");

        // Show updated stats
        $newStats = $this->consistencyService->getStatistics();
        $this->showStatistics($newStats);

        return 0;
    }
}

