@extends('layouts.simple')

@section('body')
    <div class="container small">

        <div class="my-s">
            @include('entities.breadcrumbs', ['crumbs' => [
                $page->book,
                $page->chapter,
                $page,
                $page->getUrl('/revisions') => [
                    'text' => trans('entities.pages_revisions'),
                    'icon' => 'history',
                ],
                $revision->getUrl() => trans('entities.pages_revisions_numbered', ['id' => $revision->id]),
                $revision->getUrl('/approve') => [
                    'text' => trans('entities.revision_approve'),
                    'icon' => 'check-circle',
                ]
            ]])
        </div>

        <main class="card content-wrap auto-height">
            <h1 class="list-heading">{{ trans('entities.revision_approve_title') }}</h1>

            <p class="text-muted mb-m">
                {{ trans('entities.pages_revisions_numbered', ['id' => $revision->revision_number]) }}
                &mdash;
                <strong>{{ $revision->name }}</strong>
            </p>

            <form action="{{ $revision->getUrl('/approve') }}" method="POST">
                {!! csrf_field() !!}

                <div class="form-group">
                    <label for="review_notes">{{ trans('entities.revision_review_notes') }}</label>
                    <textarea id="review_notes"
                              name="review_notes"
                              class="input-fill-width"
                              rows="3"
                              placeholder="{{ trans('entities.revision_review_notes_placeholder') }}"></textarea>
                </div>

                <div class="form-group">
                    <label for="review_interval_days">{{ trans('entities.revision_review_interval') }}</label>
                    <input type="number"
                           id="review_interval_days"
                           name="review_interval_days"
                           class="input-fill-width"
                           min="1"
                           max="365"
                           value="{{ $page->review_interval_days ?? '' }}"
                           placeholder="e.g., 90">
                    <p class="small text-muted">{{ trans('entities.revision_review_interval_help') }}</p>
                </div>

                <div class="form-group text-right">
                    <a href="{{ $page->getUrl('/revisions') }}" class="button outline">{{ trans('common.cancel') }}</a>
                    <button type="submit" class="button pos">
                        @icon('check-circle')
                        {{ trans('entities.revision_approve') }}
                    </button>
                </div>
            </form>
        </main>

    </div>
@stop

