{{--
$facets - Array with 'departments' and 'statuses' keys containing facet counts
$options - Current SearchOptions object
--}}
@if(!empty($facets['departments']) || !empty($facets['statuses']))
<div class="search-facets card content-wrap mt-m" id="search-facets">
    <h6 class="list-heading">{{ trans('entities.search_refine_results') }}</h6>

    {{-- Department Facets --}}
    @if(!empty($facets['departments']))
    <div class="facet-group mb-m">
        <h6 class="text-muted small">{{ trans('entities.search_by_department') }}</h6>
        <ul class="facet-list">
            @foreach($facets['departments'] as $dept)
                @php
                    $filterMap = $options->filters->toValueMap();
                    $isActive = isset($filterMap['in_department']) && $filterMap['in_department'] == $dept['id'];
                    $searchParams = request()->except(['filters.in_department', 'page']);
                    if (!$isActive) {
                        $searchParams['filters']['in_department'] = $dept['id'];
                    }
                @endphp
                <li class="facet-item @if($isActive) active @endif">
                    <a href="{{ url('/search') }}?{{ http_build_query($searchParams) }}" class="facet-link">
                        <span class="facet-name">{{ $dept['name'] }}</span>
                        <span class="facet-count badge">{{ $dept['count'] }}</span>
                        @if($isActive)
                            <span class="facet-remove" title="{{ trans('common.remove') }}">@icon('close')</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Status Facets --}}
    @if(!empty($facets['statuses']) && !user()->isGuest())
    <div class="facet-group">
        <h6 class="text-muted small">{{ trans('entities.search_by_status') }}</h6>
        <ul class="facet-list">
            @php
                $statusLabels = [
                    'approved' => trans('entities.search_status_approved'),
                    'in_review' => trans('entities.search_status_in_review'),
                    'draft' => trans('entities.search_status_draft'),
                    'rejected' => trans('entities.search_status_rejected'),
                ];
                $statusClasses = [
                    'approved' => 'text-positive',
                    'in_review' => 'text-warning',
                    'draft' => 'text-muted',
                    'rejected' => 'text-neg',
                ];
                $filterMap = $options->filters->toValueMap();
                $currentStatus = $filterMap['approval_status'] ?? '';
            @endphp
            @foreach($facets['statuses'] as $status => $count)
                @if($count > 0)
                    @php
                        $isActive = $currentStatus === $status;
                        $searchParams = request()->except(['filters.approval_status', 'page']);
                        if (!$isActive) {
                            $searchParams['filters']['approval_status'] = $status;
                        }
                    @endphp
                    <li class="facet-item @if($isActive) active @endif">
                        <a href="{{ url('/search') }}?{{ http_build_query($searchParams) }}" class="facet-link">
                            <span class="facet-name {{ $statusClasses[$status] ?? '' }}">{{ $statusLabels[$status] ?? $status }}</span>
                            <span class="facet-count badge">{{ $count }}</span>
                            @if($isActive)
                                <span class="facet-remove" title="{{ trans('common.remove') }}">@icon('close')</span>
                            @endif
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
    @endif
</div>
@endif

