import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/humas/data/humas_photo_picker.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_editors.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

const humasCatalogKinds = ['aset', 'media', 'kliping', 'prestasi', 'alumni'];
HumasModule humasCatalogModule(String kind) =>
    HumasModule.values.firstWhere((m) => m.path == kind);

Future<bool> editHumasCatalog(
  BuildContext context,
  HumasRepository repo,
  String kind, {
  HumasData? record,
}) async {
  final refs = await repo.get('$kind/referensi');
  if (!context.mounted || refs['dapat_kelola'] != true) return false;
  return await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => _CatalogEditor(
            repo: repo,
            kind: kind,
            refs: refs,
            record: record,
          ),
        ),
      ) ==
      true;
}

class _CatalogEditor extends ConsumerStatefulWidget {
  const _CatalogEditor({
    required this.repo,
    required this.kind,
    required this.refs,
    this.record,
  });
  final HumasRepository repo;
  final String kind;
  final HumasData refs;
  final HumasData? record;
  @override
  ConsumerState<_CatalogEditor> createState() => _CatalogEditorState();
}

class _CatalogEditorState extends ConsumerState<_CatalogEditor> {
  final _token = humasUuid();
  final _search = TextEditingController();
  List<HumasPickedFile> _files = [];
  late List<HumasData> _participants, _documents;
  late String _method;
  String? _document, _class;
  HumasData? _source;
  bool _busy = false;
  bool _photo = false;
  int _fileKey = 0;
  HumasData get d => widget.record ?? {};
  HumasData get refs => widget.refs;
  String get kind => widget.kind;
  bool get edit => widget.record != null;
  bool get fileKind => ['aset', 'kliping', 'prestasi'].contains(kind);
  @override
  void initState() {
    super.initState();
    _participants = humasItems(d['peserta'])
        .map((r) => HumasData.from(r))
        .toList();
    _documents = humasItems(refs['dokumen']);
    _method = edit
        ? 'tetap'
        : kind == 'aset'
        ? 'tautan'
        : 'tanpa';
    _class = d['anggota_kelas_id']?.toString();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  HumasField text(
    String key,
    String label, {
    bool required = false,
    int max = 180,
    bool multi = false,
  }) => HumasField(
    key,
    label,
    initial: d[key],
    required: required,
    maxLength: max,
    kind: multi ? HumasFieldKind.multiline : HumasFieldKind.text,
  );
  HumasField choice(
    String key,
    String label, {
    bool required = true,
    Object? initial,
    Map<String, String>? options,
  }) => HumasField(
    key,
    label,
    kind: HumasFieldKind.choice,
    initial: (d[key] ?? initial)?.toString(),
    required: required,
    options: options ?? humasOptions(refs[key]),
  );
  HumasField date(String key, String label, {bool required = true}) =>
      HumasField(
        key,
        label,
        kind: HumasFieldKind.date,
        initial:
            d[key] ??
            (required && !edit
                ? DateTime.now().toIso8601String().substring(0, 10)
                : null),
        required: required,
      );
  HumasField number(
    String key,
    String label, {
    bool required = false,
    int? initial,
  }) => HumasField(
    key,
    label,
    kind: HumasFieldKind.number,
    maxLength: 4,
    initial: d[key] ?? initial,
    required: required,
  );
  List<HumasField> get fields => [
    if (kind == 'aset') ...[
      text('nama', 'Nama aset', required: true),
      choice('kategori', 'Kategori'),
      date('tanggal_aset', 'Tanggal aset'),
      text('deskripsi', 'Deskripsi', multi: true, max: 5000),
      text('kata_kunci', 'Kata kunci', max: 200),
      text('kredit', 'Kredit / pemilik'),
      text(
        'ketentuan_penggunaan',
        'Ketentuan penggunaan',
        multi: true,
        max: 1000,
      ),
    ],
    if (kind == 'media') ...[
      text('nama', 'Nama media', required: true),
      choice('jenis', 'Jenis media'),
      text('identitas_akun', 'Nama akun'),
      text('penanggung_jawab', 'Penanggung jawab', required: true),
      text('jabatan_penanggung_jawab', 'Jabatan penanggung jawab', max: 100),
      date('tanggal_diperiksa', 'Tanggal pemeriksaan', required: false),
      text('catatan', 'Catatan', multi: true, max: 2000),
    ],
    if (kind == 'kliping') ...[
      text('judul', 'Judul berita', required: true),
      text('nama_media', 'Nama media', required: true),
      choice('jenis', 'Jenis media'),
      choice('topik', 'Topik'),
      date('tanggal_terbit', 'Tanggal terbit'),
      text('penulis', 'Penulis'),
      text('rujukan', 'Rujukan / halaman', max: 250),
      text('ringkasan', 'Ringkasan', multi: true, max: 3000),
      text('catatan', 'Catatan', multi: true, max: 2000),
    ],
    if (kind == 'prestasi') ...[
      text('nama_kegiatan', 'Nama kegiatan', required: true),
      text('cabang', 'Cabang', max: 120),
      text('capaian', 'Capaian', required: true),
      date('tanggal_prestasi', 'Tanggal prestasi'),
      choice(
        'tahun_pelajaran_id',
        'Tahun pelajaran',
        required: false,
        options: {
          for (final t in humasItems(refs['tahun_pelajaran']))
            '${t['id']}': humasText(t['nama']),
        },
      ),
      choice('kategori', 'Kategori'),
      choice('tingkat', 'Tingkat'),
      choice('perolehan', 'Perolehan'),
      choice('penerima', 'Penerima'),
      choice('bentuk', 'Bentuk prestasi'),
      text('nama_tim', 'Nama tim'),
      text('penyelenggara', 'Penyelenggara'),
      text('tempat', 'Tempat'),
      text('pembina', 'Pembina'),
      text('catatan', 'Catatan', multi: true, max: 3000),
      const HumasField(
        'konfirmasi_prestasi',
        'Bukti prestasi telah diperiksa',
        kind: HumasFieldKind.boolean,
        required: false,
      ),
    ],
    if (kind == 'alumni') ...[
      text('nama_lengkap', 'Nama lengkap', required: _source == null),
      text('nis', 'NIS', max: 30),
      text('nisn', 'NISN', max: 10),
      choice(
        'jenis_kelamin',
        'Jenis kelamin',
        required: false,
        options: {'L': 'Laki-laki', 'P': 'Perempuan'},
      ),
      number('tahun_masuk', 'Tahun masuk'),
      number(
        'tahun_lulus',
        'Tahun lulus',
        required: true,
        initial: DateTime.now().year,
      ),
      date('tanggal_lulus', 'Tanggal lulus', required: false),
      text('kelas_terakhir', 'Kelas terakhir', max: 80),
      choice('status_penelusuran', 'Status penelusuran'),
      choice('jenis_sekolah', 'Jenis sekolah lanjutan', required: false),
      text('nama_sekolah', 'Nama sekolah lanjutan'),
      text('kota_sekolah', 'Kota sekolah', max: 120),
      text('jurusan', 'Jurusan'),
      date('tanggal_penelusuran', 'Tanggal penelusuran', required: false),
      text(
        'catatan_penelusuran',
        'Catatan penelusuran',
        multi: true,
        max: 3000,
      ),
      text('nomor_wa', 'Nomor WhatsApp', max: 32),
      text('email', 'Email', max: 254),
      const HumasField(
        'konfirmasi_lulus',
        'Kelulusan sudah dikonfirmasi',
        kind: HumasFieldKind.boolean,
      ),
    ],
    if (kind != 'alumni')
      text(
        'tautan',
        kind == 'media' ? 'Alamat publik media' : 'Tautan publik',
        required: kind == 'media',
        max: 2000,
      ),
    if (kind != 'aset' || edit)
      choice(
        'status',
        'Status',
        initial: kind == 'prestasi' ? 'draf' : 'aktif',
      ),
    if (edit)
      text(
        kind == 'aset' ? 'catatan_revisi' : 'catatan_perubahan',
        'Alasan perubahan',
        required: true,
        multi: true,
        max: 2000,
      ),
  ];

  Future<void> _selectPerson({bool alumni = false, String? jenis}) async {
    final selected = await Navigator.push<HumasData>(
      context,
      MaterialPageRoute(
        builder: (_) => _PersonPicker(
          repo: widget.repo,
          alumni: alumni,
          jenis: jenis,
          alumniId: edit ? humasInt(d['id']) : null,
        ),
      ),
    );
    if (!mounted || selected == null) return;
    setState(() {
      if (alumni) {
        _source = selected;
        _class = null;
      } else {
        final key = jenis == 'siswa' ? 'siswa_id' : 'pegawai_id';
        if (!_participants.any((p) => p[key] == selected['id'])) {
          _participants.add({
            key: selected['id'],
            'nama': selected['nama_lengkap'],
          });
        }
      }
    });
  }

  Future<void> _manualPerson() async {
    HumasData? result;
    await Navigator.push<void>(
      context,
      MaterialPageRoute(
        builder: (_) => HumasFormView(
          title: 'Tambah penerima',
          fields: const [
            HumasField('nama', 'Nama penerima', maxLength: 180),
            HumasField('kelas', 'Kelas', maxLength: 80, required: false),
          ],
          onSave: (v) async => result = v,
        ),
      ),
    );
    if (mounted && result != null) setState(() => _participants.add(result!));
  }

  Widget _extra(bool saving) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (kind == 'prestasi') ...[
        const Text(
          'Penerima prestasi',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
        for (var i = 0; i < _participants.length; i++)
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(humasText(_participants[i]['nama'])),
            subtitle: Text(humasText(_participants[i]['kelas'], '')),
            trailing: IconButton(
              tooltip: 'Hapus penerima',
              onPressed: saving
                  ? null
                  : () => setState(() => _participants.removeAt(i)),
              icon: const Icon(Icons.close),
            ),
          ),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            OutlinedButton.icon(
              onPressed: saving ? null : () => _selectPerson(jenis: 'siswa'),
              icon: const Icon(Icons.school_outlined),
              label: const Text('Siswa'),
            ),
            OutlinedButton.icon(
              onPressed: saving ? null : () => _selectPerson(jenis: 'pegawai'),
              icon: const Icon(Icons.badge_outlined),
              label: const Text('Pegawai'),
            ),
            OutlinedButton.icon(
              onPressed: saving ? null : _manualPerson,
              icon: const Icon(Icons.person_add_alt),
              label: const Text('Manual'),
            ),
          ],
        ),
      ],
      if (kind == 'alumni') ...[
        OutlinedButton.icon(
          onPressed: saving ? null : () => _selectPerson(alumni: true),
          icon: const Icon(Icons.person_search_outlined),
          label: const Text('Pilih siswa NUSA'),
        ),
        if (_source != null) ...[
          Text(
            humasText(_source!['nama_lengkap']),
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          NusaDropdownField<String>(
            fieldKey: const Key('alumni-source-class'),
            value: _class,
            options: humasItems(_source!['kelas'])
                .map(
                  (c) => NusaDropdownOption(
                    value: '${c['id']}',
                    label: '${c['nama']} / ${c['tahun_pelajaran']}',
                  ),
                )
                .toList(),
            decoration: const InputDecoration(labelText: 'Riwayat kelas IX'),
            onChanged: saving ? null : (v) => setState(() => _class = v),
          ),
          TextButton(
            onPressed: saving
                ? null
                : () => setState(() {
                    _source = null;
                    _class = d['anggota_kelas_id']?.toString();
                  }),
            child: const Text('Batalkan pilihan siswa'),
          ),
        ],
      ],
      if (fileKind) ...[
        const SizedBox(height: 16),
        NusaDropdownField<String>(
          fieldKey: const Key('catalog-source'),
          value: _method,
          decoration: const InputDecoration(labelText: 'Sumber lampiran'),
          options: [
            if (edit)
              const NusaDropdownOption(
                value: 'tetap',
                label: 'Pertahankan lampiran',
              ),
            if (kind == 'aset')
              const NusaDropdownOption(value: 'tautan', label: 'Tautan publik'),
            if (kind != 'aset')
              NusaDropdownOption(
                value: edit ? 'hapus' : 'tanpa',
                label: edit ? 'Lepaskan lampiran' : 'Tanpa lampiran',
              ),
            if (refs['dapat_lihat_dokumen'] == true)
              const NusaDropdownOption(
                value: 'dokumen',
                label: 'Pilih dari arsip',
              ),
            if (refs['dapat_unggah'] == true)
              const NusaDropdownOption(value: 'unggah', label: 'Unggah berkas'),
          ],
          onChanged: saving
              ? null
              : (v) => setState(() {
                  _method = v!;
                  _files = [];
                }),
        ),
        const SizedBox(height: 12),
        if (_method == 'dokumen') ...[
          TextField(
            controller: _search,
            enabled: !saving && !_busy,
            decoration: InputDecoration(
              labelText: 'Cari dokumen arsip',
              suffixIcon: IconButton(
                tooltip: 'Cari dokumen',
                onPressed: saving || _busy
                    ? null
                    : () => humasRun(context, () async {
                        setState(() => _busy = true);
                        try {
                          final r = await widget.repo.get('$kind/referensi', {
                            'cari_dokumen': _search.text.trim(),
                          });
                          if (mounted) {
                            setState(() {
                              _documents = humasItems(r['dokumen']);
                              _document = null;
                            });
                          }
                        } finally {
                          if (mounted) setState(() => _busy = false);
                        }
                      }),
                icon: const Icon(Icons.search),
              ),
            ),
          ),
          NusaDropdownField<String>(
            fieldKey: const Key('catalog-document'),
            value: _document,
            decoration: const InputDecoration(labelText: 'Dokumen'),
            options: _documents
                .map(
                  (d) => NusaDropdownOption(
                    value: '${d['id']}',
                    label: humasText(d['judul']),
                  ),
                )
                .toList(),
            onChanged: saving || _busy
                ? null
                : (v) => setState(() => _document = v),
          ),
        ],
        if (_method == 'unggah') ...[
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final source in [
                HumasPhotoSource.camera,
                HumasPhotoSource.gallery,
              ])
                OutlinedButton.icon(
                  onPressed: saving || _busy
                      ? null
                      : () => humasRun(context, () async {
                          setState(() => _busy = true);
                          try {
                            final picked = await ref
                                .read(humasPhotoPickerProvider)
                                .pick(source);
                            if (mounted && picked.isNotEmpty) {
                              setState(() {
                                _files = [picked.first];
                                _photo = true;
                                _fileKey++;
                              });
                            }
                          } finally {
                            if (mounted) setState(() => _busy = false);
                          }
                        }),
                  icon: Icon(
                    source == HumasPhotoSource.camera
                        ? Icons.photo_camera_outlined
                        : Icons.photo_library_outlined,
                  ),
                  label: Text(
                    source == HumasPhotoSource.camera ? 'Kamera' : 'Galeri',
                  ),
                ),
            ],
          ),
          if (_photo && _files.isNotEmpty)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(_files.single.name),
              trailing: IconButton(
                tooltip: 'Hapus foto',
                onPressed: saving || _busy
                    ? null
                    : () => setState(() {
                        _files = [];
                        _photo = false;
                      }),
                icon: const Icon(Icons.close),
              ),
            ),
          HumasFilesField(
            key: ValueKey('$_method-$_fileKey'),
            maxFiles: 1,
            maxMb: kind == 'prestasi' ? 10 : 20,
            extensions: kind == 'aset'
                ? [
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
                  ]
                : ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
            enabled: !saving,
            onBusyChanged: (v) => setState(() => _busy = v),
            onChanged: (v) => setState(() {
              _files = v;
              _photo = false;
            }),
          ),
        ],
      ],
      const SizedBox(height: 20),
    ],
  );

  Future<void> _save(HumasData values) async {
    final data = HumasData.from(values);
    data.addAll(edit ? {'versi': d['versi']} : {'token_pembuatan': _token});
    if (fileKind) {
      data['metode'] = _method;
      if (kind == 'aset' && _method != 'tautan') data.remove('tautan');
      if (_method == 'dokumen') {
        if (_document == null) {
          throw const ValidationException('Pilih dokumen terlebih dahulu.');
        }
        data['dokumen_humas_id'] = int.parse(_document!);
      }
      if (_method == 'unggah' && _files.isEmpty) {
        throw const ValidationException('Pilih berkas terlebih dahulu.');
      }
    }
    if (kind == 'prestasi') data['peserta'] = _participants;
    if (kind == 'alumni') {
      data['siswa_id'] = _source?['id'] ?? d['siswa_id'];
      data['anggota_kelas_id'] = _class == null ? null : int.parse(_class!);
    }
    final path = edit ? '$kind/${d['id']}' : kind;
    if (fileKind && _method == 'unggah') {
      final body = HumasData.from(data);
      for (final k in body.keys.toList()) {
        if (body[k] is bool) body[k] = body[k] == true ? '1' : '0';
      }
      if (edit) body['_method'] = 'PATCH';
      final form = FormData.fromMap(body);
      form.files.add(
        MapEntry(
          'berkas',
          MultipartFile.fromBytes(
            _files.single.bytes,
            filename: _files.single.name,
          ),
        ),
      );
      await widget.repo.send(path, {}, multipart: form);
    } else {
      await widget.repo.send(path, data, method: edit ? 'PATCH' : 'POST');
    }
  }

  @override
  Widget build(BuildContext context) => HumasFormView(
    title: '${edit ? 'Edit' : 'Tambah'} ${humasCatalogModule(kind).title}',
    fields: fields,
    busy: _busy,
    extraBuilder: (_, saving) => _extra(saving),
    onSave: _save,
  );
}

class _PersonPicker extends StatefulWidget {
  const _PersonPicker({
    required this.repo,
    this.alumni = false,
    this.jenis,
    this.alumniId,
  });
  final HumasRepository repo;
  final bool alumni;
  final String? jenis;
  final int? alumniId;
  @override
  State<_PersonPicker> createState() => _PersonPickerState();
}

class _PersonPickerState extends State<_PersonPicker> {
  final _search = TextEditingController();
  final _items = <HumasData>[];
  bool _loading = false;
  Object? _error;
  int? _next;
  int _request = 0;
  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load({bool append = false}) async {
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = null;
      if (!append) {
        _items.clear();
        _next = null;
      }
    });
    try {
      final data = await widget.repo.get(
        '${widget.alumni ? 'alumni' : 'prestasi'}/pilihan',
        {
          'q': _search.text.trim(),
          'page': append ? _next : 1,
          if (!widget.alumni) 'jenis': widget.jenis,
          if (widget.alumniId != null && widget.alumni)
            'alumni_id': widget.alumniId,
        },
      );
      if (!mounted || request != _request) return;
      setState(() {
        _items.addAll(humasItems(data['items']));
        _next = data['next_page'] as int?;
      });
    } catch (e) {
      if (mounted && request == _request) {
        setState(() {
          _error = e;
          if (e is UnauthorizedException ||
              e is NetworkException && [403, 404].contains(e.statusCode)) {
            _items.clear();
            _next = null;
          }
        });
      }
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        widget.alumni ? 'Sumber data siswa' : 'Pilih ${widget.jenis}',
      ),
    ),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(
            controller: _search,
            maxLength: 120,
            onSubmitted: (_) => _load(),
            decoration: InputDecoration(
              labelText: 'Cari nama',
              suffixIcon: IconButton(
                tooltip: 'Cari',
                onPressed: _loading ? null : _load,
                icon: const Icon(Icons.search),
              ),
            ),
          ),
          if (_error != null) HumasErrorView(_error!, onRetry: _load),
          for (final item in _items)
            HumasCard(
              title: humasText(item['nama_lengkap']),
              subtitle: item['nisn'] == null ? null : 'NISN ${item['nisn']}',
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.pop(context, item),
            ),
          if (!_loading && _error == null && _items.isEmpty)
            const HumasCard(title: 'Tidak ada data yang sesuai'),
          if (_loading) const Center(child: CircularProgressIndicator()),
          if (!_loading && _next != null)
            OutlinedButton(
              onPressed: () => _load(append: true),
              child: const Text('Muat berikutnya'),
            ),
        ],
      ),
    ),
  );
}
