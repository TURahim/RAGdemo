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
                $revision->getUrl('/reject') => [
                    'text' => trans('entities.revision_reject'),
                    'icon' => 'close',
                ]
            ]])
        </div>

        <main class="card content-wrap auto-height">
            <h1 class="list-heading">{{ trans('entities.revision_reject_title') }}</h1>

            <p class="text-muted mb-m">
                {{ trans('entities.pages_revisions_numbered', ['id' => $revision->revision_number]) }}
                &mdash;
                <strong>{{ $revision->name }}</strong>
            </p>

            <div class="callout warning mb-m">
                <p>Rejecting this revision will notify the author and allow them to make corrections before resubmitting.</p>
            </div>

            <form action="{{ $revision->getUrl('/reject') }}" method="POST">
                {!! csrf_field() !!}

                <div class="form-group">
                    <label for="review_notes">{{ trans('entities.revision_review_notes') }}</label>
                    <textarea id="review_notes"
                              name="review_notes"
                              class="input-fill-width"
                              rows="4"
                              placeholder="{{ trans('entities.revision_review_notes_placeholder') }}"></textarea>
                    <p class="small text-muted">Provide feedback to help the author improve this revision.</p>
                </div>

                <div class="form-group text-right">
                    <a href="{{ $page->getUrl('/revisions') }}" class="button outline">{{ trans('common.cancel') }}</a>
                    <button type="submit" class="button neg">
                        @icon('close')
                        {{ trans('entities.revision_reject') }}
                    </button>
                </div>
            </form>
        </main>

    </div>
@stop

