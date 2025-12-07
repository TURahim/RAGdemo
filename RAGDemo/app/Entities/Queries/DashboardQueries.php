<?php

namespace BookStack\Entities\Queries;

use BookStack\Entities\Models\PageRevision;
use Illuminate\Support\Collection;

/**
 * Dashboard-specific queries for the SOP Dashboard.
 * Provides methods to fetch user-owned pages and pending review revisions.
 */
class DashboardQueries
{
    public function __construct(
        protected EntityQueries $queries,
    ) {
    }

    /**
     * Get pages owned by the current user (non-drafts).
     * Returns visible pages ordered by most recently updated.
     */
    public function currentUserOwnedPages(int $count): Collection
    {
        return $this->queries->pages->visibleForList()
            ->where('draft', '=', false)
            ->where('owned_by', '=', user()->id)
            ->orderBy('updated_at', 'desc')
            ->with('book')
            ->take($count)
            ->get();
    }

    /**
     * Get page revisions pending review (status = 'in_review').
     * Returns revisions with their associated visible pages.
     */
    public function pendingReviewRevisions(int $count): Collection
    {
        return PageRevision::query()
            ->where('status', '=', PageRevision::STATUS_IN_REVIEW)
            ->where('type', '=', 'version')
            ->whereHas('page', function ($query) {
                $query->scopes('visible');
            })
            ->with(['page', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->take($count)
            ->get();
    }

    /**
     * Get revisions due for periodic review.
     * Finds pages where next_review_date has passed.
     */
    public function overdueSopReviews(int $count): Collection
    {
        return $this->queries->pages->visibleForList()
            ->where('draft', '=', false)
            ->whereNotNull('next_review_date')
            ->where('next_review_date', '<=', now())
            ->orderBy('next_review_date', 'asc')
            ->take($count)
            ->get();
    }
}

