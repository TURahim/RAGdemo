<?php

namespace BookStack\Console\Commands;

use BookStack\Entities\Tools\ReviewReminderService;
use Illuminate\Console\Command;

/**
 * Artisan command to send SOP review reminder notifications.
 * 
 * This command should be scheduled to run daily to:
 * 1. Notify owners of SOPs that are overdue for review
 * 2. Optionally send warnings for SOPs due within N days
 * 
 * Usage:
 *   php artisan bookstack:send-review-reminders
 *   php artisan bookstack:send-review-reminders --include-upcoming=7
 *   php artisan bookstack:send-review-reminders --dry-run
 */
class SendReviewRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bookstack:send-review-reminders
                            {--include-upcoming= : Also send reminders for SOPs due within N days}
                            {--dry-run : Show what would be sent without actually sending}
                            {--stats : Only show statistics, do not send notifications}';

    /**
     * The console command description.
     */
    protected $description = 'Send email notifications for SOPs that are due for review';

    /**
     * Execute the console command.
     */
    public function handle(ReviewReminderService $reminderService): int
    {
        // Stats only mode
        if ($this->option('stats')) {
            $this->showStatistics($reminderService);
            return static::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $includeUpcoming = $this->option('include-upcoming');

        // Show current status
        $this->info('SOP Review Reminder Service');
        $this->info('===========================');
        $this->showStatistics($reminderService);
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No notifications will be sent');
            $this->newLine();
        }

        // Process overdue reminders
        $this->info('Processing overdue SOP reviews...');
        $overduePages = $reminderService->getOverduePages();

        if ($overduePages->isEmpty()) {
            $this->info('No overdue SOPs found.');
        } else {
            $this->table(
                ['Page ID', 'Title', 'Owner', 'Review Due', 'Days Overdue'],
                $overduePages->map(function ($page) {
                    $daysOverdue = $page->next_review_date 
                        ? (int) $page->next_review_date->diffInDays(now()) 
                        : 0;
                    return [
                        $page->id,
                        $page->getShortName(40),
                        $page->ownedBy?->name ?? 'N/A',
                        $page->next_review_date?->format('Y-m-d') ?? 'N/A',
                        $daysOverdue,
                    ];
                })->toArray()
            );

            if (!$dryRun) {
                $stats = $reminderService->sendOverdueReminders();
                $this->info("Overdue reminders: {$stats['sent']} sent, {$stats['skipped']} skipped, {$stats['errors']} errors");
            }
        }

        // Process upcoming reminders if requested
        if ($includeUpcoming && (int) $includeUpcoming > 0) {
            $daysAhead = (int) $includeUpcoming;
            $this->newLine();
            $this->info("Processing SOPs due within {$daysAhead} days...");
            $upcomingPages = $reminderService->getUpcomingReviewPages($daysAhead);

            if ($upcomingPages->isEmpty()) {
                $this->info('No upcoming SOP reviews found.');
            } else {
                $this->table(
                    ['Page ID', 'Title', 'Owner', 'Review Due', 'Days Until Due'],
                    $upcomingPages->map(function ($page) {
                        $daysUntil = $page->next_review_date 
                            ? (int) now()->diffInDays($page->next_review_date, false) 
                            : 0;
                        return [
                            $page->id,
                            $page->getShortName(40),
                            $page->ownedBy?->name ?? 'N/A',
                            $page->next_review_date?->format('Y-m-d') ?? 'N/A',
                            $daysUntil,
                        ];
                    })->toArray()
                );

                if (!$dryRun) {
                    $stats = $reminderService->sendUpcomingReminders($daysAhead);
                    $this->info("Upcoming reminders: {$stats['sent']} sent, {$stats['skipped']} skipped, {$stats['errors']} errors");
                }
            }
        }

        $this->newLine();
        $this->info('Review reminder processing complete.');

        return static::SUCCESS;
    }

    /**
     * Display review statistics.
     */
    protected function showStatistics(ReviewReminderService $reminderService): void
    {
        $stats = $reminderService->getReviewStatistics();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Overdue for Review', $stats['overdue']],
                ['Due Within 7 Days', $stats['upcoming_7_days']],
                ['Total with Scheduled Review', $stats['total_scheduled']],
            ]
        );
    }
}

