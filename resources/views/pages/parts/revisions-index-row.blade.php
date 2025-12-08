<div class="item-list-row flex-container-row items-center wrap">
    <div class="flex fit-content min-width-xxxxs px-m py-xs">
        <span class="hide-over-l">{{ trans('entities.pages_revisions_number') }}</span>
        {{ $revision->revision_number == 0 ? '' : $revision->revision_number }}
    </div>
    <div class="flex-2 px-m py-xs min-width-s">
        {{ $revision->name }}
        <br>
        <small class="text-muted">(<strong class="hide-over-l">{{ trans('entities.pages_revisions_editor') }}: </strong>{{ $revision->is_markdown ? 'Markdown' : 'WYSIWYG' }})</small>
        @if($revision->status ?? false)
            <br>
            @php
                $statusClass = match($revision->status) {
                    'draft' => 'text-muted',
                    'in_review' => 'text-warning',
                    'approved' => 'text-pos',
                    'rejected' => 'text-neg',
                    default => '',
                };
            @endphp
            <span class="badge {{ $statusClass }}">
                {{ trans('entities.revision_status_' . $revision->status) }}
            </span>
            @if($revision->status === 'approved' && $page->approved_revision_id === $revision->id)
                <span class="badge text-pos">@icon('check-circle') {{ trans('entities.revision_current_approved') }}</span>
            @endif
        @endif
    </div>
    <div class="flex-3 px-m py-xs min-width-l">
        <div class="flex-container-row items-center gap-s">
            @if($revision->createdBy)
                <img class="avatar flex-none" height="30" width="30" src="{{ $revision->createdBy->getAvatar(30) }}" alt="{{ $revision->createdBy->name }}">
            @endif
            <div>
                @if($revision->createdBy) {{ $revision->createdBy->name }} @else {{ trans('common.deleted_user') }} @endif
                <br>
                <div class="text-muted">
                    <small>{{ $dates->absolute($revision->created_at) }}</small>
                    <small>({{ $dates->relative($revision->created_at) }})</small>
                </div>
            </div>
        </div>
    </div>
    <div class="flex-2 px-m py-xs min-width-m text-small">
        {{ $revision->summary }}
    </div>
    <div class="flex-2 px-m py-xs actions text-small text-l-right min-width-l">
        @if(!$oldest)
            <a href="{{ $revision->getUrl('changes') }}" target="_blank" rel="noopener">{{ trans('entities.pages_revisions_changes') }}</a>
            <span class="text-muted opacity-70">&nbsp;|&nbsp;</span>
        @endif


        @if ($current)
            <a target="_blank" rel="noopener" href="{{ $revision->page->getUrl() }}"><i>{{ trans('entities.pages_revisions_current') }}</i></a>
        @else
            <a href="{{ $revision->getUrl() }}" target="_blank" rel="noopener">{{ trans('entities.pages_revisions_preview') }}</a>

            @if(userCan(\BookStack\Permissions\Permission::PageUpdate, $revision->page))
                <span class="text-muted opacity-70">&nbsp;|&nbsp;</span>
                <div component="dropdown" class="dropdown-container">
                    <a refs="dropdown@toggle" href="#" aria-haspopup="true" aria-expanded="false">{{ trans('entities.pages_revisions_restore') }}</a>
                    <ul refs="dropdown@menu" class="dropdown-menu" role="menu">
                        <li class="px-m py-s"><small class="text-muted">{{trans('entities.revision_restore_confirm')}}</small></li>
                        <li>
                            <form action="{{ $revision->getUrl('/restore') }}" method="POST">
                                {!! csrf_field() !!}
                                <input type="hidden" name="_method" value="PUT">
                                <button type="submit" class="text-link icon-item">
                                    @icon('history')
                                    <div>{{ trans('entities.pages_revisions_restore') }}</div>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            @endif

            @if(userCan(\BookStack\Permissions\Permission::PageDelete, $revision->page))
                <span class="text-muted opacity-70">&nbsp;|&nbsp;</span>
                <div component="dropdown" class="dropdown-container">
                    <a refs="dropdown@toggle" href="#" aria-haspopup="true" aria-expanded="false">{{ trans('common.delete') }}</a>
                    <ul refs="dropdown@menu" class="dropdown-menu" role="menu">
                        <li class="px-m py-s"><small class="text-muted">{{trans('entities.revision_delete_confirm')}}</small></li>
                        <li>
                            <form action="{{ $revision->getUrl('/delete/') }}" method="POST">
                                {!! csrf_field() !!}
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="text-neg icon-item">
                                    @icon('delete')
                                    <div>{{ trans('common.delete') }}</div>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            @endif
        @endif

        {{-- Approval Workflow Actions --}}
        @if(($revision->status ?? 'draft') === 'draft' && userCan(\BookStack\Permissions\Permission::PageUpdate, $revision->page))
            <span class="text-muted opacity-70">&nbsp;|&nbsp;</span>
            <form action="{{ $revision->getUrl('/submit-review') }}" method="POST" class="inline">
                {!! csrf_field() !!}
                <button type="submit" class="text-link text-warning">{{ trans('entities.revision_submit_for_review') }}</button>
            </form>
        @endif

        @if(($revision->status ?? 'draft') === 'in_review')
            @if(userCan('content-export'))
                <span class="text-muted opacity-70">&nbsp;|&nbsp;</span>
                <a href="{{ $revision->getUrl('/approve') }}" class="text-pos">{{ trans('entities.revision_approve') }}</a>
                <span class="text-muted opacity-70">&nbsp;|&nbsp;</span>
                <a href="{{ $revision->getUrl('/reject') }}" class="text-neg">{{ trans('entities.revision_reject') }}</a>
            @endif
            @if(userCan(\BookStack\Permissions\Permission::PageUpdate, $revision->page))
                <span class="text-muted opacity-70">&nbsp;|&nbsp;</span>
                <form action="{{ $revision->getUrl('/withdraw') }}" method="POST" class="inline">
                    {!! csrf_field() !!}
                    <button type="submit" class="text-link text-muted">{{ trans('entities.revision_withdraw') }}</button>
                </form>
            @endif
        @endif
    </div>
</div>