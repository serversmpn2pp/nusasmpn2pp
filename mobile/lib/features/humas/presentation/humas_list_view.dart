import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_editors.dart';
import 'package:nusa/features/humas/presentation/humas_scan_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class HumasListView extends ConsumerStatefulWidget {
  const HumasListView({required this.module, super.key});
  final HumasModule module;
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
  int _request = 0;
  @override
  void initState() {
    super.initState();
    _filter = switch (widget.module) {
      HumasModule.invitations => 'mendatang',
      HumasModule.myFeedback => 'aktif',
      HumasModule.complaints => 'aktif',
      _ => '',
    };
    Future.microtask(_load);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Map<String, String> get _filters => switch (widget.module) {
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
    });
    try {
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
    final changed = await createHumasRecord(
      context,
      ref.read(humasRepositoryProvider),
      widget.module,
    );
    if (changed && mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).value?.session?.pengguna;
    final canOpen = widget.module.canOpen(user);
    final canCreate =
        widget.module == HumasModule.myComplaints ||
        (widget.module.canManage(user) &&
            [
              HumasModule.agenda,
              HumasModule.documents,
              HumasModule.complaints,
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
      floatingActionButton: canOpen && canCreate
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
    };
    return HumasCard(
      title: humasText(record['judul']),
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
}
