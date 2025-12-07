<?php

namespace Tests\Dashboard;

use BookStack\Entities\Models\Page;
use BookStack\Entities\Models\PageRevision;
use BookStack\Users\Models\User;
use Tests\TestCase;

class SOPDashboardTest extends TestCase
{
    public function test_dashboard_accessible_when_logged_in()
    {
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $resp->assertSee('sop-dashboard');
    }

    public function test_dashboard_requires_authentication()
    {
        $resp = $this->get('/dashboard');
        $resp->assertRedirect('/login');
    }

    public function test_dashboard_shows_my_sops_section()
    {
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementExists('#my-sops');
    }

    public function test_dashboard_shows_user_owned_pages_in_my_sops()
    {
        $editor = $this->users->editor();
        
        // Create a page owned by the editor
        $chain = $this->entities->createChainBelongingToUser($editor);
        $page = $chain['page'];

        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementContains('#my-sops', $page->name);
    }

    public function test_dashboard_does_not_show_other_users_pages_in_my_sops()
    {
        $editor = $this->users->editor();
        $admin = $this->users->admin();
        
        // Create a page owned by admin
        $chain = $this->entities->createChainBelongingToUser($admin);
        $adminPage = $chain['page'];

        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementNotContains('#my-sops', $adminPage->name);
    }

    public function test_dashboard_does_not_show_draft_pages_in_my_sops()
    {
        $editor = $this->users->editor();
        
        // Create a draft page
        $draftPage = $this->actingAs($editor)->entities->newDraftPage(['name' => 'My Draft SOP']);

        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementNotContains('#my-sops', 'My Draft SOP');
    }

    public function test_dashboard_shows_empty_state_when_user_has_no_sops()
    {
        $user = User::factory()->create();
        $viewerRole = \BookStack\Users\Models\Role::getRole('Viewer');
        $user->attachRole($viewerRole);

        $resp = $this->actingAs($user)->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementContains('#my-sops', "haven't created any");
    }

    public function test_dashboard_shows_recently_updated_section()
    {
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementExists('#recently-updated');
    }

    public function test_dashboard_shows_recently_updated_pages()
    {
        $page = $this->entities->page();
        $this->asAdmin()->put($page->getUrl(), [
            'name' => 'Recently Updated SOP',
            'html' => '<p>Updated content</p>',
        ]);

        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementContains('#recently-updated', 'Recently Updated SOP');
    }

    public function test_dashboard_shows_department_shortcuts_section()
    {
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementExists('#departments');
    }

    public function test_dashboard_shows_all_visible_departments()
    {
        $shelf = $this->entities->shelf();

        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementContains('#departments', $shelf->name);
    }

    public function test_dashboard_department_links_work()
    {
        $shelf = $this->entities->shelf();

        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementExists("#departments a[href*=\"/shelves/{$shelf->slug}\"]");
    }

    public function test_dashboard_shows_pending_reviews_section()
    {
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementExists('#pending-reviews');
    }

    public function test_dashboard_shows_revisions_pending_review()
    {
        $page = $this->entities->page();
        
        // Create a revision and set it to in_review status
        $this->asAdmin()->put($page->getUrl(), [
            'name' => 'SOP Pending Review',
            'html' => '<p>Content needing review</p>',
        ]);
        
        $page->refresh();
        $revision = $page->currentRevision;
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementContains('#pending-reviews', 'SOP Pending Review');
    }

    public function test_dashboard_does_not_show_approved_revisions_in_pending()
    {
        $page = $this->entities->page();
        
        // Create a revision and set it to approved status
        $this->asAdmin()->put($page->getUrl(), [
            'name' => 'Approved SOP',
            'html' => '<p>Approved content</p>',
        ]);
        
        $page->refresh();
        $revision = $page->currentRevision;
        $revision->status = PageRevision::STATUS_APPROVED;
        $revision->save();

        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementNotContains('#pending-reviews', 'Approved SOP');
    }

    public function test_dashboard_shows_empty_state_when_no_pending_reviews()
    {
        // Ensure no revisions are in review status
        PageRevision::query()->where('status', PageRevision::STATUS_IN_REVIEW)->update(['status' => PageRevision::STATUS_DRAFT]);

        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementContains('#pending-reviews', 'No SOP documents pending review');
    }

    public function test_dashboard_shows_recent_activity_section()
    {
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementExists('#recent-activity');
    }

    public function test_dashboard_respects_page_visibility_permissions()
    {
        $editor = $this->users->editor();
        $page = $this->entities->page();
        
        // Remove editor's permissions on this page
        $editor->roles()->detach();
        $this->permissions->grantUserRolePermissions($editor, ['page-view-own']);
        
        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementNotContains('#recently-updated', $page->name);
    }

    public function test_dashboard_respects_shelf_visibility_permissions()
    {
        $editor = $this->users->editor();
        $shelf = $this->entities->shelf();
        
        // Set entity permissions to restrict access
        $this->permissions->setEntityPermissions($shelf, [], []);
        
        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementNotContains('#departments', $shelf->name);
    }

    public function test_dashboard_shows_drafts_section_when_user_has_drafts()
    {
        $editor = $this->users->editor();
        $draftPage = $this->actingAs($editor)->entities->newDraftPage(['name' => 'My Draft Document']);

        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementExists('#recent-drafts');
        $this->withHtml($resp)->assertElementContains('#recent-drafts', 'My Draft Document');
    }

    public function test_dashboard_hides_drafts_section_when_no_drafts()
    {
        $user = User::factory()->create();
        $viewerRole = \BookStack\Users\Models\Role::getRole('Viewer');
        $user->attachRole($viewerRole);

        $resp = $this->actingAs($user)->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementNotExists('#recent-drafts');
    }

    public function test_dashboard_pending_reviews_only_shows_visible_pages()
    {
        $page = $this->entities->page();
        $editor = $this->users->editor();
        
        // Create revision in review
        $this->asAdmin()->put($page->getUrl(), [
            'name' => 'Hidden Review SOP',
            'html' => '<p>Content</p>',
        ]);
        $page->refresh();
        $revision = $page->currentRevision;
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        // Restrict page visibility
        $this->permissions->setEntityPermissions($page, [], []);

        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementNotContains('#pending-reviews', 'Hidden Review SOP');
    }

    public function test_dashboard_view_all_links_work_correctly()
    {
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        
        // Check "View All" links exist for appropriate sections
        $this->withHtml($resp)->assertElementExists('a[href*="/pages/recently-updated"]');
        $this->withHtml($resp)->assertElementExists('a[href*="/search"]');
    }

    public function test_dashboard_displays_correct_sop_terminology()
    {
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        
        // Check SOP terminology is used (from Phase 1 translations)
        $resp->assertSee('SOP');
    }

    public function test_dashboard_shows_review_submission_info()
    {
        $editor = $this->users->editor();
        $page = $this->entities->page();
        
        // Create a revision in review by the editor
        $this->actingAs($editor)->put($page->getUrl(), [
            'name' => 'Review Info Test SOP',
            'html' => '<p>Content for review</p>',
        ]);
        
        $page->refresh();
        $revision = $page->currentRevision;
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        $resp = $this->asAdmin()->get('/dashboard');
        $resp->assertStatus(200);
        // Should show who submitted the revision
        $this->withHtml($resp)->assertElementContains('#pending-reviews', $editor->name);
    }
}

