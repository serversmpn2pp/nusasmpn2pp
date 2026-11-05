import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

import '../data/tahfidz_repository.dart';
import '../domain/tahfidz.dart';
import 'tahfidz_document.dart';
import 'tahfidz_forms.dart';
import 'tahfidz_widgets.dart';
import 'tahfidz_surah_view.dart';

class TahfidzView extends ConsumerStatefulWidget {
  const TahfidzView({this.reports = false, super.key});
  final bool reports;
  @override
  ConsumerState<TahfidzView> createState() => _TahfidzViewState();
}

class _TahfidzViewState extends ConsumerState<TahfidzView> {
  int? _year;
  String _status = '', _search = '';
  @override
  Widget build(BuildContext context) => TfPage(
    title: widget.reports ? 'Rapor Tahfidz' : 'Tahfidz',
    path: 'tahfidz',
    query: {
      'tahun': ?_year,
      if (_status.isNotEmpty) 'status': _status,
      if (_search.isNotEmpty) 'cari': _search,
    },
    content: (context, ref, data, reload) => [
      TfHero(
        widget.reports ? 'Rapor Tahfidz' : 'Tumbuh bersama Al-Qur’an',
        widget.reports
            ? 'Pilih kelompok dan siswa untuk meninjau rapor Tahfidz.'
            : 'Kelompok, pertemuan, dan perkembangan hafalan Juz 30.',
      ),
      NusaDropdownField<int>(
        fieldKey: const Key('tahfidz-year'),
        value: _year ?? tfInt(data['tahun_id']),
        options: [
          for (final y in tfItems(data['tahun']))
            NusaDropdownOption(value: tfInt(y['id']), label: tfText(y['nama'])),
        ],
        decoration: const InputDecoration(labelText: 'Tahun pelajaran'),
        onChanged: (v) => setState(() => _year = v),
      ),
      const SizedBox(height: 10),
      NusaDropdownField<String>(
        fieldKey: const Key('tahfidz-status'),
        value: _status,
        options: const [
          NusaDropdownOption(value: '', label: 'Semua kelompok'),
          NusaDropdownOption(value: 'aktif', label: 'Aktif'),
          NusaDropdownOption(value: 'nonaktif', label: 'Nonaktif'),
        ],
        decoration: const InputDecoration(labelText: 'Status'),
        onChanged: (v) => setState(() => _status = v ?? ''),
      ),
      const SizedBox(height: 10),
      TextField(
        decoration: const InputDecoration(
          hintText: 'Cari kelompok',
          prefixIcon: Icon(Icons.search),
        ),
        onSubmitted: (v) => setState(() => _search = v.trim()),
      ),
      Wrap(
        spacing: 8,
        children: [
          if (data['kelola'] == true)
            TfAction('Tambah Kelompok', () async {
              await _groupForm(context, ref, reload);
            }, icon: Icons.add),
          TfAction(
            'Daftar Surah',
            () => tfOpen(context, const TahfidzSurahView()),
            icon: Icons.menu_book_outlined,
            prominent: true,
            subtitle: 'Baca 37 surah lengkap dengan ayat Arab.',
            key: const Key('tahfidz-surah-list'),
          ),
          if (data['kelola'] == true) ...[
            TfAction(
              'Target Rapor',
              () => tfOpen(context, const TahfidzTargetView()),
              icon: Icons.flag_outlined,
            ),
            TfAction(
              'Wisuda',
              () => tfOpen(context, const TahfidzWisudaView()),
              icon: Icons.workspace_premium_outlined,
            ),
          ],
        ],
      ),
      if (tfItems(data['items']).isEmpty)
        const TfNotice('Belum ada kelompok pada pilihan ini.'),
      for (final g in tfItems(data['items']))
        TfCard(
          tfText(g['nama']),
          '${tfText(g['pembimbing'])}\n${tfText(g['jumlah_anggota'])} siswa · ${g['aktif'] == true ? 'Aktif' : 'Nonaktif'}',
          onTap: () async {
            await tfOpen(
              context,
              TfGroupView(tfInt(g['id']), reports: widget.reports),
            );
            await reload();
          },
        ),
    ],
  );
}

Future<void> _groupForm(
  BuildContext context,
  WidgetRef ref,
  TfReload reload, [
  TfData? group,
]) async {
  final reference = await ref
      .read(tahfidzRepositoryProvider)
      .get('tahfidz/referensi');
  if (!context.mounted) return;
  final teachers = tfItems(reference['pembimbing']);
  if (group != null &&
      !teachers.any((t) => t['id'] == group['pembimbing_id'])) {
    teachers.add({
      'id': group['pembimbing_id'],
      'nama_lengkap': group['pembimbing'],
    });
  }
  final saved = await tfEdit(
    context,
    title: group == null ? 'Tambah Kelompok Tahfidz' : 'Edit Kelompok Tahfidz',
    path: group == null ? 'tahfidz' : 'tahfidz/${group['id']}',
    method: group == null ? 'POST' : 'PUT',
    hidden: {if (group != null) 'versi': group['versi']},
    fields: [
      TfField('nama', 'Nama kelompok', value: group?['nama']),
      if (group == null)
        TfField(
          'tahun_pelajaran_id',
          'Tahun pelajaran',
          value: tfItems(reference['tahun']).firstOrNull?['id'],
          kind: 'choice',
          options: tfItems(reference['tahun']),
        ),
      TfField(
        'pembimbing_pegawai_id',
        'Guru pembimbing',
        value: group?['pembimbing_id'],
        kind: 'choice',
        options: [
          for (final p in teachers) {'id': p['id'], 'nama': p['nama_lengkap']},
        ],
      ),
      TfField(
        'aktif',
        'Kelompok aktif',
        kind: 'toggle',
        value: group?['aktif'] ?? true,
      ),
    ],
  );
  if (saved != null) await reload();
}

class TfGroupView extends StatelessWidget {
  const TfGroupView(this.id, {this.reports = false, super.key});
  final int id;
  final bool reports;
  @override
  Widget build(BuildContext context) => TfPage(
    title: 'Kelompok Tahfidz',
    path: 'tahfidz/$id',
    content: (context, ref, data, reload) {
      final g = tfMap(data['kelompok']), summary = tfMap(data['ringkasan']);
      return [
        TfHero(
          tfText(g['nama']),
          '${tfText(g['pembimbing'])}\n${tfText(g['tahun'])} · ${g['aktif'] == true ? 'Aktif' : 'Nonaktif'}',
        ),
        Wrap(
          spacing: 8,
          children: [
            if (data['kelola'] == true)
              TfAction(
                'Edit Kelompok',
                () => _groupForm(context, ref, reload, g),
                icon: Icons.edit_outlined,
              ),
            if (data['kelola'] == true && g['aktif'] == true)
              TfAction('Atur Anggota', () async {
                await tfOpen(context, TfMembersView(id));
                await reload();
              }, icon: Icons.group_add_outlined),
            if (data['catat'] == true)
              TfAction(
                'Buka Pertemuan',
                () async {
                  final result = await tfEdit(
                    context,
                    title: 'Buka Pertemuan',
                    path: 'tahfidz/$id/pertemuan',
                    fields: [
                      TfField(
                        'tanggal',
                        'Tanggal pertemuan',
                        kind: 'date',
                        value: DateTime.now()
                            .toIso8601String()
                            .split('T')
                            .first,
                      ),
                    ],
                  );
                  if (result != null && context.mounted) {
                    await tfOpen(
                      context,
                      TfMeetingView(id, tfInt(result['id'])),
                    );
                    await reload();
                  }
                },
                icon: Icons.add,
                prominent: true,
                subtitle: 'Mulai pencatatan setoran hafalan siswa.',
                key: const Key('tahfidz-open-meeting'),
              ),
            TfAction(
              'Daftar Surah',
              () => tfOpen(context, const TahfidzSurahView()),
              icon: Icons.menu_book_outlined,
              prominent: true,
              subtitle: 'Buka ayat Arab untuk memeriksa hafalan.',
              key: const Key('tahfidz-surah-list'),
            ),
          ],
        ),
        TfNotice(
          '${summary['mulai'] ?? 0} siswa sudah mulai · ${summary['lengkap'] ?? 0} siswa menyetor ${summary['target'] ?? 37} surah',
        ),
        const TfSection('Anggota & Perkembangan'),
        if (tfItems(data['anggota']).isEmpty)
          const TfNotice(
            'Belum ada anggota. Koordinator dapat mengatur anggota kelompok ini.',
          ),
        for (final a in tfItems(data['anggota']))
          TfCard(
            tfText(a['nama']),
            '${tfText(a['kelas'])} · ${a['sudah']} / ${summary['target']} surah\n${a['setoran']} setoran',
            icon: Icons.school_outlined,
            onTap: () async {
              await tfOpen(
                context,
                TfStudentView(id, tfInt(a['id']), reports: reports),
              );
              await reload();
            },
          ),
        const TfSection('Pertemuan'),
        if (tfItems(data['pertemuan']).isEmpty)
          const TfNotice('Belum ada pertemuan Tahfidz.'),
        for (final p in tfItems(data['pertemuan']))
          TfCard(
            tfDate(p['tanggal']),
            '${p['jumlah_setoran']} setoran · ${p['selesai'] == true ? 'Selesai' : 'Masih terbuka'}',
            icon: Icons.event_note_rounded,
            onTap: () async {
              await tfOpen(context, TfMeetingView(id, tfInt(p['id'])));
              await reload();
            },
          ),
      ];
    },
  );
}

class TfMeetingView extends StatelessWidget {
  const TfMeetingView(this.groupId, this.id, {super.key});
  final int groupId, id;
  @override
  Widget build(BuildContext context) => TfPage(
    title: 'Pertemuan Tahfidz',
    path: 'tahfidz/$groupId/pertemuan/$id',
    content: (context, ref, data, reload) {
      final p = tfMap(data['pertemuan']);
      return [
        TfHero(
          tfDate(p['tanggal']),
          '${tfText(tfMap(data['kelompok'])['nama'])}\n${p['selesai'] == true ? 'Selesai · rangkuman tersedia bagi orang tua' : 'Pertemuan masih terbuka'}',
        ),
        if (data['catat'] == true)
          Wrap(
            spacing: 8,
            children: [
              TfAction('Catat Setoran', () async {
                final saved = await tfEdit(
                  context,
                  title: 'Catat Setoran Tahfidz',
                  path: 'tahfidz/$groupId/pertemuan/$id/setoran',
                  hidden: {'token_setoran': tfToken()},
                  fields: [
                    TfField(
                      'anggota_tahfidz_id',
                      'Siswa',
                      kind: 'choice',
                      options: tfItems(data['peserta']),
                    ),
                    TfField(
                      'nomor_surah',
                      'Surah',
                      kind: 'choice',
                      options: [
                        for (final s in tfItems(data['surah']))
                          {
                            'id': s['nomor'],
                            'nama': '${s['nomor']}. ${s['nama']}',
                          },
                      ],
                    ),
                    const TfField('nilai', 'Nilai (0–100)', kind: 'number'),
                    const TfField(
                      'catatan',
                      'Catatan pembimbing',
                      kind: 'notes',
                      required: false,
                    ),
                  ],
                );
                if (saved != null) await reload();
              }, icon: Icons.edit_note_rounded),
              TfAction(
                'Selesaikan Pertemuan',
                () async {
                  await ref
                      .read(tahfidzRepositoryProvider)
                      .call(
                        'POST',
                        'tahfidz/$groupId/pertemuan/$id/selesaikan',
                      );
                  await reload();
                },
                confirm: 'Setoran baru tidak dapat ditambahkan setelah selesai. Rangkuman pertemuan akan dikirim ke akun orang tua yang terhubung.',
              ),
            ],
          ),
        TfAction(
          'Daftar Surah',
          () => tfOpen(context, const TahfidzSurahView()),
          icon: Icons.menu_book_outlined,
          prominent: true,
          subtitle: 'Periksa hafalan dengan teks Arab berharakat.',
        ),
        const TfSection('Setoran'),
        if (tfItems(data['items']).isEmpty)
          const TfNotice('Belum ada setoran pada pertemuan ini.'),
        for (final s in tfItems(data['items']))
          TfCard(
            tfText(s['nama']),
            '${s['surah']} · Nilai ${s['nilai']}\nPenilai: ${tfText(s['penilai'])}',
            child: s['catatan'] == null
                ? null
                : Text(
                    tfText(s['catatan']),
                    style: const TextStyle(fontSize: 12),
                  ),
          ),
      ];
    },
  );
}

class TfStudentView extends StatefulWidget {
  const TfStudentView(this.groupId, this.id, {this.reports = false, super.key});
  final int groupId, id;
  final bool reports;
  @override
  State<TfStudentView> createState() => _TfStudentViewState();
}

class _TfStudentViewState extends State<TfStudentView> {
  late String _tab;
  @override
  void initState() {
    super.initState();
    _tab = widget.reports ? 'rapor' : 'perkembangan';
  }

  @override
  Widget build(BuildContext context) {
    final path = 'tahfidz/${widget.groupId}/anggota/${widget.id}';
    return TfPage(
      title: 'Tahfidz Siswa',
      path: path,
      content: (context, ref, data, reload) => [
        TfHero(
          tfText(tfMap(data['anggota'])['nama']),
          '${data['sudah']} / ${data['target']} surah disetor',
          progress:
              tfInt(data['sudah']) /
              (tfInt(data['target']) == 0 ? 37 : tfInt(data['target'])),
        ),
        Wrap(
          spacing: 8,
          children: [
            for (final name in ['perkembangan', 'riwayat', 'rapor'])
              ChoiceChip(
                label: Text(
                  {
                    'perkembangan': 'Perkembangan',
                    'riwayat': 'Riwayat',
                    'rapor': 'Rapor & Sertifikat',
                  }[name]!,
                ),
                selected: _tab == name,
                onSelected: (_) => setState(() => _tab = name),
              ),
          ],
        ),
        if (_tab == 'perkembangan') tfProgress(tfItems(data['baris'])),
        if (_tab == 'riwayat') ...[
          if (tfItems(data['riwayat']).isEmpty)
            const TfNotice('Belum ada riwayat setoran.'),
          for (final s in tfItems(data['riwayat']))
            TfCard(
              tfText(s['surah']),
              '${tfDate(s['tanggal'])} · Nilai ${s['nilai']}\nPenilai: ${tfText(s['penilai'])}',
              child: s['catatan'] == null ? null : Text(tfText(s['catatan'])),
            ),
        ],
        if (_tab == 'rapor') ...[
          const TfSection('Rapor Tahfidz'),
          if (data['rapor'] == null)
            TfNotice(tfText(data['pesan_rapor'], 'Target rapor belum diatur.'))
          else ...[
            TfCard(
              'Pratinjau Rapor',
              'Rata-rata ${tfText(tfMap(data['rapor'])['rata'])} · ${tfText(tfMap(data['rapor'])['predikat'])}\n${data['dipublikasikan'] == true ? 'Sudah dibagikan ke orang tua' : 'Belum dipublikasikan'}',
              icon: Icons.description_outlined,
              onTap: () =>
                  tfOpen(context, TfDocumentView(tfMap(data['rapor']))),
            ),
            if (data['kelola'] == true)
              TfAction(
                'Bagikan Rapor ke Orang Tua',
                () async {
                  await ref
                      .read(tahfidzRepositoryProvider)
                      .call(
                        'POST',
                        '$path/rapor/publikasi',
                        data: {'bagikan': true, 'sidik': data['sidik']},
                      );
                  await reload();
                },
                confirm: 'Bagikan rapor yang sudah diperiksa kepada orang tua siswa ini?',
                icon: Icons.publish_rounded,
              ),
          ],
          if (data['kelola'] == true && data['dipublikasikan'] == true)
            TfAction(
              'Tarik Publikasi Rapor',
              () async {
                await ref
                    .read(tahfidzRepositoryProvider)
                    .call(
                      'POST',
                      '$path/rapor/publikasi',
                      data: {'bagikan': false},
                    );
                await reload();
              },
              confirm: 'Rapor ini akan disembunyikan dari akun orang tua.',
              icon: Icons.unpublished_outlined,
            ),
          const TfSection('Kelulusan & Sertifikat'),
          if (data['terverifikasi'] == true)
            const TfNotice('Kelulusan 37 surah Juz 30 sudah diverifikasi.'),
          if (data['dapat_verifikasi'] == true)
            TfAction(
              'Verifikasi Kelulusan',
              () async {
                await ref
                    .read(tahfidzRepositoryProvider)
                    .call('POST', '$path/verifikasi');
                await reload();
              },
              confirm: 'Pastikan siswa telah menyelesaikan seluruh 37 surah Juz 30 sesuai ketentuan sekolah.',
            ),
          if (data['sertifikat'] != null)
            TfCard(
              'Sertifikat Tahfidz',
              tfText(tfMap(data['sertifikat'])['nomor']),
              icon: Icons.workspace_premium_outlined,
              onTap: () => tfOpen(
                context,
                TfDocumentView(tfMap(data['sertifikat']), certificate: true),
              ),
            ),
          if (data['terverifikasi'] != true && data['dapat_verifikasi'] != true)
            const TfNotice(
              'Kelulusan diverifikasi pembimbing setelah seluruh 37 surah Juz 30 selesai disetor.',
            ),
        ],
      ],
    );
  }
}

class TahfidzTargetView extends StatefulWidget {
  const TahfidzTargetView({super.key});
  @override
  State<TahfidzTargetView> createState() => _TahfidzTargetViewState();
}

class _TahfidzTargetViewState extends State<TahfidzTargetView> {
  int? _year;
  @override
  Widget build(BuildContext context) => TfPage(
    title: 'Target Rapor Tahfidz',
    path: 'tahfidz/target-rapor',
    query: {'tahun': ?_year},
    content: (context, ref, data, reload) => [
      const TfHero(
        'Target Rapor',
        'Atur jumlah surah, pilihan target, tanggal rapor, dan batas predikat untuk setiap tingkat.',
      ),
      NusaDropdownField<int>(
        fieldKey: const Key('tahfidz-target-year'),
        value: _year ?? tfInt(data['tahun_id']),
        options: [
          for (final y in tfItems(data['tahun']))
            NusaDropdownOption(value: tfInt(y['id']), label: tfText(y['nama'])),
        ],
        decoration: const InputDecoration(labelText: 'Tahun pelajaran'),
        onChanged: (v) => setState(() => _year = v),
      ),
      for (final grade in [7, 8, 9])
        Builder(
          builder: (context) {
            final target =
                tfItems(data['items'])
                    .where((t) => tfInt(t['tingkat']) == grade)
                    .firstOrNull ??
                {};
            return TfCard(
              'Tingkat $grade',
              target.isEmpty
                  ? 'Belum diatur'
                  : '${target['target']} surah · ${target['mode'] == 'tetap' ? 'Surah tetap' : 'Pilihan bebas'}\nRapor ${tfDate(target['tanggal_rapor'])}',
              child: TfAction('Atur Target', () async {
                final saved = await tfEdit(
                  context,
                  title: 'Target Rapor Tingkat $grade',
                  path: 'tahfidz/target-rapor',
                  hidden: {
                    'tahun_pelajaran_id': _year ?? data['tahun_id'],
                    'tingkat': grade,
                  },
                  fields: [
                    TfField(
                      'target',
                      'Jumlah surah target (1–37)',
                      kind: 'number',
                      value: target['target'] ?? {7: 10, 8: 20, 9: 25}[grade],
                    ),
                    TfField(
                      'mode',
                      'Mode target',
                      kind: 'choice',
                      value: target['mode'] ?? 'bebas',
                      options: const [
                        {'id': 'bebas', 'nama': 'Pilihan bebas'},
                        {'id': 'tetap', 'nama': 'Surah tetap'},
                      ],
                    ),
                    TfField(
                      'surah',
                      'Surah target (untuk mode tetap)',
                      kind: 'multiple',
                      value: target['surah'] ?? [],
                      options: [
                        for (final s in tfItems(data['surah']))
                          {'id': s['nomor'], 'nama': tfText(s['nama'])},
                      ],
                    ),
                    TfField(
                      'tanggal_rapor',
                      'Tanggal rapor',
                      kind: 'date',
                      value: target['tanggal_rapor'],
                    ),
                    TfField(
                      'batas_cukup',
                      'Batas nilai Cukup',
                      kind: 'number',
                      value: target['batas_cukup'] ?? 70,
                    ),
                    TfField(
                      'batas_baik',
                      'Batas nilai Baik',
                      kind: 'number',
                      value: target['batas_baik'] ?? 80,
                    ),
                    TfField(
                      'batas_sangat_baik',
                      'Batas nilai Sangat Baik',
                      kind: 'number',
                      value: target['batas_sangat_baik'] ?? 90,
                    ),
                  ],
                );
                if (saved != null) await reload();
              }, icon: Icons.tune_rounded),
            );
          },
        ),
    ],
  );
}

class TahfidzWisudaView extends StatelessWidget {
  const TahfidzWisudaView({super.key});
  @override
  Widget build(BuildContext context) => TfPage(
    title: 'Wisuda Tahfidz',
    path: 'tahfidz/wisuda',
    content: (context, ref, data, reload) => [
      const TfHero(
        'Wisuda & Sertifikat',
        'Kelulusan diverifikasi oleh pembimbing. Koordinator menerbitkan dan membagikan sertifikat kepada orang tua.',
      ),
      TfAction(
        'Tambah Acara Wisuda',
        () => _eventForm(context, reload),
        icon: Icons.add,
      ),
      const TfSection('Acara Wisuda'),
      if (tfItems(data['acara']).isEmpty)
        const TfNotice('Belum ada acara wisuda.'),
      for (final e in tfItems(data['acara']))
        TfCard(
          tfText(e['nama']),
          '${tfDate(e['tanggal'])} · ${tfText(e['lokasi'])}',
          icon: Icons.event_outlined,
          child: tfInt(e['kelulusan_count']) == 0
              ? TfAction(
                  'Edit Acara',
                  () => _eventForm(context, reload, e),
                  icon: Icons.edit_outlined,
                )
              : const Text(
                  'Acara dikunci karena sertifikat sudah diterbitkan.',
                  style: TextStyle(fontSize: 11),
                ),
        ),
      const TfSection('Kelulusan Siswa'),
      if (tfItems(data['items']).isEmpty)
        const TfNotice('Belum ada kelulusan yang diverifikasi pembimbing.'),
      for (final l in tfItems(data['items']))
        TfCard(
          tfText(l['nama']),
          l['diterbitkan'] == true
              ? '${tfText(tfMap(l['sertifikat'])['nomor'])}\n${l['dibagikan'] == true ? 'Sudah dibagikan ke orang tua' : 'Belum dibagikan'}'
              : 'Terverifikasi · sertifikat belum terbit',
          icon: Icons.workspace_premium_outlined,
          child: Wrap(
            spacing: 8,
            children: [
              if (l['diterbitkan'] != true)
                TfAction('Terbitkan Sertifikat', () async {
                  final saved = await tfEdit(
                    context,
                    title: 'Terbitkan Sertifikat',
                    path: 'tahfidz/wisuda/kelulusan/${l['id']}/terbitkan',
                    fields: [
                      TfField(
                        'wisuda_tahfidz_id',
                        'Acara wisuda',
                        kind: 'choice',
                        options: tfItems(data['acara']),
                      ),
                    ],
                  );
                  if (saved != null) await reload();
                }),
              if (l['diterbitkan'] == true) ...[
                TfAction(
                  'Lihat Sertifikat',
                  () => tfOpen(
                    context,
                    TfDocumentView(tfMap(l['sertifikat']), certificate: true),
                  ),
                  icon: Icons.description_outlined,
                ),
                TfAction(
                  l['dibagikan'] == true
                      ? 'Tarik Publikasi'
                      : 'Bagikan ke Orang Tua',
                  () async {
                    await ref
                        .read(tahfidzRepositoryProvider)
                        .call(
                          'POST',
                          'tahfidz/wisuda/kelulusan/${l['id']}/publikasi',
                          data: {'bagikan': l['dibagikan'] != true},
                        );
                    await reload();
                  },
                  confirm: l['dibagikan'] == true
                      ? 'Sertifikat akan disembunyikan dari akun orang tua.'
                      : 'Bagikan sertifikat siswa ini ke akun orang tua yang terhubung?',
                  icon: Icons.publish_outlined,
                ),
              ],
            ],
          ),
        ),
    ],
  );
}

Future<void> _eventForm(
  BuildContext context,
  TfReload reload, [
  TfData? event,
]) async {
  final saved = await tfEdit(
    context,
    title: event == null ? 'Tambah Acara Wisuda' : 'Edit Acara Wisuda',
    path: event == null ? 'tahfidz/wisuda' : 'tahfidz/wisuda/${event['id']}',
    method: event == null ? 'POST' : 'PUT',
    fields: [
      TfField('nama', 'Nama acara', value: event?['nama']),
      TfField(
        'tanggal',
        'Tanggal wisuda',
        kind: 'date',
        value: event?['tanggal'],
      ),
      TfField('lokasi', 'Lokasi', value: event?['lokasi']),
      TfField(
        'kepala_sekolah',
        'Nama kepala sekolah',
        value: event?['kepala_sekolah'],
      ),
    ],
  );
  if (saved != null) await reload();
}

class TahfidzAnakView extends StatefulWidget {
  const TahfidzAnakView({this.studentId, super.key});
  final int? studentId;
  @override
  State<TahfidzAnakView> createState() => _TahfidzAnakViewState();
}

class _TahfidzAnakViewState extends State<TahfidzAnakView> {
  int? _student;
  String _tab = 'perkembangan';
  @override
  void initState() {
    super.initState();
    _student = widget.studentId;
  }

  @override
  void didUpdateWidget(TahfidzAnakView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.studentId != widget.studentId) _student = widget.studentId;
  }

  @override
  Widget build(BuildContext context) => TfPage(
    title: 'Tahfidz Anak Saya',
    path: 'tahfidz-anak',
    pageKey: 'riwayat',
    query: {'siswa': ?_student},
    content: (context, ref, data, reload) => [
      TfHero(
        'Perkembangan Tahfidz',
        '${data['sudah']} / ${data['target']} surah telah disetor',
        progress:
            tfInt(data['sudah']) /
            (tfInt(data['target']) == 0 ? 37 : tfInt(data['target'])),
      ),
      NusaDropdownField<int>(
        fieldKey: const Key('tahfidz-child'),
        value: _student ?? tfInt(data['siswa_id']),
        options: [
          for (final s in tfItems(data['anak']))
            NusaDropdownOption(value: tfInt(s['id']), label: tfText(s['nama'])),
        ],
        decoration: const InputDecoration(labelText: 'Anak'),
        onChanged: (v) => setState(() => _student = v),
      ),
      if (tfItems(data['anak']).isEmpty)
        const TfNotice('Belum ada anak yang terhubung dengan akun ini.'),
      const TfNotice(
        'Perkembangan ditampilkan setelah pembimbing menyelesaikan pertemuan. Rapor dan sertifikat tampil setelah dipublikasikan sekolah.',
      ),
      Wrap(
        spacing: 8,
        children: [
          for (final name in ['perkembangan', 'riwayat', 'dokumen'])
            ChoiceChip(
              label: Text(
                {
                  'perkembangan': 'Perkembangan',
                  'riwayat': 'Riwayat',
                  'dokumen': 'Dokumen',
                }[name]!,
              ),
              selected: _tab == name,
              onSelected: (_) => setState(() => _tab = name),
            ),
        ],
      ),
      if (_tab == 'perkembangan') tfProgress(tfItems(data['baris'])),
      if (_tab == 'riwayat') ...[
        if (tfItems(data['riwayat']).isEmpty)
          const TfNotice('Belum ada setoran yang dibagikan pembimbing.'),
        for (final s in tfItems(data['riwayat']))
          TfCard(
            tfText(s['surah']),
            '${tfDate(s['tanggal'])} · Nilai ${s['nilai']}',
          ),
      ],
      if (_tab == 'dokumen') ...[
        if (tfItems(data['rapor']).isEmpty &&
            tfItems(data['sertifikat']).isEmpty)
          const TfNotice('Belum ada dokumen yang dipublikasikan.'),
        for (final r in tfItems(data['rapor']))
          TfCard(
            'Rapor Tahfidz ${r['tahun']}',
            'Versi ${r['versi']}',
            icon: Icons.description_outlined,
            child: TfAction('Buka Rapor', () async {
              final detail = await ref
                  .read(tahfidzRepositoryProvider)
                  .get('tahfidz-anak/rapor/${r['id']}');
              if (context.mounted) {
                await tfOpen(context, TfDocumentView(tfMap(detail['rapor'])));
              }
            }, icon: Icons.visibility_outlined),
          ),
        for (final c in tfItems(data['sertifikat']))
          TfCard(
            'Sertifikat Tahfidz',
            tfText(c['nomor_sertifikat']),
            icon: Icons.workspace_premium_outlined,
            child: TfAction('Buka Sertifikat', () async {
              final detail = await ref
                  .read(tahfidzRepositoryProvider)
                  .get('tahfidz-anak/sertifikat/${c['id']}');
              if (context.mounted) {
                await tfOpen(
                  context,
                  TfDocumentView(
                    tfMap(detail['sertifikat']),
                    certificate: true,
                  ),
                );
              }
            }, icon: Icons.visibility_outlined),
          ),
      ],
    ],
  );
}
