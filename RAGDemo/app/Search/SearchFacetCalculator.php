<?php

namespace BookStack\Search;

use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Queries\BookshelfQueries;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchFacetCalculator
{
    public function __construct(
        protected BookshelfQueries $shelfQueries,
    ) {
    }

    /**
     * Get all visible departments (shelves) for the search filter dropdown.
     *
     * @return Collection<Bookshelf>
     */
    public function getVisibleDepartments(): Collection
    {
        return $this->shelfQueries->visibleForList()
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Calculate facet counts for search results based on the current search options.
     * Returns counts grouped by department and status.
     * Note: These are approximate counts - actual search results are permission-checked.
     *
     * @return array{departments: array, statuses: array}
     */
    public function calculateFacets(SearchOptions $searchOpts, SearchRunner $searchRunner): array
    {
        $filterMap = $searchOpts->filters->toValueMap();
        $hasSearchTerms = count($searchOpts->searches->toValueArray()) > 0;

        // Only calculate facets if there's an active search
        if (!$hasSearchTerms && empty($filterMap)) {
            return [
                'departments' => [],
                'statuses' => [],
            ];
        }

        return [
            'departments' => $this->calculateDepartmentFacets(),
            'statuses' => $this->calculateStatusFacets(),
        ];
    }

    /**
     * Calculate counts per department (shelf).
     * Uses visible shelves from the query system which respects permissions.
     *
     * @return array<int, array{id: int, name: string, count: int}>
     */
    protected function calculateDepartmentFacets(): array
    {
        // Get visible shelves with their book counts
        $shelves = $this->shelfQueries->visibleForList()
            ->withCount('books')
            ->orderBy('name', 'asc')
            ->get();

        return $shelves->map(fn($shelf) => [
            'id' => $shelf->id,
            'name' => $shelf->name,
            'count' => $shelf->books_count ?? 0,
        ])->filter(fn($item) => $item['count'] > 0)->values()->toArray();
    }

    /**
     * Calculate counts per approval status for pages.
     * Note: These are global counts, not filtered by current search.
     *
     * @return array<string, int>
     */
    protected function calculateStatusFacets(): array
    {
        // Count approved pages (have approved_revision_id set in entity_page_data)
        $approvedCount = DB::table('entities')
            ->join('entity_page_data', 'entity_page_data.page_id', '=', 'entities.id')
            ->where('entities.type', '=', 'page')
            ->whereNull('entities.deleted_at')
            ->whereNotNull('entity_page_data.approved_revision_id')
            ->count();

        // Count pages by latest revision status for non-approved statuses
        // This is a simplified count - just shows total pages with each status
        $statusCounts = DB::table('entities')
            ->join('page_revisions', function ($join) {
                $join->on('page_revisions.page_id', '=', 'entities.id');
            })
            ->where('entities.type', '=', 'page')
            ->whereNull('entities.deleted_at')
            ->whereIn('page_revisions.status', ['draft', 'in_review', 'rejected'])
            ->whereRaw('page_revisions.id = (SELECT MAX(pr.id) FROM page_revisions pr WHERE pr.page_id = entities.id)')
            ->select([
                'page_revisions.status',
                DB::raw('COUNT(DISTINCT entities.id) as count'),
            ])
            ->groupBy('page_revisions.status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'approved' => $approvedCount,
            'in_review' => $statusCounts['in_review'] ?? 0,
            'draft' => $statusCounts['draft'] ?? 0,
            'rejected' => $statusCounts['rejected'] ?? 0,
        ];
    }
}

