import 'package:flutter/material.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_collection_view.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';

const humasFeedbackScale = {
  '1': 'Sangat tidak puas',
  '2': 'Kurang puas',
  '3': 'Puas',
  '4': 'Sangat puas',
  '0': 'Tidak menilai',
};

HumasData humasFeedbackAnswers(List<HumasData> questions, HumasData values) => {
  for (final question in questions)
    if (values['${question['id']}'] != null &&
        '${values['${question['id']}']}'.trim().isNotEmpty)
      '${question['id']}': question['jenis'] == 'skala'
          ? int.parse('${values['${question['id']}']}')
          : values['${question['id']}'],
};
List<HumasField> humasFeedbackFields(List<HumasData> questions) => [
  for (final question in questions)
    HumasField(
      '${question['id']}',
      'Jawaban ${question['urutan']}',
      prompt: '${question['urutan']}. ${humasText(question['teks'])}',
      required: question['wajib'] == true,
      kind: question['jenis'] == 'skala'
          ? HumasFieldKind.choice
          : HumasFieldKind.multiline,
      selectFirst: false,
      options: question['jenis'] == 'skala' ? humasFeedbackScale : const {},
      maxLength: 2000,
    ),
];

class HumasFeedbackContent extends StatelessWidget {
  const HumasFeedbackContent({
    required this.data,
    required this.repository,
    required this.manage,
    required this.parent,
    required this.onChanged,
    super.key,
  });
  final HumasData data;
  final HumasRepository repository;
  final bool manage, parent;
  final Future<void> Function() onChanged;
  String get path =>
      '${parent ? 'umpan-balik-saya' : 'umpan-balik'}/${data['id']}';
  List<HumasData> get questions => humasItems(data['pertanyaan']);
  Future<void> _answer(BuildContext context) => humasRun(context, () async {
    // The server-issued UUID is retained while the form is open, including retries.
    if (await openHumasForm(
      context,
      title: 'Isi Umpan Balik',
      description: 'Jawaban dikirim satu kali. Periksa kembali sebelum menyimpan. Pilih “Tidak menilai” jika tidak dapat menilai layanan tersebut.',
      fields: humasFeedbackFields(questions),
      onSave: (v) async {
        await repository.send(path, {
          'token_pengiriman': data['token_pengiriman'],
          'jawaban': humasFeedbackAnswers(questions, v),
        });
      },
    )) {
      await onChanged();
    }
  });
  Future<void> _status(BuildContext context) => humasRun(context, () async {
    final options = switch (data['status']) {
      'draf' => {'aktif': 'Buka formulir', 'arsip': 'Arsipkan'},
      'aktif' => {
        'ditutup': 'Tutup pengisian',
        'aktif': 'Perpanjang periode',
        'arsip': 'Arsipkan',
      },
      'ditutup' => {'aktif': 'Buka kembali', 'arsip': 'Arsipkan'},
      _ => <String, String>{},
    };
    if (await openHumasForm(
      context,
      title: 'Ubah Status Umpan Balik',
      fields: [
        HumasField(
          'status',
          'Tindakan',
          kind: HumasFieldKind.choice,
          options: options,
        ),
        const HumasField(
          'alasan',
          'Alasan',
          kind: HumasFieldKind.multiline,
          maxLength: 2000,
        ),
        const HumasField(
          'selesai_pada',
          'Batas waktu baru (untuk buka kembali / perpanjang)',
          kind: HumasFieldKind.dateTime,
          required: false,
        ),
      ],
      onSave: (v) async {
        await repository.send('$path/status', {
          ...v,
          'versi': data['versi'],
        }, method: 'PATCH');
      },
    )) {
      await onChanged();
    }
  });
  String _decimal(Object? v) => v is num ? v.toStringAsFixed(1) : '—';
  @override
  Widget build(BuildContext context) {
    final summary = humasMap(data['rekap']);
    final perQuestion = humasMap(summary['pertanyaan']);
    final answers = {
      for (final answer in humasItems(data['jawaban']))
        '${answer['pertanyaan_umpan_balik_humas_id']}': answer,
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        HumasCard(
          title: humasText(data['judul']),
          subtitle: humasText(data['pengantar']),
          children: [
            const SizedBox(height: 12),
            HumasBadge(humasText(data['status_label'])),
            HumasFacts({
              'Mulai': humasDate(data['mulai_pada']),
              'Batas pengisian': humasDate(data['selesai_pada']),
              if (parent && data['dikirim_pada'] != null)
                'Dikirim pada': humasDate(data['dikirim_pada']),
              if (!parent) 'Penanggung jawab': data['penanggung_jawab'],
            }),
          ],
        ),
        if (parent) ...[
          if (data['dapat_mengirim'] == true)
            FilledButton.icon(
              onPressed: () => _answer(context),
              icon: const Icon(Icons.rate_review_outlined),
              label: const Text('Isi umpan balik'),
            ),
          if (data['dikirim_pada'] != null)
            const HumasCard(
              title: 'Terima kasih atas masukan Anda',
              subtitle: 'Jawaban sudah diterima. Jawaban yang telah dikirim tidak dapat diubah.',
            ),
          for (final q in questions)
            HumasCard(
              title: '${q['urutan']}. ${humasText(q['teks'])}',
              subtitle: q['jenis'] == 'skala'
                  ? humasFeedbackScale['${answers['${q['id']}']?['nilai']}'] ??
                        'Belum diisi'
                  : humasText(answers['${q['id']}']?['teks'], 'Belum diisi'),
            ),
          for (final text in (data['ringkasan_publik'] as List? ?? []))
            HumasCard(title: 'Tindak Lanjut Sekolah', subtitle: '$text'),
        ] else ...[
          HumasCard(
            title: 'Ringkasan Respons',
            children: [
              HumasFacts({
                'Sasaran': summary['sasaran'],
                'Respons': summary['respons'],
                'Partisipasi': '${_decimal(summary['persen'])}%',
              }),
            ],
          ),
          for (final q in questions)
            HumasCard(
              title: '${q['urutan']}. ${humasText(q['teks'])}',
              children: [
                if (q['jenis'] == 'skala')
                  HumasFacts({
                    'Rata-rata': _decimal(
                      humasMap(perQuestion['${q['id']}'])['rata'],
                    ),
                    'Puas / sangat puas':
                        '${_decimal(humasMap(perQuestion['${q['id']}'])['puas'])}%',
                    'Jumlah yang menilai': humasMap(
                      perQuestion['${q['id']}'],
                    )['menilai'],
                  })
                else
                  TextButton.icon(
                    onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => HumasCollectionView(
                          title: 'Masukan Tertulis',
                          path: '$path/jawaban-teks/${q['id']}',
                          repository: repository,
                          itemBuilder: (item, reload) => HumasCard(
                            title: 'Masukan orang tua',
                            subtitle: humasText(item['teks']),
                          ),
                        ),
                      ),
                    ),
                    icon: const Icon(Icons.chat_outlined),
                    label: Text(
                      '${humasInt(humasMap(perQuestion['${q['id']}'])['teks'])} masukan tertulis',
                    ),
                  ),
              ],
            ),
          OutlinedButton.icon(
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute<void>(
                builder: (_) => HumasCollectionView(
                  title: 'Tindak Lanjut Umpan Balik',
                  path: '$path/tindak-lanjut',
                  repository: repository,
                  itemBuilder: (item, reload) => HumasCard(
                    title: humasText(item['uraian']),
                    subtitle:
                        '${humasText(item['penanggung_jawab'])} • ${humasDate(item['batas_tanggal'])}',
                    children: [
                      HumasFacts({
                        'Status': item['status'],
                        'Hasil': item['hasil'],
                        'Ringkasan publik': item['ringkasan_publik'],
                      }),
                    ],
                  ),
                ),
              ),
            ),
            icon: const Icon(Icons.task_alt),
            label: const Text('Lihat tindak lanjut'),
          ),
          if (manage && data['status'] != 'arsip')
            OutlinedButton(
              onPressed: () => _status(context),
              child: const Text('Kelola periode / status'),
            ),
          if (manage)
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text(
                'Penyusunan pertanyaan, sasaran evaluasi, dan pengelolaan tindak lanjut dilakukan melalui NUSA web.',
              ),
            ),
        ],
      ],
    );
  }
}
