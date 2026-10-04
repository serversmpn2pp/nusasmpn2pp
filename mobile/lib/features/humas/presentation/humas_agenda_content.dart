import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_collection_view.dart';
import 'package:nusa/features/humas/presentation/humas_editors.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/humas_scan_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';

const humasAttendanceOptions = {
  'belum_dicatat': 'Belum dicatat',
  'hadir': 'Hadir',
  'izin': 'Izin',
  'tidak_hadir': 'Tidak hadir',
};
const humasFollowUpStatuses = {
  'belum_mulai': 'Belum mulai',
  'diproses': 'Diproses',
  'selesai': 'Selesai',
};

class HumasAgendaContent extends ConsumerWidget {
  const HumasAgendaContent({
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
  HumasData get agenda => parent ? humasMap(data['agenda']) : data;
  String get path => 'agenda/${agenda['id']}';
  Future<void> _form(
    BuildContext context,
    String title,
    String suffix,
    List<HumasField> fields, {
    String method = 'PATCH',
  }) => humasRun(context, () async {
    if (await openHumasForm(
      context,
      title: title,
      fields: fields,
      onSave: (v) async {
        await repository.send('$path$suffix', v, method: method);
      },
    )) {
      await onChanged();
    }
  });
  Future<void> _participants(BuildContext context) async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (pageContext) => HumasCollectionView(
          title: 'Peserta & Presensi Pertemuan',
          path: '$path/peserta',
          repository: repository,
          onAdd: manage && agenda['status'] != 'dibatalkan'
              ? () => humasRun(pageContext, () async {
                  await openHumasForm(
                    pageContext,
                    title: 'Tambah Peserta',
                    fields: const [
                      HumasField('nama', 'Nama', maxLength: 180),
                      HumasField(
                        'instansi',
                        'Instansi',
                        required: false,
                        maxLength: 180,
                      ),
                      HumasField(
                        'peran',
                        'Peran',
                        required: false,
                        maxLength: 180,
                      ),
                    ],
                    onSave: (v) async {
                      await repository.send('$path/peserta', {
                        'peserta': [v],
                      });
                    },
                  );
                })
              : null,
          itemBuilder: (item, reload) => HumasCard(
            title: humasText(item['nama']),
            subtitle:
                '${humasText(item['instansi'])}\n${humasText(item['catatan'], '')}',
            children: [
              const SizedBox(height: 12),
              HumasBadge(humasText(item['status_label'])),
              if (manage && agenda['status'] != 'dibatalkan')
                TextButton.icon(
                  onPressed: () => humasRun(pageContext, () async {
                    if (await openHumasForm(
                      pageContext,
                      title: 'Catat Kehadiran',
                      description: humasText(item['nama']),
                      fields: [
                        HumasField(
                          'status_kehadiran',
                          'Kehadiran',
                          kind: HumasFieldKind.choice,
                          options: humasAttendanceOptions,
                          initial: item['status_kehadiran'],
                        ),
                        HumasField(
                          'catatan',
                          'Catatan',
                          kind: HumasFieldKind.multiline,
                          required: false,
                          initial: item['catatan'],
                        ),
                      ],
                      onSave: (v) async {
                        await repository.send('$path/presensi', {
                          'jumlah_baris': 1,
                          'kehadiran': [
                            {
                              ...v,
                              'id': item['id'],
                              'versi_presensi': item['versi_presensi'],
                            },
                          ],
                        }, method: 'PUT');
                      },
                    )) {
                      await reload();
                    }
                  }),
                  icon: const Icon(Icons.fact_check_outlined),
                  label: const Text('Catat kehadiran'),
                ),
            ],
          ),
        ),
      ),
    );
    await onChanged();
  }

  Future<void> _followUps(BuildContext context) async {
    final canEdit = manage && agenda['status'] != 'dibatalkan';
    Future<void> edit(BuildContext ctx, [HumasData item = const {}]) =>
        humasRun(ctx, () async {
          await openHumasForm(
            ctx,
            title: item.isEmpty ? 'Tambah Tindak Lanjut' : 'Ubah Tindak Lanjut',
            fields: [
              HumasField(
                'uraian',
                'Uraian',
                kind: HumasFieldKind.multiline,
                maxLength: 5000,
                initial: item['uraian'],
              ),
              HumasField(
                'penanggung_jawab',
                'Penanggung jawab',
                maxLength: 180,
                initial: item['penanggung_jawab'],
              ),
              HumasField(
                'batas_tanggal',
                'Batas tanggal',
                kind: HumasFieldKind.date,
                required: false,
                initial: item['batas_tanggal'],
              ),
              if (item.isNotEmpty) ...[
                HumasField(
                  'status',
                  'Status',
                  kind: HumasFieldKind.choice,
                  options: humasFollowUpStatuses,
                  initial: item['status'],
                ),
                HumasField(
                  'catatan',
                  'Catatan (wajib jika selesai)',
                  kind: HumasFieldKind.multiline,
                  required: false,
                  maxLength: 5000,
                  initial: item['catatan'],
                ),
              ],
            ],
            onSave: (v) async {
              await repository.send(
                '$path/tindak-lanjut${item.isEmpty ? '' : '/${item['id']}'}',
                v,
                method: item.isEmpty ? 'POST' : 'PATCH',
              );
            },
          );
        });
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (pageContext) => HumasCollectionView(
          title: 'Tindak Lanjut Pertemuan',
          path: '$path/tindak-lanjut',
          repository: repository,
          onAdd: canEdit ? () => edit(pageContext) : null,
          itemBuilder: (item, reload) => HumasCard(
            title: humasText(item['uraian']),
            subtitle:
                '${humasText(item['penanggung_jawab'])} • ${humasDate(item['batas_tanggal'])}\n${humasText(item['catatan'], '')}',
            onTap: canEdit
                ? () async {
                    await edit(pageContext, item);
                    await reload();
                  }
                : null,
            children: [
              HumasBadge(
                humasFollowUpStatuses[item['status']] ??
                    humasText(item['status']),
              ),
            ],
          ),
        ),
      ),
    );
    await onChanged();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).value?.session?.pengguna;
    final a = agenda;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        HumasCard(
          title: humasText(a['judul']),
          children: [
            const SizedBox(height: 12),
            HumasBadge(humasText(a['status_label'])),
            HumasFacts({
              'Mulai': humasDate(a['waktu_mulai']),
              'Selesai': humasDate(a['waktu_selesai']),
              'Tempat': a['tempat'],
              'Tautan pertemuan': a['tautan_pertemuan'],
              'Alasan pembatalan': a['alasan_pembatalan'],
              if (!parent) ...{
                'Jenis': a['jenis_label'],
                'Sasaran': a['sasaran'],
                'Pemimpin': a['pemimpin'],
                'Notulis': a['notulis'],
              },
            }),
          ],
        ),
        if (parent) ...[
          HumasCard(
            title: 'Kehadiran Saya',
            subtitle: humasText(humasMap(data['kehadiran'])['status_label']),
            children: [
              HumasFacts({
                'Dicatat pada':
                    humasMap(data['kehadiran'])['hadir_pada'] == null
                    ? null
                    : humasDate(humasMap(data['kehadiran'])['hadir_pada']),
              }),
            ],
          ),
          if (a['presensi_dibuka'] == true)
            FilledButton.icon(
              onPressed: () async {
                await Navigator.push(
                  context,
                  MaterialPageRoute<void>(
                    builder: (_) => const HumasScanView(),
                  ),
                );
                await onChanged();
              },
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Scan QR pertemuan'),
            ),
          const Text(
            'Presensi hanya untuk undangan akun Anda. Scan QR yang ditampilkan petugas Humas di lokasi pertemuan.',
          ),
        ] else ...[
          HumasCard(title: 'Topik', subtitle: humasText(a['topik'])),
          HumasCard(
            title: 'Notulen Pertemuan',
            children: [
              HumasFacts({
                'Pembahasan': a['pembahasan'],
                'Keputusan': a['keputusan'],
              }),
            ],
          ),
          HumasCard(
            title: 'Ringkasan Kehadiran',
            children: [
              HumasFacts(
                humasMap(a['rekap_presensi']).map(
                  (k, v) => MapEntry(
                    humasAttendanceOptions[k] ?? k.replaceAll('_', ' '),
                    v,
                  ),
                ),
              ),
            ],
          ),
          OutlinedButton.icon(
            onPressed: () => _participants(context),
            icon: const Icon(Icons.groups_outlined),
            label: const Text('Peserta & presensi'),
          ),
          OutlinedButton.icon(
            onPressed: () => _followUps(context),
            icon: const Icon(Icons.task_alt),
            label: const Text('Tindak lanjut'),
          ),
          if (HumasModule.documents.canOpen(user))
            OutlinedButton.icon(
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute<void>(
                  builder: (_) => HumasCollectionView(
                    title: 'Dokumen Pertemuan',
                    path: '$path/dokumen',
                    repository: repository,
                    itemBuilder: (item, reload) => HumasCard(
                      title: humasText(item['judul']),
                      subtitle: humasText(item['kategori_label']),
                      onTap: () => context.push('/humas/dokumen/${item['id']}'),
                    ),
                  ),
                ),
              ),
              icon: const Icon(Icons.folder_open),
              label: const Text('Dokumen terkait'),
            ),
          if (manage) ...[
            OutlinedButton.icon(
              onPressed: () =>
                  _form(context, 'Ubah Agenda', '', agendaFields(a)),
              icon: const Icon(Icons.edit_outlined),
              label: const Text('Ubah agenda'),
            ),
            if (a['status'] != 'dibatalkan')
              OutlinedButton.icon(
                onPressed: () => _form(context, 'Simpan Notulen', '/notulen', [
                  HumasField(
                    'pembahasan',
                    'Pembahasan',
                    kind: HumasFieldKind.multiline,
                    maxLength: 20000,
                    initial: a['pembahasan'],
                  ),
                  HumasField(
                    'keputusan',
                    'Keputusan',
                    kind: HumasFieldKind.multiline,
                    maxLength: 20000,
                    initial: a['keputusan'],
                  ),
                ], method: 'PUT'),
                icon: const Icon(Icons.edit_note),
                label: const Text('Catat notulen'),
              ),
            OutlinedButton(
              onPressed: () => _form(context, 'Status Pertemuan', '/status', [
                HumasField(
                  'status',
                  'Status',
                  kind: HumasFieldKind.choice,
                  options: const {
                    'terjadwal': 'Terjadwal',
                    'selesai': 'Selesai',
                    'dibatalkan': 'Dibatalkan',
                  },
                  initial: a['status'],
                ),
                const HumasField(
                  'alasan_pembatalan',
                  'Alasan (wajib jika dibatalkan)',
                  kind: HumasFieldKind.multiline,
                  maxLength: 2000,
                  required: false,
                ),
              ]),
              child: const Text('Ubah status pertemuan'),
            ),
            if (a['status'] == 'terjadwal')
              OutlinedButton(
                onPressed: () =>
                    _form(context, 'Presensi QR Pertemuan', '/akses-presensi', [
                      HumasField(
                        'dibuka',
                        'Buka presensi orang tua/wali',
                        kind: HumasFieldKind.boolean,
                        initial: a['presensi_dibuka'],
                      ),
                    ]),
                child: const Text('Atur presensi QR'),
              ),
            if (a['presensi_dibuka'] == true &&
                humasMap(a['qr'])['url'] != null)
              HumasCard(
                title: 'QR Presensi Pertemuan',
                subtitle: 'Tampilkan di lokasi pertemuan untuk orang tua yang diundang.',
                children: [
                  Center(
                    child: QrImageView(
                      data: humasMap(a['qr'])['url'] as String,
                      size: 200,
                      backgroundColor: Colors.white,
                    ),
                  ),
                ],
              ),
          ],
        ],
      ],
    );
  }
}
