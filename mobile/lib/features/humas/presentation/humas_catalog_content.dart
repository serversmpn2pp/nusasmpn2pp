import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_catalog_forms.dart';
import 'package:nusa/features/humas/presentation/humas_collection_view.dart';
import 'package:nusa/features/humas/presentation/humas_contact_publication_content.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';

const humasCatalogLabels = <String, String>{
  'kategori': 'Kategori',
  'jenis': 'Jenis',
  'topik': 'Topik',
  'tingkat': 'Tingkat',
  'perolehan': 'Perolehan',
  'penerima': 'Penerima',
  'bentuk': 'Bentuk',
  'tahun_lulus': 'Tahun lulus',
  'status_penelusuran': 'Penelusuran',
  'jenis_sekolah': 'Sekolah lanjutan',
  'deskripsi': 'Deskripsi',
  'kata_kunci': 'Kata kunci',
  'kredit': 'Kredit',
  'ketentuan_penggunaan': 'Ketentuan penggunaan',
  'identitas_akun': 'Nama akun',
  'penanggung_jawab': 'Penanggung jawab',
  'jabatan_penanggung_jawab': 'Jabatan',
  'tanggal_aset': 'Tanggal aset',
  'tanggal_diperiksa': 'Terakhir diperiksa',
  'tanggal_terbit': 'Tanggal terbit',
  'tanggal_prestasi': 'Tanggal prestasi',
  'nama_media': 'Media',
  'penulis': 'Penulis',
  'rujukan': 'Rujukan',
  'ringkasan': 'Ringkasan',
  'catatan': 'Catatan',
  'capaian': 'Capaian',
  'cabang': 'Cabang',
  'penyelenggara': 'Penyelenggara',
  'tempat': 'Tempat',
  'pembina': 'Pembina',
  'nama_tim': 'Tim',
  'tahun_pelajaran': 'Tahun pelajaran',
  'nis': 'NIS',
  'nisn': 'NISN',
  'jenis_kelamin': 'Jenis kelamin',
  'tahun_masuk': 'Tahun masuk',
  'tanggal_lulus': 'Tanggal lulus',
  'kelas_terakhir': 'Kelas terakhir',
  'nama_sekolah': 'Sekolah lanjutan',
  'kota_sekolah': 'Kota sekolah',
  'jurusan': 'Jurusan',
  'tanggal_penelusuran': 'Tanggal penelusuran',
  'nomor_wa': 'WhatsApp',
  'email': 'Email',
  'catatan_penelusuran': 'Catatan penelusuran',
};

class HumasCatalogContent extends ConsumerStatefulWidget {
  const HumasCatalogContent({
    required this.module,
    required this.data,
    required this.repository,
    required this.onChanged,
    super.key,
  });
  final HumasModule module;
  final HumasData data;
  final HumasRepository repository;
  final Future<void> Function() onChanged;
  @override
  ConsumerState<HumasCatalogContent> createState() => _CatalogContentState();
}

class _CatalogContentState extends ConsumerState<HumasCatalogContent> {
  bool _busy = false;
  String get base => '${widget.module.path}/${widget.data['id']}';
  Future<void> _run(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    await humasRun(context, action);
    if (mounted) setState(() => _busy = false);
  }

  void _history() => Navigator.push(
    context,
    MaterialPageRoute<void>(
      builder: (_) => HumasCollectionView(
        title: 'Riwayat perubahan',
        path: '$base/riwayat',
        repository: widget.repository,
        itemBuilder: (r, _) => HumasCard(
          title: humasText(r['aksi']),
          subtitle:
              '${humasDate(r['created_at'])} / ${humasText(r['pengguna'])}',
          children: [if (r['catatan'] != null) Text('${r['catatan']}')],
        ),
      ),
    ),
  );
  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).value?.session?.pengguna;
    if (!widget.module.canOpen(user)) return const SizedBox.shrink();
    final d = widget.data, rights = humasMap(d['hak_akses']);
    final manage =
        widget.module.canManage(user) && rights['dapat_kelola'] == true;
    final docs =
        rights['dapat_lihat_dokumen'] == true &&
        (user!.administrator ||
            user.izin.any(
              (p) =>
                  ['dokumen_humas.lihat', 'dokumen_humas.kelola'].contains(p),
            ));
    final file = docs ? humasMap(d['berkas']) : <String, dynamic>{};
    final uri = Uri.tryParse(humasText(d['tautan'], ''));
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        HumasCard(
          title: humasText(d['judul']),
          children: [
            const SizedBox(height: 12),
            HumasBadge(humasText(d['status_label'])),
            HumasFacts({
              for (final entry in humasCatalogLabels.entries)
                if (d[entry.key] != null &&
                    (manage ||
                        ![
                          'nomor_wa',
                          'email',
                          'catatan_penelusuran',
                        ].contains(entry.key)))
                  entry.value: entry.key.startsWith('tanggal_')
                      ? humasDate(d[entry.key])
                      : d['${entry.key}_label'] ?? d[entry.key],
            }),
          ],
        ),
        if (humasItems(d['peserta']).isNotEmpty)
          HumasCard(
            title: 'Penerima prestasi',
            children: [
              for (final p in humasItems(d['peserta']))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.person_outline),
                  title: Text(humasText(p['nama'])),
                  subtitle: p['kelas'] == null ? null : Text('${p['kelas']}'),
                ),
            ],
          ),
        if (file.isNotEmpty)
          HumasCard(
            title: 'Lampiran',
            children: [
              if ([
                'image/jpeg',
                'image/png',
                'image/webp',
              ].contains(file['tipe_file']))
                HumasPublicationPhoto(
                  key: ValueKey('$base/${file['id']}'),
                  repository: widget.repository,
                  path: '$base/berkas/${file['id']}',
                ),
              const SizedBox(height: 12),
              Text(humasText(file['nama_file_asli'])),
              OutlinedButton.icon(
                onPressed: _busy
                    ? null
                    : () => _run(() async {
                        await widget.repository.download(
                          '$base/berkas/${file['id']}?unduh=1',
                          humasText(file['nama_file_asli'], 'lampiran'),
                        );
                      }),
                icon: const Icon(Icons.download_outlined),
                label: const Text('Unduh lampiran'),
              ),
            ],
          ),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            if (manage)
              FilledButton.icon(
                onPressed: _busy
                    ? null
                    : () => _run(() async {
                        if (await editHumasCatalog(
                          context,
                          widget.repository,
                          widget.module.path,
                          record: d,
                        )) {
                          await widget.onChanged();
                        }
                      }),
                icon: const Icon(Icons.edit_outlined),
                label: const Text('Edit data'),
              ),
            OutlinedButton.icon(
              onPressed: _busy ? null : _history,
              icon: const Icon(Icons.history),
              label: const Text('Riwayat perubahan'),
            ),
            if (uri != null && ['http', 'https'].contains(uri.scheme))
              OutlinedButton.icon(
                onPressed: _busy
                    ? null
                    : () => _run(() async {
                        if (!await launchUrl(
                          uri,
                          mode: LaunchMode.externalApplication,
                        )) {
                          throw const NetworkException(
                            'Tautan belum dapat dibuka.',
                          );
                        }
                      }),
                icon: const Icon(Icons.open_in_new),
                label: const Text('Buka tautan'),
              ),
            if (widget.module == HumasModule.assets &&
                HumasModule.publications.canOpen(user) &&
                (user!.administrator ||
                    user.izin.any(
                      (p) => [
                        'publikasi_humas.lihat',
                        'publikasi_humas.kelola',
                        'publikasi_humas.periksa',
                      ].contains(p),
                    )))
              OutlinedButton.icon(
                onPressed: _busy
                    ? null
                    : () => Navigator.push(
                        context,
                        MaterialPageRoute<void>(
                          builder: (_) => HumasCollectionView(
                            title: 'Pemakaian aset',
                            path: '$base/pemakaian',
                            repository: widget.repository,
                            itemBuilder: (p, _) => HumasCard(
                              title: humasText(p['judul']),
                              subtitle: humasText(p['status']),
                              onTap: p['publikasi_id'] == null
                                  ? null
                                  : () => context.push(
                                      '/humas/publikasi/${p['publikasi_id']}',
                                    ),
                            ),
                          ),
                        ),
                      ),
                icon: const Icon(Icons.collections_bookmark_outlined),
                label: const Text('Pemakaian aset'),
              ),
          ],
        ),
        if (_busy)
          const Padding(
            padding: EdgeInsets.all(16),
            child: LinearProgressIndicator(),
          ),
      ],
    );
  }
}
