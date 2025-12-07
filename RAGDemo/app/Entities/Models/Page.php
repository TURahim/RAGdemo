<?php

namespace BookStack\Entities\Models;

use BookStack\Entities\Tools\PageContent;
use BookStack\Permissions\PermissionApplicator;
use BookStack\Uploads\Attachment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class Page.
 * @property EntityPageData $pageData
 * @property int          $chapter_id
 * @property string       $html
 * @property string       $markdown
 * @property string       $text
 * @property bool         $template
 * @property bool         $draft
 * @property int          $revision_count
 * @property string       $editor
 * @property Chapter      $chapter
 * @property Collection   $attachments
 * @property Collection   $revisions
 * @property PageRevision $currentRevision
 * @property ?int         $approved_revision_id  ID of currently approved revision
 * @property ?Carbon      $next_review_date      When this SOP needs review
 * @property ?int         $review_interval_days  Days between reviews
 * @property-read ?PageRevision $approvedRevision Currently approved revision
 */
class Page extends BookChild
{
    use HasFactory;

    public string $textField = 'text';
    public string $htmlField = 'html';
    protected $hidden = ['html', 'markdown', 'text', 'pivot', 'deleted_at',  'entity_id', 'entity_type'];
    protected $fillable = ['name', 'priority'];

    protected $casts = [
        'draft'    => 'boolean',
        'template' => 'boolean',
        'next_review_date' => 'date',
    ];

    /**
     * Get the entities that are visible to the current user.
     */
    public function scopeVisible(Builder $query): Builder
    {
        $query = app()->make(PermissionApplicator::class)->restrictDraftsOnPageQuery($query);

        return parent::scopeVisible($query);
    }

    /**
     * Get the chapter that this page is in, If applicable.
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Check if this page has a chapter.
     */
    public function hasChapter(): bool
    {
        return $this->chapter()->count() > 0;
    }

    /**
     * Get the associated page revisions, ordered by created date.
     * Only provides actual saved page revision instances, Not drafts.
     */
    public function revisions(): HasMany
    {
        return $this->allRevisions()
            ->where('type', '=', 'version')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Get the current revision for the page if existing.
     */
    public function currentRevision(): HasOne
    {
        return $this->hasOne(PageRevision::class)
            ->where('type', '=', 'version')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Get all revision instances assigned to this page.
     * Includes all types of revisions.
     */
    public function allRevisions(): HasMany
    {
        return $this->hasMany(PageRevision::class);
    }

    /**
     * Get the attachments assigned to this page.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'uploaded_to')->orderBy('order', 'asc');
    }

    /**
     * Get the url of this page.
     */
    public function getUrl(string $path = ''): string
    {
        $parts = [
            'books',
            urlencode($this->book_slug ?? $this->book->slug),
            $this->draft ? 'draft' : 'page',
            $this->draft ? $this->id : urlencode($this->slug),
            trim($path, '/'),
        ];

        return url('/' . implode('/', $parts));
    }

    /**
     * Get the ID-based permalink for this page.
     */
    public function getPermalink(): string
    {
        return url("/link/{$this->id}");
    }

    /**
     * Get this page for JSON display.
     */
    public function forJsonDisplay(): self
    {
        $refreshed = $this->refresh()->unsetRelations()->load(['tags', 'createdBy', 'updatedBy', 'ownedBy']);
        $refreshed->setHidden(array_diff($refreshed->getHidden(), ['html', 'markdown']));
        $refreshed->setAttribute('raw_html', $refreshed->html);
        $refreshed->setAttribute('html', (new PageContent($refreshed))->render());

        return $refreshed;
    }

    /**
     * @return HasOne<EntityPageData, $this>
     */
    public function relatedData(): HasOne
    {
        return $this->hasOne(EntityPageData::class, 'page_id', 'id');
    }

    /**
     * Get the currently approved revision for this page.
     */
    public function approvedRevision(): BelongsTo
    {
        return $this->belongsTo(PageRevision::class, 'approved_revision_id');
    }

    /**
     * Check if this page has an approved revision.
     */
    public function hasApprovedRevision(): bool
    {
        return !is_null($this->approved_revision_id);
    }

    /**
     * Check if the current content matches the approved revision.
     * Returns true if the latest revision is the approved one.
     */
    public function isCurrentContentApproved(): bool
    {
        if (!$this->hasApprovedRevision()) {
            return false;
        }

        $latestRevision = $this->currentRevision;
        return $latestRevision && $latestRevision->id === $this->approved_revision_id;
    }

    /**
     * Check if this page is due for review.
     */
    public function isDueForReview(): bool
    {
        if (is_null($this->next_review_date)) {
            return false;
        }

        return $this->next_review_date->isPast() || $this->next_review_date->isToday();
    }

    /**
     * Check if editing should be locked due to approval status.
     * Page is locked if current content matches approved revision.
     */
    public function isEditLocked(): bool
    {
        return $this->isCurrentContentApproved();
    }
}
