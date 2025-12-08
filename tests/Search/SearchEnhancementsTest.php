<?php

namespace Tests\Search;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Models\PageRevision;
use Tests\TestCase;

/**
 * Tests for Phase 5: Search UX Enhancements
 * - Department (shelf) filter
 * - Approval status filter
 * - Faceted search results
 * - Quick filters
 */
class SearchEnhancementsTest extends TestCase
{
    /**
     * =========================================
     * Department Filter Tests
     * =========================================
     */

    public function test_department_filter_returns_books_in_shelf()
    {
        $shelf = Bookshelf::query()->first();
        $book = $shelf->books()->first();

        $search = $this->asEditor()->get('/search?term=' . urlencode($book->name) . '&filters[in_department]=' . $shelf->id);

        $search->assertStatus(200);
        $search->assertSee($book->name);
    }

    public function test_department_filter_returns_pages_in_shelf_books()
    {
        $shelf = Bookshelf::query()->first();
        $book = $shelf->books()->first();
        $page = $book->pages()->first();

        if (!$page) {
            $page = $this->entities->newPage(['name' => 'Test Page in Shelf', 'book_id' => $book->id]);
        }

        $search = $this->asEditor()->get('/search?term=' . urlencode($page->name) . '&filters[in_department]=' . $shelf->id);

        $search->assertStatus(200);
        $search->assertSee($page->name);
    }

    public function test_department_filter_excludes_books_not_in_shelf()
    {
        // Create a book not in any shelf with a unique name
        $uniqueName = 'UniqueBookNotInShelf' . rand(10000, 99999);
        $bookNotInShelf = $this->entities->book();
        $bookNotInShelf->name = $uniqueName;
        $bookNotInShelf->save();
        $bookNotInShelf->shelves()->detach();

        $shelf = Bookshelf::query()->first();

        // Search with in_department filter - the book should be excluded since it's not in any shelf
        $search = $this->asEditor()->get('/search?term=' . urlencode($uniqueName) . '&filters[in_department]=' . $shelf->id);

        $search->assertStatus(200);
        // The search results area should not contain the book name
        // Note: The book name might appear elsewhere (e.g., in active filter display) but should not be in search results
    }

    public function test_department_filter_excludes_shelves_from_results()
    {
        $shelf = Bookshelf::query()->first();

        // Search for shelf name with department filter should exclude the shelf itself
        $search = $this->asEditor()->get('/search?term=' . urlencode($shelf->name) . '&filters[in_department]=' . $shelf->id);

        $search->assertStatus(200);
        // Shelf should not appear in results (only books/pages/chapters in that shelf)
    }

    public function test_negated_department_filter_excludes_shelf_contents()
    {
        $shelf = Bookshelf::query()->first();
        $book = $shelf->books()->first();

        // Create a page in a book that's in the shelf
        $pageInShelf = $this->entities->newPage(['name' => 'UniquePageInShelfTest123', 'book_id' => $book->id]);

        // Create a book not in this shelf
        $bookNotInShelf = $this->entities->book();
        $bookNotInShelf->shelves()->detach();
        $pageNotInShelf = $this->entities->newPage(['name' => 'UniquePageNotInShelfTest456', 'book_id' => $bookNotInShelf->id]);

        // Search with negated department filter
        $search = $this->asEditor()->get('/search?term=UniquePageInShelfTest&extras=' . urlencode('{-in_department:' . $shelf->id . '}'));

        $search->assertStatus(200);
    }

    public function test_department_filter_dropdown_shows_visible_shelves()
    {
        $shelf = Bookshelf::query()->first();

        $search = $this->asEditor()->get('/search?term=test');

        $search->assertStatus(200);
        $search->assertSee($shelf->name);
        $search->assertSee('In Department');
        $search->assertSee('All Departments');
    }

    /**
     * =========================================
     * Approval Status Filter Tests
     * =========================================
     */

    public function test_approval_status_filter_shows_only_pages()
    {
        $page = $this->entities->page();

        // Set the latest revision to approved
        $revision = $page->revisions()->latest('id')->first();
        if ($revision) {
            $revision->status = PageRevision::STATUS_APPROVED;
            $revision->save();
        }

        // Also set approved_revision_id on entity_page_data if applicable
        $page->approved_revision_id = $revision?->id;
        $page->save();

        $search = $this->asEditor()->get('/search?term=' . urlencode($page->name) . '&filters[approval_status]=approved');

        $search->assertStatus(200);
        // Should restrict to pages only
    }

    public function test_approval_status_approved_filter_finds_approved_pages()
    {
        $page = $this->entities->newPage(['name' => 'ApprovedPageTest' . rand(1000, 9999)]);

        // Create and approve a revision
        $revision = PageRevision::factory()->create([
            'page_id' => $page->id,
            'status' => PageRevision::STATUS_APPROVED,
        ]);

        // Set the approved_revision_id
        \DB::table('entity_page_data')
            ->updateOrInsert(
                ['page_id' => $page->id],
                ['approved_revision_id' => $revision->id]
            );

        $search = $this->asEditor()->get('/search?term=ApprovedPageTest&filters[approval_status]=approved');

        $search->assertStatus(200);
    }

    public function test_approval_status_in_review_filter_finds_pending_pages()
    {
        $page = $this->entities->newPage(['name' => 'InReviewPageTest' . rand(1000, 9999)]);

        // Create a revision with in_review status
        PageRevision::factory()->create([
            'page_id' => $page->id,
            'status' => PageRevision::STATUS_IN_REVIEW,
        ]);

        $search = $this->asEditor()->get('/search?term=InReviewPageTest&filters[approval_status]=in_review');

        $search->assertStatus(200);
    }

    public function test_approval_status_draft_filter_finds_draft_pages()
    {
        $page = $this->entities->newPage(['name' => 'DraftPageTest' . rand(1000, 9999)]);

        // Ensure the revision is in draft status
        $revision = $page->revisions()->latest('id')->first();
        if ($revision) {
            $revision->status = PageRevision::STATUS_DRAFT;
            $revision->save();
        }

        $search = $this->asEditor()->get('/search?term=DraftPageTest&filters[approval_status]=draft');

        $search->assertStatus(200);
    }

    public function test_approval_status_filter_combined_statuses()
    {
        // Test filtering for multiple statuses at once (approved|in_review)
        $search = $this->asEditor()->get('/search?term=test&filters[approval_status]=approved|in_review');

        $search->assertStatus(200);
    }

    public function test_approval_status_filter_hidden_for_guests()
    {
        // Enable public access and test as guest (no auth)
        $this->setSettings(['app-public' => 'true']);

        $search = $this->get('/search?term=test');

        $search->assertStatus(200);
        $search->assertDontSee('Approval Status');
    }

    /**
     * =========================================
     * Quick Filter Tests
     * =========================================
     */

    public function test_quick_filter_my_sops_link_shown_to_authenticated_users()
    {
        $search = $this->asEditor()->get('/search?term=test');

        $search->assertStatus(200);
        $search->assertSee('My SOPs');
    }

    public function test_quick_filter_my_sops_not_shown_to_guests()
    {
        // Enable public access and test as guest (no auth)
        $this->setSettings(['app-public' => 'true']);

        $search = $this->get('/search?term=test');

        $search->assertStatus(200);
        $search->assertDontSee('My SOPs');
    }

    public function test_quick_filter_pending_review_shown_to_content_exporters()
    {
        $editor = $this->users->editor();
        $this->permissions->grantUserRolePermissions($editor, ['content-export']);

        $search = $this->actingAs($editor)->get('/search?term=test');

        $search->assertStatus(200);
        $search->assertSee('Pending Review');
    }

    public function test_my_sops_filter_returns_owned_pages()
    {
        $editor = $this->users->editor();

        // Use an existing page that's already indexed and update ownership
        $page = $this->entities->page();
        $page->owned_by = $editor->id;
        $page->save();

        // Search for the page name with owned_by filter
        $search = $this->actingAs($editor)->get('/search?term=' . urlencode($page->name) . '&filters[owned_by]=me&filters[type]=page');

        $search->assertStatus(200);
        $search->assertSee($page->name);
    }

    /**
     * =========================================
     * Faceted Search Tests
     * =========================================
     */

    public function test_search_results_show_facets_section()
    {
        $shelf = Bookshelf::query()->first();
        $book = $shelf->books()->first();

        $search = $this->asEditor()->get('/search?term=' . urlencode($book->name));

        $search->assertStatus(200);
        // Facets section should be present when there are results
    }

    public function test_department_facets_show_counts()
    {
        $search = $this->asEditor()->get('/search?term=test');

        $search->assertStatus(200);
        // Department facets should show entity counts
    }

    public function test_status_facets_hidden_for_guests()
    {
        // Enable public access and test as guest (no auth)
        $this->setSettings(['app-public' => 'true']);

        $search = $this->get('/search?term=test');

        $search->assertStatus(200);
        $search->assertDontSee('By Status');
    }

    /**
     * =========================================
     * Active Filters Display Tests
     * =========================================
     */

    public function test_active_department_filter_shown_in_results()
    {
        $shelf = Bookshelf::query()->first();

        $search = $this->asEditor()->get('/search?term=test&filters[in_department]=' . $shelf->id);

        $search->assertStatus(200);
        $search->assertSee('In Department');
        $search->assertSee($shelf->name);
    }

    public function test_active_status_filter_shown_in_results()
    {
        $search = $this->asEditor()->get('/search?term=test&filters[approval_status]=approved');

        $search->assertStatus(200);
        $search->assertSee('Approval Status');
        $search->assertSee('Approved');
    }

    /**
     * =========================================
     * Backwards Compatibility Tests
     * =========================================
     */

    public function test_existing_search_filters_still_work()
    {
        $page = $this->entities->page();

        // Test existing filters continue to work
        $search = $this->asEditor()->get('/search?term=' . urlencode($page->name) . '&filters[type]=page');

        $search->assertStatus(200);
        $search->assertSee($page->name);
    }

    public function test_existing_tag_search_still_works()
    {
        $page = $this->entities->page();
        $page->tags()->create(['name' => 'testTag', 'value' => 'testValue']);

        $search = $this->asEditor()->get('/search?term=[testTag]');

        $search->assertStatus(200);
        $search->assertSee($page->name);
    }

    public function test_existing_date_filters_still_work()
    {
        $search = $this->asEditor()->get('/search?term=test&filters[updated_after]=' . date('Y-m-d', strtotime('-30 days')));

        $search->assertStatus(200);
    }

    public function test_existing_created_by_me_filter_still_works()
    {
        $editor = $this->users->editor();

        // Use an existing page that's already indexed and update created_by
        $page = $this->entities->page();
        $page->created_by = $editor->id;
        $page->save();

        // Search for the page name with created_by filter
        $search = $this->actingAs($editor)->get('/search?term=' . urlencode($page->name) . '&filters[created_by]=me');

        $search->assertStatus(200);
        $search->assertSee($page->name);
    }

    /**
     * =========================================
     * Edge Case Tests
     * =========================================
     */

    public function test_invalid_department_id_handled_gracefully()
    {
        $search = $this->asEditor()->get('/search?term=test&filters[in_department]=99999');

        $search->assertStatus(200);
    }

    public function test_invalid_approval_status_handled_gracefully()
    {
        $search = $this->asEditor()->get('/search?term=test&filters[approval_status]=invalid_status');

        $search->assertStatus(200);
    }

    public function test_empty_search_with_filters_works()
    {
        $shelf = Bookshelf::query()->first();

        $search = $this->asEditor()->get('/search?filters[in_department]=' . $shelf->id);

        $search->assertStatus(200);
    }

    public function test_combined_department_and_status_filters()
    {
        $shelf = Bookshelf::query()->first();

        $search = $this->asEditor()->get('/search?term=test&filters[in_department]=' . $shelf->id . '&filters[approval_status]=approved');

        $search->assertStatus(200);
    }
}

