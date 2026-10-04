import 'package:flutter/material.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_collection_view.dart';
import 'package:nusa/features/humas/presentation/humas_editors.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';

class HumasDocumentContent extends StatelessWidget {
  const HumasDocumentContent({
    required this.data,
    required this.repository,
    required this.manage,
    required this.onChanged,
    super.key,
  });
  final HumasData data;
  final HumasRepository repository;
  final bool manage;
  final Future<void> Function() onChanged;
  String get path => 'dokumen/${data['id']}';
  Future<void> _edit(BuildContext context, {bool revision = false}) =>
      humasRun(context, () async {
        if (await editHumasDocument(
          context,
          repository,
          document: data,
          revision: revision,
        )) {
          await onChanged();
        }
      });
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      HumasCard(
        title: humasText(data['judul']),
        subtitle: humasText(data['deskripsi'], ''),
        children: [
          const SizedBox(height: 12),
          HumasBadge(humasText(data['status_label'])),
          HumasFacts({
            'Kategori': data['kategori_label'],
            'Nomor': data['nomor_dokumen'],
            'Mulai berlaku': humasDate(data['berlaku_mulai']),
            'Berlaku sampai': humasDate(data['berlaku_sampai']),
            'Versi berkas': data['versi_berkas'],
            'Berkas': humasMap(data['berkas'])['nama'],
          }),
        ],
      ),
      FilledButton.icon(
        onPressed: () => humasRun(context, () async {
          final saved = await repository.download(
            '$path/unduh',
            humasText(humasMap(data['berkas'])['nama'], 'dokumen.pdf'),
          );
          if (saved && context.mounted) {
            ScaffoldMessenger.of(
              context,
            ).showSnackBar(const SnackBar(content: Text('Berkas disimpan.')));
          }
        }),
        icon: const Icon(Icons.download),
        label: const Text('Unduh berkas'),
      ),
      OutlinedButton.icon(
        onPressed: () => Navigator.push(
          context,
          MaterialPageRoute<void>(
            builder: (pageContext) => HumasCollectionView(
              title: 'Riwayat Versi Dokumen',
              path: '$path/riwayat',
              repository: repository,
              itemBuilder: (item, reload) => HumasCard(
                title: 'Versi ${item['versi']}',
                subtitle:
                    '${humasText(item['catatan'])}\n${humasDate(item['diunggah_pada'])}',
                onTap: () => humasRun(pageContext, () async {
                  await repository.download(
                    '$path/riwayat/${item['id']}/unduh',
                    humasText(item['nama'], 'dokumen.pdf'),
                  );
                }),
                trailing: const Icon(Icons.download),
              ),
            ),
          ),
        ),
        icon: const Icon(Icons.history),
        label: const Text('Riwayat versi'),
      ),
      if (manage) ...[
        OutlinedButton.icon(
          onPressed: () => _edit(context),
          icon: const Icon(Icons.edit_outlined),
          label: const Text('Ubah informasi dokumen'),
        ),
        OutlinedButton.icon(
          onPressed: () => _edit(context, revision: true),
          icon: const Icon(Icons.upload_file),
          label: const Text('Unggah revisi berkas'),
        ),
        OutlinedButton(
          onPressed: () => humasRun(context, () async {
            if (await openHumasForm(
              context,
              title: 'Ubah Status Dokumen',
              fields: [
                HumasField(
                  'status',
                  'Status',
                  kind: HumasFieldKind.choice,
                  options: const {'aktif': 'Aktif', 'arsip': 'Arsip'},
                  initial: data['status'],
                ),
              ],
              onSave: (v) async {
                await repository.send('$path/status', {
                  ...v,
                  'sidik': data['sidik'],
                }, method: 'PATCH');
              },
            )) {
              await onChanged();
            }
          }),
          child: const Text('Ubah status'),
        ),
      ],
    ],
  );
}
