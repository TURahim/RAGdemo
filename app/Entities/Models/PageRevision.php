<?php

namespace BookStack\Entities\Models;

use BookStack\Activity\Models\Loggable;
use BookStack\App\Model;
use BookStack\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class PageRevision.
 *
 * @property mixed  $id
 * @property int    $page_id
 * @property string $name
 * @property string $slug
 * @property string $book_slug
 * @property int    $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string $type
 * @property string $summary
 * @property string $markdown
 * @property string $html
 * @property string $text
 * @property int    $revision_number
 * @property Page   $page
 * @property-read ?User $createdBy
 * @property string $status         Approval status: draft, in_review, approved, rejected
 * @property ?int   $approved_by    User ID who approved this revision
 * @property ?Carbon $approved_at   Timestamp when approved
 * @property ?string $review_notes  Notes from reviewer (optional)
 * @property-read ?User $approvedBy User who approved this revision
 */
class PageRevision extends Model implements Loggable
{
    use HasFactory;

    /**
     * Approval status constants.
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = ['name', 'text', 'summary'];
    protected $hidden = ['html', 'markdown', 'text'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /**
     * Get the user that created the page revision.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this revision.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if this revision is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if this revision is pending review.
     */
    public function isInReview(): bool
    {
        return $this->status === self::STATUS_IN_REVIEW;
    }

    /**
     * Check if this revision is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if this revision was rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Get all valid status values.
     * @return array<string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_IN_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }

    /**
     * Get the page this revision originates from.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Get the url for this revision.
     */
    public function getUrl(string $path = ''): string
    {
        return $this->page->getUrl('/revisions/' . $this->id . '/' . ltrim($path, '/'));
    }

    /**
     * Get the previous revision for the same page if existing.
     */
    public function getPreviousRevision(): ?PageRevision
    {
        $id = static::newQuery()->where('page_id', '=', $this->page_id)
            ->where('id', '<', $this->id)
            ->max('id');

        if ($id) {
            return static::query()->find($id);
        }

        return null;
    }

    /**
     * Allows checking of the exact class, Used to check entity type.
     * Included here to align with entities in similar use cases.
     * (Yup, Bit of an awkward hack).
     *
     * @deprecated Use instanceof instead.
     */
    public static function isA(string $type): bool
    {
        return $type === 'revision';
    }

    public function logDescriptor(): string
    {
        return "Revision #{$this->revision_number} (ID: {$this->id}) for page ID {$this->page_id}";
    }
}
