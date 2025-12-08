<div id="pending-reviews" class="card mb-xl">
    <h3 class="card-title">{{ trans('entities.dashboard_pending_reviews') }}</h3>
    <div class="px-m pb-m">
        @if(count($pendingReviews) > 0)
            <div class="entity-list compact">
                @foreach($pendingReviews as $revision)
                    @if($revision->page)
                        <div class="entity-list-item">
                            <span class="entity-list-item-icon text-page">@icon('page')</span>
                            <div class="entity-list-item-content">
                                <a href="{{ $revision->page->getUrl() }}" class="entity-list-item-name break-text">
                                    {{ $revision->page->name }}
                                </a>
                                <div class="entity-item-snippet text-muted text-small">
                                    @if($revision->createdBy)
                                        {{ trans('entities.dashboard_submitted_by', ['user' => $revision->createdBy->name]) }}
                                        &bull;
                                    @endif
                                    <span title="{{ $revision->created_at->format('Y-m-d H:i:s') }}">
                                        {{ trans('entities.dashboard_submitted_at', ['time' => $revision->created_at->diffForHumans()]) }}
                                    </span>
                                </div>
                            </div>
                            <a href="{{ $revision->getUrl() }}" class="entity-list-item-action text-muted" title="{{ trans('entities.pages_revisions') }}">
                                @icon('history')
                            </a>
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            <p class="text-muted">
                {{ trans('entities.dashboard_no_pending_reviews') }}
            </p>
        @endif
    </div>
</div>

@if(count($overdueReviews ?? []) > 0)
<div id="overdue-reviews" class="card mb-xl">
    <h3 class="card-title">{{ trans('entities.dashboard_overdue_reviews') }}</h3>
    <div class="px-m pb-m">
        <div class="entity-list compact">
            @foreach($overdueReviews as $page)
                <div class="entity-list-item">
                    <span class="entity-list-item-icon text-page text-warning">@icon('page')</span>
                    <div class="entity-list-item-content">
                        <a href="{{ $page->getUrl() }}" class="entity-list-item-name break-text">
                            {{ $page->name }}
                        </a>
                        <div class="entity-item-snippet text-muted text-small">
                            @if($page->next_review_date)
                                {{ trans('entities.dashboard_review_due', ['date' => $page->next_review_date->format('M j, Y')]) }}
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

