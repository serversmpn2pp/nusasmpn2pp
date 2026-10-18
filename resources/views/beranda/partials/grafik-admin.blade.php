<section class="secondary-chart secondary-{{ $kode }}" data-stat-chart="{{ $kode }}" aria-labelledby="{{ $kode }}-title">
    <div class="secondary-heading"><div><p class="section-kicker">{{ $kategori }}</p><h2 id="{{ $kode }}-title">{{ $judul }}</h2></div><span class="secondary-symbol"><i data-lucide="{{ $ikon }}" aria-hidden="true"></i></span></div>
    <div class="secondary-body">
        <div class="chart-toolbar">
            <div class="period-switch" role="group" aria-label="Periode {{ mb_strtolower($judul) }}">
                @foreach (['day' => 'Hari ini', 'week' => 'Mingguan', 'month' => 'Bulanan'] as $key => $label)
                    <button type="button" data-stat-period="{{ $key }}" aria-pressed="{{ $key === 'day' ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="chart-tools"><div class="chart-types" role="group" aria-label="Jenis grafik {{ mb_strtolower($judul) }}">
                @foreach (['bar' => ['batang', 'chart-column-increasing'], 'line' => ['garis', 'chart-no-axes-combined']] as $key => [$label, $ikonGrafik])
                    <button class="icon-button" type="button" data-stat-type="{{ $key }}" aria-label="Grafik {{ $label }}" aria-pressed="{{ $key === $data['periods']['day']['type'] ? 'true' : 'false' }}" data-admin-tooltip="Grafik {{ $label }}"><i data-lucide="{{ $ikonGrafik }}" aria-hidden="true"></i></button>
                @endforeach
            </div><button class="icon-button secondary-table" type="button" data-stat-table aria-label="Lihat data {{ mb_strtolower($judul) }}" data-admin-tooltip="Lihat data grafik"><i data-lucide="table-2" aria-hidden="true"></i></button></div>
        </div>
        <div class="chart-caption"><p data-stat-caption>{{ $data['periods']['day']['subtitle'] }}</p><span data-stat-date>{{ $data['periods']['day']['date'] }}</span></div>
        <div class="chart-container"><canvas id="{{ $kode }}-chart" role="img" aria-label="{{ $judul }}" aria-describedby="{{ $kode }}-description"></canvas><div class="chart-empty" data-stat-empty @if (! $data['periods']['day']['empty']) hidden @endif><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i><p>{{ $data['periods']['day']['empty'] }}</p></div></div>
        <p class="sr-only" id="{{ $kode }}-description" data-stat-description></p>
        <div class="chart-legend" role="group" aria-label="Seri {{ mb_strtolower($judul) }}">
            @foreach ($data['series'] as $seri)
                <button class="legend-button" type="button" data-stat-series="{{ $loop->index }}" aria-pressed="true"><span class="legend-line" style="background-color: {{ $seri['color'] }}"></span>{{ $seri['label'] }}</button>
            @endforeach
        </div>
        <dl class="secondary-metrics">
            @foreach ($data['metrics'] as $key => $label)
                <div><dt>{{ $label }}</dt><dd data-stat-count="{{ $key }}">{{ $jumlah($data['periods']['day']['summary'][$key]) }}</dd></div>
            @endforeach
        </dl>
        <p class="secondary-note" data-stat-note>{{ $data['periods']['day']['note'] }}</p>
        <p class="untimed-note" data-stat-untimed hidden></p>
        <a class="text-button" href="{{ $url }}">{{ $tautan }}<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
    </div>
    <script type="application/json" data-stat-data>{!! json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
</section>
