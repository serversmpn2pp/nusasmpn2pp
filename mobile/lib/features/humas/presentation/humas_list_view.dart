import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_editors.dart';
import 'package:nusa/features/humas/presentation/humas_faq_kinerja_editors.dart';
import 'package:nusa/features/humas/presentation/humas_contact_publication_editors.dart';
import 'package:nusa/features/humas/presentation/humas_scan_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/features/humas/presentation/humas_work_views.dart';
import 'package:nusa/features/humas/presentation/humas_work_forms.dart';

class HumasListView extends ConsumerStatefulWidget {
  const HumasListView({
    required this.module,
    this.initialUrusan,
    this.initialJenis,
    super.key,
  });
  final HumasModule module;
  final String? initialUrusan;
  final String? initialJenis;
  @override
  ConsumerState<HumasListView> createState() => _HumasListViewState();
}

class _HumasListViewState extends ConsumerState<HumasListView> {
  final _search = TextEditingController();
  Timer? _debounce;
  final _items = <HumasData>[];
  HumasPage? _page;
  bool _loading = true;
  Object? _error;
  String _filter = '';
  HumasData _references = {};
  String _urusan = '',
      _documentType = '',
      _year = '',
      _semester = '',
      _kind = '',
      _channel = '';
  int _request = 0;
  @override
  void initState() {
    super.initState();
    _urusan = widget.initialUrusan ?? '';
    _filter = switch (widget.module) {
      HumasModule.invitations => 'mendatang',
      HumasModule.myFeedback => 'aktif',
      HumasModule.complaints => 'aktif',
      _ => '',
    };
    if (!humasWorkKinds.contains(widget.module.path)) Future.microtask(_load);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Map<String, String> get _filters => switch (widget.module) {
    HumasModule.programs ||
    HumasModule.committee ||
    HumasModule.partners ||
    HumasModule.accreditation => {'': 'Semua status'},
    HumasModule.agenda => {
      '': 'Semua status',
      'terjadwal': 'Terjadwal',
      'selesai': 'Selesai',
      'dibatalkan': 'Dibatalkan',
    },
    HumasModule.documents => {
      '': 'Semua dokumen',
      'aktif': 'Aktif',
      'arsip': 'Arsip',
    },
    HumasModule.complaints => {
      'semua': 'Semua tiket',
      'aktif': 'Tiket aktif',
      'baru': 'Baru',
      'ditugaskan': 'Ditugaskan',
      'diproses': 'Diproses',
      'menunggu': 'Menunggu informasi',
      'verifikasi': 'Verifikasi',
      'selesai': 'Selesai',
      'ditutup': 'Ditutup',
    },
    HumasModule.myComplaints => {
      '': 'Semua laporan',
      'aktif': 'Aktif',
      'selesai': 'Selesai',
    },
    HumasModule.feedback => {
      '': 'Semua status',
      'draf': 'Draf',
      'aktif': 'Aktif',
      'ditutup': 'Ditutup',
      'arsip': 'Arsip',
    },
    HumasModule.myFeedback => {
      'aktif': 'Belum diisi',
      'riwayat': 'Riwayat',
      'semua': 'Semua formulir',
    },
    HumasModule.invitations => {'mendatang': 'Mendatang', 'riwayat': 'Riwayat'},
    HumasModule.faq =>
      widget.module.canManage(
            ref.read(authControllerProvider).value?.session?.pengguna,
          )
          ? {
              '': 'Semua status',
              'draf': 'Draf',
              'terbit': 'Diterbitkan',
              'arsip': 'Arsip',
            }
          : {'': 'Pertanyaan terbit'},
    HumasModule.performance => {
      '': 'Semua status',
      'draf': 'Draf',
      'final': 'Final',
    },
    HumasModule.contacts => {
      '': 'Semua status',
      ...humasOptions(_references['status']),
    },
    HumasModule.publications => {
      '': 'Semua status',
      ...humasOptions(_references['status']),
    },
  };
  Future<void> _load({bool append = false}) async {
    if (!mounted) return;
    final request = ++_request;
    final user = ref.read(authControllerProvider).value?.session?.pengguna;
    if (!widget.module.canOpen(user)) {
      if (mounted) setState(() => _loading = false);
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
      if (!append) {
        _items.clear();
        _page = null;
      }
    });
    try {
      if ([
        HumasModule.faq,
        HumasModule.performance,
        HumasModule.documents,
        HumasModule.contacts,
        HumasModule.publications,
      ].contains(widget.module)) {
        final references = await ref
            .read(humasRepositoryProvider)
            .get('${widget.module.path}/referensi');
        if (!mounted || request != _request) return;
        _references = references;
      }
      final page = await ref.read(humasRepositoryProvider).page(
        widget.module.path,
        {
          'halaman': append ? (_page?.page ?? 0) + 1 : 1,
          'per_halaman': 20,
          if (_search.text.trim().isNotEmpty &&
              ![
                HumasModule.invitations,
                HumasModule.myFeedback,
              ].contains(widget.module))
            'kata_kunci': _search.text.trim(),
          if (_filter.isNotEmpty)
            (widget.module == HumasModule.invitations ||
                        widget.module == HumasModule.myFeedback
                    ? 'tab'
                    : 'status'):
                _filter,
          if (_urusan.isNotEmpty) 'urusan': _urusan,
          if (_documentType.isNotEmpty) 'jenis_dokumen': _documentType,
          if (_year.isNotEmpty) 'tahun_pelajaran_id': _year,
          if (_semester.isNotEmpty) 'semester': _semester,
          if (_kind.isNotEmpty) 'jenis': _kind,
          if (_channel.isNotEmpty) 'kanal': _channel,
          if (widget.module == HumasModule.agenda &&
              widget.initialJenis != null)
            'jenis': widget.initialJenis,
        },
      );
      if (!mounted || request != _request) return;
      setState(() {
        if (!append) _items.clear();
        _items.addAll(page.items);
        _page = page;
        _loading = false;
      });
    } catch (error) {
      if (mounted && request == _request) {
        setState(() {
          _error = error;
          _loading = false;
          if (error is UnauthorizedException ||
              error is NetworkException &&
                  [403, 404].contains(error.statusCode)) {
            _items.clear();
            _page = null;
          }
        });
      }
    }
  }

  void _onSearch(String _) {
    _debounce?.cancel();
    ++_request;
    _debounce = Timer(const Duration(milliseconds: 550), _load);
  }

  Future<void> _create() async {
    if ([
      HumasModule.contacts,
      HumasModule.publications,
    ].contains(widget.module)) {
      await humasRun(context, () async {
        final changed = await editContactPublication(
          context,
          ref.read(humasRepositoryProvider),
          widget.module,
        );
        if (changed && mounted) await _load();
      });
      return;
    }
    if ([HumasModule.faq, HumasModule.performance].contains(widget.module)) {
      await humasRun(context, () async {
        final changed = await editFaqKinerja(
          context,
          ref.read(humasRepositoryProvider),
          widget.module,
          userName:
              ref.read(authControllerProvider).value?.session?.pengguna.nama ??
              '',
        );
        if (changed && mounted) await _load();
      });
      return;
    }
    final changed = await createHumasRecord(
      context,
      ref.read(humasRepositoryProvider),
      widget.module,
    );
    if (changed && mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    if (humasWorkKinds.contains(widget.module.path)) {
      return HumasWorkList(kind: widget.module.path, base: widget.module.path);
    }
    final user = ref.watch(authControllerProvider).value?.session?.pengguna;
    final canOpen = widget.module.canOpen(user);
    final canCreate =
        widget.module == HumasModule.myComplaints ||
        (widget.module.canCreate(user) &&
            [
              HumasModule.agenda,
              HumasModule.documents,
              HumasModule.complaints,
              HumasModule.faq,
              HumasModule.performance,
              HumasModule.contacts,
              HumasModule.publications,
            ].contains(widget.module));
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.module.title),
        actions: [
          if (canOpen && widget.module == HumasModule.invitations)
            IconButton(
              tooltip: 'Scan QR pertemuan',
              icon: const Icon(Icons.qr_code_scanner_rounded),
              onPressed: () async {
                await Navigator.push(
                  context,
                  MaterialPageRoute<void>(
                    builder: (_) => const HumasScanView(),
                  ),
                );
                if (mounted) _load();
              },
            ),
          if (canOpen)
            IconButton(
              onPressed: _load,
              icon: const Icon(Icons.refresh_rounded),
            ),
        ],
      ),
      floatingActionButton:
          canOpen &&
              canCreate &&
              (![
                    HumasModule.contacts,
                    HumasModule.publications,
                  ].contains(widget.module) ||
                  (!_loading &&
                      _error == null &&
                      (widget.module == HumasModule.contacts
                          ? humasMap(
                                  _references['hak_akses'],
                                )['dapat_kelola'] ==
                                true
                          : _references['dapat_buat'] == true))) &&
              (_references['dapat_kelola'] != false) &&
              (humasMap(_references['hak_akses'])['dapat_kelola'] != false) &&
              (_references['dapat_buat'] != false) &&
              (widget.module != HumasModule.performance ||
                  _references['dapat_himpun'] != false)
          ? FloatingActionButton(
              onPressed: _create,
              tooltip: 'Tambah',
              child: const Icon(Icons.add),
            )
          : null,
      body: !canOpen
          ? const Center(
              child: Text('Menu ini tidak tersedia untuk akun Anda.'),
            )
          : SafeArea(
              child: RefreshIndicator(
                onRefresh: _load,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
                  children: [
                    if (![
                      HumasModule.invitations,
                      HumasModule.myFeedback,
                      HumasModule.performance,
                    ].contains(widget.module)) ...[
                      TextField(
                        controller: _search,
                        onChanged: _onSearch,
                        maxLength: 120,
                        decoration: const InputDecoration(
                          counterText: '',
                          hintText: 'Cari judul atau informasi…',
                          prefixIcon: Icon(Icons.search_rounded),
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                    NusaDropdownField<String>(
                      fieldKey: Key('humas-filter-${widget.module.path}'),
                      value: _filter,
                      options: [
                        for (final entry in _filters.entries)
                          NusaDropdownOption(
                            value: entry.key,
                            label: entry.value,
                          ),
                      ],
                      decoration: const InputDecoration(labelText: 'Tampilkan'),
                      onChanged: (v) {
                        _filter = v!;
                        _debounce?.cancel();
                        _load();
                      },
                    ),
                    const SizedBox(height: 16),
                    if ([
                      HumasModule.documents,
                      HumasModule.faq,
                      HumasModule.contacts,
                    ].contains(widget.module))
                      _referenceFilter(
                        'Urusan',
                        'urusan',
                        _urusan,
                        humasOptions(_references['urusan']),
                        (v) => _urusan = v,
                      ),
                    if (widget.module == HumasModule.publications) ...[
                      _referenceFilter(
                        'Jenis konten',
                        'jenis',
                        _kind,
                        humasOptions(_references['jenis']),
                        (v) => _kind = v,
                      ),
                      _referenceFilter(
                        'Media tujuan',
                        'kanal',
                        _channel,
                        humasOptions(_references['kanal']),
                        (v) => _channel = v,
                      ),
                      if (_references['cakupan'] == 'milik_sendiri')
                        const Padding(
                          padding: EdgeInsets.only(bottom: 12),
                          child: HumasBadge('Kontribusi publikasi saya'),
                        ),
                    ],
                    if (widget.module == HumasModule.documents)
                      _referenceFilter(
                        'Jenis dokumen',
                        'jenis_dokumen',
                        _documentType,
                        humasOptions(_references['jenis_dokumen']),
                        (v) => _documentType = v,
                      ),
                    if (widget.module == HumasModule.performance) ...[
                      _referenceFilter(
                        'Tahun pelajaran',
                        'tahun_pelajaran_id',
                        _year,
                        {
                          for (final t in humasItems(
                            _references['tahun_pelajaran'],
                          ))
                            '${t['id']}': humasText(t['nama']),
                        },
                        (v) => _year = v,
                      ),
                      _referenceFilter(
                        'Semester',
                        'semester',
                        _semester,
                        humasOptions(_references['semester']),
                        (v) => _semester = v,
                      ),
                    ],
                    if (_page != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text('${_page!.total} data'),
                      ),
                    if (_error != null) HumasErrorView(_error!, onRetry: _load),
                    for (final item in _items) _item(item),
                    if (_items.isEmpty && !_loading && _error == null)
                      const HumasCard(
                        title: 'Belum ada data',
                        subtitle: 'Data sesuai filter akan tampil di sini.',
                      ),
                    if (_loading)
                      const Center(
                        child: Padding(
                          padding: EdgeInsets.all(16),
                          child: CircularProgressIndicator(),
                        ),
                      ),
                    if (_page?.hasNext == true && !_loading)
                      OutlinedButton(
                        onPressed: () => _load(append: true),
                        child: const Text('Muat berikutnya'),
                      ),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _item(HumasData item) {
    final record = widget.module == HumasModule.invitations
        ? humasMap(item['agenda'])
        : item;
    final subtitle = switch (widget.module) {
      HumasModule.programs ||
      HumasModule.committee ||
      HumasModule.partners ||
      HumasModule.accreditation => '',
      HumasModule.agenda || HumasModule.invitations =>
        '${humasDate(record['waktu_mulai'])}\n${humasText(record['tempat'])}',
      HumasModule.documents =>
        '${humasText(record['kategori_label'])}\nBerlaku sampai: ${humasDate(record['berlaku_sampai'])}',
      HumasModule.complaints || HumasModule.myComplaints =>
        '${humasText(record['nomor'])} • ${humasDate(record['tanggal_diterima'])}',
      HumasModule.feedback =>
        '${humasInt(record['respons'])}/${humasInt(record['sasaran'])} respons',
      HumasModule.myFeedback =>
        record['dikirim_pada'] != null
            ? 'Diisi ${humasDate(record['dikirim_pada'])}'
            : 'Batas pengisian: ${humasDate(record['selesai_pada'])}',
      HumasModule.faq => humasText(record['urusan_label']),
      HumasModule.performance =>
        '${humasText(record['tahun_pelajaran'])} / ${humasText(record['semester_label'])}\nData s.d. ${humasDate(record['data_sampai'])}',
      HumasModule.contacts =>
        '${humasText(record['instansi'])}\n${humasText(record['jabatan'])} / ${humasText(record['urusan_label'])}',
      HumasModule.publications =>
        '${humasText(record['jenis_label'])} / ${humasText(record['kanal_label'])}\n${humasText(record['pembuat'])}',
    };
    return HumasCard(
      title: humasText(
        record[widget.module == HumasModule.faq
            ? 'pertanyaan'
            : widget.module == HumasModule.contacts
            ? 'nama'
            : 'judul'],
      ),
      subtitle: subtitle,
      trailing: const Icon(Icons.chevron_right_rounded),
      children: [
        const SizedBox(height: 12),
        HumasBadge(humasText(record['status_label'])),
      ],
      onTap: () async {
        await context.push('${widget.module.route}/${record['id']}');
        if (mounted) _load();
      },
    );
  }

  Widget _referenceFilter(
    String label,
    String key,
    String value,
    Map<String, String> options,
    void Function(String) change,
  ) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: NusaDropdownField<String>(
      fieldKey: Key('humas-filter-$key'),
      value: options.containsKey(value) ? value : '',
      options: [
        NusaDropdownOption(value: '', label: 'Semua ${label.toLowerCase()}'),
        for (final e in options.entries)
          NusaDropdownOption(value: e.key, label: e.value),
      ],
      decoration: InputDecoration(labelText: label),
      onChanged: (v) {
        change(v ?? '');
        _debounce?.cancel();
        _load();
      },
    ),
  );
}
