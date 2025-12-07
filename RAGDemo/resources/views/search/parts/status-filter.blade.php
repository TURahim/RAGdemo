{{--
$filters - Array of current search filter values
Approval status filter - only shown for authenticated users
--}}
@if(!user()->isGuest())
<div class="form-group">
    <h6>{{ trans('entities.search_approval_status') }}</h6>
    @php
        $currentStatuses = isset($filters['approval_status']) ? explode('|', $filters['approval_status']) : [];
    @endphp

    <label class="checkbox">
        <input type="checkbox"
               name="filters[approval_status][]"
               value="approved"
               @if(in_array('approved', $currentStatuses)) checked @endif>
        <span class="text-positive">{{ trans('entities.search_status_approved') }}</span>
    </label>

    <label class="checkbox">
        <input type="checkbox"
               name="filters[approval_status][]"
               value="in_review"
               @if(in_array('in_review', $currentStatuses)) checked @endif>
        <span class="text-warning">{{ trans('entities.search_status_in_review') }}</span>
    </label>

    <label class="checkbox">
        <input type="checkbox"
               name="filters[approval_status][]"
               value="draft"
               @if(in_array('draft', $currentStatuses)) checked @endif>
        <span class="text-muted">{{ trans('entities.search_status_draft') }}</span>
    </label>

    <label class="checkbox">
        <input type="checkbox"
               name="filters[approval_status][]"
               value="rejected"
               @if(in_array('rejected', $currentStatuses)) checked @endif>
        <span class="text-neg">{{ trans('entities.search_status_rejected') }}</span>
    </label>

    <p class="text-muted small mt-xs">{{ trans('entities.search_status_pages_only') }}</p>
</div>
@endif

