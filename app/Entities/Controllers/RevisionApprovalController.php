<?php

namespace BookStack\Entities\Controllers;

use BookStack\Entities\Models\PageRevision;
use BookStack\Entities\Queries\PageQueries;
use BookStack\Entities\Tools\RevisionApprovalService;
use BookStack\Exceptions\NotFoundException;
use BookStack\Http\Controller;
use BookStack\Permissions\Permission;
use Illuminate\Http\Request;

class RevisionApprovalController extends Controller
{
    public function __construct(
        protected PageQueries $pageQueries,
        protected RevisionApprovalService $approvalService,
    ) {
    }

    /**
     * Submit a revision for review.
     *
     * @throws NotFoundException
     */
    public function submitForReview(string $bookSlug, string $pageSlug, int $revisionId)
    {
        $page = $this->pageQueries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        $this->checkOwnablePermission(Permission::PageUpdate, $page);

        /** @var ?PageRevision $revision */
        $revision = $page->revisions()->where('id', '=', $revisionId)->first();
        if ($revision === null) {
            throw new NotFoundException("Revision #{$revisionId} not found");
        }

        $this->approvalService->submitForReview($revision);

        $this->showSuccessNotification(trans('entities.revision_submitted_for_review'));

        return redirect($revision->getUrl());
    }

    /**
     * Show the approval form for a revision.
     *
     * @throws NotFoundException
     */
    public function showApproveForm(string $bookSlug, string $pageSlug, int $revisionId)
    {
        $page = $this->pageQueries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        
        if (!$this->approvalService->userCanApprove()) {
            $this->showPermissionError();
        }

        /** @var ?PageRevision $revision */
        $revision = $page->revisions()->where('id', '=', $revisionId)->first();
        if ($revision === null) {
            throw new NotFoundException("Revision #{$revisionId} not found");
        }

        $this->setPageTitle(trans('entities.revision_approve_title'));

        return view('pages.revision-approve', [
            'page' => $page,
            'revision' => $revision,
            'book' => $page->book,
        ]);
    }

    /**
     * Approve a revision.
     *
     * @throws NotFoundException
     */
    public function approve(Request $request, string $bookSlug, string $pageSlug, int $revisionId)
    {
        $page = $this->pageQueries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        
        if (!$this->approvalService->userCanApprove()) {
            $this->showPermissionError();
        }

        /** @var ?PageRevision $revision */
        $revision = $page->revisions()->where('id', '=', $revisionId)->first();
        if ($revision === null) {
            throw new NotFoundException("Revision #{$revisionId} not found");
        }

        $validated = $this->validate($request, [
            'review_notes' => ['nullable', 'string', 'max:1000'],
            'review_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $this->approvalService->approve(
            $revision,
            $validated['review_notes'] ?? null,
            $validated['review_interval_days'] ?? null
        );

        $this->showSuccessNotification(trans('entities.revision_approved'));

        return redirect($page->getUrl());
    }

    /**
     * Show the rejection form for a revision.
     *
     * @throws NotFoundException
     */
    public function showRejectForm(string $bookSlug, string $pageSlug, int $revisionId)
    {
        $page = $this->pageQueries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        
        if (!$this->approvalService->userCanApprove()) {
            $this->showPermissionError();
        }

        /** @var ?PageRevision $revision */
        $revision = $page->revisions()->where('id', '=', $revisionId)->first();
        if ($revision === null) {
            throw new NotFoundException("Revision #{$revisionId} not found");
        }

        $this->setPageTitle(trans('entities.revision_reject_title'));

        return view('pages.revision-reject', [
            'page' => $page,
            'revision' => $revision,
            'book' => $page->book,
        ]);
    }

    /**
     * Reject a revision.
     *
     * @throws NotFoundException
     */
    public function reject(Request $request, string $bookSlug, string $pageSlug, int $revisionId)
    {
        $page = $this->pageQueries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        
        if (!$this->approvalService->userCanApprove()) {
            $this->showPermissionError();
        }

        /** @var ?PageRevision $revision */
        $revision = $page->revisions()->where('id', '=', $revisionId)->first();
        if ($revision === null) {
            throw new NotFoundException("Revision #{$revisionId} not found");
        }

        $validated = $this->validate($request, [
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->approvalService->reject($revision, $validated['review_notes'] ?? null);

        $this->showSuccessNotification(trans('entities.revision_rejected'));

        return redirect($page->getUrl('/revisions'));
    }

    /**
     * Withdraw a revision from review (reset to draft).
     *
     * @throws NotFoundException
     */
    public function withdrawFromReview(string $bookSlug, string $pageSlug, int $revisionId)
    {
        $page = $this->pageQueries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        $this->checkOwnablePermission(Permission::PageUpdate, $page);

        /** @var ?PageRevision $revision */
        $revision = $page->revisions()->where('id', '=', $revisionId)->first();
        if ($revision === null) {
            throw new NotFoundException("Revision #{$revisionId} not found");
        }

        $this->approvalService->resetToDraft($revision);

        $this->showSuccessNotification(trans('entities.revision_withdrawn'));

        return redirect($revision->getUrl());
    }
}

