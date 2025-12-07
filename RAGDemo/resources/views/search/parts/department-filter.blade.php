{{--
$departments - Collection of visible bookshelves/departments
$filters - Array of current search filter values
--}}
@if($departments->count() > 0)
<div class="form-group">
    <label for="filter-department">{{ trans('entities.search_in_department') }}</label>
    <select id="filter-department" name="filters[in_department]" class="input-fill-width">
        <option value="">{{ trans('entities.search_all_departments') }}</option>
        @foreach($departments as $department)
            <option value="{{ $department->id }}"
                @if(isset($filters['in_department']) && $filters['in_department'] == $department->id) selected @endif>
                {{ $department->name }}
            </option>
        @endforeach
    </select>
</div>
@endif

