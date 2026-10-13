import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/sts_publication/data/sts_publication_repository.dart';
import 'package:nusa/features/sts_publication/application/published_sts_document_service.dart';
import 'package:nusa/features/sts_publication/presentation/sts_publication_widgets.dart';

class PublishedStsView extends ConsumerStatefulWidget {
  const PublishedStsView({this.id, super.key});
  final int? id;
  @override
  ConsumerState<PublishedStsView> createState() => _PublishedStsState();
}

class _PublishedStsState extends ConsumerState<PublishedStsView> {
  StsData? _page;
  Object? _error;
  bool _loading = true, _printing = false, _behaviorTab = false;
  int _serial = 0;
  int? _account;
  String _child = '', _year = '';
  bool _current(int? id) =>
      mounted &&
      id == ref.read(authControllerProvider).value?.session?.pengguna.id &&
      stsRecipient(ref.read(authControllerProvider).value?.session?.pengguna);
  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  @override
  void didUpdateWidget(covariant PublishedStsView old) {
    super.didUpdateWidget(old);
    if (old.id != widget.id) {
      _serial++;
      _page = null;
      Future.microtask(_load);
    }
  }

  Future<void> _load() async {
    if (!mounted) return;
    final user = ref.read(authControllerProvider).value?.session?.pengguna;
    if (!stsRecipient(user)) return;
    final serial = ++_serial, account = user!.id, report = widget.id;
    setState(() {
      _account = account;
      _page = null;
      _loading = true;
      _error = null;
      _child = '';
      _year = '';
      _behaviorTab = false;
    });
    try {
      final repo = ref.read(stsPublicationRepositoryProvider);
      final page = report == null
          ? await repo.list()
          : await repo.detail(report);
      if (_current(account) && serial == _serial && report == widget.id) {
        setState(() {
          _page = page;
          _loading = false;
        });
      }
    } catch (e) {
      if (_current(account) && serial == _serial) {
        setState(() {
          _error = e;
          _loading = false;
        });
      }
    }
  }

  Future<void> _print() async {
    if (_printing || !_current(_account) || widget.id == null) return;
    final account = _account, report = widget.id!;
    setState(() => _printing = true);
    try {
      // Reauthorize immediately before exporting, rather than trusting an old screen copy.
      final data = await ref
          .read(stsPublicationRepositoryProvider)
          .detail(report);
      if (!_current(account) || report != widget.id) return;
      await ref.read(publishedStsDocumentServiceProvider).printReport(data);
    } catch (e) {
      if (_current(account)) {
        setState(() {
          _page = null;
          _error = e;
          _loading = false;
        });
      }
    } finally {
      if (mounted) setState(() => _printing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).value?.session?.pengguna;
    ref.listen(authControllerProvider, (previous, next) {
      if (previous?.value?.session?.pengguna.id !=
          next.value?.session?.pengguna.id) {
        _serial++;
        setState(() {
          _page = null;
          _error = null;
          _account = null;
          _child = '';
          _year = '';
          _behaviorTab = false;
        });
        Future.microtask(_load);
      }
    });
    final allowed =
        stsRecipient(user) && (_account == null || _account == user?.id);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          user?.jenisAkun == 'Orang tua'
              ? 'Rapor STS Anak Saya'
              : 'Rapor STS Saya',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          if (widget.id != null && _page != null && allowed)
            IconButton(
              key: const Key('published-sts-print'),
              tooltip: 'Cetak / simpan PDF',
              onPressed: _printing ? null : _print,
              icon: Icon(
                _printing ? Icons.hourglass_top : Icons.print_outlined,
              ),
            ),
          IconButton(
            tooltip: 'Muat ulang',
            onPressed: _printing || !allowed ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: !allowed
          ? const Center(
              child: Text(
                'Rapor pribadi hanya tersedia untuk siswa dan orang tua.',
              ),
            )
          : _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(stsError(_error!), textAlign: TextAlign.center),
                    TextButton.icon(
                      onPressed: _load,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Coba lagi'),
                    ),
                  ],
                ),
              ),
            )
          : SafeArea(
              top: false,
              child: RefreshIndicator(
                onRefresh: _load,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(16),
                  key: PageStorageKey(
                    'published-sts-${user?.id}-${widget.id}-${_behaviorTab ? 'behavior' : 'report'}',
                  ),
                  children: widget.id == null
                      ? _list()
                      : _report(stsMap(_page?['rapor'])),
                ),
              ),
            ),
    );
  }

  List<Widget> _list() {
    final items = stsItems(_page?['items']);
    final children = {
      for (final r in items)
        '${stsMap(r['siswa'])['id']}': stsText(stsMap(r['siswa'])['nama']),
    };
    final years = items
        .map((r) => stsText(stsMap(r['kegiatan'])['tahun_pelajaran']))
        .toSet()
        .toList();
    final filtered = items.where(
      (r) =>
          (_child.isEmpty || '${stsMap(r['siswa'])['id']}' == _child) &&
          (_year.isEmpty || stsMap(r['kegiatan'])['tahun_pelajaran'] == _year),
    );
    return [
      StsPublicationHeader(
        title: _page?['mode'] == 'orang_tua'
            ? 'Rapor anak terpublikasi'
            : 'Rapor terpublikasi',
        subtitle:
            'Salinan rapor resmi yang diterbitkan sekolah untuk akun Anda.',
        metrics: {
          'Rapor': '${items.length}',
          'Tahun': '${years.length}',
          'Lampiran':
              '${items.where((r) => r['sertakan_perilaku'] == true).length}',
        },
      ),
      const SizedBox(height: 12),
      const StsPublicationNotice(
        'Hanya rapor yang sudah dipublikasikan untuk akun Anda yang tampil. Jika belum tersedia, hubungi wali kelas.',
      ),
      const SizedBox(height: 16),
      if (children.length > 1) ...[
        DropdownButtonFormField<String>(
          key: ValueKey('published-sts-child-$_child'),
          initialValue: _child,
          isExpanded: true,
          decoration: const InputDecoration(
            labelText: 'Anak',
            prefixIcon: Icon(Icons.family_restroom_rounded),
          ),
          items: [
            const DropdownMenuItem(value: '', child: Text('Semua anak')),
            for (final c in children.entries)
              DropdownMenuItem(
                value: c.key,
                child: Text(c.value, overflow: TextOverflow.ellipsis),
              ),
          ],
          onChanged: (v) => setState(() => _child = v!),
        ),
        const SizedBox(height: 12),
      ],
      if (years.length > 1) ...[
        DropdownButtonFormField<String>(
          key: ValueKey('published-sts-year-$_year'),
          initialValue: _year,
          isExpanded: true,
          decoration: const InputDecoration(
            labelText: 'Tahun pelajaran',
            prefixIcon: Icon(Icons.calendar_month_rounded),
          ),
          items: [
            const DropdownMenuItem(value: '', child: Text('Semua tahun')),
            for (final y in years) DropdownMenuItem(value: y, child: Text(y)),
          ],
          onChanged: (v) => setState(() => _year = v!),
        ),
        const SizedBox(height: 12),
      ],
      if (items.isEmpty)
        const Padding(
          padding: EdgeInsets.symmetric(vertical: 60),
          child: Text(
            'Belum ada Rapor STS yang dipublikasikan untuk akun ini.',
            textAlign: TextAlign.center,
          ),
        ),
      if (items.isNotEmpty && filtered.isEmpty)
        const StsPublicationNotice(
          'Tidak ada rapor yang sesuai dengan pilihan anak dan tahun pelajaran.',
        ),
      for (final r in filtered) ...[
        PublishedStsReportCard(
          report: r,
          onTap: () => context.push('/rapor-sts-saya/${r['id']}'),
        ),
        const SizedBox(height: 12),
      ],
    ];
  }

  List<Widget> _report(StsData r) {
    final l = stsMap(r['perilaku']);
    return [
      StsPublicationHeader(
        title: stsText(stsMap(r['siswa'])['nama']),
        subtitle:
            '${stsText(stsMap(r['kelas'])['nama'])} · ${stsText(stsMap(r['kegiatan'])['tahun_pelajaran'])}\n${stsText(stsMap(r['kegiatan'])['nama'])}',
      ),
      const SizedBox(height: 12),
      Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          const StsPublicationBadge('Terpublikasi', success: true),
          StsPublicationBadge('Versi ${_page?['versi']}'),
          if (l.isNotEmpty) const StsPublicationBadge('Dengan lampiran'),
        ],
      ),
      const SizedBox(height: 8),
      Text(
        'Terbit ${stsDate(_page?['dipublikasikan_pada'])} · Tanggal rapor ${stsDate(stsMap(r['periode'])['tanggal_rapor'])}',
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 12),
      ),
      if (l.isNotEmpty) ...[
        const SizedBox(height: 16),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            ChoiceChip(
              key: const Key('published-sts-report-tab'),
              showCheckmark: false,
              label: const Text('Rapor'),
              avatar: const Icon(Icons.description_outlined, size: 18),
              selected: !_behaviorTab,
              onSelected: _printing
                  ? null
                  : (_) => setState(() => _behaviorTab = false),
            ),
            ChoiceChip(
              key: const Key('published-sts-behavior-tab'),
              showCheckmark: false,
              label: const Text('Lampiran Perilaku'),
              avatar: const Icon(Icons.fact_check_outlined, size: 18),
              selected: _behaviorTab,
              onSelected: _printing
                  ? null
                  : (_) => setState(() => _behaviorTab = true),
            ),
          ],
        ),
      ],
      const SizedBox(height: 20),
      ...(_behaviorTab && l.isNotEmpty
          ? _behavior(l)
          : _academic(r, hasBehavior: l.isNotEmpty)),
      const SizedBox(height: 28),
    ];
  }

  List<Widget> _academic(StsData r, {required bool hasBehavior}) => [
    _heading('Nilai STS'),
    for (final n in stsItems(r['nilai'])) ...[
      StsPublicationSurface(
        child: LayoutBuilder(
          builder: (_, constraints) {
            final subject = Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  stsText(n['mapel']),
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 4),
                Text(
                  stsText(n['keterangan']),
                  style: const TextStyle(
                    fontSize: 12,
                    color: NusaColors.textSecondary,
                  ),
                ),
              ],
            );
            final score = StsPublicationBadge(
              _score(n['nilai']),
              success: n['nilai'] != null,
            );
            return MediaQuery.textScalerOf(context).scale(14) > 22
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [subject, const SizedBox(height: 10), score],
                  )
                : Row(
                    children: [
                      Expanded(child: subject),
                      const SizedBox(width: 12),
                      score,
                    ],
                  );
          },
        ),
      ),
      const SizedBox(height: 10),
    ],
    StsPublicationFacts({
      'Jumlah': _score(r['jumlah']),
      'Rata-rata': _score(r['rata']),
    }),
    if (r['nilai_tuntas'] != true)
      const Padding(
        padding: EdgeInsets.only(top: 12),
        child: StsPublicationNotice(
          'Jumlah dan rata-rata belum dihitung karena ada nilai belum tersedia.',
          warning: true,
        ),
      ),
    const SizedBox(height: 20),
    _heading('Kehadiran dan keterlambatan'),
    StsPublicationFacts({
      'Sakit': '${stsInt(stsMap(r['kehadiran'])['sakit'])} hari',
      'Izin': '${stsInt(stsMap(r['kehadiran'])['izin'])} hari',
      'Alfa': '${stsInt(stsMap(r['kehadiran'])['alfa'])} hari',
      'Terlambat': '${stsInt(stsMap(r['keterlambatan'])['jumlah'])} kali',
      'Total keterlambatan':
          '${stsInt(stsMap(r['keterlambatan'])['total_menit'])} menit',
    }),
    const SizedBox(height: 10),
    Text(
      'Periode ${stsDate(stsMap(r['periode'])['awal'])} - ${stsDate(stsMap(r['periode'])['akhir'])}',
      style: const TextStyle(fontSize: 12),
    ),
    const SizedBox(height: 20),
    _heading('Wali kelas'),
    Text(stsText(stsMap(r['wali_kelas'])['nama_lengkap'])),
    if (!hasBehavior) ...[
      const SizedBox(height: 20),
      const StsPublicationNotice(
        'Lampiran perilaku tidak disertakan dalam publikasi rapor ini. Ini bukan keterangan bahwa tidak ada kejadian.',
      ),
    ],
  ];

  List<Widget> _behavior(StsData l) => [
    _heading('Lampiran perilaku dan pembinaan'),
    StsPublicationNotice(
      '${stsText(l['label_isi'])}. Ringkasan diperiksa BK dan diterbitkan bersama rapor; bukan catatan konseling privat.',
    ),
    const SizedBox(height: 12),
    StsPublicationFacts({
      'Jumlah kejadian': stsInt(stsMap(l['ringkasan'])['jumlah_kejadian']),
      'Poin masuk': stsInt(stsMap(l['ringkasan'])['poin_masuk']),
      'Pengurangan': stsInt(stsMap(l['ringkasan'])['poin_dikurangi']),
      'Saldo': stsInt(stsMap(l['ringkasan'])['saldo']),
    }),
    const SizedBox(height: 16),
    if (stsItems(l['baris']).isEmpty)
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 16),
        child: Text(stsText(l['pesan_kosong'])),
      ),
    for (final b in stsItems(l['baris'])) ...[
      StsPublicationSurface(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                StsPublicationBadge(stsDate(b['tanggal'])),
                StsPublicationBadge('Poin: ${stsInt(b['poin'])}'),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              stsText(b['kejadian']),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            const Text(
              'Tindak lanjut',
              style: TextStyle(fontSize: 11, color: NusaColors.textSecondary),
            ),
            Text(stsText(b['tindakan'])),
          ],
        ),
      ),
      const SizedBox(height: 12),
    ],
    if (l['catatan'] != null) ...[
      _heading('Catatan pembinaan'),
      StsPublicationSurface(child: Text(stsText(l['catatan']))),
    ],
    const SizedBox(height: 12),
    Text('Guru BK: ${stsText(stsMap(l['guru_bk'])['nama_lengkap'])}'),
    Text(
      'Wakil Kesiswaan: ${stsText(stsMap(l['wakil_kesiswaan'])['nama_lengkap'])}',
    ),
  ];

  Widget _heading(String s) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text(
      s,
      style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
    ),
  );
  String _score(Object? v) =>
      v is num ? v.toStringAsFixed(2).replaceAll('.', ',') : '-';
}
