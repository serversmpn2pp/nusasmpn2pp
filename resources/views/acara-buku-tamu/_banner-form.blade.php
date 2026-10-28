@php
    use App\Models\AcaraBukuTamu as Acara;
    $mode = old('banner_tampilan', $acara->banner_tampilan);
    $mode = in_array($mode, array_keys(Acara::BANNER_TAMPILAN), true) ? $mode : 'potong';
    $fokusDesktop = old('banner_fokus_desktop', $acara->banner_fokus_desktop);
    $fokusDesktop = is_numeric($fokusDesktop) ? max(0, min(100, (int) $fokusDesktop)) : 66;
    $fokusMobile = old('banner_fokus_mobile', $acara->banner_fokus_mobile);
    $fokusMobile = is_numeric($fokusMobile) ? max(0, min(100, (int) $fokusMobile)) : 74;
@endphp
<section class="ho-band ho-band--yellow event-banner-editor" data-banner-editor data-banner-mode="{{ $mode }}" data-banner-rules="{{ json_encode(Acara::syaratBanner()) }}" style="--banner-fokus-desktop:{{ $fokusDesktop }}%;--banner-fokus-mobile:{{ $fokusMobile }}%;">
    <h2><i data-lucide="image" aria-hidden="true"></i>Gambar acara</h2>
    <div class="event-banner-layout">
        <div class="event-banner-settings">
            <div class="field"><label for="banner">Banner / foto <small>(opsional)</small></label><input class="input" type="file" name="banner" id="banner" accept="image/jpeg,image/png,image/webp" aria-describedby="banner-syarat banner-rekomendasi banner-error"><small id="banner-syarat">JPG, PNG, WebP &middot; maksimal 4 MB &middot; minimal 1000 &times; 600 px.</small><small id="banner-rekomendasi">Rekomendasi: 1920 &times; 1080 px (landscape) atau 1200 &times; 1500 px (portrait). Maksimal 6000 px per sisi dan 24 megapiksel.</small><p class="event-banner-error" id="banner-error" role="alert" hidden></p></div>
            <fieldset class="event-banner-modes"><legend>Tampilan gambar</legend><div>@foreach(Acara::BANNER_TAMPILAN as $key => $label)<label><input type="radio" name="banner_tampilan" value="{{ $key }}" @checked($mode === $key)><span><i data-lucide="{{ $key === 'potong' ? 'crop' : 'image' }}" aria-hidden="true"></i><strong>{{ $label }}</strong><small>{{ $key === 'potong' ? 'Foto, tepi menyesuaikan layar' : 'Poster / teks, tanpa pemotongan' }}</small></span></label>@endforeach</div></fieldset>
            <div class="event-banner-focus" data-banner-focus>
                <div class="field"><label for="banner_fokus_desktop">Fokus vertikal desktop <output for="banner_fokus_desktop">{{ $fokusDesktop }}%</output></label><input type="range" name="banner_fokus_desktop" id="banner_fokus_desktop" min="0" max="100" step="1" value="{{ $fokusDesktop }}"></div>
                <div class="field"><label for="banner_fokus_mobile">Fokus vertikal HP <output for="banner_fokus_mobile">{{ $fokusMobile }}%</output></label><input type="range" name="banner_fokus_mobile" id="banner_fokus_mobile" min="0" max="100" step="1" value="{{ $fokusMobile }}"></div>
            </div>
        </div>
        <div class="event-banner-views">
            <figure><figcaption><i data-lucide="monitor" aria-hidden="true"></i>Desktop</figcaption><div class="event-banner-frame event-banner-frame--desktop"><img src="{{ $acara->urlBanner() }}" data-banner-preview data-banner-original="{{ $acara->urlBanner() }}" alt="Pratinjau gambar acara pada desktop"></div></figure>
            <figure><figcaption><i data-lucide="smartphone" aria-hidden="true"></i>HP</figcaption><div class="event-banner-frame event-banner-frame--mobile"><img src="{{ $acara->urlBanner() }}" data-banner-preview alt="Pratinjau gambar acara pada HP"></div></figure>
            <p class="event-banner-info" data-banner-info role="status" aria-live="polite"></p>
        </div>
    </div>
</section>
