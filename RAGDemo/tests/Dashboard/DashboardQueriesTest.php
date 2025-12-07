<?php

namespace Tests\Dashboard;

use BookStack\Entities\Models\Page;
use BookStack\Entities\Models\PageRevision;
use BookStack\Entities\Queries\DashboardQueries;
use BookStack\Users\Models\User;
use Tests\TestCase;

class DashboardQueriesTest extends TestCase
{
    protected DashboardQueries $queries;

    protected function setUp(): void
    {
        parent::setUp();
        // Note: DashboardQueries class will need to be created as part of implementation
        // These tests define the expected behavior
    }

    public function test_current_user_owned_pages_returns_only_owned_pages()
    {
        $editor = $this->users->editor();
        $admin = $this->users->admin();
        
        // Create pages owned by different users
        $editorChain = $this->entities->createChainBelongingToUser($editor);
        $adminChain = $this->entities->createChainBelongingToUser($admin);
        
        $editorPage = $editorChain['page'];
        $adminPage = $adminChain['page'];

        $this->actingAs($editor);
        $queries = app(DashboardQueries::class);
        $result = $queries->currentUserOwnedPages(10);

        $pageIds = $result->pluck('id')->toArray();
        $this->assertContains($editorPage->id, $pageIds);
        $this->assertNotContains($adminPage->id, $pageIds);
    }

    public function test_current_user_owned_pages_excludes_drafts()
    {
        $editor = $this->users->editor();
        
        // Create a draft page
        $draftPage = $this->actingAs($editor)->entities->newDraftPage(['name' => 'Draft Page']);
        
        // Create a published page
        $chain = $this->entities->createChainBelongingToUser($editor);
        $publishedPage = $chain['page'];

        $this->actingAs($editor);
        $queries = app(DashboardQueries::class);
        $result = $queries->currentUserOwnedPages(10);

        $pageIds = $result->pluck('id')->toArray();
        $this->assertContains($publishedPage->id, $pageIds);
        $this->assertNotContains($draftPage->id, $pageIds);
    }

    public function test_current_user_owned_pages_respects_count_limit()
    {
        $editor = $this->users->editor();
        
        // Create multiple pages
        for ($i = 0; $i < 10; $i++) {
            $this->entities->createChainBelongingToUser($editor);
        }

        $this->actingAs($editor);
        $queries = app(DashboardQueries::class);
        $result = $queries->currentUserOwnedPages(5);

        $this->assertLessThanOrEqual(5, $result->count());
    }

    public function test_current_user_owned_pages_orders_by_updated_at_desc()
    {
        $editor = $this->users->editor();
        
        // Create pages with different update times
        $oldChain = $this->entities->createChainBelongingToUser($editor);
        $oldPage = $oldChain['page'];
        $oldPage->updated_at = now()->subDays(5);
        $oldPage->save();

        $newChain = $this->entities->createChainBelongingToUser($editor);
        $newPage = $newChain['page'];
        $newPage->updated_at = now();
        $newPage->save();

        $this->actingAs($editor);
        $queries = app(DashboardQueries::class);
        $result = $queries->currentUserOwnedPages(10);

        $this->assertTrue($result->first()->id === $newPage->id || $result->first()->updated_at >= $result->last()->updated_at);
    }

    public function test_current_user_owned_pages_respects_visibility_permissions()
    {
        $editor = $this->users->editor();
        
        // Create a page owned by editor
        $chain = $this->entities->createChainBelongingToUser($editor);
        $page = $chain['page'];
        
        // Restrict visibility on the page
        $this->permissions->setEntityPermissions($page, [], []);

        $this->actingAs($editor);
        $queries = app(DashboardQueries::class);
        $result = $queries->currentUserOwnedPages(10);

        $pageIds = $result->pluck('id')->toArray();
        $this->assertNotContains($page->id, $pageIds);
    }

    public function test_pending_review_revisions_returns_only_in_review_status()
    {
        $page = $this->entities->page();
        
        // Create revisions with different statuses
        $this->asAdmin()->put($page->getUrl(), ['name' => 'Update 1', 'html' => '<p>Content 1</p>']);
        $page->refresh();
        $draftRevision = $page->currentRevision;
        $draftRevision->status = PageRevision::STATUS_DRAFT;
        $draftRevision->save();

        $this->asAdmin()->put($page->getUrl(), ['name' => 'Update 2', 'html' => '<p>Content 2</p>']);
        $page->refresh();
        $inReviewRevision = $page->currentRevision;
        $inReviewRevision->status = PageRevision::STATUS_IN_REVIEW;
        $inReviewRevision->save();

        $this->asAdmin()->put($page->getUrl(), ['name' => 'Update 3', 'html' => '<p>Content 3</p>']);
        $page->refresh();
        $approvedRevision = $page->currentRevision;
        $approvedRevision->status = PageRevision::STATUS_APPROVED;
        $approvedRevision->save();

        $queries = app(DashboardQueries::class);
        $result = $queries->pendingReviewRevisions(10);

        $revisionIds = $result->pluck('id')->toArray();
        $this->assertContains($inReviewRevision->id, $revisionIds);
        $this->assertNotContains($draftRevision->id, $revisionIds);
        $this->assertNotContains($approvedRevision->id, $revisionIds);
    }

    public function test_pending_review_revisions_only_returns_version_type()
    {
        $page = $this->entities->page();
        
        // Create a version revision in review
        $this->asAdmin()->put($page->getUrl(), ['name' => 'Version Update', 'html' => '<p>Content</p>']);
        $page->refresh();
        $revision = $page->currentRevision;
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->type = 'version';
        $revision->save();

        // Create an update_draft type revision (shouldn't appear)
        $draftRevision = PageRevision::query()->create([
            'page_id' => $page->id,
            'name' => 'Draft Update',
            'html' => '<p>Draft content</p>',
            'type' => 'update_draft',
            'status' => PageRevision::STATUS_IN_REVIEW,
            'created_by' => $this->users->admin()->id,
        ]);

        $queries = app(DashboardQueries::class);
        $result = $queries->pendingReviewRevisions(10);

        $revisionIds = $result->pluck('id')->toArray();
        $this->assertContains($revision->id, $revisionIds);
        $this->assertNotContains($draftRevision->id, $revisionIds);
    }

    public function test_pending_review_revisions_respects_page_visibility()
    {
        $page = $this->entities->page();
        $editor = $this->users->editor();
        
        // Create revision in review
        $this->asAdmin()->put($page->getUrl(), ['name' => 'Hidden Page Update', 'html' => '<p>Content</p>']);
        $page->refresh();
        $revision = $page->currentRevision;
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        // Restrict page visibility
        $this->permissions->setEntityPermissions($page, [], []);

        $this->actingAs($editor);
        $queries = app(DashboardQueries::class);
        $result = $queries->pendingReviewRevisions(10);

        $revisionIds = $result->pluck('id')->toArray();
        $this->assertNotContains($revision->id, $revisionIds);
    }

    public function test_pending_review_revisions_respects_count_limit()
    {
        $admin = $this->users->admin();
        
        // Create multiple pages with in_review revisions
        for ($i = 0; $i < 10; $i++) {
            $chain = $this->entities->createChainBelongingToUser($admin);
            $page = $chain['page'];
            
            // Update page to create a revision
            $this->asAdmin()->put($page->getUrl(), [
                'name' => "Page $i for review",
                'html' => '<p>Content</p>',
            ]);
            $page->refresh();
            
            if ($page->currentRevision) {
                $page->currentRevision->status = PageRevision::STATUS_IN_REVIEW;
                $page->currentRevision->save();
            }
        }

        $queries = app(DashboardQueries::class);
        $result = $queries->pendingReviewRevisions(5);

        $this->assertLessThanOrEqual(5, $result->count());
    }

    public function test_pending_review_revisions_orders_by_created_at_desc()
    {
        $admin = $this->users->admin();
        
        // Create old page and revision
        $chain1 = $this->entities->createChainBelongingToUser($admin);
        $page1 = $chain1['page'];
        $this->asAdmin()->put($page1->getUrl(), ['name' => 'Old Page', 'html' => '<p>Content</p>']);
        $page1->refresh();
        $oldRevision = $page1->currentRevision;
        if ($oldRevision) {
            $oldRevision->status = PageRevision::STATUS_IN_REVIEW;
            $oldRevision->created_at = now()->subDays(5);
            $oldRevision->save();
        }

        // Create new page and revision
        $chain2 = $this->entities->createChainBelongingToUser($admin);
        $page2 = $chain2['page'];
        $this->asAdmin()->put($page2->getUrl(), ['name' => 'New Page', 'html' => '<p>Content</p>']);
        $page2->refresh();
        $newRevision = $page2->currentRevision;
        if ($newRevision) {
            $newRevision->status = PageRevision::STATUS_IN_REVIEW;
            $newRevision->created_at = now();
            $newRevision->save();
        }

        $queries = app(DashboardQueries::class);
        $result = $queries->pendingReviewRevisions(10);

        if ($result->count() >= 2) {
            $this->assertTrue($result->first()->created_at >= $result->last()->created_at);
        }
    }

    public function test_pending_review_revisions_loads_page_relation()
    {
        $page = $this->entities->page();
        $this->asAdmin()->put($page->getUrl(), ['name' => 'Test Page', 'html' => '<p>Content</p>']);
        $page->refresh();
        if ($page->currentRevision) {
            $page->currentRevision->status = PageRevision::STATUS_IN_REVIEW;
            $page->currentRevision->save();
        }

        $queries = app(DashboardQueries::class);
        $result = $queries->pendingReviewRevisions(10);

        if ($result->count() > 0) {
            $revision = $result->first();
            $this->assertTrue($revision->relationLoaded('page'));
            $this->assertNotNull($revision->page);
        }
    }

    public function test_pending_review_revisions_loads_created_by_relation()
    {
        $page = $this->entities->page();
        $this->asAdmin()->put($page->getUrl(), ['name' => 'Test Page', 'html' => '<p>Content</p>']);
        $page->refresh();
        if ($page->currentRevision) {
            $page->currentRevision->status = PageRevision::STATUS_IN_REVIEW;
            $page->currentRevision->save();
        }

        $queries = app(DashboardQueries::class);
        $result = $queries->pendingReviewRevisions(10);

        if ($result->count() > 0) {
            $revision = $result->first();
            $this->assertTrue($revision->relationLoaded('createdBy'));
        }
    }

    public function test_returns_empty_collection_when_no_owned_pages()
    {
        $user = User::factory()->create();
        $viewerRole = \BookStack\Users\Models\Role::getRole('Viewer');
        $user->attachRole($viewerRole);

        $this->actingAs($user);
        $queries = app(DashboardQueries::class);
        $result = $queries->currentUserOwnedPages(10);

        $this->assertCount(0, $result);
    }

    public function test_returns_empty_collection_when_no_pending_reviews()
    {
        // Clear any existing in_review revisions
        PageRevision::query()->where('status', PageRevision::STATUS_IN_REVIEW)
            ->update(['status' => PageRevision::STATUS_DRAFT]);

        $queries = app(DashboardQueries::class);
        $result = $queries->pendingReviewRevisions(10);

        $this->assertCount(0, $result);
    }
}

