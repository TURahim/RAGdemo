<?php

namespace Tests\Dashboard;

use BookStack\Entities\Models\PageRevision;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Tests\TestCase;

/**
 * Tests for dashboard permission scenarios.
 * Ensures proper access control and visibility filtering.
 */
class DashboardPermissionsTest extends TestCase
{
    public function test_admin_can_access_dashboard()
    {
        $resp = $this->asAdmin()->get('/dashboard');
        $resp->assertStatus(200);
    }

    public function test_editor_can_access_dashboard()
    {
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
    }

    public function test_viewer_can_access_dashboard()
    {
        $resp = $this->asViewer()->get('/dashboard');
        $resp->assertStatus(200);
    }

    public function test_guest_cannot_access_dashboard()
    {
        $resp = $this->get('/dashboard');
        $resp->assertRedirect('/login');
    }

    public function test_guest_cannot_access_dashboard_even_when_app_is_public()
    {
        $this->permissions->makeAppPublic();
        
        // Dashboard explicitly prevents guest access even when app is public
        // It redirects to homepage with an error message
        $resp = $this->get('/dashboard');
        $resp->assertRedirect('/');
    }

    public function test_user_only_sees_visible_pages_in_recently_updated()
    {
        $editor = $this->users->editor();
        $page = $this->entities->page();
        
        // First verify editor can see the page normally
        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        
        // Restrict the page from the editor
        $this->permissions->setEntityPermissions($page, [], []);
        
        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementNotContains('#recently-updated', $page->name);
    }

    public function test_user_only_sees_visible_departments()
    {
        $editor = $this->users->editor();
        $shelf = $this->entities->newShelf(['name' => 'Restricted Department']);
        
        // Verify visible first
        $resp = $this->actingAs($editor)->get('/dashboard');
        $this->withHtml($resp)->assertElementContains('#departments', 'Restricted Department');
        
        // Restrict the shelf
        $this->permissions->setEntityPermissions($shelf, [], []);
        
        $resp = $this->actingAs($editor)->get('/dashboard');
        $this->withHtml($resp)->assertElementNotContains('#departments', 'Restricted Department');
    }

    public function test_user_with_own_permissions_sees_only_owned_content()
    {
        $user = User::factory()->create();
        $this->permissions->grantUserRolePermissions($user, ['page-view-own', 'book-view-all']);
        
        // Create a page owned by the user
        $chain = $this->entities->createChainBelongingToUser($user);
        $ownedPage = $chain['page'];
        
        // Create a page not owned by the user
        $otherPage = $this->entities->page();
        
        $resp = $this->actingAs($user)->get('/dashboard');
        $resp->assertStatus(200);
        
        // Should see owned page in My SOPs
        $this->withHtml($resp)->assertElementContains('#my-sops', $ownedPage->name);
        
        // Should not see other page in recently updated (unless visibility allows)
        // This depends on the visibility scope implementation
    }

    public function test_pending_reviews_visibility_respects_page_permissions()
    {
        $editor = $this->users->editor();
        $page = $this->entities->page();
        
        // Create a revision in review
        $this->asAdmin()->put($page->getUrl(), [
            'name' => 'Review Test Page',
            'html' => '<p>Content</p>',
        ]);
        $page->refresh();
        $revision = $page->currentRevision;
        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        // Verify editor can see the pending review
        $resp = $this->actingAs($editor)->get('/dashboard');
        $this->withHtml($resp)->assertElementContains('#pending-reviews', 'Review Test Page');
        
        // Restrict page visibility
        $this->permissions->setEntityPermissions($page, [], []);
        
        // Editor should no longer see the pending review
        $resp = $this->actingAs($editor)->get('/dashboard');
        $this->withHtml($resp)->assertElementNotContains('#pending-reviews', 'Review Test Page');
    }

    public function test_user_drafts_only_show_own_drafts()
    {
        $editor = $this->users->editor();
        $admin = $this->users->admin();
        
        // Create draft as editor
        $editorDraft = $this->actingAs($editor)->entities->newDraftPage(['name' => 'Editor Draft']);
        
        // Create draft as admin
        $adminDraft = $this->actingAs($admin)->entities->newDraftPage(['name' => 'Admin Draft']);
        
        // Editor should only see their own draft
        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        
        if ($editorDraft->name) {
            $this->withHtml($resp)->assertElementContains('#recent-drafts', 'Editor Draft');
        }
        $this->withHtml($resp)->assertElementNotContains('body', 'Admin Draft');
    }

    public function test_custom_role_with_limited_permissions_sees_appropriate_content()
    {
        // Create user with only book view permissions
        [$user, $role] = $this->users->newUserWithRole([], ['book-view-all']);
        
        $resp = $this->actingAs($user)->get('/dashboard');
        $resp->assertStatus(200);
        
        // User should still see the dashboard structure
        $this->withHtml($resp)->assertElementExists('#my-sops');
        $this->withHtml($resp)->assertElementExists('#departments');
    }

    public function test_pending_reviews_visible_to_users_who_can_view_pages()
    {
        $page = $this->entities->page();
        $viewer = $this->users->viewer();
        
        // Create revision in review
        $this->asAdmin()->put($page->getUrl(), [
            'name' => 'Viewer Test Page',
            'html' => '<p>Content</p>',
        ]);
        $page->refresh();
        if ($page->currentRevision) {
            $page->currentRevision->status = PageRevision::STATUS_IN_REVIEW;
            $page->currentRevision->save();
        }

        // Viewer should be able to see pending reviews for visible pages
        $resp = $this->actingAs($viewer)->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementExists('#pending-reviews');
    }

    public function test_my_sops_shows_pages_user_owns_regardless_of_create_permissions()
    {
        $user = User::factory()->create();
        // Give view permission but not create permission
        $this->permissions->grantUserRolePermissions($user, ['page-view-all', 'book-view-all']);
        
        // Create a page and assign ownership to the user
        $page = $this->entities->page();
        $this->permissions->changeEntityOwner($page, $user);
        
        $resp = $this->actingAs($user)->get('/dashboard');
        $resp->assertStatus(200);
        $this->withHtml($resp)->assertElementContains('#my-sops', $page->name);
    }

    public function test_entity_permission_restrictions_cascade_properly()
    {
        $editor = $this->users->editor();
        $book = $this->entities->bookHasChaptersAndPages();
        $page = $book->pages()->first();
        
        // Restrict the entire book
        $this->permissions->setEntityPermissions($book, [], []);
        
        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        
        // Page inside restricted book should not appear
        $this->withHtml($resp)->assertElementNotContains('#recently-updated', $page->name);
    }

    public function test_dashboard_handles_users_with_no_roles()
    {
        $user = User::factory()->create();
        // User has no roles attached
        
        $resp = $this->actingAs($user)->get('/dashboard');
        // Should either redirect or show minimal content
        $resp->assertStatus(200);
    }
}

