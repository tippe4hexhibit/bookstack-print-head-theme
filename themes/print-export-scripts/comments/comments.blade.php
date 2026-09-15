{{--
    Override of resources/views/comments/comments.blade.php.

    Identical to core except the whole comments section is now wrapped in
    @auth/@endauth, so it's only rendered for signed-in users. Guests (the
    "Public" role, anyone browsing without logging in) never see the
    comments UI at all on a publicly-viewable page.

    Everything inside stays exactly as core has it, including the existing
    per-role permission checks (userCan(Permission::CommentCreateAll), etc.)
    that already gate who can add/see the "add comment" button, and the
    comment-create/update/delete routes in CommentController which enforce
    those same permissions server-side regardless of this view. So for
    signed-in users, visibility and the ability to respond are unchanged
    from core and still follow whatever the site's role permissions say.
--}}
@auth
<section components="page-comments tabs"
         option:page-comments:page-id="{{ $page->id }}"
         option:page-comments:created-text="{{ trans('entities.comment_created_success') }}"
         option:page-comments:count-text="{{ trans('entities.comment_thread_count') }}"
         option:page-comments:archived-count-text="{{ trans('entities.comment_archived_count') }}"
         option:page-comments:wysiwyg-text-direction="{{ $locale->htmlDirection() }}"
         class="comments-list tab-container"
         aria-label="{{ trans('entities.comments') }}">

    <div refs="page-comments@comment-count-bar" class="flex-container-row items-center">
        <div role="tablist" class="flex">
            <button type="button"
                    role="tab"
                    id="comment-tab-active"
                    aria-controls="comment-tab-panel-active"
                    refs="page-comments@active-tab"
                    aria-selected="true">{{ trans_choice('entities.comment_thread_count', $commentTree->activeThreadCount()) }}</button>
            <button type="button"
                    role="tab"
                    id="comment-tab-archived"
                    aria-controls="comment-tab-panel-archived"
                    refs="page-comments@archived-tab"
                    aria-selected="false">{{ trans_choice('entities.comment_archived_count', count($commentTree->getArchived())) }}</button>
        </div>
        @if ($commentTree->empty() && userCan(\BookStack\Permissions\Permission::CommentCreateAll))
            <div refs="page-comments@add-button-container" class="ml-m flex-container-row" >
                <button type="button"
                        refs="page-comments@add-comment-button"
                        class="button outline mb-m ml-auto">{{ trans('entities.comment_add') }}</button>
            </div>
        @endif
    </div>

    <div id="comment-tab-panel-active"
         refs="page-comments@active-container"
         tabindex="0"
         role="tabpanel"
         aria-labelledby="comment-tab-active"
         class="comment-container no-outline">
        <div refs="page-comments@comment-container">
            @foreach($commentTree->getActive() as $branch)
                @include('comments.comment-branch', ['branch' => $branch, 'readOnly' => false])
            @endforeach
        </div>

        <p class="text-center text-muted italic empty-state">{{ trans('entities.comment_none') }}</p>

        @if(userCan(\BookStack\Permissions\Permission::CommentCreateAll))
            @include('comments.create')
            @if (!$commentTree->empty())
                <div refs="page-comments@addButtonContainer" class="ml-m flex-container-row">
                    <button type="button"
                            refs="page-comments@add-comment-button"
                            class="button outline mb-m ml-auto">{{ trans('entities.comment_add') }}</button>
                </div>
            @endif
        @endif
    </div>

    <div refs="page-comments@archive-container"
         id="comment-tab-panel-archived"
         tabindex="0"
         role="tabpanel"
         aria-labelledby="comment-tab-archived"
         hidden="hidden"
         class="comment-container no-outline">
        @foreach($commentTree->getArchived() as $branch)
            @include('comments.comment-branch', ['branch' => $branch, 'readOnly' => false])
        @endforeach
            <p class="text-center text-muted italic empty-state">{{ trans('entities.comment_none') }}</p>
    </div>

    @if(userCan(\BookStack\Permissions\Permission::CommentCreateAll) || $commentTree->canUpdateAny())
        @push('body-end')
            @include('form.editor-translations')
            @include('entities.selector-popup')
        @endpush
    @endif

</section>
@endauth
