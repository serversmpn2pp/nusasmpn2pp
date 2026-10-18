@props(['id', 'title'])
<dialog {{ $attributes->class(['ui-dialog'])->merge(['id' => $id, 'aria-labelledby' => $id.'-title']) }}>
    <div class="ui-dialog-header">
        <h2 class="ui-dialog-title" id="{{ $id }}-title">{{ $title }}</h2>
        <button type="button" class="ui-dialog-close" data-ui-dialog-close aria-label="Tutup dialog" autofocus>
            <i data-lucide="x" aria-hidden="true"></i>
        </button>
    </div>
    <div class="ui-dialog-body">{{ $slot }}</div>
    @isset ($footer)<div class="ui-dialog-footer">{{ $footer }}</div>@endisset
</dialog>
