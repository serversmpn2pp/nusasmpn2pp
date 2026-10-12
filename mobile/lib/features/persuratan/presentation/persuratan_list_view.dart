import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/persuratan/data/persuratan_repository.dart';
import 'package:nusa/features/persuratan/domain/persuratan.dart';
import 'package:nusa/features/persuratan/presentation/surat_editors.dart';
import 'package:nusa/features/persuratan/presentation/widgets/surat_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class PersuratanListView extends ConsumerStatefulWidget {
  const PersuratanListView({required this.module, super.key});
  final SuratModule module;
  @override
  ConsumerState<PersuratanListView> createState() => _PersuratanListViewState();
}

class _PersuratanListViewState extends ConsumerState<PersuratanListView> {
  final _search = TextEditingController();
  final _year = TextEditingController();
  final _items = <SuratData>[];
  SuratPage? _page;
  String _status = '';
  String _kind = '';
  String _nature = '';
  bool _special = false;
  bool _review = false;
  bool _filters = false;
  bool _loading = true;
  bool _creating = false;
  Object? _error;
  int _request = 0;
  int? _account;
  Timer? _debounce;
  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _year.dispose();
    super.dispose();
  }

  Future<void> _load({bool append = false}) async {
    if (!mounted) return;
    final u = ref.read(authControllerProvider).value?.session?.pengguna;
    if (!widget.module.canOpen(u)) {
      setState(() {
        _items.clear();
        _page = null;
        _loading = false;
      });
      return;
    }
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = null;
      _account = u!.id;
      if (!append) {
        _items.clear();
        _page = null;
      }
    });
    try {
      final page = await ref.read(persuratanRepositoryProvider).page(
        widget.module.path,
        {
          'halaman': append ? (_page?.page ?? 0) + 1 : 1,
          'per_halaman': 20,
          if (_search.text.trim().isNotEmpty) 'kata_kunci': _search.text.trim(),
          if (_status.isNotEmpty) 'status': _status,
          if (_kind.isNotEmpty) 'jenis': _kind,
          if (widget.module == SuratModule.archive && _nature.isNotEmpty)
            'sifat': _nature,
          if (widget.module == SuratModule.archive &&
              _year.text.trim().isNotEmpty)
            'tahun': _year.text.trim(),
          if (widget.module == SuratModule.myLetters && _special)
            'belum_dibaca': 1,
          if (widget.module.tasks && _special) 'terlambat': 1,
          if (widget.module == SuratModule.monitoring && _review)
            'pemeriksaan_saya': 1,
        },
      );
      if (!mounted ||
          request != _request ||
          u!.id !=
              ref.read(authControllerProvider).value?.session?.pengguna.id) {
        return;
      }
      setState(() {
        _items.addAll(page.items);
        _page = page;
        _loading = false;
      });
    } catch (e) {
      if (mounted && request == _request) {
        setState(() {
          _error = e;
          _loading = false;
        });
      }
    }
  }

  void _change(VoidCallback change) {
    _debounce?.cancel();
    setState(change);
    _load();
  }

  void _searchChanged(String _) {
    _debounce?.cancel();
    ++_request;
    _debounce = Timer(const Duration(milliseconds: 450), _load);
  }

  Future<void> _create() async {
    setState(() => _creating = true);
    try {
      final user = ref.read(authControllerProvider).value?.session?.pengguna;
      if (user == null) return;
      if (await editSurat(
            context,
            ref.read(persuratanRepositoryProvider),
            user.id,
          ) &&
          mounted) {
        await _load();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(suratError(e))));
      }
    } finally {
      if (mounted) setState(() => _creating = false);
    }
  }

  void _reset() => _change(() {
    _search.clear();
    _year.clear();
    _kind = _status = _nature = '';
    _special = _review = false;
  });
  @override
  Widget build(BuildContext context) {
    ref.listen(authControllerProvider, (previous, next) {
      if (previous?.value?.session?.pengguna.id !=
          next.value?.session?.pengguna.id) {
        ++_request;
        _load();
      }
    });
    final u = ref.watch(authControllerProvider).value?.session?.pengguna;
    final allowed = widget.module.canOpen(u);
    final filtersOn =
        _status.isNotEmpty ||
        _kind.isNotEmpty ||
        _nature.isNotEmpty ||
        _year.text.isNotEmpty ||
        _special ||
        _review;
    final statuses = suratMap(suratMap(_page?.data['referensi'])['status'])
        .map((k, v) => MapEntry(k, '$v'));
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.module.title),
        actions: [
          if (allowed)
            IconButton(
              tooltip: 'Perbarui',
              onPressed: _loading ? null : _load,
              icon: const Icon(Icons.refresh),
            ),
        ],
      ),
      floatingActionButton:
          allowed &&
              widget.module == SuratModule.archive &&
              suratPermission(u, 'surat.kelola')
          ? FloatingActionButton(
              tooltip: 'Tambah surat',
              onPressed: _creating ? null : _create,
              child: const Icon(Icons.add),
            )
          : null,
      body: !allowed
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
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            key: const Key('surat-search'),
                            controller: _search,
                            decoration: const InputDecoration(
                              labelText: 'Cari surat',
                              hintText: 'Nomor, perihal, atau penerima',
                              prefixIcon: Icon(Icons.search),
                            ),
                            onChanged: _searchChanged,
                          ),
                        ),
                        IconButton(
                          tooltip: 'Filter surat',
                          onPressed: () => setState(() => _filters = !_filters),
                          icon: Icon(
                            filtersOn
                                ? Icons.filter_alt
                                : Icons.filter_alt_outlined,
                          ),
                        ),
                        if (filtersOn || _search.text.isNotEmpty)
                          IconButton(
                            tooltip: 'Reset filter',
                            onPressed: _reset,
                            icon: const Icon(Icons.filter_alt_off_outlined),
                          ),
                      ],
                    ),
                    if (_filters) ...[
                      const SizedBox(height: 12),
                      if (widget.module != SuratModule.approval)
                        _choice('status', 'Status', _status, {
                          '': 'Semua status',
                          ...statuses,
                        }, (v) => _status = v),
                      const SizedBox(height: 12),
                      if (widget.module != SuratModule.myLetters)
                        _choice(
                          'jenis',
                          'Jenis',
                          _kind,
                          widget.module.tasks
                              ? {
                                  '': 'Semua jenis',
                                  'disposisi': 'Disposisi surat masuk',
                                  'tugas': 'Pelaksanaan tugas',
                                }
                              : {
                                  '': 'Semua jenis',
                                  'masuk': 'Surat masuk',
                                  'keluar': 'Surat keluar',
                                  'tugas': 'Surat tugas',
                                },
                          (v) => _kind = v,
                        ),
                      if (widget.module == SuratModule.archive) ...[
                        const SizedBox(height: 12),
                        _choice('sifat', 'Sifat', _nature, {
                          '': 'Semua sifat',
                          'biasa': 'Biasa',
                          'penting': 'Penting',
                          if (suratPermission(u, 'surat.rahasia'))
                            'rahasia': 'Rahasia',
                        }, (v) => _nature = v),
                        const SizedBox(height: 12),
                        TextField(
                          key: const Key('surat-year'),
                          controller: _year,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(
                            labelText: 'Tahun surat',
                            hintText: 'Semua tahun',
                          ),
                          maxLength: 4,
                          onChanged: _searchChanged,
                        ),
                      ],
                      Wrap(
                        spacing: 8,
                        children: [
                          if (widget.module.tasks ||
                              widget.module == SuratModule.myLetters)
                            FilterChip(
                              label: Text(
                                widget.module.tasks
                                    ? 'Terlewat tenggat'
                                    : 'Belum dibaca',
                              ),
                              selected: _special,
                              onSelected: (v) => _change(() => _special = v),
                            ),
                          if (widget.module == SuratModule.monitoring)
                            FilterChip(
                              label: const Text('Pemeriksaan saya'),
                              selected: _review,
                              onSelected: (v) => _change(() => _review = v),
                            ),
                        ],
                      ),
                    ],
                    const SizedBox(height: 16),
                    if (_page != null &&
                        _account == u?.id &&
                        widget.module != SuratModule.approval)
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          for (final e in suratMap(
                            _page!.data['statistik'],
                          ).entries.where((e) => suratInt(e.value) > 0))
                            SuratStatus(
                              '${statuses[e.key] ?? e.key}: ${e.value}',
                              e.key,
                            ),
                        ],
                      ),
                    if (_page != null && _account == u?.id)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        child: Text(
                          '${_page!.total} ${widget.module.tasks ? 'tindak lanjut' : 'surat'}',
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                      ),
                    if (_loading)
                      const Padding(
                        padding: EdgeInsets.all(20),
                        child: Center(child: CircularProgressIndicator()),
                      ),
                    if (_error != null) SuratErrorView(_error!, retry: _load),
                    if (!_loading && _error == null && _items.isEmpty)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 48),
                        child: Column(
                          children: [
                            Icon(Icons.inbox_outlined, size: 48),
                            SizedBox(height: 12),
                            Text('Belum ada surat yang sesuai.'),
                          ],
                        ),
                      ),
                    if (_account == u?.id)
                      for (final item in _items) _tile(item),
                    if (_page?.hasNext == true && !_loading && _error == null)
                      OutlinedButton.icon(
                        onPressed: () => _load(append: true),
                        icon: const Icon(Icons.expand_more),
                        label: const Text('Muat berikutnya'),
                      ),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _choice(
    String name,
    String label,
    String value,
    Map<String, String> options,
    void Function(String) assign,
  ) => NusaDropdownField<String>(
    fieldKey: Key('filter-$name'),
    value: value,
    options: options.entries
        .map((e) => NusaDropdownOption(value: e.key, label: e.value))
        .toList(),
    decoration: InputDecoration(labelText: label),
    onChanged: (v) => _change(() => assign(v ?? '')),
  );
  Widget _tile(SuratData item) {
    final letter = widget.module.tasks ? suratMap(item['surat']) : item;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () async {
            await context.push(widget.module.detailRoute(suratInt(item['id'])));
            if (mounted) _load();
          },
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  children: [
                    SuratStatus(
                      suratText(item['status_label']),
                      suratText(item['status']),
                    ),
                    if (item['sifat'] == 'rahasia')
                      const SuratStatus('Rahasia', 'dikembalikan'),
                    if (item['terlambat'] == true)
                      const SuratStatus('Terlewat tenggat', 'dikembalikan'),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  suratText(letter['perihal']),
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 6),
                Text(suratText(letter['nomor_surat'])),
                const SizedBox(height: 12),
                if (widget.module.tasks)
                  SuratFacts({
                    'Penerima': item['nama_pegawai'],
                    'Tenggat': suratDate(item['batas_tanggal']),
                  })
                else
                  SuratFacts({
                    'Jenis': item['jenis_label'],
                    'Tanggal': suratDate(item['tanggal_surat']),
                    'Pihak': item['pihak'],
                    if (widget.module == SuratModule.myLetters)
                      'Dibaca': item['dibaca_pada'] == null
                          ? 'Belum dibaca'
                          : suratDate(item['dibaca_pada']),
                  }),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
