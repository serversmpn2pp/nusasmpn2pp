import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_collection_view.dart';
import 'package:nusa/features/humas/presentation/humas_editors.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/humas_work_forms.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/features/humas/presentation/humas_work_relations.dart';

class HumasWorkList extends ConsumerStatefulWidget {
  const HumasWorkList({required this.kind, required this.base, super.key});
  final String kind, base;
  @override
  ConsumerState<HumasWorkList> createState() => _WorkListState();
}

class _WorkListState extends ConsumerState<HumasWorkList> {
  final _search = TextEditingController(), _items = <HumasData>[];
  final _filters = <String, String>{};
  HumasData _refs = {};
  HumasPage? _page;
  Object? _error;
  bool _loading = true, _manage = false;
  int _request = 0;
  Timer? _timer;
  HumasRepository get repo => ref.read(humasRepositoryProvider);
  bool get allowed =>
      humasWorkModule(widget.kind)
          .canOpen(ref.read(authControllerProvider).value?.session?.pengguna);
  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  @override
  void dispose() {
    _timer?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _load({bool append = false}) async {
    if (!mounted || !allowed) return;
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = null;
      _manage = false;
      if (!append) {
        _items.clear();
        _page = null;
      }
    });
    try {
      final refs = await repo.get('${widget.base}/referensi');
      final data = await repo.get(widget.base, {
        'halaman': append ? (_page?.page ?? 0) + 1 : 1,
        'per_halaman': 20,
        'kata_kunci': _search.text.trim(),
        ..._filters,
      });
      if (!mounted || request != _request) return;
      setState(() {
        _refs = refs;
        _page = HumasPage.fromJson(data);
        _items.addAll(_page!.items);
        _manage = data['dapat_kelola'] == true && refs['dapat_kelola'] == true;
      });
    } catch (e) {
      if (mounted && request == _request) {
        setState(() {
          _error = e;
          _items.clear();
          _page = null;
        });
      }
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  Future<void> _edit() => humasRun(context, () async {
    if (await editHumasWork(context, repo, widget.kind, widget.base) &&
        mounted) {
      await _load();
    }
  });
  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).value?.session?.pengguna;
    if (!humasWorkModule(widget.kind).canOpen(user)) {
      return Scaffold(
        appBar: AppBar(title: Text(humasWorkTitle(widget.kind))),
        body: const Center(
          child: Text('Menu ini tidak tersedia untuk akun Anda.'),
        ),
      );
    }
    return Scaffold(
      appBar: AppBar(
        title: Text(humasWorkTitle(widget.kind)),
        actions: [
          IconButton(
            tooltip: 'Muat ulang',
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: _manage && !_loading && _error == null
          ? FloatingActionButton(
              onPressed: _edit,
              tooltip: 'Tambah',
              child: const Icon(Icons.add),
            )
          : null,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
            children: [
              TextField(
                controller: _search,
                maxLength: 120,
                decoration: const InputDecoration(
                  hintText: 'Cari nama atau judul',
                  prefixIcon: Icon(Icons.search),
                  counterText: '',
                ),
                onChanged: (_) {
                  _timer?.cancel();
                  _timer = Timer(const Duration(milliseconds: 550), _load);
                },
              ),
              const SizedBox(height: 12),
              for (final f in <String, String>{
                'status': 'Status',
                if (['program', 'akreditasi'].contains(widget.kind))
                  'tahun_pelajaran_id': 'Tahun pelajaran',
                if (widget.kind == 'program') 'semester': 'Periode',
                if (['program', 'mou'].contains(widget.kind))
                  'bidang': 'Bidang',
                if (widget.kind == 'mitra') 'jenis': 'Jenis mitra',
              }.entries)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: NusaDropdownField<String>(
                    fieldKey: Key('work-filter-${f.key}'),
                    value: _filters[f.key] ?? '',
                    decoration: InputDecoration(labelText: f.value),
                    options: [
                      NusaDropdownOption(
                        value: '',
                        label: 'Semua ${f.value.toLowerCase()}',
                      ),
                      if (f.key == 'tahun_pelajaran_id')
                        ...humasItems(_refs['tahun_pelajaran']).map(
                          (t) => NusaDropdownOption(
                            value: '${t['id']}',
                            label: humasText(t['nama']),
                          ),
                        )
                      else
                        ...humasOptions(_refs[f.key]).entries.map(
                          (e) =>
                              NusaDropdownOption(value: e.key, label: e.value),
                        ),
                    ],
                    onChanged: (v) {
                      _filters[f.key] = v!;
                      _timer?.cancel();
                      _load();
                    },
                  ),
                ),
              if (_page != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text('${_page!.total} data'),
                ),
              if (_error != null) HumasErrorView(_error!, onRetry: _load),
              for (final item in _items)
                HumasCard(
                  title: humasText(item['nama'] ?? item['judul']),
                  subtitle: widget.kind == 'program'
                      ? '${humasText(item['tahun_pelajaran'])} / ${humasText(item['semester_label'])}\n${humasInt(item['realisasi'])}/${humasInt(item['target_kegiatan'])} kegiatan final'
                      : humasText(
                          item['masa_berlaku'] ??
                              item['instrumen'] ??
                              item['jenis'] ??
                              item['tanggal_selesai'],
                        ),
                  trailing: const Icon(Icons.chevron_right),
                  children: [
                    const SizedBox(height: 12),
                    HumasBadge(humasText(item['status_label'])),
                  ],
                  onTap: () async {
                    if (humasWorkKinds.contains(widget.kind)) {
                      await context.push('/humas/${widget.base}/${item['id']}');
                    } else {
                      await Navigator.push(
                        context,
                        MaterialPageRoute<void>(
                          builder: (_) => HumasWorkDetail(
                            kind: widget.kind,
                            base: widget.base,
                            id: humasInt(item['id']),
                          ),
                        ),
                      );
                    }
                    if (mounted) await _load();
                  },
                ),
              if (_items.isEmpty && !_loading && _error == null)
                const HumasCard(
                  title: 'Belum ada data',
                  subtitle: 'Tidak ada data yang sesuai dengan filter.',
                ),
              if (_loading)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
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
}

class HumasWorkDetail extends ConsumerStatefulWidget {
  const HumasWorkDetail({
    required this.kind,
    required this.base,
    required this.id,
    super.key,
  });
  final String kind, base;
  final int id;
  @override
  ConsumerState<HumasWorkDetail> createState() => _WorkDetailState();
}

class _WorkDetailState extends ConsumerState<HumasWorkDetail> {
  HumasData? _data;
  Object? _error;
  bool _loading = true;
  int _request = 0;
  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  Future<void> _load() async {
    if (!mounted ||
        !humasWorkModule(
          widget.kind,
        ).canOpen(ref.read(authControllerProvider).value?.session?.pengguna)) {
      return;
    }
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = null;
      _data = null;
    });
    try {
      final d = await ref
          .read(humasRepositoryProvider)
          .get('${widget.base}/${widget.id}');
      if (mounted && request == _request) setState(() => _data = d);
    } catch (e) {
      if (mounted && request == _request) setState(() => _error = e);
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final allowed = humasWorkModule(widget.kind)
        .canOpen(ref.watch(authControllerProvider).value?.session?.pengguna);
    return Scaffold(
      appBar: AppBar(
        title: Text(humasWorkTitle(widget.kind)),
        actions: [
          if (allowed)
            IconButton(
              tooltip: 'Muat ulang',
              onPressed: _loading ? null : _load,
              icon: const Icon(Icons.refresh),
            ),
        ],
      ),
      body: !allowed
          ? const Center(
              child: Text('Menu ini tidak tersedia untuk akun Anda.'),
            )
          : SafeArea(
              child: RefreshIndicator(
                onRefresh: _load,
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (_loading)
                      const Center(child: CircularProgressIndicator()),
                    if (_error != null) HumasErrorView(_error!, onRetry: _load),
                    if (_data != null && !_loading && _error == null)
                      HumasWorkContent(
                        kind: widget.kind,
                        base: widget.base,
                        data: _data!,
                        repository: ref.read(humasRepositoryProvider),
                        onChanged: _load,
                      ),
                  ],
                ),
              ),
            ),
    );
  }
}

class HumasWorkContent extends StatefulWidget {
  const HumasWorkContent({
    required this.kind,
    required this.base,
    required this.data,
    required this.repository,
    required this.onChanged,
    super.key,
  });
  final String kind, base;
  final HumasData data;
  final HumasRepository repository;
  final Future<void> Function() onChanged;
  @override
  State<HumasWorkContent> createState() => _WorkContentState();
}

class _WorkContentState extends State<HumasWorkContent> {
  bool _busy = false;
  HumasData get d => widget.data;
  HumasData get rights => humasMap(d['hak_akses']);
  String get path => '${widget.base}/${d['id']}';
  HumasRepository get repo => widget.repository;
  Future<void> _run(Future<void> Function() work) async {
    if (_busy) return;
    setState(() => _busy = true);
    await humasRun(context, work);
    if (mounted) setState(() => _busy = false);
  }

  Widget button(
    String label,
    IconData icon,
    Future<void> Function() work, {
    bool primary = false,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: primary
        ? FilledButton.icon(
            onPressed: _busy ? null : () => _run(work),
            icon: Icon(icon),
            label: Text(label),
          )
        : OutlinedButton.icon(
            onPressed: _busy ? null : () => _run(work),
            icon: Icon(icon),
            label: Text(label),
          ),
  );
  Future<void> _action(
    String suffix,
    String title,
    List<HumasField> fields, {
    String method = 'POST',
    bool token = false,
  }) async {
    final uuid = humasUuid();
    final changed = await openHumasForm(
      context,
      title: title,
      fields: fields,
      onSave: (values) async {
        await repo.send('$path$suffix', {
          ...values,
          'versi': d['versi'],
          if (token) 'token_pembuatan': uuid,
        }, method: method);
      },
    );
    if (changed) await widget.onChanged();
  }

  Future<void> _evidence() async {
    final refs = await repo.get('${widget.base}/referensi', {
      'record_id': d['id'],
    });
    if (!mounted) return;
    final files = <HumasPickedFile>[];
    var picking = false;
    final token = humasUuid();
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => StatefulBuilder(
          builder: (context, setForm) => HumasFormView(
            title: 'Tambah Bukti',
            busy: picking,
            fields: [
              if (widget.kind == 'laporan')
                const HumasField('judul', 'Judul bukti', maxLength: 180),
              HumasField(
                widget.kind == 'butir'
                    ? 'riwayat_dokumen_humas_id'
                    : 'dokumen_humas_id',
                'Dokumen arsip',
                kind: HumasFieldKind.choice,
                required: widget.kind == 'butir',
                options: {
                  for (final doc in humasItems(refs['dokumen']))
                    if (widget.kind != 'butir' || doc['id_versi'] != null)
                      '${doc[widget.kind == 'butir' ? 'id_versi' : 'id']}':
                          '${humasText(doc['judul'])} / v${humasInt(doc['versi'])}',
                },
              ),
              if (widget.kind == 'butir')
                const HumasField(
                  'catatan',
                  'Catatan bukti',
                  required: false,
                  maxLength: 2000,
                ),
            ],
            extraBuilder:
                widget.kind != 'laporan' || rights['dapat_unggah'] != true
                ? null
                : (context, saving) => HumasFilesField(
                    maxFiles: 1,
                    maxMb: 10,
                    extensions: const ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
                    enabled: !saving,
                    onBusyChanged: (v) {
                      if (context.mounted) setForm(() => picking = v);
                    },
                    onChanged: (f) {
                      files
                        ..clear()
                        ..addAll(f);
                    },
                  ),
            onSave: (v) async {
              final payload = {
                ...v,
                'token_pembuatan': token,
                'versi': d['versi'],
              };
              FormData? form;
              if (files.isNotEmpty) {
                if (v['dokumen_humas_id'] != null) {
                  throw const ValidationException(
                    'Pilih dokumen arsip atau unggah berkas, bukan keduanya.',
                  );
                }
                form = FormData.fromMap(payload);
                form.files.add(
                  MapEntry(
                    'berkas',
                    MultipartFile.fromBytes(
                      files.single.bytes,
                      filename: files.single.name,
                    ),
                  ),
                );
              }
              await repo.send('$path/bukti', payload, multipart: form);
            },
          ),
        ),
      ),
    );
    if (changed == true) await widget.onChanged();
  }

  Future<void> _uploadMou() async {
    final token = humasUuid(), files = <HumasPickedFile>[];
    var picking = false;
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => StatefulBuilder(
          builder: (formContext, setForm) => HumasFormView(
            title: 'Unggah Dokumen MoU',
            busy: picking,
            fields: [
              HumasField(
                'judul',
                'Judul dokumen',
                maxLength: 180,
                initial: d['judul'],
              ),
              HumasField(
                'nomor_dokumen',
                'Nomor dokumen',
                required: false,
                maxLength: 120,
                initial: d['nomor'],
              ),
            ],
            extraBuilder: (context, saving) => HumasFilesField(
              maxFiles: 1,
              maxMb: 20,
              extensions: const [
                'pdf',
                'doc',
                'docx',
                'xls',
                'xlsx',
                'ppt',
                'pptx',
                'jpg',
                'jpeg',
                'png',
                'webp',
              ],
              enabled: !saving,
              onBusyChanged: (v) {
                if (formContext.mounted) setForm(() => picking = v);
              },
              onChanged: (f) {
                files
                  ..clear()
                  ..addAll(f);
              },
            ),
            onSave: (v) async {
              if (files.isEmpty) {
                throw const ValidationException(
                  'Pilih berkas MoU terlebih dahulu.',
                );
              }
              final payload = {
                ...v,
                'urusan': 'kemitraan',
                'jenis_dokumen': 'mou',
                'ingatkan_hari_sebelum': humasInt(d['ingatkan_hari_sebelum']),
                'kerja_sama_humas_id': d['id'],
                'token_unggahan_mou': token,
              };
              final form = FormData.fromMap(payload)
                ..files.add(
                  MapEntry(
                    'berkas',
                    MultipartFile.fromBytes(
                      files.single.bytes,
                      filename: files.single.name,
                    ),
                  ),
                );
              await repo.send('dokumen', payload, multipart: form);
            },
          ),
        ),
      ),
    );
    if (changed == true) await widget.onChanged();
  }

  @override
  Widget build(BuildContext context) {
    final kind = widget.kind,
        manage = rights['dapat_kelola'] == true,
        doc = rights['dapat_lihat_dokumen'] == true;
    final facts = <String, Object?>{
      'Tahun pelajaran': d['tahun_pelajaran'],
      'Periode': d['semester_label'],
      'Bidang': d['bidang_label'] ?? d['bidang'],
      'Instrumen': d['instrumen'],
      'Mitra': d['mitra'],
      'Mulai': d['tanggal_mulai'] == null
          ? null
          : humasDate(d['tanggal_mulai']),
      'Selesai': d['tanggal_selesai'] == null
          ? null
          : humasDate(d['tanggal_selesai']),
      'Penanggung jawab': d['penanggung_jawab'],
      'Tujuan': d['tujuan'],
      'Sasaran': d['sasaran'],
      'Target hasil': d['target_hasil'],
      'Target kegiatan': d['target_kegiatan'],
      'Realisasi final': d['realisasi'],
      'Nomor SK': d['nomor_sk'],
      'Nomor MoU': d['nomor'],
      'Ruang lingkup': d['ruang_lingkup'],
      'Narahubung': d['nama_kontak'],
      'Telepon': d['nomor_kontak'],
      'Email': d['email'],
      'Alamat': d['alamat'],
      'Tempat': d['tempat'],
      'Pelaksana': d['pelaksana'],
      'Jumlah peserta': d['jumlah_peserta'],
      'Uraian': d['uraian'],
      'Hasil': d['hasil'],
      'Kendala': d['kendala'],
      'Tindak lanjut': d['tindak_lanjut'],
      'Evaluasi': d['evaluasi'] ?? d['catatan_evaluasi'],
      'Capaian': d['capaian'],
      'Kode butir': d['kode'],
      'Deskripsi': d['deskripsi'],
      'Target bukti': d['target_bukti'],
      'Pemeriksaan': d['catatan_pemeriksaan'],
      'Catatan': d['catatan'],
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        HumasCard(
          title: humasText(d['nama'] ?? d['judul']),
          trailing: Icon(switch (kind) {
            'komite' => Icons.groups_outlined,
            'mitra' || 'mou' => Icons.handshake_outlined,
            'akreditasi' || 'butir' => Icons.fact_check_outlined,
            _ => Icons.assignment_outlined,
          }),
          children: [
            const SizedBox(height: 12),
            HumasBadge(humasText(d['status_label'])),
            if (d['versi'] != null)
              HumasFacts({'Versi': humasInt(d['versi']) + 1}),
          ],
        ),
        HumasCard(title: 'Rincian', children: [HumasFacts(facts)]),
        if (kind == 'komite')
          HumasCard(
            title: 'Pengurus komite',
            children: [
              for (final m in humasItems(d['pengurus']))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(humasText(m['nama'])),
                  subtitle: Text(
                    '${humasText(m['jabatan'])} / ${m['aktif'] == true ? 'Aktif' : 'Nonaktif'}${!manage || m['nomor_telepon'] == null ? '' : '\n${m['nomor_telepon']}'}',
                  ),
                ),
            ],
          ),
        if (['mitra', 'program-komite'].contains(kind) &&
            rights['dapat_lihat_agenda'] == true)
          button('Kegiatan terkait', Icons.event_outlined, () async {
            await Navigator.push(
              context,
              MaterialPageRoute<void>(
                builder: (_) => HumasWorkRelations(
                  path: '$path/${kind == 'mitra' ? 'agenda' : 'rapat'}',
                  base: widget.base,
                  recordId: humasInt(d['id']),
                  repository: repo,
                ),
              ),
            );
            await widget.onChanged();
          }),
        if (kind == 'mou' && doc)
          button('Dokumen pendukung', Icons.folder_copy_outlined, () async {
            await Navigator.push(
              context,
              MaterialPageRoute<void>(
                builder: (_) => HumasWorkRelations(
                  path: 'kemitraan/${d['id']}/pendukung',
                  base: widget.base,
                  recordId: humasInt(d['id']),
                  repository: repo,
                  supporting: true,
                ),
              ),
            );
            await widget.onChanged();
          }),
        if (manage)
          button('Edit ${humasWorkTitle(kind)}', Icons.edit_outlined, () async {
            if (await editHumasWork(
              context,
              repo,
              kind,
              widget.base,
              record: d,
            )) {
              await widget.onChanged();
            }
          }),
        if (kind == 'mou' &&
            manage &&
            rights['dapat_unggah'] == true &&
            d['status'] != 'diakhiri' &&
            d['dokumen_humas_id'] == null)
          button('Unggah dokumen MoU', Icons.upload_file_outlined, _uploadMou),
        if (humasWorkKinds.contains(kind))
          button(
            switch (kind) {
              'program' => 'Laporan pelaksanaan',
              'komite' => 'Program komite',
              'mitra' => 'Daftar MoU',
              _ => 'Butir dan bukti',
            },
            Icons.folder_open_outlined,
            () async {
              final child = const {
                'program': ('laporan', 'laporan'),
                'komite': ('program-komite', 'program'),
                'mitra': ('mou', 'mou'),
                'akreditasi': ('butir', 'butir'),
              }[kind]!;
              await Navigator.push(
                context,
                MaterialPageRoute<void>(
                  builder: (_) =>
                      HumasWorkList(kind: child.$1, base: '$path/${child.$2}'),
                ),
              );
              await widget.onChanged();
            },
            primary: true,
          ),
        if (kind == 'akreditasi' && rights['dapat_ubah_status'] == true)
          button(
            'Ubah status portofolio',
            Icons.published_with_changes,
            () => _action('/status', 'Status Portofolio', [
              HumasField(
                'status',
                'Status tujuan',
                kind: HumasFieldKind.choice,
                options: switch (d['status']) {
                  'draf' => {'siap': 'Siap', 'arsip': 'Arsip'},
                  'siap' => {'draf': 'Buka revisi', 'arsip': 'Arsip'},
                  _ => {'draf': 'Buka revisi'},
                },
              ),
              const HumasField(
                'alasan',
                'Alasan',
                kind: HumasFieldKind.multiline,
                maxLength: 2000,
              ),
            ]),
          ),
        if (kind == 'akreditasi' && rights['dapat_ekspor'] == true)
          button('Unduh bundel ZIP', Icons.archive_outlined, () async {
            await repo.download(
              '$path/bundel',
              'portofolio-${d['id']}.zip',
              data: {'versi': d['versi']},
            );
          }),
        if (['program', 'laporan', 'akreditasi'].contains(kind))
          button('Unduh lembar cetak (HTML)', Icons.print_outlined, () async {
            await repo.download('$path/cetak', '$kind-${d['id']}.html');
          }),
        if (kind == 'komite' && doc && d['berkas'] != null)
          button('Unduh SK', Icons.download_outlined, () async {
            final f = humasMap(d['berkas']);
            await repo.download(
              '$path/berkas/${f['id']}?unduh=1',
              humasText(f['nama'], 'sk-komite.pdf'),
            );
          }),
        if (kind == 'mou' && doc && d['dokumen_humas_id'] != null)
          button('Unduh dokumen MoU', Icons.download_outlined, () async {
            await repo.download(
              'dokumen/${d['dokumen_humas_id']}/unduh',
              humasText(humasMap(d['dokumen'])['nama'], 'mou-${d['id']}'),
            );
          }),
        if (['laporan', 'butir'].contains(kind) && doc) ...[
          for (final b in humasItems(d['bukti']))
            HumasCard(
              title: humasText(b['judul']),
              subtitle: 'Versi berkas ${humasInt(b['versi_berkas'])}',
              children: [
                button('Unduh bukti', Icons.download_outlined, () async {
                  await repo.download(
                    '$path/bukti/${b['id']}',
                    humasText(b['nama'], 'bukti'),
                  );
                }),
                if (manage)
                  button(
                    'Lepas bukti',
                    Icons.link_off,
                    () => _action('/bukti/${b['id']}', 'Lepas Bukti', [
                      HumasField(
                        kind == 'butir' ? 'alasan' : 'catatan_perubahan',
                        'Alasan pelepasan',
                        kind: HumasFieldKind.multiline,
                        maxLength: 2000,
                      ),
                    ], method: 'DELETE'),
                  ),
              ],
            ),
          if (manage) button('Tambah bukti', Icons.attach_file, _evidence),
        ],
        if (kind == 'butir' && manage) ...[
          if (doc)
            button(
              'Periksa butir',
              Icons.fact_check_outlined,
              () => _action('/periksa', 'Pemeriksaan Butir', [
                const HumasField(
                  'status',
                  'Hasil pemeriksaan',
                  kind: HumasFieldKind.choice,
                  options: {
                    'belum_diperiksa': 'Belum diperiksa',
                    'perlu_perbaikan': 'Perlu perbaikan',
                    'terpenuhi': 'Terpenuhi',
                    'tidak_berlaku': 'Tidak berlaku',
                  },
                ),
                const HumasField(
                  'catatan_pemeriksaan',
                  'Catatan pemeriksaan',
                  kind: HumasFieldKind.multiline,
                  maxLength: 5000,
                ),
              ]),
            ),
          button(
            'Keluarkan butir',
            Icons.delete_outline,
            () => _action('', 'Keluarkan Butir', [
              const HumasField(
                'alasan',
                'Alasan',
                kind: HumasFieldKind.multiline,
                maxLength: 2000,
              ),
            ], method: 'DELETE'),
          ),
        ],
        if (kind == 'laporan' &&
            d['terbuka'] == true &&
            rights['dapat_ubah_status'] == true)
          for (final a
              in (d['status'] == 'draf'
                      ? {
                          'finalisasi': 'Finalisasi laporan',
                          'batalkan': 'Batalkan laporan',
                        }
                      : {'revisi': 'Buka revisi'})
                  .entries)
            button(
              a.value,
              Icons.task_alt,
              () => _action('/tindakan/${a.key}', a.value, [
                HumasField(
                  'catatan_perubahan',
                  'Catatan perubahan',
                  required: a.key != 'finalisasi',
                  kind: HumasFieldKind.multiline,
                  maxLength: 2000,
                ),
              ]),
            ),
        if (kind != 'butir')
          button('Riwayat perubahan', Icons.history, () async {
            await Navigator.push(
              context,
              MaterialPageRoute<void>(
                builder: (_) => HumasCollectionView(
                  title: 'Riwayat Perubahan',
                  path: '$path/riwayat',
                  repository: repo,
                  itemBuilder: (i, reload) => HumasCard(
                    title: humasText(i['aksi']),
                    subtitle:
                        '${humasText(i['catatan'])}\n${humasText(i['pengguna'])} / ${humasDate(i['created_at'])}',
                  ),
                ),
              ),
            );
          }),
        if (_busy) const LinearProgressIndicator(),
      ],
    );
  }
}
