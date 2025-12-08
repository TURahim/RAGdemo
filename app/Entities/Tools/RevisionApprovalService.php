<?php

namespace BookStack\Entities\Tools;

use BookStack\Activity\ActivityType;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Models\PageRevision;
use BookStack\Exceptions\NotifyException;
use BookStack\Facades\Activity;
use BookStack\Permissions\JointPermissionBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RevisionApprovalService
{
    public function __construct(
        protected JointPermissionBuilder $permissionBuilder,
    ) {
    }

    /**
     * Submit a revision for review.
     * Changes status from 'draft' to 'in_review'.
     *
     * @throws NotifyException
     */
    public function submitForReview(PageRevision $revision): PageRevision
    {
        if (!$revision->isDraft()) {
            throw new NotifyException(
                trans('entities.revision_cannot_submit_status', ['status' => $revision->status]),
                $revision->getUrl()
            );
        }

        $revision->status = PageRevision::STATUS_IN_REVIEW;
        $revision->save();

        Activity::add(ActivityType::REVISION_SUBMIT_REVIEW, $revision);

        return $revision;
    }

    /**
     * Approve a revision.
     * Changes status from 'in_review' to 'approved' and sets as page's approved revision.
     *
     * @throws NotifyException
     */
    public function approve(PageRevision $revision, ?string $notes = null, ?int $reviewIntervalDays = null): PageRevision
    {
        if (!$revision->isInReview()) {
            throw new NotifyException(
                trans('entities.revision_cannot_approve_status', ['status' => $revision->status]),
                $revision->getUrl()
            );
        }

        return DB::transaction(function () use ($revision, $notes, $reviewIntervalDays) {
            // Update revision status
            $revision->status = PageRevision::STATUS_APPROVED;
            $revision->approved_by = user()->id;
            $revision->approved_at = Carbon::now();
            $revision->review_notes = $notes;
            $revision->save();

            // Update page's approved revision
            $page = $revision->page;
            $this->setPageApprovedRevision($page, $revision, $reviewIntervalDays);

            Activity::add(ActivityType::REVISION_APPROVE, $revision);

            return $revision;
        });
    }

    /**
     * Reject a revision.
     * Changes status from 'in_review' to 'rejected'.
     *
     * @throws NotifyException
     */
    public function reject(PageRevision $revision, ?string $notes = null): PageRevision
    {
        if (!$revision->isInReview()) {
            throw new NotifyException(
                trans('entities.revision_cannot_reject_status', ['status' => $revision->status]),
                $revision->getUrl()
            );
        }

        $revision->status = PageRevision::STATUS_REJECTED;
        $revision->review_notes = $notes;
        $revision->save();

        Activity::add(ActivityType::REVISION_REJECT, $revision);

        return $revision;
    }

    /**
     * Reset a revision back to draft status.
     * Can be used for rejected revisions or to withdraw from review.
     *
     * @throws NotifyException
     */
    public function resetToDraft(PageRevision $revision): PageRevision
    {
        if ($revision->isApproved()) {
            throw new NotifyException(
                trans('entities.revision_cannot_reset_approved'),
                $revision->getUrl()
            );
        }

        $revision->status = PageRevision::STATUS_DRAFT;
        $revision->approved_by = null;
        $revision->approved_at = null;
        $revision->review_notes = null;
        $revision->save();

        return $revision;
    }

    /**
     * Set the approved revision for a page and update review schedule.
     */
    protected function setPageApprovedRevision(Page $page, PageRevision $revision, ?int $reviewIntervalDays = null): void
    {
        // Get the page's entity_page_data
        $pageData = $page->relatedData()->firstOrCreate(['page_id' => $page->id]);

        $pageData->approved_revision_id = $revision->id;

        // Set review interval if provided
        if ($reviewIntervalDays !== null) {
            $pageData->review_interval_days = $reviewIntervalDays;
        }

        // Calculate next review date based on interval
        if ($pageData->review_interval_days) {
            $pageData->next_review_date = Carbon::now()->addDays($pageData->review_interval_days);
        }

        $pageData->save();

        // Clear page's cached data
        $page->refresh();

        // Rebuild permissions in case approval affects visibility
        $this->permissionBuilder->rebuildForEntity($page);
    }

    /**
     * Check if a user can approve revisions.
     * By default, users with 'content-export' permission can approve.
     * This can be customized to use a dedicated 'revision-approve' permission.
     */
    public function userCanApprove(): bool
    {
        return userCan('content-export');
    }

    /**
     * Check if a user can override approval locks (admin override).
     */
    public function userCanOverrideApproval(): bool
    {
        return userCan('settings-manage');
    }

    /**
     * Get the approval status label for display.
     */
    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            PageRevision::STATUS_DRAFT => trans('entities.revision_status_draft'),
            PageRevision::STATUS_IN_REVIEW => trans('entities.revision_status_in_review'),
            PageRevision::STATUS_APPROVED => trans('entities.revision_status_approved'),
            PageRevision::STATUS_REJECTED => trans('entities.revision_status_rejected'),
            default => $status,
        };
    }

    /**
     * Get the CSS class for a status badge.
     */
    public function getStatusClass(string $status): string
    {
        return match ($status) {
            PageRevision::STATUS_DRAFT => 'text-muted',
            PageRevision::STATUS_IN_REVIEW => 'text-warning',
            PageRevision::STATUS_APPROVED => 'text-pos',
            PageRevision::STATUS_REJECTED => 'text-neg',
            default => '',
        };
    }
}

