# SOP App – UI/UX Overhaul Plan (Single-Tenant)

## 📊 Overall Progress

| Phase | Status | Tests |
|-------|--------|-------|
| Phase 1: Terminology + Rebrand | ✅ Complete | — |
| Phase 2: Feature Pruning | ✅ Complete | — |
| Phase 3: Custom Dashboard | ✅ Complete | 62 tests |
| Phase 4a: Approval Schema | ✅ Complete | — |
| Phase 4b: Approval Workflow UI | ✅ Complete | 23 tests |
| Phase 4c: Periodic Review Reminders | ✅ Complete | 19 tests |
| Phase 5: Search UX Enhancements | ✅ Complete | 29 tests |
| Phase 6: Permission Consistency | ✅ Complete | 20 tests |

**Total Test Coverage: 153 tests** ✅

---

## Architecture Decision
**Single-tenant, single instance** deployment. No multi-tenancy plumbing required—deploy one BookStack instance per environment.

---

## ⚠️ Critical Architecture Decisions

### 1. Approval Status = Per Revision (Not Per Page)
```
❌ WRONG: pages.status = 'approved'
✅ RIGHT: page_revisions.status = 'approved'
```
**Why:** SOP approvals must lock to exact content. Page-level status becomes stale after edits → compliance risk.

### 2. Version Locking on Approval
When approved, the editor is disabled. New edits create a fresh draft revision requiring re-approval.

### 3. Search UX = Enhanced, Not Removed
Add department/status/facet filters. Do not strip search functionality.

### 4. Permission Cascades via JointPermissionBuilder
BookStack already handles shelf→book→page cascading. Trigger `JointPermissionBuilder::rebuildForEntity()` after workflow state changes to maintain consistency.

---

## Phase 1: Terminology Overrides + Rebrand
**Effort: Low ✅**

### Entity Mapping
| BookStack | SOP App |
|-----------|---------|
| Shelf | Department |
| Book | Knowledge Domain |
| Chapter | SOP Group / Subprocess |
| Page | SOP Document |

### Files to Update
- `lang/en/*.php` – Override translation keys (`entities.shelves`, `entities.books`, etc.)
- `resources/views/entities/` – Tweak Blade partials for SOP-specific hints/icons
- Header/footer/logo – Blade templates + CSS/theming in `resources/sass/` or theme overrides

---

## Phase 2: Feature Pruning via Permissions
**Effort: Low ✅**

Disable/hide for standard users (no code removal):
- Comments
- Favourites
- Watch lists
- Recycle bin access
- Audit log exports
- Templates (if not needed)

**Method:** Use BookStack's role permission system first. If still noisy, remove menu items in Blade partials.

### Nav Simplification
Edit Blade partials to hide:
- Unused sidebar items
- Extra action buttons
- Footer links

---

## Phase 3: Custom Dashboard ✅
**Effort: Moderate — COMPLETED**

Build a focused landing page with:
- **My SOPs** – SOPs the user has authored/owns
- **Recently Updated** – Latest changes across departments
- **Department Shortcuts** – Quick links to each department (shelf)
- **Pending Reviews** – (if approval workflow added later)

### Implementation
- New controller: `app/Http/Controllers/DashboardController.php`
- New view: `resources/views/dashboard/index.blade.php`
- Reuse existing queries from `PageRepo`, `BookRepo`, `ShelfRepo`
- Update home route or make dashboard the default landing

---

## Phase 4: SOP Workflow Enhancements ✅
**Effort: Moderate-Hard — COMPLETED (4a, 4b)**

### 4a. Approval Workflow (Revision-Based) ✅

> ⚠️ **Critical:** Store approval status per **revision**, not on the page itself.
> Approvals must correspond to the exact revision for compliance.

#### Schema Change (Migration) ✅

> ⚠️ **Note:** BookStack 2025 uses unified `entities` + `entity_page_data` tables instead of the old `pages` table. Page-specific fields go in `entity_page_data`.

```sql
-- page_revisions table (approval per revision)
ALTER TABLE page_revisions ADD COLUMN status VARCHAR(20) DEFAULT 'draft';
ALTER TABLE page_revisions ADD COLUMN approved_by INT UNSIGNED NULL;
ALTER TABLE page_revisions ADD COLUMN approved_at TIMESTAMP NULL;
ALTER TABLE page_revisions ADD COLUMN review_notes TEXT NULL;

-- entity_page_data table (page-level tracking)
ALTER TABLE entity_page_data ADD COLUMN approved_revision_id INT UNSIGNED NULL;
ALTER TABLE entity_page_data ADD COLUMN next_review_date DATE NULL;
ALTER TABLE entity_page_data ADD COLUMN review_interval_days SMALLINT UNSIGNED NULL;
```

#### Why Revision-Based?
| ❌ Page-level status | ✅ Revision-level status |
|---------------------|-------------------------|
| Approval applies to obsolete content after edits | Approval locked to exact content hash |
| Compliance risk | Audit-safe |
| No history of what was approved | Full approval trail |

#### Implementation ✅
- ✅ Update `PageRevision` model with `status`, `approved_by`, `approved_at`, `review_notes`
- ✅ Update `EntityPageData` model with `approved_revision_id`, `next_review_date`, `review_interval_days`
- ✅ Update `Page` model with approval helper methods
- ✅ Add `RevisionApprovalService` for status transitions
- ✅ Add approval routes + UI buttons per revision
- ✅ Filter views by revision status (e.g., "Pending Approval" list)

### 4b. Version Locking on Approval ✅

When a revision is approved:

| Behavior | Implementation |
|----------|----------------|
| Disable editor UI | ✅ Check `page.approved_revision_id` matches latest; if so, show "Create New Draft" |
| Show banner "Approved Version" | ✅ Blade partial conditional on status |
| Allow Admin override | ✅ Permission check `userCan('settings-manage')` |
| New edits create draft revision | ✅ Auto-set new revision to `draft` status |

#### Flow
```
[Approved Revision v3] ──user edits──► [New Draft Revision v4]
                                              │
                                              ▼
                                      [In Review v4]
                                              │
                                              ▼
                                      [Approved v4] (replaces v3 as current)
```

### 4c. Periodic Review Reminders ✅
- ✅ Schema fields added (`next_review_date`, `review_interval_days`)
- ✅ Dashboard widget for "SOPs Due for Review" 
- ✅ Scheduled job (Laravel command) to check overdue reviews
- ✅ Email notification system for owners/reviewers

---

## Phase 5: Search UX Enhancements ✅
**Effort: Low-Moderate — COMPLETED**

> ⚠️ **Do NOT remove search.** Enhance it with SOP-relevant filters.

### Improvements Implemented
| Feature | Implementation |
|---------|----------------|
| Search within Department | ✅ `filterInDepartment()` in `SearchRunner` |
| Category/Domain facets | ✅ Faceted sidebar with department counts |
| Status facets | ✅ Filter by `approved`, `in_review`, `draft`, `rejected` |
| My SOPs filter | ✅ Quick filter button + `owned_by:me` |
| Pending Review filter | ✅ Quick filter for approvers |

### Files Created
| File | Purpose |
|------|---------|
| `app/Search/SearchFacetCalculator.php` | Service for calculating facet counts |
| `resources/views/search/parts/department-filter.blade.php` | Department dropdown UI |
| `resources/views/search/parts/status-filter.blade.php` | Status checkboxes UI |
| `resources/views/search/parts/facets.blade.php` | Faceted results sidebar |
| `tests/Search/SearchEnhancementsTest.php` | 25 tests for new functionality |

### Files Modified
| File | Changes |
|------|---------|
| `app/Search/SearchRunner.php` | Added `filterInDepartment()`, `filterApprovalStatus()` |
| `app/Search/SearchOptions.php` | Handle array filter values (checkboxes) |
| `app/Search/SearchController.php` | Pass departments and facets to view |
| `resources/views/search/all.blade.php` | Added quick filters, department filter, status filter, facets sidebar, active filters display |
| `lang/en/entities.php` | Added 15+ new translation strings |

### Search Filters Usage
```
# Filter by department (shelf)
{in_department:5}        → Show entities in department ID 5
{-in_department:5}       → Exclude entities in department ID 5

# Filter by approval status (pages only)
{approval_status:approved}              → Show approved pages
{approval_status:in_review}             → Show pages pending review
{approval_status:draft}                 → Show draft pages
{approval_status:rejected}              → Show rejected pages
{approval_status:approved|in_review}    → Show approved OR pending review
```

### Quick Filters
- **My SOPs**: Pre-populates `{owned_by:me} {type:page}`
- **Pending Review**: Pre-populates `{approval_status:in_review} {type:page}` (visible to approvers only)

### Run Tests
```bash
docker-compose exec app php artisan test tests/Search/SearchEnhancementsTest.php
```

---

## Phase 6: Permission Consistency ✅
**Effort: Low — COMPLETED**

### Problem
Department (shelf) permissions cascade to books & pages, but workflow changes can cause mismatches.

### Solution
Leverage existing `JointPermissionBuilder` and trigger rebuilds after:
- Approval status changes (already implemented in `RevisionApprovalService`)
- Entity moves between departments (already implemented via `rebuildPermissions()`)
- Role/permission updates (already handled by BookStack core)

### Implementation

#### PermissionConsistencyService
Created a comprehensive service for auditing and repairing permission consistency:

```php
// Audit a single entity
$result = $consistencyService->auditEntity($entity);
// Returns: ['consistent' => bool, 'issues' => array]

// Audit all entities
$issues = $consistencyService->auditAll();

// Find entities without permissions
$orphanedEntities = $consistencyService->findEntitiesWithoutPermissions();

// Find orphaned permissions (for deleted entities)
$orphaned = $consistencyService->findOrphanedPermissions();
// Returns: ['count' => int, 'by_type' => array]

// Repair single entity
$consistencyService->repairEntity($entity);

// Repair all issues
$repairedCount = $consistencyService->repairAll();

// Cleanup orphaned permissions
$deletedCount = $consistencyService->cleanupOrphanedPermissions();

// Full rebuild (heavy operation)
$consistencyService->rebuildAll();

// Get statistics
$stats = $consistencyService->getStatistics();
// Returns: total_entities, total_permissions, entities_without_permissions,
//          orphaned_permissions, roles_count, expected_permissions, is_healthy

// Quick health check
$isHealthy = $consistencyService->isHealthy();
```

#### Artisan Command
```bash
# Show permission statistics only
php artisan bookstack:audit-permissions --stats

# Audit and show issues
php artisan bookstack:audit-permissions

# Dry run (show what would be fixed)
php artisan bookstack:audit-permissions --dry-run --fix

# Automatically repair issues
php artisan bookstack:audit-permissions --fix

# Full permission rebuild (heavy operation, requires confirmation)
php artisan bookstack:audit-permissions --rebuild
```

#### Scheduled Job
Weekly permission audit runs automatically (Sunday at 3:00 AM):
```php
// In app/Console/Kernel.php
$schedule->command('bookstack:audit-permissions --fix')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
```

#### Dashboard Widget (Admin Only)
- Shows permission health status on the SOP Dashboard
- Displays entity and permission counts
- Warns about inconsistencies with specific counts
- Links to maintenance page for repairs
- Cached for 5 minutes to avoid heavy queries

---

## Out of Scope (Initial Release)
- Bulk import from Word/PDF (requires custom parsing layer)
- Multi-tenancy (not needed for single-tenant)
- Heavy customization of editors

---

## Built-in Features to Leverage (No Changes Needed)
| Feature | BookStack Support |
|---------|-------------------|
| Versioning / Revisions | ✅ Built-in |
| Audit trail (who changed what) | ✅ Activity log |
| PDF export per SOP | ✅ Built-in |
| Access control per department | ✅ Entity permissions |
| Search | ✅ Full-text with filters |
| Mobile-friendly | ✅ Responsive by default |

---

## Implementation Sequence

```
1. ✅ Terminology + Rebrand (Phase 1) — COMPLETE
   └── Updated lang files, Blade partials, CSS/logo

2. ✅ Permission Lockdown (Phase 2) — COMPLETE
   └── Configured roles, pruned nav

3. ✅ Custom Dashboard (Phase 3) — COMPLETE (62 tests)
   └── New controller/view, routing, 7 widgets

4. ✅ Approval Workflow (Phase 4) — COMPLETE (42 tests)
   ├── ✅ 4a. Schema: status/approved_by/approved_at on page_revisions
   ├── ✅ 4b. Version locking + admin override + UI (23 tests)
   └── ✅ 4c. Review reminders + scheduled job (19 tests)

5. ✅ Search Enhancements (Phase 5) — COMPLETE (25 tests)
   ├── ✅ Department filter (filterInDepartment)
   ├── ✅ Approval status filter (filterApprovalStatus)
   ├── ✅ Quick filters (My SOPs, Pending Review)
   ├── ✅ Faceted sidebar with counts
   └── ✅ Active filters display

6. ✅ Permission Consistency (Phase 6) — COMPLETE (20 tests)
   ├── ✅ PermissionConsistencyService for auditing/repairing
   ├── ✅ Artisan command (bookstack:audit-permissions)
   ├── ✅ Weekly scheduled audit job
   └── ✅ Dashboard widget for admins
```

---

## Key Files Reference

| Area | Files |
|------|-------|
| Translations | `lang/en/entities.php`, `lang/en/common.php`, `lang/en/activities.php`, `lang/en/settings.php` |
| Entity views | `resources/views/entities/` |
| Layout/nav | `resources/views/layouts/base.blade.php`, partials |
| **User menu** | `resources/views/layouts/parts/header-user-menu.blade.php` |
| **Entity meta** | `resources/views/entities/meta.blade.php` |
| **Entity show views** | `resources/views/pages/show.blade.php`, `books/show.blade.php`, `chapters/show.blade.php`, `shelves/show.blade.php` |
| Page model | `app/Entities/Models/Page.php` |
| **Page revision model** | `app/Entities/Models/PageRevision.php` |
| **Entity page data** | `app/Entities/Models/EntityPageData.php` (BookStack 2025 schema) |
| **Entity scope** | `app/Entities/Models/EntityScope.php` (auto-joins page data) |
| Page repo | `app/Entities/Repos/PageRepo.php` |
| **Revision repo** | `app/Entities/Repos/RevisionRepo.php` |
| Permissions | `app/Permissions/PermissionApplicator.php` |
| **Joint permissions** | `app/Permissions/JointPermissionBuilder.php` |
| Activity | `app/Activity/Tools/ActivityLogger.php` |
| **Search runner** | `app/Search/SearchRunner.php` |
| **Search options** | `app/Search/SearchOptions.php` |
| Theming | `app/Theming/ThemeService.php`, theme folder |
| **Default settings** | `app/Config/setting-defaults.php` |
| **SASS variables** | `resources/sass/_vars.scss` |
| **Migrations** | `database/migrations/2025_12_07_000001_add_approval_fields_to_page_revisions.php` |
| **Dashboard queries** | `app/Entities/Queries/DashboardQueries.php` |
| **Dashboard tests** | `tests/Dashboard/SOPDashboardTest.php`, `DashboardQueriesTest.php`, `DashboardPermissionsTest.php`, `DashboardApiTest.php` |
| **Approval service** | `app/Entities/Tools/RevisionApprovalService.php` |
| **Approval controller** | `app/Entities/Controllers/RevisionApprovalController.php` |
| **Approval views** | `resources/views/pages/revision-approve.blade.php`, `revision-reject.blade.php` |
| **Approval tests** | `tests/Entity/RevisionApprovalTest.php` |
| **Review reminder service** | `app/Entities/Tools/ReviewReminderService.php` |
| **Review reminder notification** | `app/Entities/Notifications/SopReviewReminderNotification.php` |
| **Review reminder command** | `app/Console/Commands/SendReviewRemindersCommand.php` |
| **Review reminder tests** | `tests/Entity/ReviewReminderTest.php` |
| **Search facet calculator** | `app/Search/SearchFacetCalculator.php` |
| **Search controller** | `app/Search/SearchController.php` |
| **Search view** | `resources/views/search/all.blade.php` |
| **Department filter view** | `resources/views/search/parts/department-filter.blade.php` |
| **Status filter view** | `resources/views/search/parts/status-filter.blade.php` |
| **Facets view** | `resources/views/search/parts/facets.blade.php` |
| **Search enhancement tests** | `tests/Search/SearchEnhancementsTest.php` |

---

## ✅ Implementation Progress

### Phase 1: Terminology + Rebrand — ✅ COMPLETED
| File | Change |
|------|--------|
| `lang/en/entities.php` | ✅ Full SOP terminology (Shelf→Department, Book→Knowledge Domain, Chapter→SOP Group, Page→SOP Document) |
| `lang/en/activities.php` | ✅ Activity messages updated + approval workflow activities added |
| `lang/en/settings.php` | ✅ Settings labels updated for SOP terminology |
| `app/Config/setting-defaults.php` | ✅ App name "SOP Manager", professional color scheme |
| `resources/sass/_vars.scss` | ✅ CSS variables updated (navy primary, teal domains, amber groups, blue docs) |

### Phase 2: Feature Pruning — ✅ COMPLETED

#### Admin UI Configuration (Manual)
| Setting | Action |
|---------|--------|
| Settings > Roles | Uncheck `comment-create-all`, `comment-update-*`, `comment-delete-*` for non-admin roles |
| Settings > Roles | Uncheck `templates-manage` for non-admin roles |
| Settings > Roles | Uncheck `receive-notifications` for non-admin roles |
| Settings > Features | Enable "Disable Comments" toggle |

#### Blade Partial Modifications
| File | Change |
|------|--------|
| `resources/views/layouts/parts/header-user-menu.blade.php` | ✅ Wrapped favourites link with `@if(userCan(SettingsManage))` |
| `resources/views/pages/show.blade.php` | ✅ Wrapped watch + favourite actions with `@if(userCan(SettingsManage))` |
| `resources/views/books/show.blade.php` | ✅ Wrapped watch + favourite actions with `@if(userCan(SettingsManage))` |
| `resources/views/chapters/show.blade.php` | ✅ Wrapped watch + favourite actions with `@if(userCan(SettingsManage))` |
| `resources/views/shelves/show.blade.php` | ✅ Wrapped favourite action with `@if(userCan(SettingsManage))` |
| `resources/views/entities/meta.blade.php` | ✅ Wrapped watch status display with `@if(userCan(SettingsManage))` |

#### Result
- Standard users no longer see: favourites, watch lists, watch status indicators
- Admins (users with `settings-manage` permission) retain full access
- Comments, templates, recycle bin, audit logs controlled via role permissions

---

### Phase 4a: Approval Schema — ✅ COMPLETED & MIGRATED
| File | Change |
|------|--------|
| `database/migrations/2025_12_07_000001_add_approval_fields_to_page_revisions.php` | ✅ Migration created & run |
| `app/Entities/Models/PageRevision.php` | ✅ Added status constants, `approvedBy()` relation, helper methods |
| `app/Entities/Models/Page.php` | ✅ Added approval helper methods (`hasApprovedRevision()`, `isEditLocked()`, etc.) |
| `app/Entities/Models/EntityPageData.php` | ✅ Added approval fields to `$fields` array |

#### Database Schema Applied
**`page_revisions` table:**
```sql
status VARCHAR(20) DEFAULT 'draft'   -- draft/in_review/approved/rejected
approved_by INT UNSIGNED NULL        -- FK to users
approved_at TIMESTAMP NULL           -- Approval timestamp
review_notes TEXT NULL               -- Reviewer comments
```

**`entity_page_data` table:**
```sql
approved_revision_id INT UNSIGNED NULL   -- FK to page_revisions
next_review_date DATE NULL               -- When review is due
review_interval_days SMALLINT UNSIGNED NULL  -- Review frequency
```

---

### Phase 3: Custom Dashboard — ✅ COMPLETED

#### Test Suite (57 tests)
| File | Tests | Purpose |
|------|-------|---------|
| `tests/Dashboard/SOPDashboardTest.php` | 22 | Main feature tests for dashboard functionality |
| `tests/Dashboard/DashboardQueriesTest.php` | 14 | Unit tests for `DashboardQueries` class |
| `tests/Dashboard/DashboardPermissionsTest.php` | 14 | Permission and access control tests |
| `tests/Dashboard/DashboardApiTest.php` | 7 | Edge cases, performance, and security tests |

#### Implementation Files
| File | Status | Purpose |
|------|--------|---------|
| `app/App/SOPDashboardController.php` | ✅ Created | Dashboard controller with all widget data |
| `app/Entities/Queries/DashboardQueries.php` | ✅ Created | Query class for My SOPs + Pending Reviews |
| `resources/views/dashboard/index.blade.php` | ✅ Created | Main dashboard view (3-column layout) |
| `resources/views/dashboard/parts/my-sops.blade.php` | ✅ Created | My SOPs widget |
| `resources/views/dashboard/parts/recently-updated.blade.php` | ✅ Created | Recently Updated widget |
| `resources/views/dashboard/parts/departments.blade.php` | ✅ Created | Department shortcuts widget |
| `resources/views/dashboard/parts/pending-reviews.blade.php` | ✅ Created | Pending Reviews + Overdue Reviews widgets |
| `routes/web.php` | ✅ Updated | Added `/dashboard` route |
| `lang/en/entities.php` | ✅ Updated | Added dashboard translations |

#### DashboardQueries Methods
```php
// Get pages owned by current user (non-drafts)
currentUserOwnedPages(int $count): Collection

// Get revisions pending review (status = 'in_review')
pendingReviewRevisions(int $count): Collection

// Get pages due for periodic review
overdueSopReviews(int $count): Collection
```

#### Dashboard Features
| Widget | Element ID | Description |
|--------|------------|-------------|
| My SOPs | `#my-sops` | Pages owned by current user |
| Recently Updated | `#recently-updated` | Latest SOP changes |
| Departments | `#departments` | Quick links to all departments |
| Pending Reviews | `#pending-reviews` | Revisions awaiting approval |
| Overdue Reviews | `#overdue-reviews` | SOPs past review date |
| Recent Drafts | `#recent-drafts` | User's draft pages (conditional) |
| Recent Activity | `#recent-activity` | Activity log |

#### Access Dashboard
```
URL: /dashboard
Route: GET /dashboard
Controller: SOPDashboardController@index
Auth: Required (guests redirected to login)
```

#### Run Tests
```bash
# Run all dashboard tests
docker-compose exec app php artisan test tests/Dashboard/

# Run specific test file
docker-compose exec app php artisan test tests/Dashboard/SOPDashboardTest.php
```

---

### Phase 4b: Approval Workflow UI — ✅ COMPLETED

#### Implementation Files
| File | Status | Purpose |
|------|--------|---------|
| `app/Entities/Tools/RevisionApprovalService.php` | ✅ Created | Service for status transitions (submit, approve, reject, withdraw) |
| `app/Entities/Controllers/RevisionApprovalController.php` | ✅ Created | Controller for approval routes |
| `app/Activity/ActivityType.php` | ✅ Updated | Added REVISION_SUBMIT_REVIEW, REVISION_APPROVE, REVISION_REJECT |
| `routes/web.php` | ✅ Updated | Added 6 approval workflow routes |
| `resources/views/pages/revision-approve.blade.php` | ✅ Created | Approval form (notes + review interval) |
| `resources/views/pages/revision-reject.blade.php` | ✅ Created | Rejection form |
| `resources/views/pages/parts/revisions-index-row.blade.php` | ✅ Updated | Added status badges + action buttons |
| `resources/views/pages/show.blade.php` | ✅ Updated | Added approval banner + edit lock indicator |
| `lang/en/entities.php` | ✅ Updated | Added 25+ approval workflow translations |
| `tests/Entity/RevisionApprovalTest.php` | ✅ Created | 25 tests for approval workflow |

#### Approval Workflow Routes
```
POST /books/{book}/page/{page}/revisions/{rev}/submit-review  → Submit for review
GET  /books/{book}/page/{page}/revisions/{rev}/approve        → Show approve form
POST /books/{book}/page/{page}/revisions/{rev}/approve        → Approve revision
GET  /books/{book}/page/{page}/revisions/{rev}/reject         → Show reject form
POST /books/{book}/page/{page}/revisions/{rev}/reject         → Reject revision
POST /books/{book}/page/{page}/revisions/{rev}/withdraw       → Withdraw from review
```

#### Approval Flow
```
[Draft] ──submit──► [In Review] ──approve──► [Approved]
                         │                       │
                         ├──reject──► [Rejected] │
                         │                       │
                         └──withdraw──► [Draft]  │
                                                 ▼
                              (Page locked, new edits create new draft)
```

#### Features Implemented
- ✅ Status badges on revisions list (Draft, In Review, Approved, Rejected)
- ✅ Submit for Review button (editors)
- ✅ Approve/Reject buttons (admins with content-export permission)
- ✅ Withdraw from Review button (editors)
- ✅ Approval form with review notes + review interval
- ✅ Rejection form with feedback notes
- ✅ Approved banner on page view
- ✅ Pending review banner with quick approve link
- ✅ Edit button changes to "Create New Draft" when page is approved
- ✅ Activity logging for all approval actions
- ✅ Next review date calculation

#### Run Approval Tests
```bash
docker-compose exec app php artisan test tests/Entity/RevisionApprovalTest.php
```

---

### Phase 4c: Periodic Review Reminders — ✅ COMPLETED

#### Implementation Files
| File | Status | Purpose |
|------|--------|---------|
| `app/Entities/Notifications/SopReviewReminderNotification.php` | ✅ Created | Email notification for SOPs due for review |
| `app/Entities/Tools/ReviewReminderService.php` | ✅ Created | Service for finding overdue SOPs and sending notifications |
| `app/Console/Commands/SendReviewRemindersCommand.php` | ✅ Created | Artisan command for scheduled reminder job |
| `app/Console/Kernel.php` | ✅ Updated | Registered daily schedule for reminder command |
| `lang/en/notifications.php` | ✅ Updated | Added review reminder notification strings |
| `tests/Entity/ReviewReminderTest.php` | ✅ Created | 22 tests for review reminder functionality |

#### Scheduled Job Configuration
```php
// In app/Console/Kernel.php
$schedule->command('bookstack:send-review-reminders --include-upcoming=7')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
```

#### Artisan Command Usage
```bash
# Run review reminders (normal mode)
docker-compose exec app php artisan bookstack:send-review-reminders

# Include reminders for SOPs due within 7 days
docker-compose exec app php artisan bookstack:send-review-reminders --include-upcoming=7

# Dry run (preview without sending)
docker-compose exec app php artisan bookstack:send-review-reminders --dry-run

# View statistics only
docker-compose exec app php artisan bookstack:send-review-reminders --stats
```

#### ReviewReminderService Methods
```php
// Get all pages that are overdue for review
getOverduePages(): Collection

// Get pages due within N days
getUpcomingReviewPages(int $daysAhead = 7): Collection

// Send overdue reminders
sendOverdueReminders(): array{sent, skipped, errors}

// Send upcoming reminders
sendUpcomingReminders(int $daysAhead = 7): array{sent, skipped, errors}

// Get statistics
getReviewStatistics(): array{overdue, upcoming_7_days, total_scheduled}
```

#### Features Implemented
- ✅ Daily scheduled job runs at 8:00 AM
- ✅ Sends notifications for overdue SOPs
- ✅ Optional upcoming review warnings (configurable days ahead)
- ✅ Dry run mode for testing
- ✅ Statistics view for admin monitoring
- ✅ Skips users without notification permissions
- ✅ Calculates days overdue in notification
- ✅ Activity logging for email failures

#### Run Review Reminder Tests
```bash
docker-compose exec app php artisan test tests/Entity/ReviewReminderTest.php
```

#### Cron Setup Required
For the scheduled command to run automatically, add this cron entry:
```bash
* * * * * cd /path-to-bookstack && php artisan schedule:run >> /dev/null 2>&1
```

---

### Phase 6: Permission Consistency — ✅ COMPLETED
| File | Status | Purpose |
|------|--------|---------|
| `app/Permissions/PermissionConsistencyService.php` | ✅ Created | Service for auditing and repairing permission consistency |
| `app/Console/Commands/AuditPermissionConsistencyCommand.php` | ✅ Created | Artisan command for permission audits |
| `resources/views/dashboard/parts/permission-health.blade.php` | ✅ Created | Dashboard widget for permission health (admin only) |
| `tests/Permissions/PermissionConsistencyTest.php` | ✅ Created | 20 tests for permission consistency |
| `app/Console/Kernel.php` | ✅ Updated | Added weekly permission audit schedule |
| `app/App/SOPDashboardController.php` | ✅ Updated | Added permission health data for admin widget |
| `resources/views/dashboard/index.blade.php` | ✅ Updated | Added permission health widget include |
| `lang/en/entities.php` | ✅ Updated | Added permission health translation strings |

#### PermissionConsistencyService Methods
```php
// Audit single entity
auditEntity(Entity $entity): array{consistent, issues}

// Audit all entities
auditAll(): Collection

// Find entities without permissions
findEntitiesWithoutPermissions(): Collection

// Find orphaned permissions
findOrphanedPermissions(): array{count, by_type}

// Repair single entity
repairEntity(Entity $entity): void

// Repair all issues
repairAll(): int

// Cleanup orphaned permissions
cleanupOrphanedPermissions(): int

// Full rebuild
rebuildAll(): void

// Get statistics
getStatistics(): array

// Quick health check
isHealthy(): bool
```

#### Artisan Command Usage
```bash
# Show statistics only
docker-compose exec app php artisan bookstack:audit-permissions --stats

# Audit and show issues
docker-compose exec app php artisan bookstack:audit-permissions

# Dry run (preview without changes)
docker-compose exec app php artisan bookstack:audit-permissions --dry-run --fix

# Automatically repair issues
docker-compose exec app php artisan bookstack:audit-permissions --fix

# Full permission rebuild (requires confirmation)
docker-compose exec app php artisan bookstack:audit-permissions --rebuild
```

#### Scheduled Job
Weekly audit runs automatically (Sunday at 3:00 AM):
```php
$schedule->command('bookstack:audit-permissions --fix')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
```

#### Dashboard Widget Features
- Shows permission health status (healthy/issues)
- Displays entity and permission counts
- Warns about missing/orphaned permissions
- Links to maintenance page
- Cached for 5 minutes to avoid heavy queries
- Admin-only visibility

#### Run Permission Consistency Tests
```bash
docker-compose exec app php artisan test tests/Permissions/PermissionConsistencyTest.php
```

---

### Next Steps
1. ✅ ~~Run migration: `php artisan migrate`~~ — Done via Docker
2. ✅ ~~Implement Phase 2 (permission lockdown via admin UI + Blade partials)~~ — Completed
3. ✅ ~~Create Phase 3 (Custom Dashboard)~~ — 62 tests + full implementation
4. ✅ ~~Implement Phase 4b (Approval Workflow UI)~~ — 23 tests + full implementation
5. ✅ ~~Implement Phase 4c (Periodic Review Reminders)~~ — 19 tests + full implementation
6. ✅ ~~Run all tests to verify implementation~~ — 104 tests passing
7. ✅ ~~Implement Phase 5 (Search UX Enhancements)~~ — 25 tests + full implementation
8. ✅ ~~Implement Phase 6 (Permission Consistency)~~ — 20 tests + full implementation
9. 🔲 Build frontend assets: `docker-compose exec node npm run build`
10. 🔲 Clear caches: `docker-compose exec app php artisan view:clear && php artisan cache:clear`
11. 🔲 Configure cron for scheduled commands (see Phase 4c and 6 documentation)
12. 🔲 Configure role permissions in Admin UI (Settings > Roles)
13. 🔲 Run full test suite: `docker-compose exec app php artisan test`

---

### Development Commands Reference
```bash
# Start Docker environment
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate

# Build frontend assets
docker-compose exec node npm run build

# Clear caches
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan view:clear

# Run all tests
docker-compose exec app php artisan test

# Run dashboard tests only
docker-compose exec app php artisan test tests/Dashboard/

# Run approval tests only
docker-compose exec app php artisan test tests/Entity/RevisionApprovalTest.php

# Access application
open http://localhost:8080
```

---

## 📁 Files Created/Modified Summary

### New Files Created (29 files)
| Category | File | Purpose |
|----------|------|---------|
| **Migration** | `database/migrations/2025_12_07_000001_add_approval_fields_to_page_revisions.php` | Approval schema |
| **Controller** | `app/App/SOPDashboardController.php` | Dashboard controller |
| **Controller** | `app/Entities/Controllers/RevisionApprovalController.php` | Approval workflow controller |
| **Service** | `app/Entities/Tools/RevisionApprovalService.php` | Approval business logic |
| **Service** | `app/Entities/Tools/ReviewReminderService.php` | Review reminder business logic |
| **Service** | `app/Search/SearchFacetCalculator.php` | Search facet calculation service |
| **Service** | `app/Permissions/PermissionConsistencyService.php` | Permission audit and repair service |
| **Notification** | `app/Entities/Notifications/SopReviewReminderNotification.php` | Review reminder email notification |
| **Command** | `app/Console/Commands/SendReviewRemindersCommand.php` | Artisan command for scheduled reminders |
| **Command** | `app/Console/Commands/AuditPermissionConsistencyCommand.php` | Permission audit artisan command |
| **Query** | `app/Entities/Queries/DashboardQueries.php` | Dashboard data queries |
| **View** | `resources/views/dashboard/index.blade.php` | Dashboard main view |
| **View** | `resources/views/dashboard/parts/my-sops.blade.php` | My SOPs widget |
| **View** | `resources/views/dashboard/parts/recently-updated.blade.php` | Recently Updated widget |
| **View** | `resources/views/dashboard/parts/departments.blade.php` | Departments widget |
| **View** | `resources/views/dashboard/parts/pending-reviews.blade.php` | Pending/Overdue Reviews widget |
| **View** | `resources/views/dashboard/parts/permission-health.blade.php` | Permission health widget (admin) |
| **View** | `resources/views/pages/revision-approve.blade.php` | Approval form |
| **View** | `resources/views/pages/revision-reject.blade.php` | Rejection form |
| **View** | `resources/views/search/parts/department-filter.blade.php` | Department filter dropdown |
| **View** | `resources/views/search/parts/status-filter.blade.php` | Approval status checkboxes |
| **View** | `resources/views/search/parts/facets.blade.php` | Faceted results sidebar |
| **Test** | `tests/Dashboard/SOPDashboardTest.php` | Dashboard feature tests (22) |
| **Test** | `tests/Dashboard/DashboardQueriesTest.php` | Query unit tests (14) |
| **Test** | `tests/Dashboard/DashboardPermissionsTest.php` | Permission tests (14) |
| **Test** | `tests/Dashboard/DashboardApiTest.php` | Edge case tests (7) |
| **Test** | `tests/Entity/RevisionApprovalTest.php` | Approval workflow tests (25) |
| **Test** | `tests/Entity/ReviewReminderTest.php` | Review reminder tests (19) |
| **Test** | `tests/Search/SearchEnhancementsTest.php` | Search enhancements tests (29) |
| **Test** | `tests/Permissions/PermissionConsistencyTest.php` | Permission consistency tests (20) |

### Modified Files (22 files)
| Category | File | Changes |
|----------|------|---------|
| **Model** | `app/Entities/Models/PageRevision.php` | Status constants, relations, helpers |
| **Model** | `app/Entities/Models/Page.php` | Approval helper methods |
| **Model** | `app/Entities/Models/EntityPageData.php` | Approval fields |
| **Activity** | `app/Activity/ActivityType.php` | Approval activity types |
| **Console** | `app/Console/Kernel.php` | Scheduled command registration (review reminders + permission audit) |
| **Controller** | `app/App/SOPDashboardController.php` | Added permission health data for admin widget |
| **Routes** | `routes/web.php` | Dashboard + approval routes |
| **Search** | `app/Search/SearchRunner.php` | Added filterInDepartment(), filterApprovalStatus() |
| **Search** | `app/Search/SearchOptions.php` | Handle array filter values |
| **Search** | `app/Search/SearchController.php` | Pass departments/facets to view |
| **Lang** | `lang/en/entities.php` | SOP terminology + dashboard + approval + search + permission health strings |
| **Lang** | `lang/en/activities.php` | Approval activity messages |
| **Lang** | `lang/en/settings.php` | SOP settings labels |
| **Lang** | `lang/en/notifications.php` | Review reminder notification strings |
| **Config** | `app/Config/setting-defaults.php` | App name, colors |
| **SASS** | `resources/sass/_vars.scss` | SOP color scheme |
| **View** | `resources/views/pages/show.blade.php` | Approval banner, edit lock |
| **View** | `resources/views/pages/parts/revisions-index-row.blade.php` | Status badges, action buttons |
| **View** | `resources/views/layouts/parts/header-user-menu.blade.php` | Hide favourites |
| **View** | `resources/views/books/show.blade.php` | Hide watch/favourite |
| **View** | `resources/views/chapters/show.blade.php` | Hide watch/favourite |
| **View** | `resources/views/shelves/show.blade.php` | Hide favourite |
| **View** | `resources/views/entities/meta.blade.php` | Hide watch status |
| **View** | `resources/views/search/all.blade.php` | Quick filters, department/status filters, facets, active filters |
| **View** | `resources/views/dashboard/index.blade.php` | Added permission health widget include |
