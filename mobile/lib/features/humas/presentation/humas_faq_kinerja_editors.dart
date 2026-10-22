import 'package:flutter/material.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_editors.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';

Future<bool> editFaqKinerja(
  BuildContext context,
  HumasRepository repository,
  HumasModule module, {
  HumasData? record,
  String userName = '',
}) async {
  final references = await repository.get('${module.path}/referensi');
  if (!context.mounted) return false;
  final data = record ?? <String, dynamic>{};
  final token = humasUuid();
  final faq = module == HumasModule.faq;
  final today = DateTime.now().toIso8601String().substring(0, 10);
  final fields = faq
      ? <HumasField>[
          HumasField(
            'pertanyaan',
            'Pertanyaan',
            maxLength: 240,
            initial: data['pertanyaan'],
          ),
          HumasField(
            'jawaban',
            'Jawaban',
            kind: HumasFieldKind.multiline,
            maxLength: 10000,
            initial: data['jawaban'],
          ),
          HumasField(
            'urusan',
            'Urusan',
            kind: HumasFieldKind.choice,
            options: humasOptions(references['urusan']),
            initial: data['urusan'],
          ),
          HumasField(
            'sasaran',
            'Pembaca',
            kind: HumasFieldKind.choice,
            options: humasOptions(references['sasaran']),
            initial: data['sasaran'] ?? 'semua',
          ),
          HumasField(
            'urutan',
            'Urutan tampil',
            kind: HumasFieldKind.number,
            maxLength: 3,
            initial: data['urutan'] ?? 0,
          ),
        ]
      : <HumasField>[
          HumasField(
            'judul',
            'Judul laporan',
            maxLength: 180,
            initial: data['judul'] ?? 'Laporan Kinerja Humas',
          ),
          if (record == null) ...[
            HumasField(
              'tahun_pelajaran_id',
              'Tahun pelajaran',
              kind: HumasFieldKind.choice,
              options: {
                for (final t in humasItems(references['tahun_pelajaran']))
                  '${t['id']}': humasText(t['nama']),
              },
              initial: humasItems(references['tahun_pelajaran'])
                  .where((t) => t['aktif'] == true)
                  .firstOrNull?['id']
                  ?.toString(),
            ),
            HumasField(
              'semester',
              'Semester',
              kind: HumasFieldKind.choice,
              options: humasOptions(references['semester']),
              initial: DateTime.now().month < 7 ? 'genap' : 'ganjil',
            ),
          ],
          HumasField(
            'tanggal_laporan',
            'Tanggal laporan',
            kind: HumasFieldKind.date,
            initial: data['tanggal_laporan'] ?? today,
          ),
          for (final e in const {
            'ringkasan': 'Ringkasan pelaksanaan',
            'capaian': 'Capaian utama',
            'kendala': 'Kendala',
            'tindak_lanjut': 'Tindak lanjut',
            'rekomendasi': 'Rekomendasi semester berikutnya',
          }.entries)
            HumasField(
              e.key,
              e.value,
              kind: HumasFieldKind.multiline,
              maxLength: 10000,
              required: false,
              initial: data[e.key],
            ),
          HumasField(
            'nama_penyusun',
            'Nama penyusun / Wakil Humas',
            maxLength: 180,
            initial: data['nama_penyusun'] ?? userName,
          ),
          HumasField(
            'nip_penyusun',
            'NIP penyusun',
            maxLength: 40,
            required: false,
            initial: data['nip_penyusun'],
          ),
          HumasField(
            'nama_pimpinan',
            'Nama pimpinan / Kepala Sekolah',
            maxLength: 180,
            required: false,
            initial: data['nama_pimpinan'],
          ),
          HumasField(
            'nip_pimpinan',
            'NIP pimpinan',
            maxLength: 40,
            required: false,
            initial: data['nip_pimpinan'],
          ),
        ];
  return openHumasForm(
    context,
    title: faq
        ? (record == null ? 'Tambah FAQ' : 'Edit FAQ')
        : (record == null ? 'Himpun Laporan Semester' : 'Edit Draf Laporan'),
    description: faq
        ? 'FAQ disimpan sebagai draf dan perlu diterbitkan kembali setelah perubahan.'
        : null,
    fields: [
      ...fields,
      if (record != null)
        const HumasField('alasan', 'Alasan perubahan', maxLength: 1000),
    ],
    onSave: (values) async {
      await repository.send(
        record == null ? module.path : '${module.path}/${data['id']}',
        {
          ...values,
          if (record == null)
            'token_pembuatan': token
          else
            'versi': data['versi'],
        },
        method: record == null ? 'POST' : 'PATCH',
      );
    },
  );
}
