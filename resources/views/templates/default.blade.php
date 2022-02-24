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
</div>
