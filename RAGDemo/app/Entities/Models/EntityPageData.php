<?php

namespace BookStack\Entities\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $page_id
 * @property bool   $draft
 * @property bool   $template
 * @property int    $revision_count
 * @property string $editor
 * @property string $html
 * @property string $text
 * @property string $markdown
 * @property ?int   $approved_revision_id   ID of currently approved revision
 * @property ?string $next_review_date      Date when SOP needs review
 * @property ?int   $review_interval_days   Days between reviews
 */
class EntityPageData extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'page_id';
    public $incrementing = false;

    public static array $fields = [
        'draft',
        'template',
        'revision_count',
        'editor',
        'html',
        'text',
        'markdown',
        // SOP approval workflow fields
        'approved_revision_id',
        'next_review_date',
        'review_interval_days',
    ];
}
