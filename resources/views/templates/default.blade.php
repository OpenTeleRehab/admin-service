@if ($page->file_id)
<div class="banner-container">
    <img src="{{ "/api/admin/file/" . $page->file_id }}" alt="banner" class="w-100">
    <div class="banner-title p-3">
        {{ $page->title }}
    </div>
</div>
@else
    <h2 class="p-3">{{ $page->title }}</h2>
@endif
<div class="p-3 flex-grow-1" style="{{ 'color: ' . $page->text_color . '; background-color: ' . $page->background_color }}">
    {!! $page->content !!}

    @if ($page->auto_translated)
        <div class="d-flex justify-content-end">
            <img src="/images/google-translation.png" alt="text attribution" />
        </div>
    @endif
</div>
