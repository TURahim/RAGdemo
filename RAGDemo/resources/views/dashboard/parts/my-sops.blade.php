<div id="my-sops" class="card mb-xl">
    <h3 class="card-title">{{ trans('entities.dashboard_my_sops') }}</h3>
    <div class="px-m">
        @if(count($mySOPs) > 0)
            @include('entities.list', [
                'entities' => $mySOPs,
                'style' => 'compact',
                'showPath' => true,
            ])
        @else
            <p class="text-muted pb-m">
                {{ trans('entities.dashboard_no_sops') }}
            </p>
        @endif
    </div>
    @if(count($mySOPs) > 0)
        <a href="{{ url('/search?term=' . urlencode('{owned_by:me} {type:page}')) }}" class="card-footer-link">
            {{ trans('common.view_all') }}
        </a>
    @endif
</div>

