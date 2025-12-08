<?php

namespace BookStack\Entities\Tools;

use BookStack\Entities\Models\Page;
use BookStack\Entities\Notifications\SopReviewReminderNotification;
use BookStack\Entities\Queries\EntityQueries;
use BookStack\Permissions\Permission;
use BookStack\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending SOP review reminder notifications.
 * 
 * This service identifies SOPs that are due for review and sends
 * email notifications to the appropriate owners/reviewers.
 */
class ReviewReminderService
{
    public function __construct(
        protected EntityQueries $queries,
    ) {
    }

    /**
     * Get all pages that are due for review.
     * Includes pages where next_review_date is today or in the past.
     */
    public function getOverduePages(): Collection
    {
        return Page::query()
            ->where('draft', '=', false)
            ->whereNotNull('next_review_date')
            ->where('next_review_date', '<=', Carbon::now()->toDateString())
            ->with(['ownedBy', 'book'])
            ->get();
    }

    /**
     * Get pages that are due for review within the next N days.
     * Useful for "upcoming review" warnings.
     */
    public function getUpcomingReviewPages(int $daysAhead = 7): Collection
    {
        $futureDate = Carbon::now()->addDays($daysAhead)->toDateString();

        return Page::query()
            ->where('draft', '=', false)
            ->whereNotNull('next_review_date')
            ->where('next_review_date', '>', Carbon::now()->toDateString())
            ->where('next_review_date', '<=', $futureDate)
            ->with(['ownedBy', 'book'])
            ->get();
    }

    /**
     * Send review reminder notifications for all overdue pages.
     * 
     * @return array{sent: int, skipped: int, errors: int}
     */
    public function sendOverdueReminders(): array
    {
        $stats = ['sent' => 0, 'skipped' => 0, 'errors' => 0];
        $overduePages = $this->getOverduePages();

        foreach ($overduePages as $page) {
            $result = $this->sendReminderForPage($page, isOverdue: true);
            $stats[$result]++;
        }

        return $stats;
    }

    /**
     * Send upcoming review reminder notifications.
     * 
     * @param int $daysAhead Number of days to look ahead
     * @return array{sent: int, skipped: int, errors: int}
     */
    public function sendUpcomingReminders(int $daysAhead = 7): array
    {
        $stats = ['sent' => 0, 'skipped' => 0, 'errors' => 0];
        $upcomingPages = $this->getUpcomingReviewPages($daysAhead);

        foreach ($upcomingPages as $page) {
            $result = $this->sendReminderForPage($page, isOverdue: false);
            $stats[$result]++;
        }

        return $stats;
    }

    /**
     * Send a reminder notification for a specific page.
     * 
     * @return string Result status: 'sent', 'skipped', or 'errors'
     */
    protected function sendReminderForPage(Page $page, bool $isOverdue = false): string
    {
        $recipient = $this->getRecipientForPage($page);

        if (!$recipient) {
            return 'skipped';
        }

        // Check if user can receive notifications
        if (!$recipient->can(Permission::ReceiveNotifications)) {
            return 'skipped';
        }

        // Calculate days overdue
        $daysOverdue = 0;
        if ($isOverdue && $page->next_review_date) {
            $daysOverdue = (int) $page->next_review_date->diffInDays(Carbon::now());
        }

        try {
            $recipient->notify(new SopReviewReminderNotification($page, $daysOverdue));
            return 'sent';
        } catch (\Exception $exception) {
            Log::error("Failed to send SOP review reminder for page [id:{$page->id}] to user [id:{$recipient->id}]: {$exception->getMessage()}");
            return 'errors';
        }
    }

    /**
     * Get the user who should receive the reminder for a page.
     * Priority: owner > creator > null
     */
    protected function getRecipientForPage(Page $page): ?User
    {
        // First try the page owner
        if ($page->ownedBy && $page->ownedBy->exists) {
            return $page->ownedBy;
        }

        // Fall back to creator
        if ($page->createdBy && $page->createdBy->exists) {
            return $page->createdBy;
        }

        return null;
    }

    /**
     * Get summary statistics of review status across all pages.
     */
    public function getReviewStatistics(): array
    {
        $now = Carbon::now()->toDateString();
        $weekFromNow = Carbon::now()->addDays(7)->toDateString();

        $overdueCount = Page::query()
            ->where('draft', '=', false)
            ->whereNotNull('next_review_date')
            ->where('next_review_date', '<=', $now)
            ->count();

        $upcomingCount = Page::query()
            ->where('draft', '=', false)
            ->whereNotNull('next_review_date')
            ->where('next_review_date', '>', $now)
            ->where('next_review_date', '<=', $weekFromNow)
            ->count();

        $scheduledCount = Page::query()
            ->where('draft', '=', false)
            ->whereNotNull('next_review_date')
            ->count();

        return [
            'overdue' => $overdueCount,
            'upcoming_7_days' => $upcomingCount,
            'total_scheduled' => $scheduledCount,
        ];
    }
}

