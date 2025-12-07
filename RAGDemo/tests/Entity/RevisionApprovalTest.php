<?php

namespace Tests\Entity;

use BookStack\Activity\ActivityType;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Models\PageRevision;
use BookStack\Entities\Tools\RevisionApprovalService;
use BookStack\Users\Models\User;
use Tests\TestCase;

class RevisionApprovalTest extends TestCase
{
    protected function createPageWithRevision(): array
    {
        $page = $this->entities->page();
        $this->asAdmin()->put($page->getUrl(), [
            'name' => 'Test Page for Approval',
            'html' => '<p>Content for approval</p>',
        ]);
        $page->refresh();
        $revision = $page->currentRevision;

        return [$page, $revision];
    }

    public function test_revision_can_be_submitted_for_review()
    {
        [$page, $revision] = $this->createPageWithRevision();

        // Ensure revision starts as draft
        $this->assertEquals(PageRevision::STATUS_DRAFT, $revision->status);

        // Submit for review
        $resp = $this->asEditor()->post($revision->getUrl('/submit-review'));
        $resp->assertRedirect();

        $revision->refresh();
        $this->assertEquals(PageRevision::STATUS_IN_REVIEW, $revision->status);
    }

    public function test_only_draft_revisions_can_be_submitted()
    {
        [$page, $revision] = $this->createPageWithRevision();
        
        // Set revision to in_review already
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $resp = $this->asEditor()->post($revision->getUrl('/submit-review'));
        $resp->assertRedirect();
        $resp->assertSessionHas('error');
    }

    public function test_revision_can_be_approved()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $resp = $this->asAdmin()->post($revision->getUrl('/approve'), [
            'review_notes' => 'Looks good!',
            'review_interval_days' => 90,
        ]);
        $resp->assertRedirect($page->getUrl());

        $revision->refresh();
        $page->refresh();

        $this->assertEquals(PageRevision::STATUS_APPROVED, $revision->status);
        $this->assertNotNull($revision->approved_by);
        $this->assertNotNull($revision->approved_at);
        $this->assertEquals('Looks good!', $revision->review_notes);
    }

    public function test_approved_revision_sets_page_approved_revision()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $this->asAdmin()->post($revision->getUrl('/approve'));

        $page->refresh();
        $pageData = $page->relatedData;

        $this->assertEquals($revision->id, $pageData->approved_revision_id);
    }

    public function test_approval_sets_next_review_date()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $this->asAdmin()->post($revision->getUrl('/approve'), [
            'review_interval_days' => 30,
        ]);

        $page->refresh();
        $pageData = $page->relatedData;

        $this->assertEquals(30, $pageData->review_interval_days);
        $this->assertNotNull($pageData->next_review_date);
        
        // Compare as date string since it may be stored as string
        $reviewDate = $pageData->next_review_date;
        if (is_string($reviewDate)) {
            $reviewDate = \Carbon\Carbon::parse($reviewDate);
        }
        $this->assertTrue($reviewDate->isFuture());
    }

    public function test_revision_can_be_rejected()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $resp = $this->asAdmin()->post($revision->getUrl('/reject'), [
            'review_notes' => 'Needs more detail',
        ]);
        $resp->assertRedirect($page->getUrl('/revisions'));

        $revision->refresh();
        $this->assertEquals(PageRevision::STATUS_REJECTED, $revision->status);
        $this->assertEquals('Needs more detail', $revision->review_notes);
    }

    public function test_only_in_review_revisions_can_be_approved()
    {
        [$page, $revision] = $this->createPageWithRevision();
        // Revision is still draft
        
        $resp = $this->asAdmin()->post($revision->getUrl('/approve'));
        $resp->assertRedirect();
        $resp->assertSessionHas('error');
    }

    public function test_only_in_review_revisions_can_be_rejected()
    {
        [$page, $revision] = $this->createPageWithRevision();
        // Revision is still draft
        
        $resp = $this->asAdmin()->post($revision->getUrl('/reject'));
        $resp->assertRedirect();
        $resp->assertSessionHas('error');
    }

    public function test_revision_can_be_withdrawn_from_review()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $resp = $this->asEditor()->post($revision->getUrl('/withdraw'));
        $resp->assertRedirect();

        $revision->refresh();
        $this->assertEquals(PageRevision::STATUS_DRAFT, $revision->status);
    }

    public function test_approved_revision_cannot_be_withdrawn()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_APPROVED;
        $revision->approved_by = $this->users->admin()->id;
        $revision->approved_at = now();
        $revision->save();

        $resp = $this->asEditor()->post($revision->getUrl('/withdraw'));
        $resp->assertRedirect();
        $resp->assertSessionHas('error');
    }

    public function test_approval_requires_permission()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        // Create a user without the content-export permission required for approval
        $user = \BookStack\Users\Models\User::factory()->create();
        $this->permissions->grantUserRolePermissions($user, ['page-view-all', 'book-view-all']);
        
        $resp = $this->actingAs($user)->post($revision->getUrl('/approve'));
        $this->assertPermissionError($resp);
    }

    public function test_rejection_requires_permission()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        // Create a user without the content-export permission required for rejection
        $user = \BookStack\Users\Models\User::factory()->create();
        $this->permissions->grantUserRolePermissions($user, ['page-view-all', 'book-view-all']);
        
        $resp = $this->actingAs($user)->post($revision->getUrl('/reject'));
        $this->assertPermissionError($resp);
    }

    public function test_approval_form_page_accessible()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $resp = $this->asAdmin()->get($revision->getUrl('/approve'));
        $resp->assertStatus(200);
        $resp->assertSee(trans('entities.revision_approve_title'));
    }

    public function test_rejection_form_page_accessible()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $resp = $this->asAdmin()->get($revision->getUrl('/reject'));
        $resp->assertStatus(200);
        $resp->assertSee(trans('entities.revision_reject_title'));
    }

    public function test_revisions_page_shows_status()
    {
        [$page, $revision] = $this->createPageWithRevision();
        
        // Set status to in_review and save
        PageRevision::query()->where('id', $revision->id)->update(['status' => PageRevision::STATUS_IN_REVIEW]);
        $revision->refresh();
        
        // Verify the status was saved
        $this->assertEquals(PageRevision::STATUS_IN_REVIEW, $revision->status);

        $resp = $this->asEditor()->get($page->getUrl('/revisions'));
        $resp->assertStatus(200);
        
        // The status badge should show "In Review"
        $resp->assertSee('In Review');
    }

    public function test_revisions_page_shows_approval_actions()
    {
        [$page, $revision] = $this->createPageWithRevision();
        
        // Set status to in_review using direct update
        PageRevision::query()->where('id', $revision->id)->update(['status' => PageRevision::STATUS_IN_REVIEW]);
        $revision->refresh();

        $resp = $this->asAdmin()->get($page->getUrl('/revisions'));
        $resp->assertStatus(200);
        
        // Check for approve and reject links/buttons
        $resp->assertSee('Approve');
        $resp->assertSee('Reject');
    }

    public function test_revisions_page_shows_submit_for_review_button()
    {
        [$page, $revision] = $this->createPageWithRevision();
        // Revision is draft by default

        $resp = $this->asEditor()->get($page->getUrl('/revisions'));
        $resp->assertStatus(200);
        $resp->assertSee(trans('entities.revision_submit_for_review'));
    }

    public function test_page_shows_approved_banner()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_APPROVED;
        $revision->approved_by = $this->users->admin()->id;
        $revision->approved_at = now();
        $revision->save();

        // Set as page's approved revision
        $pageData = $page->relatedData()->firstOrCreate(['page_id' => $page->id]);
        $pageData->approved_revision_id = $revision->id;
        $pageData->save();

        $resp = $this->asEditor()->get($page->getUrl());
        $resp->assertStatus(200);
        $resp->assertSee(trans('entities.revision_status_approved'));
    }

    public function test_page_shows_pending_review_banner()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $resp = $this->asEditor()->get($page->getUrl());
        $resp->assertStatus(200);
        $resp->assertSee(trans('entities.page_pending_review_banner'));
    }

    public function test_activity_logged_on_submit_for_review()
    {
        [$page, $revision] = $this->createPageWithRevision();
        
        $this->asEditor()->post($revision->getUrl('/submit-review'));

        $this->assertActivityExists(ActivityType::REVISION_SUBMIT_REVIEW);
    }

    public function test_activity_logged_on_approval()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $this->asAdmin()->post($revision->getUrl('/approve'));

        $this->assertActivityExists(ActivityType::REVISION_APPROVE);
    }

    public function test_activity_logged_on_rejection()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $this->asAdmin()->post($revision->getUrl('/reject'));

        $this->assertActivityExists(ActivityType::REVISION_REJECT);
    }

    public function test_edit_button_shows_create_new_draft_when_approved()
    {
        [$page, $revision] = $this->createPageWithRevision();
        $revision->status = PageRevision::STATUS_APPROVED;
        $revision->approved_by = $this->users->admin()->id;
        $revision->approved_at = now();
        $revision->save();

        // Set as page's approved revision
        $pageData = $page->relatedData()->firstOrCreate(['page_id' => $page->id]);
        $pageData->approved_revision_id = $revision->id;
        $pageData->save();

        $resp = $this->asEditor()->get($page->getUrl());
        $resp->assertStatus(200);
        $resp->assertSee(trans('entities.page_create_new_draft'));
    }
}

