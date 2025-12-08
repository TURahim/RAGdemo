{{-- Permission Health Widget - Admin Only --}}
@if(userCan('settings-manage') && isset($permissionHealth))
<div id="permission-health" class="card mb-xl">
    <h3 class="card-title">{{ trans('entities.permission_health_title') }}</h3>
    <div class="px-m py-m">
        @if($permissionHealth['is_healthy'])
            <div class="flex-container-row items-center gap-s">
                <span class="text-pos">
                    @icon('check-circle')
                </span>
                <span class="text-pos">{{ trans('entities.permission_health_ok') }}</span>
            </div>
            <p class="small text-muted mt-s mb-none">
                {{ trans('entities.permission_health_stats', [
                    'entities' => number_format($permissionHealth['total_entities']),
                    'permissions' => number_format($permissionHealth['total_permissions'])
                ]) }}
            </p>
        @else
            <div class="flex-container-row items-center gap-s">
                <span class="text-warn">
                    @icon('warning')
                </span>
                <span class="text-warn">{{ trans('entities.permission_health_issues') }}</span>
            </div>
            
            <div class="mt-s">
                @if($permissionHealth['entities_without_permissions'] > 0)
                    <p class="small text-neg mb-xs">
                        {{ trans('entities.permission_health_missing', ['count' => $permissionHealth['entities_without_permissions']]) }}
                    </p>
                @endif
                @if($permissionHealth['orphaned_permissions'] > 0)
                    <p class="small text-neg mb-xs">
                        {{ trans('entities.permission_health_orphaned', ['count' => $permissionHealth['orphaned_permissions']]) }}
                    </p>
                @endif
            </div>

            <div class="mt-m">
                <a href="{{ url('/settings/maintenance') }}" class="button outline small">
                    {{ trans('entities.permission_health_repair') }}
                </a>
            </div>
        @endif

        <p class="small text-muted mt-m mb-none">
            {{ trans('entities.permission_health_audit_hint') }}
        </p>
    </div>
</div>
@endif

