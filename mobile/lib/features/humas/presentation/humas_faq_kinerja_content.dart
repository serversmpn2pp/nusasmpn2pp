import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_collection_view.dart';
import 'package:nusa/features/humas/presentation/humas_faq_kinerja_editors.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';

class HumasFaqKinerjaContent extends StatelessWidget {
  const HumasFaqKinerjaContent({
    required this.module,
    required this.data,
    required this.repository,
    required this.manage,
    required this.onChanged,
    super.key,
  });
  final HumasModule module;
  final HumasData data;
  final HumasRepository repository;
  final bool manage;
  final Future<void> Function() onChanged;
  bool get faq => module == HumasModule.faq;
  bool get canManage => manage && data['dapat_kelola'] == true;
  String get path => '${module.path}/${humasInt(data['id'])}';

  Future<void> _edit(BuildContext context) => humasRun(context, () async {
    if (await editFaqKinerja(context, repository, module, record: data)) {
      await onChanged();
    }
  });

  Future<void> _action(BuildContext context, String action, String label) =>
      humasRun(context, () async {
        final snapshot = humasMap(data['snapshot']);
        if (await openHumasForm(
          context,
          title: label,
          fields: [
            const HumasField(
              'alasan',
              'Alasan / catatan pemeriksaan',
              maxLength: 1000,
            ),
            if (!faq && action == 'finalisasi' && snapshot['sementara'] == true)
              const HumasField(
                'konfirmasi_sementara',
                'Saya memahami laporan masih sementara dan belum mencakup akhir semester',
                kind: HumasFieldKind.boolean,
              ),
          ],
          onSave: (v) async {
            await repository.send(
              faq ? '$path/status' : '$path/tindakan/$action',
              {...v, 'versi': data['versi'], if (faq) 'status': action},
              method: faq ? 'PATCH' : 'POST',
            );
          },
        )) {
          await onChanged();
        }
      });

  Widget _history(BuildContext context) => OutlinedButton.icon(
    icon: const Icon(Icons.history_rounded),
    label: const Text('Riwayat perubahan'),
    onPressed: () => Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => HumasCollectionView(
          title: 'Riwayat Perubahan',
          path: '$path/riwayat',
          repository: repository,
          itemBuilder: (a, reload) => HumasCard(
            title:
                '${humasText(a['aksi']).replaceAll('_', ' ').replaceAll('-', ' ')} / versi ${a['versi']}',
            subtitle:
                '${humasText(a['alasan'])}\n${humasDate(a['created_at'])}',
          ),
        ),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    if (faq) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          HumasCard(
            title: humasText(data['pertanyaan']),
            subtitle: humasText(data['urusan_label']),
            children: [
              const SizedBox(height: 12),
              SelectableText(
                humasText(data['jawaban']),
                style: const TextStyle(height: 1.6),
              ),
              if (canManage) ...[
                const SizedBox(height: 12),
                HumasBadge(humasText(data['status_label'])),
                HumasFacts({
                  'Pembaca': data['sasaran_label'],
                  'Versi': data['versi'],
                }),
              ],
            ],
          ),
          if (canManage) ...[
            OutlinedButton.icon(
              onPressed: () => _edit(context),
              icon: const Icon(Icons.edit_outlined),
              label: const Text('Edit FAQ'),
            ),
            if (data['status'] == 'draf')
              FilledButton.icon(
                onPressed: () => _action(context, 'terbit', 'Terbitkan FAQ'),
                icon: const Icon(Icons.publish_rounded),
                label: const Text('Terbitkan FAQ'),
              ),
            if (data['status'] != 'draf')
              OutlinedButton(
                onPressed: () => _action(context, 'draf', 'Kembalikan ke Draf'),
                child: const Text('Kembalikan ke draf'),
              ),
            if (data['status'] != 'arsip')
              OutlinedButton(
                onPressed: () => _action(context, 'arsip', 'Arsipkan FAQ'),
                child: const Text('Arsipkan FAQ'),
              ),
            _history(context),
          ],
        ],
      );
    }
    final snapshot = humasMap(data['snapshot']);
    final metrics = humasMap(snapshot['metrik']);
    final columns = humasMap(snapshot['kolom_bulanan']);
    final months = humasMap(snapshot['bulan']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        HumasCard(
          title: humasText(data['judul']),
          subtitle:
              '${humasText(data['tahun_pelajaran'])} / ${humasText(data['semester_label'])}',
          children: [
            const SizedBox(height: 12),
            HumasBadge(humasText(data['status_label'])),
            HumasFacts({
              'Data s.d.': humasDate(snapshot['data_sampai']),
              'Dihimpun': humasDate(snapshot['dihimpun_pada']),
              'Tanggal laporan': humasDate(data['tanggal_laporan']),
              'Versi': data['versi'],
            }),
          ],
        ),
        if (snapshot['sementara'] == true)
          const _ReportBand(
            title: 'Laporan sementara',
            color: Color(0xFFFFF8DC),
            child: Text(
              'Rekap belum mencakup akhir semester. Capaian mengikuti batas data yang tercantum.',
            ),
          ),
        if (snapshot['akses_dibatasi'] == true)
          const _ReportBand(
            title: 'Akses sumber terbatas',
            color: Color(0xFFFFF8DC),
            child: Text(
              'Sebagian indikator dan program tidak ditampilkan sesuai izin akun. Laporan tidak dapat diubah.',
            ),
          ),
        for (final m in metrics.values)
          HumasCard(
            title: humasText(humasMap(m)['label']),
            trailing: Text(
              '${humasInt(humasMap(m)['jumlah'])}',
              style: const TextStyle(
                fontSize: 25,
                fontWeight: FontWeight.bold,
                color: NusaColors.primary,
              ),
            ),
            subtitle: humasText(humasMap(m)['dasar']),
          ),
        const _ReportBand(
          title: 'Realisasi program kerja',
          child: Text(
            'Target mengikuti keseluruhan program, termasuk program tahunan. Kegiatan final hanya dihitung dalam rentang data laporan.',
          ),
        ),
        for (final p in humasItems(snapshot['program']))
          HumasCard(
            title: humasText(p['nama']),
            subtitle: '${humasText(p['semester'])} / ${humasText(p['status'])}',
            children: [
              HumasFacts({
                'Target program': p['target'],
                'Kegiatan final': p['realisasi'],
              }),
            ],
          ),
        if (columns.isNotEmpty && months.isNotEmpty)
          _ReportBand(
            title: 'Rekap bulanan',
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: DataTable(
                columns: [
                  const DataColumn(label: Text('Bulan')),
                  for (final c in columns.values)
                    DataColumn(label: Text('$c'), numeric: true),
                ],
                rows: [
                  for (final b in months.values)
                    DataRow(
                      cells: [
                        DataCell(Text(humasText(humasMap(b)['label']))),
                        for (final k in columns.keys)
                          DataCell(
                            Text(
                              '${humasInt(humasMap(humasMap(b)['jumlah'])[k])}',
                            ),
                          ),
                      ],
                    ),
                ],
              ),
            ),
          ),
        for (final e in const {
          'ringkasan': 'Ringkasan pelaksanaan',
          'capaian': 'Capaian utama',
          'kendala': 'Kendala',
          'tindak_lanjut': 'Tindak lanjut',
          'rekomendasi': 'Rekomendasi semester berikutnya',
        }.entries)
          _ReportBand(
            title: e.value,
            child: SelectableText(
              humasText(data[e.key], 'Belum diisi.'),
              style: const TextStyle(height: 1.6),
            ),
          ),
        HumasCard(
          title: 'Penandatangan laporan',
          children: [
            HumasFacts({
              'Penyusun': data['nama_penyusun'],
              'NIP penyusun': data['nip_penyusun'],
              'Pimpinan': data['nama_pimpinan'],
              'NIP pimpinan': data['nip_pimpinan'],
            }),
          ],
        ),
        OutlinedButton.icon(
          onPressed: () => humasRun(context, () async {
            final saved = await repository.download(
              '$path/cetak',
              'laporan-kinerja-humas-${data['id']}.html',
            );
            if (saved && context.mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Lembar cetak HTML disimpan.')),
              );
            }
          }),
          icon: const Icon(Icons.download_rounded),
          label: const Text('Unduh lembar cetak (HTML)'),
        ),
        if (canManage) ...[
          if (data['status'] == 'draf') ...[
            OutlinedButton.icon(
              onPressed: () => _edit(context),
              icon: const Icon(Icons.edit_outlined),
              label: const Text('Edit draf laporan'),
            ),
            OutlinedButton.icon(
              onPressed: () =>
                  _action(context, 'perbarui-data', 'Perbarui Rekap Sumber'),
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Perbarui rekap sumber'),
            ),
            FilledButton.icon(
              onPressed: () =>
                  _action(context, 'finalisasi', 'Finalisasi Laporan'),
              icon: const Icon(Icons.lock_outline_rounded),
              label: const Text('Finalisasi laporan'),
            ),
          ] else
            OutlinedButton.icon(
              onPressed: () => _action(context, 'buka-revisi', 'Buka Revisi'),
              icon: const Icon(Icons.lock_open_rounded),
              label: const Text('Buka revisi'),
            ),
        ],
        _history(context),
      ],
    );
  }
}

class _ReportBand extends StatelessWidget {
  const _ReportBand({
    required this.title,
    required this.child,
    this.color = const Color(0xFFE8F4F0),
  });
  final String title;
  final Widget child;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 16),
    padding: const EdgeInsets.all(16),
    color: color,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.bold,
            color: NusaColors.primary,
          ),
        ),
        const SizedBox(height: 12),
        child,
      ],
    ),
  );
}
