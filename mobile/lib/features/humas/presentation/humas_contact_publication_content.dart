import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_collection_view.dart';
import 'package:nusa/features/humas/presentation/humas_contact_publication_editors.dart';
import 'package:nusa/features/humas/presentation/humas_editors.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';
import 'package:url_launcher/url_launcher.dart';

class HumasContactPublicationContent extends StatefulWidget {
  const HumasContactPublicationContent({
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
  State<HumasContactPublicationContent> createState() =>
      _HumasContactPublicationContentState();
}

class _HumasContactPublicationContentState
    extends State<HumasContactPublicationContent> {
  bool _busy = false;
  HumasData get data => widget.data;
  HumasData get rights => humasMap(data['hak_akses']);
  bool get contacts => widget.module == HumasModule.contacts;
  String get path => '${widget.module.path}/${data['id']}';

  Future<void> _run(Future<void> Function() work) async {
    if (_busy) return;
    setState(() => _busy = true);
    await humasRun(context, work);
    if (mounted) setState(() => _busy = false);
  }

  Future<void> _edit() => _run(() async {
    if (await editContactPublication(
      context,
      widget.repository,
      widget.module,
      record: data,
    )) {
      await widget.onChanged();
    }
  });

  Future<void> _launch(Uri uri) => _run(() async {
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      throw const NetworkException(
        'Aplikasi untuk membuka tautan ini tidak tersedia.',
      );
    }
  });

  Future<void> _action(String action, String label) => _run(() async {
    final files = <HumasPickedFile>[];
    var picking = false;
    final confirmation = ['setujui', 'tayang'].contains(action);
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => StatefulBuilder(
          builder: (formContext, setFormState) => HumasFormView(
            title: label,
            busy: picking,
            fields: [
              HumasField(
                'catatan',
                'Catatan pemeriksaan / perubahan',
                kind: HumasFieldKind.multiline,
                maxLength: 2000,
                required: ['minta-revisi', 'buka-revisi'].contains(action),
              ),
              if (action == 'tayang') ...[
                const HumasField(
                  'url_tayang',
                  'Tautan publikasi yang sudah tayang',
                  maxLength: 2000,
                ),
                HumasField(
                  'waktu_tayang',
                  'Waktu tayang',
                  kind: HumasFieldKind.dateTime,
                  initial: DateTime.now().toIso8601String().substring(0, 16),
                ),
              ],
              if (confirmation)
                HumasField(
                  'konfirmasi',
                  action == 'tayang'
                      ? 'Saya memastikan konten sudah ditayangkan'
                      : 'Saya telah memeriksa dan menyetujui konten',
                  kind: HumasFieldKind.boolean,
                ),
            ],
            extraBuilder: action != 'tayang'
                ? null
                : (context, saving) => HumasFilesField(
                    maxFiles: 1,
                    maxMb: 5,
                    extensions: const ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
                    enabled: !saving,
                    onBusyChanged: (value) {
                      if (formContext.mounted) {
                        setFormState(() => picking = value);
                      }
                    },
                    onChanged: (picked) {
                      files
                        ..clear()
                        ..addAll(picked);
                    },
                  ),
            onSave: (values) async {
              if (confirmation && values['konfirmasi'] != true) {
                throw const ValidationException(
                  'Konfirmasi pemeriksaan wajib dicentang.',
                );
              }
              final payload = <String, dynamic>{
                ...values..remove('konfirmasi'),
                'versi': data['versi'],
              };
              final form = FormData.fromMap(payload);
              if (files.isNotEmpty) {
                form.files.add(
                  MapEntry(
                    'bukti',
                    MultipartFile.fromBytes(
                      files.single.bytes,
                      filename: files.single.name,
                    ),
                  ),
                );
              }
              await widget.repository.send(
                '$path/tindakan/$action',
                payload,
                multipart: files.isEmpty ? null : form,
              );
            },
          ),
        ),
      ),
    );
    if (changed == true) await widget.onChanged();
  });

  Widget _button(
    String label,
    IconData icon,
    VoidCallback action, {
    bool primary = false,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: primary
        ? FilledButton.icon(
            onPressed: _busy ? null : action,
            icon: Icon(icon),
            label: Text(label),
          )
        : OutlinedButton.icon(
            onPressed: _busy ? null : action,
            icon: Icon(icon),
            label: Text(label),
          ),
  );

  Widget _history() => _button(
    'Riwayat perubahan',
    Icons.history_rounded,
    () => Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => HumasCollectionView(
          title: 'Riwayat Perubahan',
          path: '$path/riwayat',
          repository: widget.repository,
          itemBuilder: (item, reload) => HumasCard(
            title: humasText(item['aksi']),
            subtitle:
                '${humasText(item[contacts ? 'alasan' : 'catatan'])}\n${humasDate(item['created_at'])}',
            children: [
              HumasFacts({'Petugas': item['pengguna'], 'Versi': item['versi']}),
            ],
          ),
        ),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    if (contacts) {
      final private =
          data['kontak_terbatas'] != false ||
          rights['dapat_lihat_kontak'] != true;
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          HumasCard(
            title: humasText(data['nama']),
            subtitle: humasText(data['instansi']),
            trailing: const Icon(
              Icons.contact_page_rounded,
              color: NusaColors.primary,
            ),
            children: [
              const SizedBox(height: 12),
              HumasBadge(humasText(data['status_label'])),
              HumasFacts({
                'Jabatan': data['jabatan'],
                'Urusan': data['urusan_label'],
              }),
            ],
          ),
          if (private)
            const HumasCard(
              title: 'Kontak privat dibatasi',
              subtitle: 'Nomor telepon, email, dan catatan hanya tersedia bagi petugas berizin.',
            )
          else ...[
            HumasCard(
              title: 'Informasi kontak',
              children: [
                HumasFacts({
                  'Telepon / WhatsApp': data['nomor_kontak'],
                  'Email': data['email'],
                  'Dasar penggunaan': data['dasar_penggunaan'],
                  'Catatan privat': data['catatan'],
                }),
              ],
            ),
            if (humasText(data['nomor_kontak'], '').isNotEmpty)
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  IconButton(
                    tooltip: 'Telepon kontak',
                    onPressed: _busy
                        ? null
                        : () => _launch(
                            Uri(scheme: 'tel', path: '${data['nomor_kontak']}'),
                          ),
                    icon: const Icon(Icons.call_outlined),
                  ),
                  IconButton(
                    tooltip: 'Salin nomor kontak',
                    onPressed: () async {
                      await Clipboard.setData(
                        ClipboardData(text: '${data['nomor_kontak']}'),
                      );
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                            content: Text('Nomor kontak disalin.'),
                          ),
                        );
                      }
                    },
                    icon: const Icon(Icons.copy_outlined),
                  ),
                ],
              ),
            if (humasText(data['email'], '').isNotEmpty)
              _button(
                'Kirim email',
                Icons.email_outlined,
                () => _launch(Uri(scheme: 'mailto', path: '${data['email']}')),
              ),
            _history(),
          ],
          if (rights['dapat_kelola'] == true)
            _button('Edit kontak', Icons.edit_outlined, _edit),
        ],
      );
    }
    final url = Uri.tryParse(humasText(data['url_tayang'], ''));
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        HumasCard(
          title: humasText(data['judul']),
          subtitle:
              '${humasText(data['jenis_label'])} / ${humasText(data['kanal_label'])}',
          trailing: const Icon(
            Icons.campaign_rounded,
            color: NusaColors.primary,
          ),
          children: [
            const SizedBox(height: 12),
            HumasBadge(humasText(data['status_label'])),
            HumasFacts({
              'Pembuat': data['pembuat'],
              'Pemeriksa': data['pemeriksa'],
              'Rencana tayang': humasDate(data['rencana_tayang']),
              'Catatan pemeriksaan': data['catatan_pemeriksaan'],
              'Versi': humasInt(data['versi']) + 1,
            }),
          ],
        ),
        if (humasText(data['ringkasan'], '').isNotEmpty)
          HumasCard(title: 'Ringkasan', subtitle: '${data['ringkasan']}'),
        HumasCard(
          title: 'Naskah publikasi',
          children: [
            const SizedBox(height: 12),
            SelectableText(
              humasText(data['isi']),
              style: const TextStyle(height: 1.6),
            ),
          ],
        ),
        for (final file in humasItems(data['lampiran']))
          HumasCard(
            title: humasText(file['nama']),
            subtitle: file['jenis'] == 'foto'
                ? 'Foto publikasi'
                : 'Bukti tayang',
            children: [
              if (humasText(file['tipe_file'], '').startsWith('image/'))
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: HumasPublicationPhoto(
                    key: ValueKey('${data['id']}-${file['id']}'),
                    repository: widget.repository,
                    path: '$path/lampiran/${file['id']}',
                  ),
                ),
              _button(
                'Unduh lampiran',
                Icons.download_outlined,
                () => _run(() async {
                  await widget.repository.download(
                    '$path/lampiran/${file['id']}?unduh=1',
                    humasText(file['nama'], 'lampiran'),
                  );
                }),
              ),
            ],
          ),
        if (humasItems(data['aset']).isNotEmpty)
          HumasCard(
            title: 'Aset promosi terpakai',
            children: [
              for (final asset in humasItems(data['aset']))
                HumasFacts({
                  humasText(asset['nama']):
                      'Versi ${humasInt(asset['versi']) + 1}',
                }),
            ],
          ),
        if (url != null && ['http', 'https'].contains(url.scheme))
          _button(
            'Buka publikasi tayang',
            Icons.open_in_new_rounded,
            () => _launch(url),
          ),
        _button(
          'Unduh naskah',
          Icons.download_outlined,
          () => _run(() async {
            await widget.repository.download(
              '$path/naskah',
              'naskah-publikasi-${data['id']}.txt',
            );
          }),
        ),
        if (rights['dapat_edit'] == true)
          _button('Edit draf publikasi', Icons.edit_outlined, _edit),
        for (final entry in const {
          'dapat_ajukan': ('ajukan', 'Ajukan ke pimpinan', Icons.send_outlined),
          'dapat_tarik': ('tarik', 'Tarik pengajuan', Icons.undo_rounded),
          'dapat_periksa': (
            'setujui',
            'Setujui konten',
            Icons.verified_outlined,
          ),
          'dapat_buka_revisi': (
            'buka-revisi',
            'Buka revisi',
            Icons.edit_note_rounded,
          ),
          'dapat_tayang': (
            'tayang',
            'Catat bukti tayang',
            Icons.publish_rounded,
          ),
        }.entries)
          if (rights[entry.key] == true)
            _button(
              entry.value.$2,
              entry.value.$3,
              () => _action(entry.value.$1, entry.value.$2),
              primary: [
                'dapat_ajukan',
                'dapat_periksa',
                'dapat_tayang',
              ].contains(entry.key),
            ),
        if (rights['dapat_periksa'] == true)
          _button(
            'Minta revisi',
            Icons.rate_review_outlined,
            () => _action('minta-revisi', 'Minta Revisi'),
          ),
        _history(),
        if (_busy) const LinearProgressIndicator(),
      ],
    );
  }
}

class HumasPublicationPhoto extends StatefulWidget {
  const HumasPublicationPhoto({
    required this.repository,
    required this.path,
    super.key,
  });
  final HumasRepository repository;
  final String path;
  @override
  State<HumasPublicationPhoto> createState() => _HumasPublicationPhotoState();
}

class _HumasPublicationPhotoState extends State<HumasPublicationPhoto> {
  late Future<Uint8List> _photo;
  @override
  void initState() {
    super.initState();
    _photo = widget.repository.bytes(widget.path);
  }

  @override
  Widget build(BuildContext context) => AspectRatio(
    aspectRatio: 4 / 3,
    child: FutureBuilder<Uint8List>(
      future: _photo,
      builder: (context, state) {
        if (state.hasError) {
          return Center(
            child: IconButton(
              tooltip: 'Muat ulang foto',
              onPressed: () =>
                  setState(() => _photo = widget.repository.bytes(widget.path)),
              icon: const Icon(Icons.refresh),
            ),
          );
        }
        if (!state.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        return Image.memory(
          state.data!,
          fit: BoxFit.contain,
          cacheWidth: 1200,
          errorBuilder: (_, _, _) =>
              const Center(child: Icon(Icons.image_not_supported_outlined)),
        );
      },
    ),
  );
}
