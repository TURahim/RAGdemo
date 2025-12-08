<?php

namespace Tests\Permissions;

use BookStack\Permissions\JointPermissionBuilder;
use BookStack\Permissions\Models\JointPermission;
use BookStack\Permissions\PermissionConsistencyService;
use BookStack\Users\Models\Role;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionConsistencyTest extends TestCase
{
    protected PermissionConsistencyService $consistencyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->consistencyService = app(PermissionConsistencyService::class);
    }

    public function test_audit_entity_returns_consistent_for_valid_entity()
    {
        $page = $this->entities->page();
        
        $result = $this->consistencyService->auditEntity($page);
        
        $this->assertTrue($result['consistent']);
        $this->assertEmpty($result['issues']);
    }

    public function test_audit_entity_detects_missing_permissions()
    {
        $page = $this->entities->page();
        
        // Delete some permissions for this entity
        JointPermission::query()
            ->where('entity_type', 'page')
            ->where('entity_id', $page->id)
            ->limit(1)
            ->delete();
        
        $result = $this->consistencyService->auditEntity($page);
        
        $this->assertFalse($result['consistent']);
        $this->assertNotEmpty($result['issues']);
        $this->assertEquals('missing_permissions', $result['issues'][0]['type']);
    }

    public function test_find_entities_without_permissions_detects_orphaned_entities()
    {
        // Get an existing page
        $page = $this->entities->page();
        
        // Delete all its permissions
        JointPermission::query()
            ->where('entity_type', 'page')
            ->where('entity_id', $page->id)
            ->delete();
        
        $orphanedEntities = $this->consistencyService->findEntitiesWithoutPermissions();
        
        $this->assertGreaterThanOrEqual(1, $orphanedEntities->count());
        $this->assertTrue($orphanedEntities->contains('id', $page->id));
        
        // Repair for cleanup
        $this->consistencyService->repairEntity($page);
    }

    public function test_find_orphaned_permissions_detects_permissions_for_deleted_entities()
    {
        // Create orphaned permissions manually (for an entity ID that doesn't exist)
        $fakeEntityId = 999999;
        $role = Role::first();
        
        DB::table('joint_permissions')->insert([
            'entity_type' => 'page',
            'entity_id' => $fakeEntityId,
            'role_id' => $role->id,
            'status' => 1,
            'owner_id' => null,
        ]);
        
        $result = $this->consistencyService->findOrphanedPermissions();
        
        $this->assertGreaterThan(0, $result['count']);
        $this->assertArrayHasKey('page', $result['by_type']);
        
        // Cleanup
        DB::table('joint_permissions')
            ->where('entity_type', 'page')
            ->where('entity_id', $fakeEntityId)
            ->delete();
    }

    public function test_repair_entity_rebuilds_permissions()
    {
        $page = $this->entities->page();
        
        // Delete permissions
        JointPermission::query()
            ->where('entity_type', 'page')
            ->where('entity_id', $page->id)
            ->delete();
        
        // Verify they're gone
        $countBefore = JointPermission::query()
            ->where('entity_type', 'page')
            ->where('entity_id', $page->id)
            ->count();
        $this->assertEquals(0, $countBefore);
        
        // Repair
        $this->consistencyService->repairEntity($page);
        
        // Verify they're restored
        $countAfter = JointPermission::query()
            ->where('entity_type', 'page')
            ->where('entity_id', $page->id)
            ->count();
        $this->assertGreaterThan(0, $countAfter);
    }

    public function test_cleanup_orphaned_permissions_removes_stale_records()
    {
        // Create an orphaned permission record
        $fakePageId = 999999;
        $role = Role::first();
        
        DB::table('joint_permissions')->insert([
            'entity_type' => 'page',
            'entity_id' => $fakePageId,
            'role_id' => $role->id,
            'status' => 1,
            'owner_id' => null,
        ]);
        
        // Verify it exists
        $existsBefore = DB::table('joint_permissions')
            ->where('entity_type', 'page')
            ->where('entity_id', $fakePageId)
            ->exists();
        $this->assertTrue($existsBefore);
        
        // Cleanup
        $deletedCount = $this->consistencyService->cleanupOrphanedPermissions();
        
        // Verify it's gone
        $existsAfter = DB::table('joint_permissions')
            ->where('entity_type', 'page')
            ->where('entity_id', $fakePageId)
            ->exists();
        $this->assertFalse($existsAfter);
        $this->assertGreaterThan(0, $deletedCount);
    }

    public function test_get_statistics_returns_expected_structure()
    {
        $stats = $this->consistencyService->getStatistics();
        
        $this->assertArrayHasKey('total_entities', $stats);
        $this->assertArrayHasKey('total_permissions', $stats);
        $this->assertArrayHasKey('entities_without_permissions', $stats);
        $this->assertArrayHasKey('orphaned_permissions', $stats);
        $this->assertArrayHasKey('roles_count', $stats);
        $this->assertArrayHasKey('expected_permissions', $stats);
        $this->assertArrayHasKey('is_healthy', $stats);
    }

    public function test_is_healthy_returns_true_for_consistent_system()
    {
        // Rebuild all permissions to ensure consistency
        app(JointPermissionBuilder::class)->rebuildForAll();
        
        $isHealthy = $this->consistencyService->isHealthy();
        
        $this->assertTrue($isHealthy);
    }

    public function test_is_healthy_returns_false_when_issues_exist()
    {
        // Create an orphaned permission
        DB::table('joint_permissions')->insert([
            'entity_type' => 'page',
            'entity_id' => 888888,
            'role_id' => Role::first()->id,
            'status' => 1,
            'owner_id' => null,
        ]);
        
        $isHealthy = $this->consistencyService->isHealthy();
        
        $this->assertFalse($isHealthy);
        
        // Cleanup
        $this->consistencyService->cleanupOrphanedPermissions();
    }

    public function test_repair_all_fixes_multiple_issues()
    {
        // Get an existing page and remove its permissions
        $page = $this->entities->page();
        
        DB::table('joint_permissions')
            ->where('entity_type', 'page')
            ->where('entity_id', $page->id)
            ->delete();
        
        // Create orphaned permission
        DB::table('joint_permissions')->insert([
            'entity_type' => 'page',
            'entity_id' => 777777,
            'role_id' => Role::first()->id,
            'status' => 1,
            'owner_id' => null,
        ]);
        
        // Verify issues exist
        $this->assertFalse($this->consistencyService->isHealthy());
        
        // Repair all
        $repairedCount = $this->consistencyService->repairAll();
        
        // Page should now have permissions
        $pagePermissions = DB::table('joint_permissions')
            ->where('entity_type', 'page')
            ->where('entity_id', $page->id)
            ->count();
        $this->assertGreaterThan(0, $pagePermissions);
        
        // Orphaned should be gone
        $orphanedExists = DB::table('joint_permissions')
            ->where('entity_type', 'page')
            ->where('entity_id', 777777)
            ->exists();
        $this->assertFalse($orphanedExists);
    }

    public function test_audit_command_shows_statistics()
    {
        $this->artisan('bookstack:audit-permissions', ['--stats' => true])
            ->assertExitCode(0);
    }

    public function test_audit_command_dry_run_does_not_modify()
    {
        // Create orphaned permission
        DB::table('joint_permissions')->insert([
            'entity_type' => 'page',
            'entity_id' => 666666,
            'role_id' => Role::first()->id,
            'status' => 1,
            'owner_id' => null,
        ]);
        
        $this->artisan('bookstack:audit-permissions', ['--dry-run' => true, '--fix' => true])
            ->assertExitCode(0);
        
        // Orphaned should still exist (dry run)
        $exists = DB::table('joint_permissions')
            ->where('entity_type', 'page')
            ->where('entity_id', 666666)
            ->exists();
        $this->assertTrue($exists);
        
        // Cleanup manually
        DB::table('joint_permissions')
            ->where('entity_type', 'page')
            ->where('entity_id', 666666)
            ->delete();
    }

    public function test_audit_command_fix_repairs_issues()
    {
        // Create orphaned permission
        DB::table('joint_permissions')->insert([
            'entity_type' => 'page',
            'entity_id' => 555555,
            'role_id' => Role::first()->id,
            'status' => 1,
            'owner_id' => null,
        ]);
        
        $this->artisan('bookstack:audit-permissions', ['--fix' => true])
            ->assertExitCode(0);
        
        // Orphaned should be gone
        $exists = DB::table('joint_permissions')
            ->where('entity_type', 'page')
            ->where('entity_id', 555555)
            ->exists();
        $this->assertFalse($exists);
    }

    public function test_permission_rebuild_after_approval_maintains_consistency()
    {
        $page = $this->entities->page();
        $revision = $page->revisions()->first();
        
        if (!$revision) {
            $this->markTestSkipped('No revision available for testing');
        }
        
        // Check consistency before
        $resultBefore = $this->consistencyService->auditEntity($page);
        $this->assertTrue($resultBefore['consistent']);
        
        // Simulate approval (which triggers permission rebuild)
        $page->rebuildPermissions();
        
        // Check consistency after
        $resultAfter = $this->consistencyService->auditEntity($page);
        $this->assertTrue($resultAfter['consistent']);
    }

    public function test_dashboard_shows_permission_health_for_admin()
    {
        $admin = $this->users->admin();
        
        $response = $this->actingAs($admin)->get('/dashboard');
        
        $response->assertStatus(200);
        $response->assertSee('Permission Health');
    }

    public function test_dashboard_hides_permission_health_for_non_admin()
    {
        $editor = $this->users->editor();
        
        $response = $this->actingAs($editor)->get('/dashboard');
        
        $response->assertStatus(200);
        $response->assertDontSee('Permission Health');
    }

    public function test_audit_all_checks_all_entity_types()
    {
        // Ensure we have at least one of each entity type
        $shelf = $this->entities->shelf();
        $book = $this->entities->book();
        $chapter = $this->entities->chapter();
        $page = $this->entities->page();
        
        // Run audit
        $issues = $this->consistencyService->auditAll();
        
        // Should complete without errors (issues may be empty if all consistent)
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $issues);
    }

    public function test_statistics_expected_permissions_calculation()
    {
        $stats = $this->consistencyService->getStatistics();
        
        $expectedCalc = $stats['total_entities'] * $stats['roles_count'];
        
        $this->assertEquals($expectedCalc, $stats['expected_permissions']);
    }
}

