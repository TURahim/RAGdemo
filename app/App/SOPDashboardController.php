<?php

namespace BookStack\App;

use BookStack\Activity\ActivityQueries;
use BookStack\Entities\Queries\DashboardQueries;
use BookStack\Entities\Queries\EntityQueries;
use BookStack\Http\Controller;
use BookStack\Permissions\PermissionConsistencyService;
use Illuminate\Http\Request;

class SOPDashboardController extends Controller
{
    public function __construct(
        protected EntityQueries $queries,
        protected DashboardQueries $dashboardQueries,
        protected PermissionConsistencyService $permissionConsistencyService,
    ) {
    }

    /**
     * Display the SOP Dashboard.
     * Shows My SOPs, Recently Updated, Department Shortcuts, and Pending Reviews.
     */
    public function index(Request $request, ActivityQueries $activities)
    {
        $this->preventGuestAccess();

        // Recent activity
        $activity = $activities->latest(8);

        // User's draft pages
        $draftPages = [];
        if ($this->isSignedIn()) {
            $draftPages = $this->queries->pages->currentUserDraftsForList()
                ->orderBy('updated_at', 'desc')
                ->with('book')
                ->take(6)
                ->get();
        }

        // My SOPs - Pages owned by the current user
        $mySOPs = $this->dashboardQueries->currentUserOwnedPages(6);

        // Recently Updated SOPs
        $recentlyUpdatedPages = $this->queries->pages->visibleForList()
            ->where('draft', false)
            ->orderBy('updated_at', 'desc')
            ->with('book')
            ->take(10)
            ->get();

        // All visible departments (shelves)
        $departments = $this->queries->shelves->visibleForList()
            ->orderBy('name', 'asc')
            ->get();

        // Pending reviews - Revisions in 'in_review' status
        $pendingReviews = $this->dashboardQueries->pendingReviewRevisions(5);

        // SOPs due for review
        $overdueReviews = $this->dashboardQueries->overdueSopReviews(5);

        // Permission health (admin only) - cached to avoid heavy queries on every load
        $permissionHealth = null;
        if (userCan('settings-manage')) {
            $permissionHealth = cache()->remember('permission_health_stats', 300, function () {
                return $this->permissionConsistencyService->getStatistics();
            });
        }

        $this->setPageTitle(trans('entities.dashboard_title'));

        return view('dashboard.index', [
            'activity'             => $activity,
            'draftPages'           => $draftPages,
            'mySOPs'               => $mySOPs,
            'recentlyUpdatedPages' => $recentlyUpdatedPages,
            'departments'          => $departments,
            'pendingReviews'       => $pendingReviews,
            'overdueReviews'       => $overdueReviews,
            'permissionHealth'     => $permissionHealth,
        ]);
    }
}

