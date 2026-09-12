{{--
$activity - array
Theme override: Hides the "Recent Activity" section from guests since it
surfaces who made each change. "Recently Updated Pages" stays visible
to guests as it doesn't attribute changes to a user.
--}}
@if(!user()->isGuest())
    <div id="recent-activity" class="mb-xl">
        <h5>{{ trans('entities.recent_activity') }}</h5>
        @include('common.activity-list', ['activity' => $activity])
    </div>
@endif
