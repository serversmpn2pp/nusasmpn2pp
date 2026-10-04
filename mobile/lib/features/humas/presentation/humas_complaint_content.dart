import 'package:flutter/material.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_collection_view.dart';
import 'package:nusa/features/humas/presentation/humas_editors.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';

class HumasComplaintContent extends StatelessWidget {
  const HumasComplaintContent({
    required this.data,
    required this.repository,
    required this.manage,
    required this.parent,
    required this.userId,
    required this.canHandle,
    required this.onChanged,
    super.key,
  });
  final HumasData data;
  final HumasRepository repository;
  final bool manage, parent, canHandle;
  final int userId;
  final Future<void> Function() onChanged;
  String get path => '${parent ? 'pengaduan-saya' : 'pengaduan'}/${data['id']}';
  bool get active => const [
    'baru',
    'ditugaskan',
    'diproses',
    'menunggu',
    'verifikasi',
  ].contains(data['status']);
  Map<String, String> get actions => {
    if ((manage ||
            (canHandle && humasInt(data['petugas_pengguna_id']) == userId)) &&
        const [
          'ditugaskan',
          'diproses',
          'menunggu',
        ].contains(data['status'])) ...{
      'proses': 'Mulai / lanjutkan penanganan',
      'menunggu': 'Menunggu informasi',
      'usulkan-selesai': 'Usulkan selesai',
    },
    if (manage && active) ...{
      'disposisi': 'Tugaskan petugas',
      if (data['petugas_pengguna_id'] != null) 'tarik': 'Tarik penugasan',
      'selesaikan': 'Sahkan selesai',
      'tutup': 'Tutup tiket',
    },
    if (manage &&
        const ['selesai', 'ditutup', 'verifikasi'].contains(data['status']))
      'buka-kembali': 'Buka kembali',
  };
  Future<void> _action(BuildContext context, String action, String title) =>
      humasRun(context, () async {
        final refs = action == 'disposisi'
            ? await repository.get('pengaduan/referensi')
            : <String, dynamic>{};
        if (!context.mounted) return;
        if (await openHumasForm(
          context,
          title: title,
          description: 'Tindakan dicatat dalam riwayat penanganan.',
          fields: [
            if (action == 'disposisi') ...[
              HumasField(
                'petugas_pengguna_id',
                'Petugas',
                kind: HumasFieldKind.choice,
                options: {
                  for (final p in humasItems(refs['petugas']))
                    '${p['id']}': humasText(p['nama']),
                },
              ),
              const HumasField(
                'batas_tanggal',
                'Batas penyelesaian',
                kind: HumasFieldKind.date,
              ),
            ],
            const HumasField(
              'catatan',
              'Alasan / catatan penanganan',
              kind: HumasFieldKind.multiline,
              maxLength: 3000,
            ),
          ],
          onSave: (v) async {
            await repository.send('$path/tindakan/$action', {
              ...v,
              'versi': data['versi'],
            });
          },
        )) {
          await onChanged();
        }
      });
  Future<void> _message(BuildContext context) => humasRun(context, () async {
    final token = humasUuid();
    if (await openHumasForm(
      context,
      title: parent ? 'Informasi Tambahan' : 'Balasan Resmi Humas',
      description: parent
          ? 'Informasi ini diteruskan kepada petugas Humas.'
          : 'Balasan ini dapat dibaca oleh orang tua pelapor.',
      fields: const [
        HumasField(
          'isi_pesan',
          'Pesan',
          kind: HumasFieldKind.multiline,
          maxLength: 3000,
        ),
      ],
      onSave: (v) async {
        await repository.send('$path/${parent ? 'informasi' : 'balasan'}', {
          ...v,
          'token_pengiriman': token,
          'versi': data['versi'],
        });
      },
    )) {
      await onChanged();
    }
  });
  Future<void> _edit(BuildContext context) => humasRun(context, () async {
    final refs = await repository.get('pengaduan/referensi');
    if (!context.mounted) return;
    final identity = humasMap(data['identitas_privat']);
    if (await openHumasForm(
      context,
      title: 'Koreksi Tiket',
      fields: [
        HumasField('judul', 'Judul', maxLength: 180, initial: data['judul']),
        HumasField(
          'jenis',
          'Jenis',
          kind: HumasFieldKind.choice,
          options: humasOptions(refs['jenis']),
          initial: data['jenis'],
        ),
        HumasField(
          'kategori',
          'Kategori',
          kind: HumasFieldKind.choice,
          options: humasOptions(refs['kategori']),
          initial: data['kategori'],
        ),
        HumasField(
          'kanal',
          'Kanal',
          kind: HumasFieldKind.choice,
          options: humasOptions(refs['kanal']),
          initial: data['kanal'],
        ),
        HumasField(
          'tanggal_diterima',
          'Tanggal diterima',
          kind: HumasFieldKind.date,
          initial: data['tanggal_diterima'],
        ),
        HumasField(
          'isi',
          'Isi',
          kind: HumasFieldKind.multiline,
          maxLength: 5000,
          initial: data['isi'],
        ),
        HumasField(
          'anonim',
          'Anonim',
          kind: HumasFieldKind.boolean,
          initial: identity['anonim'],
        ),
        HumasField(
          'nama_pelapor',
          'Nama pelapor (wajib jika tidak anonim)',
          required: false,
          maxLength: 180,
          initial: identity['nama'],
        ),
        HumasField(
          'kontak_pelapor',
          'Kontak',
          required: false,
          maxLength: 250,
          initial: identity['kontak'],
        ),
        HumasField(
          'prioritas',
          'Prioritas',
          kind: HumasFieldKind.choice,
          options: humasOptions(refs['prioritas']),
          initial: data['prioritas'],
        ),
        const HumasField(
          'catatan_perubahan',
          'Alasan koreksi',
          kind: HumasFieldKind.multiline,
          maxLength: 2000,
        ),
      ],
      onSave: (v) async {
        await repository.send(path, {
          ...v,
          'versi': data['versi'],
        }, method: 'PATCH');
      },
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
        subtitle: humasText(data['isi']),
        children: [
          const SizedBox(height: 12),
          HumasBadge(humasText(data['status_label'])),
          HumasFacts({
            'Nomor': data['nomor'],
            'Tanggal laporan': humasDate(data['tanggal_diterima']),
            'Jenis': data['jenis'],
            'Kategori': data['kategori'],
            if (!parent) ...{
              'Prioritas': data['prioritas'],
              'Batas penanganan': humasDate(data['batas_tanggal']),
              'Hasil penanganan': data['hasil_penanganan'],
            },
          }),
        ],
      ),
      if (manage && data.containsKey('identitas_privat'))
        HumasCard(
          title: 'Identitas Pelapor • Privat',
          children: [
            HumasFacts({
              'Nama': humasMap(data['identitas_privat'])['nama'],
              'Kontak': humasMap(data['identitas_privat'])['kontak'],
              'Anonim': humasMap(data['identitas_privat'])['anonim'] == true
                  ? 'Ya'
                  : 'Tidak',
            }),
            const SizedBox(height: 12),
            const Text(
              'Hanya petugas pengelola yang berwenang boleh mengakses identitas pelapor.',
            ),
          ],
        ),
      if (parent && data['rahasiakan_identitas'] == true)
        const HumasCard(
          title: 'Identitas dirahasiakan',
          subtitle: 'Identitas Anda tidak ditampilkan kepada petugas penanganan. Pengelola Humas tetap dapat memverifikasi laporan.',
        ),
      for (final file in humasItems(data['lampiran']))
        HumasCard(
          title: humasText(file['nama']),
          trailing: const Icon(Icons.download),
          onTap: () => humasRun(context, () async {
            await repository.download(
              '$path/lampiran/${file['id']}',
              humasText(file['nama'], 'lampiran.pdf'),
            );
          }),
        ),
      OutlinedButton.icon(
        onPressed: () => Navigator.push(
          context,
          MaterialPageRoute<void>(
            builder: (_) => HumasCollectionView(
              title: 'Komunikasi dengan Pelapor',
              path: '$path/pesan',
              repository: repository,
              itemBuilder: (item, reload) => HumasCard(
                title: item['asal'] == 'humas'
                    ? 'Balasan resmi Humas'
                    : 'Informasi orang tua',
                subtitle:
                    '${humasText(item['isi'])}\n${humasDate(item['created_at'])}',
              ),
            ),
          ),
        ),
        icon: const Icon(Icons.chat_bubble_outline),
        label: const Text('Riwayat komunikasi'),
      ),
      if ((parent && active) || (manage && data['kanal'] == 'akun_orang_tua'))
        FilledButton.icon(
          onPressed: () => _message(context),
          icon: const Icon(Icons.send_outlined),
          label: Text(
            parent ? 'Kirim informasi tambahan' : 'Kirim balasan resmi',
          ),
        ),
      if (!parent) ...[
        OutlinedButton.icon(
          onPressed: () => Navigator.push(
            context,
            MaterialPageRoute<void>(
              builder: (_) => HumasCollectionView(
                title: 'Riwayat Penanganan',
                path: '$path/riwayat',
                repository: repository,
                itemBuilder: (item, reload) => HumasCard(
                  title: humasText(item['aksi']),
                  subtitle:
                      '${humasText(item['catatan'])}\n${humasDate(item['created_at'])}',
                ),
              ),
            ),
          ),
          icon: const Icon(Icons.history),
          label: const Text('Riwayat penanganan'),
        ),
        if (manage && active && data['kanal'] != 'akun_orang_tua')
          OutlinedButton.icon(
            onPressed: () => _edit(context),
            icon: const Icon(Icons.edit_outlined),
            label: const Text('Koreksi tiket'),
          ),
        for (final entry in actions.entries)
          OutlinedButton(
            onPressed: () => _action(context, entry.key, entry.value),
            child: Text(entry.value),
          ),
      ],
    ],
  );
}
