import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/sts_publication/data/sts_publication_repository.dart';
import 'package:nusa/features/sts_publication/application/published_sts_document_service.dart';

class PublishedStsView extends ConsumerStatefulWidget {
  const PublishedStsView({this.id, super.key});
  final int? id;
  @override
  ConsumerState<PublishedStsView> createState() => _PublishedStsState();
}

class _PublishedStsState extends ConsumerState<PublishedStsView> {
  StsData? _page;
  Object? _error;
  bool _loading = true, _printing = false;
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
        });
        Future.microtask(_load);
      }
    });
    final allowed =
        stsRecipient(user) && (_account == null || _account == user?.id);
    return Scaffold(
      appBar: AppBar(
        toolbarHeight: 68,
        title: Text(
          user?.jenisAkun == 'Orang tua'
              ? 'Rapor STS Anak Saya'
              : 'Rapor STS Saya',
          maxLines: 2,
          style: const TextStyle(fontSize: 18),
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
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: widget.id == null
                    ? _list()
                    : _report(stsMap(_page?['rapor'])),
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
      if (children.length > 1) ...[
        DropdownButtonFormField<String>(
          initialValue: _child,
          isExpanded: true,
          decoration: const InputDecoration(labelText: 'Anak'),
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
          initialValue: _year,
          isExpanded: true,
          decoration: const InputDecoration(labelText: 'Tahun pelajaran'),
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
      for (final r in filtered)
        Card(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
            side: const BorderSide(color: NusaColors.outline),
          ),
          child: ListTile(
            contentPadding: const EdgeInsets.all(16),
            title: Text(
              stsText(stsMap(r['siswa'])['nama']),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            subtitle: Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                '${stsText(stsMap(r['kelas'])['nama'])} - ${stsText(stsMap(r['kegiatan'])['tahun_pelajaran'])}\n${stsText(stsMap(r['kegiatan'])['nama'])}\nVersi ${r['versi']} - ${stsDate(r['dipublikasikan_pada'])}${r['sertakan_perilaku'] == true ? '\nDengan lampiran perilaku' : ''}',
              ),
            ),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.push('/rapor-sts-saya/${r['id']}'),
          ),
        ),
    ];
  }

  List<Widget> _report(StsData r) {
    final l = stsMap(r['perilaku']);
    return [
      Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Image.asset(
            'assets/images/logo-padang-panjang.png',
            height: 62,
            width: 55,
            fit: BoxFit.contain,
          ),
          const SizedBox(width: 20),
          Image.asset(
            'assets/images/logo-smpn2pp.png',
            height: 62,
            width: 62,
            fit: BoxFit.contain,
          ),
        ],
      ),
      const SizedBox(height: 16),
      const Text(
        'SMP NEGERI 2 PADANG PANJANG',
        textAlign: TextAlign.center,
        style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
      ),
      const Text(
        'Laporan Hasil Capaian Pembelajaran\nSumatif Tengah Semester',
        textAlign: TextAlign.center,
      ),
      const SizedBox(height: 20),
      Text(
        stsText(stsMap(r['siswa'])['nama']),
        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
      ),
      Text(
        '${stsText(stsMap(r['kelas'])['nama'])} - ${stsText(stsMap(r['kegiatan'])['tahun_pelajaran'])} - ${stsText(stsMap(r['kegiatan'])['semester'])}',
      ),
      Text(
        'Versi ${_page?['versi']} - Terbit ${stsDate(_page?['dipublikasikan_pada'])}',
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 12),
      ),
      const SizedBox(height: 20),
      _heading('Nilai STS'),
      for (final n in stsItems(r['nilai'])) ...[
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(stsText(n['mapel'])),
          subtitle: Text(stsText(n['keterangan'])),
          trailing: Text(
            _score(n['nilai']),
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
          ),
        ),
        const Divider(height: 1),
      ],
      _facts({'Jumlah': _score(r['jumlah']), 'Rata-rata': _score(r['rata'])}),
      if (r['nilai_tuntas'] != true)
        const Text(
          'Jumlah dan rata-rata belum dihitung karena ada nilai belum tersedia.',
          style: TextStyle(fontSize: 12, color: NusaColors.textSecondary),
        ),
      const SizedBox(height: 20),
      _heading('Kehadiran dan keterlambatan'),
      _facts({
        'Sakit': '${stsInt(stsMap(r['kehadiran'])['sakit'])} hari',
        'Izin': '${stsInt(stsMap(r['kehadiran'])['izin'])} hari',
        'Alfa': '${stsInt(stsMap(r['kehadiran'])['alfa'])} hari',
        'Terlambat': '${stsInt(stsMap(r['keterlambatan'])['jumlah'])} kali',
        'Total keterlambatan':
            '${stsInt(stsMap(r['keterlambatan'])['total_menit'])} menit',
      }),
      Text(
        'Periode ${stsDate(stsMap(r['periode'])['awal'])} - ${stsDate(stsMap(r['periode'])['akhir'])}',
        style: const TextStyle(fontSize: 12),
      ),
      const SizedBox(height: 20),
      _heading('Wali kelas'),
      Text(stsText(stsMap(r['wali_kelas'])['nama_lengkap'])),
      Text('Tanggal rapor: ${stsDate(stsMap(r['periode'])['tanggal_rapor'])}'),
      if (l.isNotEmpty) ...[
        const SizedBox(height: 28),
        _heading('Lampiran perilaku dan pembinaan'),
        Text(stsText(l['label_isi']), style: const TextStyle(fontSize: 12)),
        if (stsItems(l['baris']).isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Text(stsText(l['pesan_kosong'])),
          ),
        for (final b in stsItems(l['baris'])) ...[
          const Divider(),
          Text(
            stsDate(b['tanggal']),
            style: const TextStyle(
              fontSize: 12,
              color: NusaColors.textSecondary,
            ),
          ),
          Text(
            stsText(b['kejadian']),
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          Text(stsText(b['tindakan'])),
          Text('Poin: ${stsInt(b['poin'])}'),
        ],
        _facts({
          'Jumlah kejadian': stsInt(stsMap(l['ringkasan'])['jumlah_kejadian']),
          'Poin masuk': stsInt(stsMap(l['ringkasan'])['poin_masuk']),
          'Pengurangan': stsInt(stsMap(l['ringkasan'])['poin_dikurangi']),
          'Saldo': stsInt(stsMap(l['ringkasan'])['saldo']),
        }),
        if (l['catatan'] != null) Text(stsText(l['catatan'])),
        const SizedBox(height: 12),
        Text('Guru BK: ${stsText(stsMap(l['guru_bk'])['nama_lengkap'])}'),
        Text(
          'Wakil Kesiswaan: ${stsText(stsMap(l['wakil_kesiswaan'])['nama_lengkap'])}',
        ),
      ],
      const SizedBox(height: 28),
    ];
  }

  Widget _heading(String s) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text(
      s,
      style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
    ),
  );
  String _score(Object? v) =>
      v is num ? v.toStringAsFixed(2).replaceAll('.', ',') : '-';
  Widget _facts(Map<String, Object> values) => LayoutBuilder(
    builder: (_, c) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 16),
      child: Wrap(
        spacing: 16,
        runSpacing: 16,
        children: [
          for (final e in values.entries)
            SizedBox(
              width: (c.maxWidth - 16) / 2,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    e.key,
                    style: const TextStyle(
                      fontSize: 12,
                      color: NusaColors.textSecondary,
                    ),
                  ),
                  Text(
                    '${e.value}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ),
        ],
      ),
    ),
  );
}
