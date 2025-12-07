<?php

namespace Tests\Entity;

use BookStack\Entities\Models\Page;
use BookStack\Entities\Notifications\SopReviewReminderNotification;
use BookStack\Entities\Tools\ReviewReminderService;
use BookStack\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests for the SOP Review Reminder functionality (Phase 4c).
 */
class ReviewReminderTest extends TestCase
{
    protected ReviewReminderService $reminderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reminderService = app(ReviewReminderService::class);
    }

    /**
     * Helper to set page review date properly (uses save() to trigger entity_page_data update).
     */
    protected function setPageReviewDate(Page $page, ?Carbon $date, bool $draft = false): void
    {
        $page->draft = $draft;
        $page->next_review_date = $date;
        $page->save();
    }

    // ==========================================
    // Service: getOverduePages Tests
    // ==========================================

    public function test_get_overdue_pages_returns_pages_with_past_review_date()
    {
        $page = $this->entities->page();
        $this->setPageReviewDate($page, Carbon::now()->subDays(5));

        $overduePages = $this->reminderService->getOverduePages();

        $this->assertTrue($overduePages->contains('id', $page->id));
    }

    public function test_get_overdue_pages_returns_pages_due_today()
    {
        $page = $this->entities->page();
        $this->setPageReviewDate($page, Carbon::now()->startOfDay());

        $overduePages = $this->reminderService->getOverduePages();

        $this->assertTrue($overduePages->contains('id', $page->id));
    }

    public function test_get_overdue_pages_excludes_pages_with_future_review_date()
    {
        $page = $this->entities->page();
        $this->setPageReviewDate($page, Carbon::now()->addDays(5));

        $overduePages = $this->reminderService->getOverduePages();

        $this->assertFalse($overduePages->contains('id', $page->id));
    }

    public function test_get_overdue_pages_excludes_pages_without_review_date()
    {
        $page = $this->entities->page();
        $this->setPageReviewDate($page, null);

        $overduePages = $this->reminderService->getOverduePages();

        $this->assertFalse($overduePages->contains('id', $page->id));
    }

    public function test_get_overdue_pages_excludes_draft_pages()
    {
        $page = $this->entities->page();
        $this->setPageReviewDate($page, Carbon::now()->subDays(5), draft: true);

        $overduePages = $this->reminderService->getOverduePages();

        $this->assertFalse($overduePages->contains('id', $page->id));
    }

    // ==========================================
    // Service: getUpcomingReviewPages Tests
    // ==========================================

    public function test_get_upcoming_review_pages_returns_pages_within_range()
    {
        $page = $this->entities->page();
        $this->setPageReviewDate($page, Carbon::now()->addDays(3));

        $upcomingPages = $this->reminderService->getUpcomingReviewPages(7);

        $this->assertTrue($upcomingPages->contains('id', $page->id));
    }

    public function test_get_upcoming_review_pages_excludes_pages_beyond_range()
    {
        $page = $this->entities->page();
        $this->setPageReviewDate($page, Carbon::now()->addDays(10));

        $upcomingPages = $this->reminderService->getUpcomingReviewPages(7);

        $this->assertFalse($upcomingPages->contains('id', $page->id));
    }

    public function test_get_upcoming_review_pages_excludes_overdue_pages()
    {
        $page = $this->entities->page();
        $this->setPageReviewDate($page, Carbon::now()->subDays(1));

        $upcomingPages = $this->reminderService->getUpcomingReviewPages(7);

        $this->assertFalse($upcomingPages->contains('id', $page->id));
    }

    // ==========================================
    // Service: sendOverdueReminders Tests
    // ==========================================

    public function test_send_overdue_reminders_sends_to_page_owner()
    {
        Notification::fake();

        $owner = $this->users->editor();
        $this->permissions->grantUserRolePermissions($owner, ['receive-notifications']);
        
        $page = $this->entities->page();
        $page->owned_by = $owner->id;
        $this->setPageReviewDate($page, Carbon::now()->subDays(5));

        $stats = $this->reminderService->sendOverdueReminders();

        Notification::assertSentTo($owner, SopReviewReminderNotification::class);
        $this->assertEquals(1, $stats['sent']);
    }

    public function test_send_overdue_reminders_skips_users_without_notification_permission()
    {
        Notification::fake();

        // Create a user and ensure they don't have receive-notifications permission
        $owner = $this->users->viewer();
        $this->permissions->removeUserRolePermissions($owner, ['receive-notifications']);
        
        $page = $this->entities->page();
        $page->owned_by = $owner->id;
        $this->setPageReviewDate($page, Carbon::now()->subDays(5));

        $stats = $this->reminderService->sendOverdueReminders();

        Notification::assertNotSentTo($owner, SopReviewReminderNotification::class);
        $this->assertEquals(1, $stats['skipped']);
    }

    public function test_send_overdue_reminders_returns_correct_stats()
    {
        Notification::fake();

        // Create page with owner who can receive notifications
        $owner1 = $this->users->editor();
        $this->permissions->grantUserRolePermissions($owner1, ['receive-notifications']);
        
        $page1 = $this->entities->page();
        $page1->owned_by = $owner1->id;
        $this->setPageReviewDate($page1, Carbon::now()->subDays(5));

        // Create page with owner who cannot receive notifications
        $owner2 = $this->users->viewer();
        
        $page2 = $this->entities->page();
        $page2->owned_by = $owner2->id;
        $this->setPageReviewDate($page2, Carbon::now()->subDays(3));

        $stats = $this->reminderService->sendOverdueReminders();

        $this->assertEquals(1, $stats['sent']);
        $this->assertEquals(1, $stats['skipped']);
        $this->assertEquals(0, $stats['errors']);
    }

    // ==========================================
    // Service: getReviewStatistics Tests
    // ==========================================

    public function test_get_review_statistics_returns_correct_counts()
    {
        // Create overdue page
        $page1 = $this->entities->page();
        $this->setPageReviewDate($page1, Carbon::now()->subDays(5));

        // Create upcoming page (within 7 days)
        $page2 = $this->entities->page();
        $this->setPageReviewDate($page2, Carbon::now()->addDays(3));

        // Create future page (beyond 7 days)
        $page3 = $this->entities->page();
        $this->setPageReviewDate($page3, Carbon::now()->addDays(30));

        // Create page without review date (should not be counted)
        $page4 = $this->entities->page();
        $this->setPageReviewDate($page4, null);

        $stats = $this->reminderService->getReviewStatistics();

        $this->assertGreaterThanOrEqual(1, $stats['overdue']);
        $this->assertGreaterThanOrEqual(1, $stats['upcoming_7_days']);
        $this->assertGreaterThanOrEqual(3, $stats['total_scheduled']);
    }

    // ==========================================
    // Notification Tests
    // ==========================================

    public function test_notification_contains_page_details()
    {
        Notification::fake();

        $owner = $this->users->editor();
        $this->permissions->grantUserRolePermissions($owner, ['receive-notifications']);
        
        $page = $this->entities->page();
        $page->owned_by = $owner->id;
        $this->setPageReviewDate($page, Carbon::now()->subDays(5));

        $this->reminderService->sendOverdueReminders();

        Notification::assertSentTo($owner, function (SopReviewReminderNotification $notification, $channels) use ($owner, $page) {
            $mail = $notification->toMail($owner);
            
            // Check subject contains page name
            $this->assertStringContainsString($page->getShortName(), $mail->subject);
            
            return true;
        });
    }

    public function test_overdue_notification_shows_days_overdue()
    {
        Notification::fake();

        $owner = $this->users->editor();
        $this->permissions->grantUserRolePermissions($owner, ['receive-notifications']);
        
        $page = $this->entities->page();
        $page->owned_by = $owner->id;
        $this->setPageReviewDate($page, Carbon::now()->subDays(10));

        $this->reminderService->sendOverdueReminders();

        Notification::assertSentTo($owner, function (SopReviewReminderNotification $notification) use ($owner) {
            $mail = $notification->toMail($owner);
            
            // Should use overdue subject
            $this->assertStringContainsString('Overdue', $mail->subject);
            
            return true;
        });
    }

    // ==========================================
    // Artisan Command Tests
    // ==========================================

    public function test_artisan_command_runs_successfully()
    {
        $this->artisan('bookstack:send-review-reminders', ['--stats' => true])
            ->assertExitCode(0);
    }

    public function test_artisan_command_dry_run_does_not_send()
    {
        Notification::fake();

        $owner = $this->users->editor();
        $this->permissions->grantUserRolePermissions($owner, ['receive-notifications']);
        
        $page = $this->entities->page();
        $page->owned_by = $owner->id;
        $this->setPageReviewDate($page, Carbon::now()->subDays(5));

        $this->artisan('bookstack:send-review-reminders', ['--dry-run' => true])
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_artisan_command_with_include_upcoming_option()
    {
        $this->artisan('bookstack:send-review-reminders', [
            '--stats' => true,
            '--include-upcoming' => 7,
        ])->assertExitCode(0);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function test_page_without_owner_is_skipped()
    {
        Notification::fake();

        $page = $this->entities->page();
        $page->owned_by = null;
        $page->created_by = null;
        $this->setPageReviewDate($page, Carbon::now()->subDays(5));

        $stats = $this->reminderService->sendOverdueReminders();

        $this->assertEquals(1, $stats['skipped']);
        Notification::assertNothingSent();
    }

    public function test_multiple_overdue_pages_same_owner_sends_multiple_notifications()
    {
        Notification::fake();

        $owner = $this->users->editor();
        $this->permissions->grantUserRolePermissions($owner, ['receive-notifications']);
        
        // Create multiple overdue pages owned by same user
        for ($i = 0; $i < 3; $i++) {
            $page = $this->entities->page();
            $page->owned_by = $owner->id;
            $this->setPageReviewDate($page, Carbon::now()->subDays($i + 1));
        }

        $stats = $this->reminderService->sendOverdueReminders();

        $this->assertEquals(3, $stats['sent']);
        Notification::assertSentToTimes($owner, SopReviewReminderNotification::class, 3);
    }
}
