import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_editors.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/core/errors/app_exception.dart';

const humasWorkKinds = ['program', 'komite', 'mitra', 'akreditasi'];
String humasWorkTitle(String kind) => const {
  'program': 'Program Kerja Humas',
  'komite': 'Komite Sekolah',
  'mitra': 'Kemitraan & MoU',
  'akreditasi': 'Portofolio Akreditasi',
  'laporan': 'Laporan Pelaksanaan',
  'program-komite': 'Program Komite',
  'mou': 'MoU',
  'butir': 'Butir Akreditasi',
}[kind]!;
HumasModule humasWorkModule(String kind) => switch (kind) {
  'laporan' || 'program' => HumasModule.programs,
  'program-komite' || 'komite' => HumasModule.committee,
  'mou' || 'mitra' => HumasModule.partners,
  _ => HumasModule.accreditation,
};

Future<bool> editHumasWork(
  BuildContext context,
  HumasRepository repo,
  String kind,
  String base, {
  HumasData? record,
}) async {
  final refs = await repo.get('$base/referensi', {
    if (record != null) 'record_id': record['id'],
  });
  if (!context.mounted || refs['dapat_kelola'] != true) return false;
  return await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) => _WorkEditor(
            repo: repo,
            kind: kind,
            base: base,
            refs: refs,
            record: record,
          ),
        ),
      ) ==
      true;
}

class _WorkEditor extends StatefulWidget {
  const _WorkEditor({
    required this.repo,
    required this.kind,
    required this.base,
    required this.refs,
    this.record,
  });
  final HumasRepository repo;
  final String kind, base;
  final HumasData refs;
  final HumasData? record;
  @override
  State<_WorkEditor> createState() => _WorkEditorState();
}

class _WorkEditorState extends State<_WorkEditor> {
  final _token = humasUuid(), _search = TextEditingController();
  final _files = <HumasPickedFile>[];
  late List<HumasData> _members, _documents;
  String? _document;
  late String _method;
  bool _busy = false;
  String? _error;
  HumasData get d => widget.record ?? {};
  HumasData get refs => widget.refs;
  @override
  void initState() {
    super.initState();
    _members = humasItems(d['pengurus']).map((r) => HumasData.from(r)).toList();
    _documents = humasItems(refs['dokumen']);
    _document = d['dokumen_humas_id']?.toString();
    _method = widget.record == null ? 'tanpa' : 'tetap';
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  HumasField text(
    String key,
    String label, {
    bool required = true,
    int max = 180,
    bool multi = false,
  }) => HumasField(
    key,
    label,
    required: required,
    maxLength: max,
    kind: multi ? HumasFieldKind.multiline : HumasFieldKind.text,
    initial: d[key],
  );
  HumasField choice(
    String key,
    String label,
    Map<String, String> options, {
    bool required = true,
    Object? initial,
  }) => HumasField(
    key,
    label,
    kind: HumasFieldKind.choice,
    options: options,
    initial: (d[key] ?? initial)?.toString(),
    required: required,
  );
  HumasField number(String key, String label, {int initial = 1}) => HumasField(
    key,
    label,
    kind: HumasFieldKind.number,
    initial: d[key] ?? initial,
  );
  HumasField date(String key, String label, {bool required = true}) =>
      HumasField(
        key,
        label,
        kind: HumasFieldKind.date,
        initial: d[key],
        required: required,
      );
  List<HumasField> get fields => [
    if (['program', 'akreditasi'].contains(widget.kind))
      choice('tahun_pelajaran_id', 'Tahun pelajaran', {
        for (final t in humasItems(refs['tahun_pelajaran']))
          '${t['id']}': humasText(t['nama']),
      }),
    text(
      ['laporan', 'mou', 'butir'].contains(widget.kind) ? 'judul' : 'nama',
      ['laporan', 'mou', 'butir'].contains(widget.kind) ? 'Judul' : 'Nama',
    ),
    if (widget.kind == 'program') ...[
      choice('semester', 'Periode', humasOptions(refs['semester'])),
      choice('bidang', 'Bidang', humasOptions(refs['bidang'])),
      text('tujuan', 'Tujuan', multi: true, max: 5000),
      text('sasaran', 'Sasaran', multi: true, max: 5000),
      text('target_hasil', 'Target hasil', multi: true, max: 5000),
      number('target_kegiatan', 'Target kegiatan'),
      text('penanggung_jawab', 'Penanggung jawab'),
    ],
    if (widget.kind == 'komite') ...[
      text('nomor_sk', 'Nomor SK', required: false, max: 150),
      date('tanggal_sk', 'Tanggal SK', required: false),
      text('catatan', 'Catatan', required: false, multi: true, max: 2000),
    ],
    if (widget.kind == 'mitra') ...[
      choice('jenis', 'Jenis mitra', humasOptions(refs['jenis'])),
      text('alamat', 'Alamat', required: false, multi: true, max: 1000),
      text('nama_kontak', 'Narahubung', required: false),
      text('jabatan_kontak', 'Jabatan narahubung', required: false, max: 120),
      text('nomor_kontak', 'Nomor telepon', required: false, max: 32),
      text('email', 'Email', required: false),
      text('website', 'Website', required: false, max: 1000),
      text('catatan', 'Catatan', required: false, multi: true, max: 5000),
    ],
    if (widget.kind == 'akreditasi') ...[
      text('instrumen', 'Instrumen'),
      text('penanggung_jawab', 'Penanggung jawab'),
      text('catatan', 'Catatan', required: false, multi: true, max: 5000),
    ],
    if (widget.kind == 'laporan') ...[
      text('tempat', 'Tempat'),
      text('pelaksana', 'Pelaksana'),
      number('jumlah_peserta', 'Jumlah peserta', initial: 0),
      text('uraian', 'Uraian kegiatan', multi: true, max: 10000),
      text('hasil', 'Hasil kegiatan', multi: true, max: 10000),
      text('kendala', 'Kendala', multi: true, max: 10000, required: false),
      text(
        'tindak_lanjut',
        'Tindak lanjut',
        multi: true,
        max: 10000,
        required: false,
      ),
      if (refs['dapat_pilih_agenda'] == true)
        choice('agenda_humas_id', 'Agenda terkait', {
          for (final a in humasItems(refs['agenda']))
            '${a['id']}': humasText(a['judul']),
        }, required: false),
    ],
    if (widget.kind == 'program-komite') ...[
      text('tujuan', 'Tujuan', multi: true, max: 5000),
      text('target_hasil', 'Target hasil', multi: true, max: 5000),
      choice('pengurus_komite_humas_id', 'Penanggung jawab', {
        for (final p in humasItems(refs['pengurus']))
          '${p['id']}': humasText(p['nama']),
      }, required: false),
      text('capaian', 'Capaian', multi: true, max: 10000, required: false),
      text(
        'catatan_evaluasi',
        'Evaluasi',
        multi: true,
        max: 5000,
        required: false,
      ),
    ],
    if (widget.kind == 'mou') ...[
      text('nomor', 'Nomor MoU', required: false, max: 120),
      choice('bidang', 'Bidang', humasOptions(refs['bidang'])),
      text('ruang_lingkup', 'Ruang lingkup', multi: true, max: 5000),
      text('penanggung_jawab', 'Penanggung jawab', required: false),
      choice(
        'ingatkan_hari_sebelum',
        'Pengingat sebelum berakhir',
        humasOptions(refs['pengingat']),
        initial: '30',
      ),
      text(
        'alasan_diakhiri',
        'Alasan pengakhiran',
        required: false,
        multi: true,
        max: 1000,
      ),
    ],
    if (widget.kind == 'butir') ...[
      text('kode', 'Kode butir', max: 40),
      text('deskripsi', 'Deskripsi', required: false, multi: true, max: 5000),
      number('urutan', 'Urutan'),
      number('target_bukti', 'Target bukti'),
    ],
    if ([
      'program',
      'komite',
      'laporan',
      'program-komite',
      'mou',
    ].contains(widget.kind)) ...[
      date('tanggal_mulai', 'Tanggal mulai', required: widget.kind != 'mou'),
      date(
        'tanggal_selesai',
        'Tanggal selesai',
        required: widget.kind != 'mou',
      ),
    ],
    if (!['laporan', 'akreditasi', 'butir'].contains(widget.kind))
      choice('status', 'Status', humasOptions(refs['status'])),
    if (widget.kind == 'program')
      text(
        'evaluasi',
        'Evaluasi akhir / alasan pembatalan',
        multi: true,
        max: 10000,
        required: false,
      ),
    if (widget.record != null &&
        [
          'program',
          'komite',
          'laporan',
          'program-komite',
          'akreditasi',
        ].contains(widget.kind))
      text(
        widget.kind == 'akreditasi' ? 'alasan' : 'catatan_perubahan',
        'Alasan perubahan',
        multi: true,
        max: 2000,
      ),
  ];
  Future<void> _member([HumasData? old, int? index]) async {
    await openHumasForm(
      context,
      title: old == null ? 'Tambah Pengurus' : 'Edit Pengurus',
      fields: [
        HumasField(
          'nama',
          'Nama pengurus',
          maxLength: 180,
          initial: old?['nama'],
        ),
        HumasField(
          'jabatan',
          'Jabatan',
          kind: HumasFieldKind.choice,
          options: humasOptions(refs['jabatan']),
          initial: old?['jabatan'],
        ),
        HumasField(
          'nomor_telepon',
          'Nomor telepon',
          required: false,
          maxLength: 30,
          initial: old?['nomor_telepon'],
        ),
        HumasField(
          'aktif',
          'Aktif',
          kind: HumasFieldKind.boolean,
          initial: old?['aktif'] ?? true,
        ),
      ],
      onSave: (v) async {
        setState(() {
          final row = {...v, if (old?['id'] != null) 'id': old!['id']};
          if (index == null) {
            _members.add(row);
          } else {
            _members[index] = row;
          }
        });
      },
    );
  }

  Future<void> _findDocuments() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final result = await widget.repo.get('${widget.base}/referensi', {
        'cari_dokumen': _search.text.trim(),
        if (widget.record != null) 'record_id': d['id'],
      });
      if (mounted) {
        setState(
          () => _documents = {
            for (final a in _documents.where((a) => '${a['id']}' == _document))
              '${a['id']}': a,
            for (final a in humasItems(result['dokumen'])) '${a['id']}': a,
          }.values.toList(),
        );
      }
    } catch (e) {
      if (mounted) setState(() => _error = humasError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => HumasFormView(
    title:
        '${widget.record == null ? 'Tambah' : 'Edit'} ${humasWorkTitle(widget.kind)}',
    busy: _busy,
    fields: fields,
    extraBuilder: (context, saving) => Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (widget.kind == 'komite') ...[
          const Text(
            'Pengurus',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          for (var i = 0; i < _members.length; i++)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(humasText(_members[i]['nama'])),
              subtitle: Text(
                '${humasText(humasOptions(refs['jabatan'])['${_members[i]['jabatan']}'])} / ${_members[i]['aktif'] == true ? 'Aktif' : 'Nonaktif'}',
              ),
              trailing: IconButton(
                tooltip: 'Edit pengurus',
                onPressed: saving ? null : () => _member(_members[i], i),
                icon: const Icon(Icons.edit_outlined),
              ),
            ),
          OutlinedButton.icon(
            onPressed: saving || _members.length >= 50 ? null : () => _member(),
            icon: const Icon(Icons.person_add_outlined),
            label: const Text('Tambah pengurus'),
          ),
          NusaDropdownField<String>(
            fieldKey: const Key('work-sk-method'),
            value: _method,
            enabled: !saving,
            decoration: const InputDecoration(labelText: 'Berkas SK'),
            options: [
              for (final e in humasOptions(refs['metode']).entries)
                if ((e.key != 'unggah' || refs['dapat_unggah'] == true) &&
                    (e.key != 'dokumen' ||
                        refs['dapat_lihat_dokumen'] == true) &&
                    (widget.record == null
                        ? ['tanpa', 'dokumen', 'unggah'].contains(e.key)
                        : [
                            'tetap',
                            'dokumen',
                            'unggah',
                            'lepas',
                          ].contains(e.key)))
                  NusaDropdownOption(value: e.key, label: e.value),
            ],
            onChanged: (v) => setState(() => _method = v!),
          ),
          if (_method == 'unggah' && refs['dapat_unggah'] == true)
            HumasFilesField(
              maxFiles: 1,
              maxMb: 20,
              extensions: const ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
              enabled: !saving,
              onBusyChanged: (v) => setState(() => _busy = v),
              onChanged: (f) {
                _files
                  ..clear()
                  ..addAll(f);
              },
            ),
        ],
        if (refs['dapat_lihat_dokumen'] == true &&
            (widget.kind == 'mou' ||
                (widget.kind == 'komite' && _method == 'dokumen'))) ...[
          TextField(
            controller: _search,
            enabled: !saving,
            maxLength: 120,
            decoration: InputDecoration(
              labelText: 'Cari dokumen arsip',
              suffixIcon: IconButton(
                tooltip: 'Cari dokumen',
                onPressed: saving ? null : _findDocuments,
                icon: const Icon(Icons.search),
              ),
            ),
          ),
          NusaDropdownField<String>(
            fieldKey: const Key('work-linked-document'),
            value: _documents.any((d) => '${d['id']}' == _document)
                ? _document
                : '',
            enabled: !saving,
            decoration: const InputDecoration(labelText: 'Dokumen terkait'),
            options: [
              const NusaDropdownOption(value: '', label: 'Tanpa dokumen'),
              for (final doc in _documents)
                NusaDropdownOption(
                  value: '${doc['id']}',
                  label: humasText(doc['judul']),
                ),
            ],
            onChanged: (v) => setState(() => _document = v),
          ),
        ],
        if (_error != null)
          Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        const SizedBox(height: 16),
      ],
    ),
    onSave: (values) async {
      final payload = <String, dynamic>{
        ...values,
        if (widget.record == null && widget.kind != 'butir')
          'token_pembuatan': _token,
        if (widget.record != null || widget.kind == 'butir')
          'versi': d['versi'] ?? refs['versi_induk'],
      };
      if (widget.kind == 'komite') {
        payload['pengurus'] = _members;
        payload['metode'] = _method;
        if (_method == 'dokumen') payload['dokumen_humas_id'] = _document;
      }
      if (widget.kind == 'mou' && refs['dapat_lihat_dokumen'] == true) {
        payload['dokumen_humas_id'] = _document;
      }
      FormData? form;
      if (widget.kind == 'komite' && _method == 'unggah') {
        if (_files.isEmpty) {
          throw const ValidationException('Pilih berkas SK terlebih dahulu.');
        }
        form = FormData.fromMap({
          ...payload,
          'pengurus': [
            for (final member in _members)
              {...member, 'aktif': member['aktif'] == true ? '1' : '0'},
          ],
          if (widget.record != null) '_method': 'PATCH',
        });
        form.files.add(
          MapEntry(
            'berkas',
            MultipartFile.fromBytes(
              _files.single.bytes,
              filename: _files.single.name,
            ),
          ),
        );
      }
      await widget.repo.send(
        widget.record == null ? widget.base : '${widget.base}/${d['id']}',
        payload,
        method: form != null || widget.record == null ? 'POST' : 'PATCH',
        multipart: form,
      );
    },
  );
}
