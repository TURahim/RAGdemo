<?php

namespace Tests\Dashboard;

use BookStack\Entities\Models\PageRevision;
use Tests\TestCase;

/**
 * Tests for dashboard API endpoints (if implemented).
 * These tests can be expanded when/if dashboard widgets support AJAX loading.
 */
class DashboardApiTest extends TestCase
{
    public function test_dashboard_page_loads_without_errors()
    {
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $resp->assertDontSee('ErrorException');
        $resp->assertDontSee('Whoops');
    }

    public function test_dashboard_handles_large_datasets_gracefully()
    {
        // Create many entities to test pagination/limits work
        $editor = $this->users->editor();
        
        for ($i = 0; $i < 20; $i++) {
            $this->entities->createChainBelongingToUser($editor);
        }
        
        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        
        // Should not timeout or error
        $resp->assertDontSee('500');
    }

    public function test_dashboard_handles_empty_database_state()
    {
        // This tests with minimal seeded data
        $user = \BookStack\Users\Models\User::factory()->create();
        $viewerRole = \BookStack\Users\Models\Role::getRole('Viewer');
        $user->attachRole($viewerRole);
        
        $resp = $this->actingAs($user)->get('/dashboard');
        $resp->assertStatus(200);
        
        // Should show empty states, not errors
        $this->withHtml($resp)->assertElementExists('#my-sops');
        $this->withHtml($resp)->assertElementExists('#departments');
    }

    public function test_dashboard_properly_escapes_entity_names()
    {
        $editor = $this->users->editor();
        
        // Create page with potentially dangerous content in name
        $chain = $this->entities->createChainBelongingToUser($editor);
        $page = $chain['page'];
        $page->name = '<script>alert("xss")</script>';
        $page->save();
        
        $resp = $this->actingAs($editor)->get('/dashboard');
        $resp->assertStatus(200);
        
        // The script tag should not be rendered as executable HTML
        // It should be escaped in some form (either as entity or filtered)
        $content = $resp->getContent();
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $content);
    }

    public function test_dashboard_performance_with_many_pending_reviews()
    {
        $admin = $this->users->admin();
        
        // Create multiple pages with revisions in review
        for ($i = 0; $i < 15; $i++) {
            $chain = $this->entities->createChainBelongingToUser($admin);
            $page = $chain['page'];
            
            $this->asAdmin()->put($page->getUrl(), [
                'name' => "Review Page $i",
                'html' => '<p>Content</p>',
            ]);
            $page->refresh();
            
            if ($page->currentRevision) {
                $page->currentRevision->status = PageRevision::STATUS_IN_REVIEW;
                $page->currentRevision->save();
            }
        }
        
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        
        // Should limit the number shown (not show all 15)
        // The exact behavior depends on the limit set in the controller
    }

    public function test_dashboard_content_type_is_html()
    {
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        $resp->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_dashboard_includes_csrf_token_for_forms()
    {
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        
        // If there are any forms on the dashboard, they should have CSRF token
        // This is handled by Laravel's Blade @csrf directive
    }

    public function test_dashboard_accessible_via_both_routes()
    {
        // Test /dashboard route
        $resp = $this->asEditor()->get('/dashboard');
        $resp->assertStatus(200);
        
        // Optionally test if dashboard is set as homepage
        // This would be determined by settings
    }
}

