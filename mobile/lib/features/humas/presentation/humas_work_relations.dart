import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_editors.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';

class HumasWorkRelations extends StatefulWidget {
  const HumasWorkRelations({
    required this.path,
    required this.base,
    required this.recordId,
    required this.repository,
    this.supporting = false,
    super.key,
  });
  final String path, base;
  final int recordId;
  final HumasRepository repository;
  final bool supporting;
  @override
  State<HumasWorkRelations> createState() => _RelationsState();
}

class _RelationsState extends State<HumasWorkRelations> {
  final _items = <HumasData>[];
  HumasData _metadata = {};
  HumasPage? _page;
  Object? _error;
  bool _loading = true, _saving = false;
  int _request = 0;
  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  Future<void> _load({bool append = false}) async {
    if (!mounted) return;
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = null;
      if (!append) {
        _items.clear();
        _page = null;
      }
    });
    try {
      final d = await widget.repository.get(widget.path, {
        'halaman': append ? (_page?.page ?? 0) + 1 : 1,
        'per_halaman': 20,
      });
      if (mounted && request == _request) {
        setState(() {
          _metadata = d;
          _page = HumasPage.fromJson(d);
          _items.addAll(_page!.items);
        });
      }
    } catch (e) {
      if (mounted && request == _request) {
        setState(() {
          _error = e;
          _items.clear();
          _metadata = {};
          _page = null;
        });
      }
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  int get version => widget.supporting
      ? humasInt(humasMap(_metadata['mou'])['versi'])
      : humasInt(_metadata['versi']);
  Future<void> _change([HumasData? item]) async {
    if (_saving) return;
    final originalVersion = version;
    setState(() => _saving = true);
    await humasRun(context, () async {
      final ref = item != null
          ? <String, dynamic>{}
          : await widget.repository.get(
              widget.supporting
                  ? '${widget.path}/pilihan'
                  : '${widget.base}/referensi',
              widget.supporting ? {} : {'record_id': widget.recordId},
            );
      if (!mounted) return;
      final changed = await openHumasForm(
        context,
        title: item != null
            ? 'Lepas hubungan'
            : widget.supporting
            ? 'Tambah Dokumen Pendukung'
            : 'Hubungkan Agenda',
        fields: [
          if (item == null)
            HumasField(
              widget.supporting ? 'id_versi' : 'agenda_humas_id',
              widget.supporting ? 'Dokumen pendukung' : 'Agenda',
              kind: HumasFieldKind.choice,
              options: {
                for (final d in humasItems(
                  ref[widget.supporting ? 'items' : 'agenda'],
                ))
                  '${d[widget.supporting ? 'id_versi' : 'id']}': humasText(
                    d['judul'],
                  ),
              },
            ),
          if (item == null && widget.supporting)
            HumasField(
              'jenis',
              'Jenis dokumen',
              kind: HumasFieldKind.choice,
              options: humasOptions(_metadata['jenis']),
            ),
          HumasField(
            widget.supporting ? 'catatan' : 'catatan_perubahan',
            'Catatan / alasan',
            kind: HumasFieldKind.multiline,
            maxLength: 2000,
          ),
        ],
        onSave: (v) async {
          await widget.repository.send(
            item == null ? widget.path : '${widget.path}/${item['id']}',
            {...v, 'versi': originalVersion},
            method: item == null ? 'POST' : 'DELETE',
          );
        },
      );
      if (changed) await _load();
    });
    if (mounted) setState(() => _saving = false);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(widget.supporting ? 'Dokumen Pendukung' : 'Kegiatan Terkait'),
      actions: [
        IconButton(
          tooltip: 'Muat ulang',
          onPressed: _loading || _saving ? null : _load,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    floatingActionButton:
        !_loading &&
            !_saving &&
            _error == null &&
            _metadata['dapat_kelola'] == true
        ? FloatingActionButton(
            tooltip: 'Tambah',
            onPressed: () => _change(),
            child: const Icon(Icons.add),
          )
        : null,
    body: SafeArea(
      child: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
          children: [
            if (_error != null) HumasErrorView(_error!, onRetry: _load),
            for (final item in _items)
              HumasCard(
                title: humasText(item['judul']),
                subtitle: widget.supporting
                    ? 'Versi ${humasInt(item['versi_berkas'])} / ${humasText(item['jenis'])}'
                    : humasDate(item['waktu_mulai']),
                children: [
                  if (!widget.supporting)
                    TextButton.icon(
                      onPressed: () =>
                          context.push('/humas/agenda/${item['id']}'),
                      icon: const Icon(Icons.open_in_new),
                      label: const Text('Buka agenda'),
                    ),
                  if (widget.supporting) ...[
                    if (item['dilepas_pada'] != null)
                      const HumasBadge('Dilepas'),
                    TextButton.icon(
                      onPressed: _saving
                          ? null
                          : () => humasRun(context, () async {
                              await widget.repository.download(
                                'dokumen/${item['id_dokumen']}/riwayat/${item['id_versi']}/unduh',
                                humasText(item['nama_berkas'], 'dokumen'),
                              );
                            }),
                      icon: const Icon(Icons.download),
                      label: const Text('Unduh versi bukti'),
                    ),
                  ],
                  if (_metadata['dapat_kelola'] == true &&
                      item['dilepas_pada'] == null)
                    TextButton.icon(
                      onPressed: _saving || _loading
                          ? null
                          : () => _change(item),
                      icon: const Icon(Icons.link_off),
                      label: const Text('Lepas hubungan'),
                    ),
                ],
              ),
            if (_items.isEmpty && !_loading && _error == null)
              const HumasCard(title: 'Belum ada data'),
            if (_loading || _saving)
              const Center(child: CircularProgressIndicator()),
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
