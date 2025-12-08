@extends('layouts.simple')

@section('body')

    <div class="container px-xl py-s flex-container-row gap-l wrap justify-space-between">
        <div class="icon-list inline block">
            @include('home.parts.expand-toggle', ['classes' => 'text-muted text-link', 'target' => '.entity-list.compact .entity-item-snippet', 'key' => 'home-details'])
        </div>
        <div>
            <div class="icon-list inline block">
                @include('common.dark-mode-toggle', ['classes' => 'text-muted icon-list-item text-link'])
            </div>
        </div>
    </div>

    <div class="container" id="sop-dashboard">
        <div class="grid third gap-x-xxl no-row-gap">

            {{-- Column 1: My SOPs + Drafts --}}
            <div>
                @include('dashboard.parts.my-sops')

                @if(count($draftPages) > 0)
                    <div id="recent-drafts" class="card mb-xl">
                        <h3 class="card-title">{{ trans('entities.my_recent_drafts') }}</h3>
                        <div class="px-m">
                            @include('entities.list', ['entities' => $draftPages, 'style' => 'compact'])
                        </div>
                    </div>
                @endif
            </div>

            {{-- Column 2: Recently Updated + Pending Reviews --}}
            <div>
                @include('dashboard.parts.recently-updated')
                @include('dashboard.parts.pending-reviews')
            </div>

            {{-- Column 3: Departments + Activity + Permission Health --}}
            <div>
                @include('dashboard.parts.departments')

                <div id="recent-activity" class="card mb-xl">
                    <h3 class="card-title">{{ trans('entities.recent_activity') }}</h3>
                    <div class="px-m">
                        @include('common.activity-list', ['activity' => $activity])
                    </div>
                </div>

                @include('dashboard.parts.permission-health')
            </div>

        </div>
    </div>

@stop

