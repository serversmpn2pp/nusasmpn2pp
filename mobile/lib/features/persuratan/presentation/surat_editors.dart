import 'package:flutter/material.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/persuratan/data/persuratan_repository.dart';
import 'package:nusa/features/persuratan/domain/persuratan.dart';
import 'package:nusa/features/persuratan/presentation/surat_form_view.dart';
import 'package:nusa/features/persuratan/presentation/widgets/surat_widgets.dart';

Future<bool> suratForm(
  BuildContext context,
  int account,
  String title,
  List<SuratField> fields,
  Future<void> Function(SuratData) save, {
  Widget Function(bool)? extra,
}) async =>
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => SuratFormView(
          title: title,
          accountId: account,
          fields: fields,
          onSave: save,
          extra: extra,
        ),
      ),
    ) ==
    true;

Future<bool> editSurat(
  BuildContext context,
  PersuratanRepository repo,
  int account, {
  SuratData? record,
  bool revision = false,
}) async {
  final reference = await repo.get('persuratan/referensi');
  if (!context.mounted) return false;
  if (suratMap(reference['hak_akses'])['dapat_kelola'] != true) {
    throw const ValidationException(
      'Akun ini tidak berwenang mengelola surat.',
    );
  }
  var kind = record?['jenis'] as String?;
  kind ??= await showModalBottomSheet<String>(
    context: context,
    useSafeArea: true,
    builder: (context) => Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (final e in suratMap(reference['jenis']).entries)
          ListTile(
            leading: Icon(
              e.key == 'masuk'
                  ? Icons.move_to_inbox_outlined
                  : e.key == 'tugas'
                  ? Icons.assignment_ind_outlined
                  : Icons.outbox_outlined,
            ),
            title: Text('${e.value}'),
            onTap: () => Navigator.pop(context, e.key),
          ),
      ],
    ),
  );
  if (kind == null || !context.mounted) return false;
  final type = kind;
  final old = record ?? <String, dynamic>{};
  final update = record != null && !revision;
  final today = DateTime.now().toIso8601String().substring(0, 10);
  var recipients = suratItems(old['penugasan'])
      .map((p) => suratInt(p['pegawai_id']))
      .where((id) => id > 0)
      .toSet();
  SuratUpload? primary;
  var attachments = <SuratUpload>[];
  final oldAttachments = update
      ? suratItems(old['berkas'])
            .where((b) => b['aktif'] == true && b['jenis'] == 'lampiran')
            .toList()
      : <SuratData>[];
  var retained = oldAttachments.map((b) => suratInt(b['id'])).toSet();
  final oldPrimary = update
      ? suratItems(old['berkas'])
            .where((b) => b['aktif'] == true && b['jenis'] == 'utama')
            .firstOrNull
      : null;
  final fields = [
    SuratField(
      'nomor_surat',
      'Nomor surat',
      maxLength: 120,
      initial: revision ? '' : old['nomor_surat'],
    ),
    if (type == 'masuk')
      SuratField(
        'nomor_agenda',
        'Nomor agenda',
        maxLength: 80,
        initial: old['nomor_agenda'],
      ),
    SuratField(
      'tanggal_surat',
      'Tanggal surat',
      kind: SuratFieldKind.date,
      initial: revision ? today : old['tanggal_surat'] ?? today,
    ),
    if (type == 'masuk')
      SuratField(
        'tanggal_diterima',
        'Tanggal diterima',
        kind: SuratFieldKind.date,
        initial: old['tanggal_diterima'] ?? today,
      ),
    SuratField('perihal', 'Perihal', initial: old['perihal']),
    SuratField(
      'pihak',
      type == 'masuk' ? 'Pengirim' : 'Tujuan surat',
      initial: old['pihak'],
    ),
    SuratField(
      'sifat',
      'Sifat surat',
      kind: SuratFieldKind.choice,
      initial: old['sifat'] ?? 'biasa',
      options: suratMap(reference['sifat']).map((k, v) => MapEntry(k, '$v')),
    ),
    if (type != 'masuk')
      SuratField(
        'pengesah_pengguna_id',
        'Pengesah',
        kind: SuratFieldKind.choice,
        initial: old['pengesah_pengguna_id']?.toString(),
        options: {
          for (final p in suratItems(reference['pengesah']))
            '${p['id']}': suratText(p['nama']),
        },
      ),
    if (type == 'tugas') ...[
      SuratField(
        'mulai_tugas',
        'Mulai tugas',
        kind: SuratFieldKind.date,
        initial: old['mulai_tugas'],
      ),
      SuratField(
        'selesai_tugas',
        'Selesai tugas',
        kind: SuratFieldKind.date,
        initial: old['selesai_tugas'],
      ),
      SuratField('tempat_tugas', 'Tempat tugas', initial: old['tempat_tugas']),
      SuratField(
        'uraian_tugas',
        'Uraian tugas',
        kind: SuratFieldKind.multiline,
        maxLength: 5000,
        initial: old['uraian_tugas'],
      ),
    ],
    SuratField(
      'keterangan',
      'Keterangan',
      required: false,
      kind: SuratFieldKind.multiline,
      maxLength: 5000,
      initial: old['keterangan'],
    ),
    if (update)
      const SuratField(
        'catatan_revisi',
        'Alasan perubahan',
        kind: SuratFieldKind.multiline,
        maxLength: 2000,
      ),
  ];
  return suratForm(
    context,
    account,
    revision
        ? 'Revisi surat - nomor baru'
        : update
        ? 'Edit surat'
        : '${reference['jenis'][type]}',
    fields,
    (values) async {
      if (!update && primary == null) {
        throw const ValidationException(
          'Berkas utama wajib dipilih atau difoto.',
        );
      }
      if (type == 'tugas' && (recipients.isEmpty || recipients.length > 100)) {
        throw const ValidationException('Pilih 1 sampai 100 penerima tugas.');
      }
      if (retained.length + attachments.length > 5) {
        throw const ValidationException(
          'Gabungan lampiran lama dan baru maksimal 5 berkas.',
        );
      }
      final data = {
        ...values,
        'jenis': type,
        'pegawai_ids': type == 'tugas' ? recipients.toList() : <int>[],
        if (update) ...{
          '_method': 'PATCH',
          'versi': old['versi'],
          'hapus_lampiran': oldAttachments
              .map((b) => suratInt(b['id']))
              .where((id) => !retained.contains(id))
              .toList(),
        },
        if (revision) ...{
          'revisi_dari_id': old['id'],
          'versi_asal': old['versi'],
        },
      };
      await repo.send(
        update ? 'persuratan/${old['id']}' : 'persuratan',
        data,
        multipart: suratMultipart(
          data,
          primary: primary,
          attachments: attachments,
        ),
      );
    },
    extra: (saving) => Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (revision)
          Text(
            'Surat asal: ${suratText(old['nomor_surat'])}',
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        if (type == 'tugas')
          SuratRecipients(
            options: suratItems(reference['pegawai']),
            selected: recipients,
            enabled: !saving,
            onChanged: (v) => recipients = v,
          ),
        if (oldPrimary != null)
          Text('Berkas utama tersimpan: ${suratText(oldPrimary['nama'])}'),
        SuratUploadField(
          title: 'Berkas utama',
          primary: true,
          maxFiles: 1,
          enabled: !saving,
          onChanged: (v) => primary = v.firstOrNull,
        ),
        if (oldAttachments.isNotEmpty)
          SuratRetainedFiles(
            files: oldAttachments,
            selected: retained,
            enabled: !saving,
            onChanged: (v) => retained = v,
          ),
        SuratUploadField(
          title: 'Lampiran baru',
          enabled: !saving,
          onChanged: (v) => attachments = v,
        ),
      ],
    ),
  );
}

Future<bool> disposeSurat(
  BuildContext context,
  PersuratanRepository repo,
  int account,
  SuratData data,
) {
  var recipients = <int>{};
  return suratForm(
    context,
    account,
    'Disposisi surat',
    const [
      SuratField(
        'instruksi',
        'Instruksi',
        kind: SuratFieldKind.multiline,
        maxLength: 5000,
      ),
      SuratField('batas_tanggal', 'Tenggat', kind: SuratFieldKind.date),
    ],
    (values) async {
      if (recipients.isEmpty || recipients.length > 100) {
        throw const ValidationException('Pilih 1 sampai 100 penerima.');
      }
      await repo.send('persuratan/${data['id']}/disposisi', {
        ...values,
        'versi': data['versi'],
        'pegawai_ids': recipients.toList(),
      });
    },
    extra: (busy) => SuratRecipients(
      options: suratItems(data['penerima_disposisi']),
      selected: recipients,
      enabled: !busy,
      onChanged: (v) => recipients = v,
    ),
  );
}

Future<bool> reportSurat(
  BuildContext context,
  PersuratanRepository repo,
  int account,
  SuratData task,
) {
  final last = suratItems(task['laporan']).firstOrNull;
  final oldFiles = suratItems(last?['bukti']);
  var retained = oldFiles.map((b) => suratInt(b['id'])).toSet();
  var evidence = <SuratUpload>[];
  return suratForm(
    context,
    account,
    'Laporan pelaksanaan',
    [
      SuratField(
        'hasil',
        'Hasil / progres',
        kind: SuratFieldKind.multiline,
        maxLength: 10000,
        initial: last?['hasil'],
      ),
      const SuratField(
        'aksi',
        'Penyimpanan',
        kind: SuratFieldKind.choice,
        initial: 'draf',
        options: {'draf': 'Simpan progres', 'ajukan': 'Ajukan untuk diperiksa'},
      ),
    ],
    (values) async {
      if (retained.length + evidence.length > 5) {
        throw const ValidationException(
          'Gabungan bukti lama dan baru maksimal 5 berkas.',
        );
      }
      final data = {
        ...values,
        'versi': task['versi'],
        'bukti_lama': retained.toList(),
      };
      await repo.send(
        'tindak-lanjut-surat/${task['id']}/laporan',
        data,
        multipart: suratMultipart(data, evidence: evidence),
      );
    },
    extra: (busy) => Column(
      children: [
        if (oldFiles.isNotEmpty)
          SuratRetainedFiles(
            files: oldFiles,
            selected: retained,
            enabled: !busy,
            onChanged: (v) => retained = v,
          ),
        SuratUploadField(
          title: 'Bukti baru',
          enabled: !busy,
          onChanged: (v) => evidence = v,
        ),
      ],
    ),
  );
}

Future<bool> changeSuratTask(
  BuildContext context,
  PersuratanRepository repo,
  int account,
  SuratData data,
) => suratForm(
  context,
  account,
  'Ubah tindak lanjut',
  [
    SuratField(
      'instruksi',
      'Instruksi',
      kind: SuratFieldKind.multiline,
      maxLength: 5000,
      initial: data['instruksi'],
    ),
    SuratField(
      'batas_tanggal',
      'Tenggat',
      kind: SuratFieldKind.date,
      initial: data['batas_tanggal'],
    ),
    SuratField(
      'pemeriksa_pengguna_id',
      'Pemeriksa',
      kind: SuratFieldKind.choice,
      initial: suratMap(data['pemeriksa'])['id']?.toString(),
      options: {
        for (final p in suratItems(data['calon_pemeriksa']))
          '${p['id']}': suratText(p['nama']),
      },
    ),
    const SuratField(
      'catatan',
      'Alasan perubahan',
      kind: SuratFieldKind.multiline,
      maxLength: 2000,
    ),
  ],
  (values) async {
    await repo.send('tindak-lanjut-surat/${data['id']}/tindakan/ubah', {
      ...values,
      'versi': data['versi'],
    });
  },
);

Future<bool> suratDecision(
  BuildContext context,
  PersuratanRepository repo,
  int account,
  SuratData data,
  String path,
  String action,
  String title,
) => suratForm(
  context,
  account,
  title,
  [
    if (action == 'sahkan')
      const SuratField(
        'konfirmasi_berkas',
        'Saya telah memeriksa berkas final surat',
        kind: SuratFieldKind.confirmation,
      ),
    SuratField(
      'catatan',
      'Catatan',
      required: ['tarik', 'kembalikan', 'kirim', 'batal'].contains(action),
      kind: SuratFieldKind.multiline,
      maxLength: 2000,
    ),
  ],
  (values) async {
    await repo.send('$path/${data['id']}/tindakan/$action', {
      ...values,
      'versi': data['versi'],
    });
  },
  extra: (_) => Text(
    'Nomor surat: ${suratText(path == 'persuratan' ? data['nomor_surat'] : suratMap(data['surat'])['nomor_surat'])}',
  ),
);

class SuratRetainedFiles extends StatefulWidget {
  const SuratRetainedFiles({
    required this.files,
    required this.selected,
    required this.onChanged,
    required this.enabled,
    super.key,
  });
  final List<SuratData> files;
  final Set<int> selected;
  final ValueChanged<Set<int>> onChanged;
  final bool enabled;
  @override
  State<SuratRetainedFiles> createState() => _SuratRetainedFilesState();
}

class _SuratRetainedFilesState extends State<SuratRetainedFiles> {
  late Set<int> _selected = Set.of(widget.selected);
  @override
  void didUpdateWidget(covariant SuratRetainedFiles oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.selected, widget.selected)) {
      _selected = Set.of(widget.selected);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const SuratHeading('Berkas lama disertakan'),
      for (final f in widget.files)
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(suratText(f['nama'])),
          value: _selected.contains(suratInt(f['id'])),
          onChanged: !widget.enabled
              ? null
              : (v) {
                  setState(() {
                    if (v == true) {
                      _selected.add(suratInt(f['id']));
                    } else {
                      _selected.remove(suratInt(f['id']));
                    }
                  });
                  widget.onChanged(Set.of(_selected));
                },
        ),
    ],
  );
}
