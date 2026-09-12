{{--
$activity - array
Theme override: Hides the "Recent Activity" card from guests since it
surfaces who made each change. "Recently Updated Pages" stays visible
to guests as it doesn't attribute changes to a user.
--}}
@if(!user()->isGuest())
    <div id="recent-activity" class="card mb-xl">
        <h3 class="card-title">{{ trans('entities.recent_activity') }}</h3>
        <div class="px-m">
            @include('common.activity-list', ['activity' => $activity])
        </div>
    </div>
@endif
