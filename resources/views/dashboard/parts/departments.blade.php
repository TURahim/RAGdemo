<div id="departments" class="card mb-xl">
    <h3 class="card-title">{{ trans('entities.dashboard_departments') }}</h3>
    <div class="px-m pb-m">
        @if(count($departments) > 0)
            <div class="entity-list compact">
                @foreach($departments as $department)
                    <a href="{{ $department->getUrl() }}" class="entity-list-item entity-list-item-link">
                        <span class="entity-list-item-icon text-bookshelf">@icon('bookshelf')</span>
                        <span class="entity-list-item-name break-text">{{ $department->name }}</span>
                        @if($department->books_count ?? false)
                            <span class="text-muted text-small ml-xs">({{ $department->books_count }})</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-muted">
                {{ trans('entities.shelves_empty') }}
            </p>
        @endif
    </div>
    @if(count($departments) > 0)
        <a href="{{ url('/shelves') }}" class="card-footer-link">
            {{ trans('common.view_all') }}
        </a>
    @endif
</div>

