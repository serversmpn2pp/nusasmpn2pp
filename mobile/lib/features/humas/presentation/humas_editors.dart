import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';

const humasAgendaTypes = {
  'orang_tua': 'Pertemuan orang tua/wali',
  'komite': 'Rapat komite sekolah',
  'koordinasi_eksternal': 'Koordinasi eksternal / dinas',
  'kemitraan': 'Pertemuan mitra sekolah',
  'internal': 'Koordinasi internal',
  'lainnya': 'Kegiatan Humas lainnya',
};
Map<String, String> humasOptions(Object? value) =>
    humasMap(value).map((k, v) => MapEntry(k, '$v'));

List<HumasField> agendaFields([HumasData data = const {}]) => [
  HumasField('judul', 'Judul agenda', maxLength: 180, initial: data['judul']),
  HumasField(
    'jenis',
    'Jenis pertemuan',
    kind: HumasFieldKind.choice,
    options: humasAgendaTypes,
    initial: data['jenis'],
  ),
  HumasField(
    'waktu_mulai',
    'Waktu mulai',
    kind: HumasFieldKind.dateTime,
    initial: data['waktu_mulai'],
  ),
  HumasField(
    'waktu_selesai',
    'Waktu selesai',
    kind: HumasFieldKind.dateTime,
    initial: data['waktu_selesai'],
  ),
  HumasField('tempat', 'Tempat', maxLength: 180, initial: data['tempat']),
  HumasField(
    'topik',
    'Topik pertemuan',
    kind: HumasFieldKind.multiline,
    maxLength: 10000,
    initial: data['topik'],
  ),
  HumasField(
    'sasaran',
    'Sasaran',
    required: false,
    maxLength: 250,
    initial: data['sasaran'],
  ),
  HumasField(
    'pemimpin',
    'Pemimpin',
    required: false,
    maxLength: 180,
    initial: data['pemimpin'],
  ),
  HumasField(
    'notulis',
    'Notulis',
    required: false,
    maxLength: 180,
    initial: data['notulis'],
  ),
  HumasField(
    'tautan_pertemuan',
    'Tautan pertemuan online',
    required: false,
    maxLength: 1000,
    initial: data['tautan_pertemuan'],
  ),
];

Future<bool> createHumasRecord(
  BuildContext context,
  HumasRepository repository,
  HumasModule module,
) async {
  try {
    if (module == HumasModule.agenda) {
      return await openHumasForm(
        context,
        title: 'Tambah Agenda',
        fields: agendaFields(),
        onSave: (data) async {
          await repository.send('agenda', data);
        },
      );
    }
    if (module == HumasModule.documents) {
      return await editHumasDocument(context, repository);
    }
    final references = await repository.get('${module.path}/referensi');
    if (!context.mounted) return false;
    final parent = module == HumasModule.myComplaints;
    final files = <HumasPickedFile>[];
    return await openHumasForm(
      context,
      title: parent ? 'Kirim Aspirasi / Pengaduan' : 'Catat Tiket',
      description: parent
          ? 'Sampaikan informasi yang jelas. Identitas dan lampiran hanya dapat diakses petugas yang berwenang.'
          : null,
      fields: [
        const HumasField('judul', 'Judul', maxLength: 180),
        HumasField(
          'jenis',
          'Jenis laporan',
          kind: HumasFieldKind.choice,
          options: humasOptions(references['jenis']),
        ),
        HumasField(
          'kategori',
          'Kategori',
          kind: HumasFieldKind.choice,
          options: humasOptions(references['kategori']),
        ),
        const HumasField(
          'isi',
          'Isi laporan',
          kind: HumasFieldKind.multiline,
          maxLength: 5000,
        ),
        if (parent)
          const HumasField(
            'rahasiakan_identitas',
            'Rahasiakan identitas dari petugas penanganan',
            kind: HumasFieldKind.boolean,
          ),
        if (!parent) ...[
          HumasField(
            'kanal',
            'Kanal laporan',
            kind: HumasFieldKind.choice,
            options: humasOptions(references['kanal']),
          ),
          HumasField(
            'tanggal_diterima',
            'Tanggal diterima',
            kind: HumasFieldKind.date,
            initial: DateTime.now().toIso8601String().substring(0, 10),
          ),
          HumasField(
            'prioritas',
            'Prioritas',
            kind: HumasFieldKind.choice,
            options: humasOptions(references['prioritas']),
          ),
          const HumasField(
            'anonim',
            'Pelapor anonim',
            kind: HumasFieldKind.boolean,
          ),
          const HumasField(
            'nama_pelapor',
            'Nama pelapor (wajib jika tidak anonim)',
            required: false,
            maxLength: 180,
          ),
          const HumasField(
            'kontak_pelapor',
            'Kontak pelapor',
            required: false,
            maxLength: 250,
          ),
        ],
      ],
      extra: HumasFilesField(
        maxFiles: 3,
        maxMb: 10,
        extensions: const ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
        onChanged: (picked) {
          files
            ..clear()
            ..addAll(picked);
        },
      ),
      onSave: (data) async {
        final payload = {
          ...data,
          'token_pembuatan': references['token_pembuatan'],
        };
        final form = FormData.fromMap(
          payload.map(
            (key, value) =>
                MapEntry(key, value is bool ? (value ? '1' : '0') : value),
          ),
        );
        for (final file in files) {
          form.files.add(
            MapEntry(
              'lampiran[]',
              MultipartFile.fromBytes(file.bytes, filename: file.name),
            ),
          );
        }
        await repository.send(module.path, payload, multipart: form);
      },
    );
  } catch (error) {
    if (context.mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(humasError(error))));
    }
    return false;
  }
}

Future<bool> editHumasDocument(
  BuildContext context,
  HumasRepository repository, {
  HumasData? document,
  bool revision = false,
}) async {
  final references = await repository.get('dokumen/referensi');
  if (!context.mounted) return false;
  final data = document ?? {};
  final files = <HumasPickedFile>[];
  final withFile = document == null || revision;
  return openHumasForm(
    context,
    title: document == null
        ? 'Unggah Dokumen'
        : revision
        ? 'Revisi Berkas'
        : 'Ubah Metadata Dokumen',
    fields: [
      HumasField('judul', 'Judul', maxLength: 180, initial: data['judul']),
      HumasField(
        'kategori',
        'Kategori',
        kind: HumasFieldKind.choice,
        options: humasOptions(references['kategori']),
        initial: data['kategori'],
      ),
      HumasField(
        'nomor_dokumen',
        'Nomor dokumen',
        required: false,
        maxLength: 120,
        initial: data['nomor_dokumen'],
      ),
      HumasField(
        'deskripsi',
        'Deskripsi',
        kind: HumasFieldKind.multiline,
        required: false,
        maxLength: 5000,
        initial: data['deskripsi'],
      ),
      HumasField(
        'berlaku_mulai',
        'Berlaku mulai',
        kind: HumasFieldKind.date,
        required: false,
        initial: data['berlaku_mulai'],
      ),
      HumasField(
        'berlaku_sampai',
        'Berlaku sampai',
        kind: HumasFieldKind.date,
        required: false,
        initial: data['berlaku_sampai'],
      ),
      HumasField(
        'ingatkan_hari_sebelum',
        'Pengingat (hari sebelum berakhir)',
        kind: HumasFieldKind.number,
        initial: data['ingatkan_hari_sebelum'] ?? 30,
      ),
      if (revision)
        const HumasField(
          'catatan_revisi',
          'Catatan revisi',
          kind: HumasFieldKind.multiline,
          maxLength: 1000,
        ),
    ],
    extra: withFile
        ? HumasFilesField(
            maxFiles: 1,
            maxMb: 20,
            extensions: (references['format_berkas'] as List)
                .map((v) => '$v')
                .toList(),
            onChanged: (picked) {
              files
                ..clear()
                ..addAll(picked);
            },
          )
        : null,
    onSave: (values) async {
      final payload = {
        ...values,
        if (document != null) 'sidik': document['sidik'],
      };
      if (withFile && files.isEmpty) {
        throw const ValidationException('Pilih berkas terlebih dahulu.');
      }
      final path = document == null
          ? 'dokumen'
          : 'dokumen/${document['id']}${revision ? '/revisi' : ''}';
      final form = withFile
          ? FormData.fromMap({
              ...payload,
              'berkas': MultipartFile.fromBytes(
                files.single.bytes,
                filename: files.single.name,
              ),
            })
          : null;
      await repository.send(
        path,
        payload,
        method: withFile ? 'POST' : 'PATCH',
        multipart: form,
      );
    },
  );
}

class HumasPickedFile {
  const HumasPickedFile(this.name, this.bytes);
  final String name;
  final Uint8List bytes;
}

class HumasFilesField extends StatefulWidget {
  const HumasFilesField({
    required this.maxFiles,
    required this.maxMb,
    required this.extensions,
    required this.onChanged,
    super.key,
  });
  final int maxFiles;
  final int maxMb;
  final List<String> extensions;
  final ValueChanged<List<HumasPickedFile>> onChanged;
  @override
  State<HumasFilesField> createState() => _HumasFilesFieldState();
}

class _HumasFilesFieldState extends State<HumasFilesField> {
  final _files = <HumasPickedFile>[];
  bool _picking = false;
  String? _error;
  Future<void> _pick() async {
    setState(() {
      _picking = true;
      _error = null;
    });
    try {
      final picked = widget.maxFiles == 1
          ? [
              ?await FilePicker.pickFile(
                type: FileType.custom,
                allowedExtensions: widget.extensions,
              ),
            ]
          : await FilePicker.pickFiles(
              type: FileType.custom,
              allowedExtensions: widget.extensions,
            );
      if (picked.length + _files.length > widget.maxFiles) {
        throw ValidationException('Maksimal ${widget.maxFiles} berkas.');
      }
      final additions = <HumasPickedFile>[];
      for (final file in picked) {
        if (await file.length() > widget.maxMb * 1024 * 1024) {
          throw ValidationException(
            'Berkas ${file.name} melebihi ${widget.maxMb} MB.',
          );
        }
        additions.add(HumasPickedFile(file.name, await file.readAsBytes()));
      }
      if (!mounted) return;
      setState(() {
        _files.addAll(additions);
      });
      widget.onChanged(List.unmodifiable(_files));
    } catch (error) {
      if (mounted) setState(() => _error = humasError(error));
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Lampiran • maksimal ${widget.maxFiles} berkas, ${widget.maxMb} MB/berkas',
        ),
        for (var i = 0; i < _files.length; i++)
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(_files[i].name),
            trailing: IconButton(
              icon: const Icon(Icons.close),
              onPressed: () {
                setState(() => _files.removeAt(i));
                widget.onChanged(List.unmodifiable(_files));
              },
            ),
          ),
        OutlinedButton.icon(
          onPressed: _picking || _files.length >= widget.maxFiles
              ? null
              : _pick,
          icon: const Icon(Icons.attach_file),
          label: Text(_picking ? 'Memilih…' : 'Pilih berkas'),
        ),
        if (_error != null)
          Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
      ],
    ),
  );
}
