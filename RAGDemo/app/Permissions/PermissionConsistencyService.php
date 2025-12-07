<?php

namespace BookStack\Permissions;

use BookStack\Entities\Models\Entity;
use BookStack\Entities\Queries\EntityQueries;
use BookStack\Permissions\Models\JointPermission;
use BookStack\Users\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for auditing and repairing permission consistency.
 * Detects mismatches between entities and their joint_permissions cache,
 * and can auto-repair them by triggering rebuilds.
 * 
 * Note: BookStack 2025 uses a unified 'entities' table instead of separate
 * bookshelves/books/chapters/pages tables. Entity types are differentiated
 * by the 'type' column.
 */
class PermissionConsistencyService
{
    /**
     * Entity types in the system.
     */
    protected const ENTITY_TYPES = ['bookshelf', 'book', 'chapter', 'page'];

    public function __construct(
        protected JointPermissionBuilder $permissionBuilder,
        protected EntityQueries $entityQueries,
    ) {
    }

    /**
     * Audit a single entity for permission consistency.
     * Checks if the entity has the expected joint_permissions entries.
     *
     * @return array{consistent: bool, issues: array}
     */
    public function auditEntity(Entity $entity): array
    {
        $issues = [];

        // Get all roles
        $roleIds = Role::query()->pluck('id')->toArray();

        // Get existing joint permissions for this entity
        $existingPermissions = JointPermission::query()
            ->where('entity_type', $entity->getMorphClass())
            ->where('entity_id', $entity->id)
            ->pluck('role_id')
            ->toArray();

        // Check if permissions exist for all roles
        $missingRoles = array_diff($roleIds, $existingPermissions);
        if (!empty($missingRoles)) {
            $issues[] = [
                'type' => 'missing_permissions',
                'entity_type' => $entity->getMorphClass(),
                'entity_id' => $entity->id,
                'entity_name' => $entity->name,
                'missing_role_ids' => $missingRoles,
            ];
        }

        // Check for orphaned permissions (permissions for non-existent roles)
        $orphanedRoles = array_diff($existingPermissions, $roleIds);
        if (!empty($orphanedRoles)) {
            $issues[] = [
                'type' => 'orphaned_permissions',
                'entity_type' => $entity->getMorphClass(),
                'entity_id' => $entity->id,
                'entity_name' => $entity->name,
                'orphaned_role_ids' => $orphanedRoles,
            ];
        }

        return [
            'consistent' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Audit all entities for permission consistency.
     *
     * @return Collection<int, array>
     */
    public function auditAll(): Collection
    {
        $allIssues = collect();

        // Audit shelves
        $this->entityQueries->shelves->start()->withTrashed()
            ->chunk(100, function ($shelves) use ($allIssues) {
                foreach ($shelves as $shelf) {
                    $result = $this->auditEntity($shelf);
                    if (!$result['consistent']) {
                        $allIssues->push(...$result['issues']);
                    }
                }
            });

        // Audit books with their children
        $this->entityQueries->books->start()->withTrashed()
            ->with(['chapters', 'pages'])
            ->chunk(50, function ($books) use ($allIssues) {
                foreach ($books as $book) {
                    $result = $this->auditEntity($book);
                    if (!$result['consistent']) {
                        $allIssues->push(...$result['issues']);
                    }

                    foreach ($book->chapters as $chapter) {
                        $result = $this->auditEntity($chapter);
                        if (!$result['consistent']) {
                            $allIssues->push(...$result['issues']);
                        }
                    }

                    foreach ($book->pages as $page) {
                        $result = $this->auditEntity($page);
                        if (!$result['consistent']) {
                            $allIssues->push(...$result['issues']);
                        }
                    }
                }
            });

        return $allIssues;
    }

    /**
     * Find entities that have no joint permissions at all.
     * Uses the unified 'entities' table (BookStack 2025 schema).
     *
     * @return Collection<int, Entity>
     */
    public function findEntitiesWithoutPermissions(): Collection
    {
        $orphanedEntities = collect();

        foreach (self::ENTITY_TYPES as $entityType) {
            // Get entity IDs that have permissions
            $entitiesWithPerms = JointPermission::query()
                ->where('entity_type', $entityType)
                ->distinct()
                ->pluck('entity_id');

            // Find entities of this type without permissions using the unified entities table
            $orphaned = DB::table('entities')
                ->where('type', $entityType)
                ->whereNotIn('id', $entitiesWithPerms)
                ->get();

            foreach ($orphaned as $row) {
                // Convert to proper entity model
                $entity = $this->getEntityQueryByType($entityType)
                    ?->withTrashed()
                    ->find($row->id);
                
                if ($entity) {
                    $orphanedEntities->push($entity);
                }
            }
        }

        return $orphanedEntities;
    }

    /**
     * Find orphaned joint_permissions (permissions for deleted entities).
     * Uses the unified 'entities' table (BookStack 2025 schema).
     *
     * @return array{count: int, by_type: array}
     */
    public function findOrphanedPermissions(): array
    {
        $orphanedByType = [];

        foreach (self::ENTITY_TYPES as $entityType) {
            $orphanedCount = DB::table('joint_permissions')
                ->leftJoin('entities', function ($join) use ($entityType) {
                    $join->on('joint_permissions.entity_id', '=', 'entities.id')
                        ->where('entities.type', '=', $entityType);
                })
                ->where('joint_permissions.entity_type', $entityType)
                ->whereNull('entities.id')
                ->count();

            if ($orphanedCount > 0) {
                $orphanedByType[$entityType] = $orphanedCount;
            }
        }

        return [
            'count' => array_sum($orphanedByType),
            'by_type' => $orphanedByType,
        ];
    }

    /**
     * Repair permissions for a single entity.
     */
    public function repairEntity(Entity $entity): void
    {
        $this->permissionBuilder->rebuildForEntity($entity);
        
        Log::info('Permission consistency: Repaired permissions for entity', [
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->id,
            'entity_name' => $entity->name,
        ]);
    }

    /**
     * Repair all permission inconsistencies.
     * Returns the count of entities repaired.
     */
    public function repairAll(): int
    {
        // First, find entities without permissions
        $orphanedEntities = $this->findEntitiesWithoutPermissions();
        
        foreach ($orphanedEntities as $entity) {
            $this->repairEntity($entity);
        }

        $repairedCount = $orphanedEntities->count();

        // Clean up orphaned permissions (for deleted entities)
        $this->cleanupOrphanedPermissions();

        Log::info('Permission consistency: Full repair completed', [
            'entities_repaired' => $repairedCount,
        ]);

        return $repairedCount;
    }

    /**
     * Clean up joint_permissions for entities that no longer exist.
     * Uses the unified 'entities' table (BookStack 2025 schema).
     */
    public function cleanupOrphanedPermissions(): int
    {
        $deletedCount = 0;

        foreach (self::ENTITY_TYPES as $entityType) {
            // Find and delete orphaned permissions for this entity type
            $orphanedIds = DB::table('joint_permissions')
                ->leftJoin('entities', function ($join) use ($entityType) {
                    $join->on('joint_permissions.entity_id', '=', 'entities.id')
                        ->where('entities.type', '=', $entityType);
                })
                ->where('joint_permissions.entity_type', $entityType)
                ->whereNull('entities.id')
                ->pluck('joint_permissions.entity_id')
                ->unique()
                ->toArray();

            if (!empty($orphanedIds)) {
                $deleted = DB::table('joint_permissions')
                    ->where('entity_type', $entityType)
                    ->whereIn('entity_id', $orphanedIds)
                    ->delete();
                
                $deletedCount += $deleted;
            }
        }

        if ($deletedCount > 0) {
            Log::info('Permission consistency: Cleaned up orphaned permissions', [
                'deleted_count' => $deletedCount,
            ]);
        }

        return $deletedCount;
    }

    /**
     * Perform a full rebuild of all permissions.
     * This is a heavy operation and should be used sparingly.
     */
    public function rebuildAll(): void
    {
        $this->permissionBuilder->rebuildForAll();
        
        Log::info('Permission consistency: Full permission rebuild completed');
    }

    /**
     * Get statistics about permission consistency.
     *
     * @return array{
     *     total_entities: int,
     *     total_permissions: int,
     *     entities_without_permissions: int,
     *     orphaned_permissions: int,
     *     roles_count: int,
     *     is_healthy: bool
     * }
     */
    public function getStatistics(): array
    {
        // Count entities from unified entities table
        $totalEntities = DB::table('entities')
            ->whereIn('type', self::ENTITY_TYPES)
            ->count();

        $totalPermissions = JointPermission::count();
        $rolesCount = Role::count();

        $entitiesWithoutPermissions = $this->findEntitiesWithoutPermissions()->count();
        $orphanedPermissions = $this->findOrphanedPermissions();

        $isHealthy = $entitiesWithoutPermissions === 0 && $orphanedPermissions['count'] === 0;

        return [
            'total_entities' => $totalEntities,
            'total_permissions' => $totalPermissions,
            'entities_without_permissions' => $entitiesWithoutPermissions,
            'orphaned_permissions' => $orphanedPermissions['count'],
            'orphaned_permissions_by_type' => $orphanedPermissions['by_type'],
            'roles_count' => $rolesCount,
            'expected_permissions' => $totalEntities * $rolesCount,
            'is_healthy' => $isHealthy,
        ];
    }

    /**
     * Check if the permission system is in a healthy state.
     */
    public function isHealthy(): bool
    {
        return $this->getStatistics()['is_healthy'];
    }

    /**
     * Get the entity query builder for a given entity type.
     */
    protected function getEntityQueryByType(string $type): ?\Illuminate\Database\Eloquent\Builder
    {
        return match ($type) {
            'bookshelf' => $this->entityQueries->shelves->start(),
            'book' => $this->entityQueries->books->start(),
            'chapter' => $this->entityQueries->chapters->start(),
            'page' => $this->entityQueries->pages->start(),
            default => null,
        };
    }
}

