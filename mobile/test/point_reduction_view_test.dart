import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/point_reduction/data/point_reduction_remote_data_source.dart';
import 'package:nusa/features/point_reduction/domain/point_reduction.dart';
import 'package:nusa/features/point_reduction/presentation/point_reduction_view.dart';

void main() {
  test('domain membaca pengajuan, saldo siswa, bukti, dan hak akses', () {
    final page = PointReductionPage.fromJson(_pageJson());

    expect(page.summary.pending, 1);
    expect(page.summary.approvedPoints, 30);
    expect(page.options.students.single.balance, 25);
    expect(page.items.single.evidence?.mimeType, 'image/png');
    expect(page.items.single.canDecide, isTrue);
  });

  testWidgets('daftar penghargaan rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakePointReductionRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          pointReductionRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const PointReductionView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Penghargaan & Pengurangan Poin'), findsOneWidget);
    expect(find.text('Siswa Penghargaan Native'), findsOneWidget);
    expect(find.text('Teladan disiplin'), findsOneWidget);
    expect(find.byKey(const Key('point-reduction-status')), findsOneWidget);
    expect(find.byKey(const Key('point-reduction-create')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('putusan penghargaan mengirim keputusan dan catatan', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakePointReductionRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          pointReductionRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const PointReductionView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('point-reduction-decide-7')));
    await tester.pumpAndSettle();
    expect(find.text('Putusan Penghargaan'), findsOneWidget);
    await tester.enterText(
      find.byKey(const Key('point-reduction-decision-note')),
      'Prestasi telah diverifikasi.',
    );
    await tester.tap(find.byKey(const Key('point-reduction-approve')));
    await tester.pumpAndSettle();

    expect(remote.decisionCalls, 1);
    expect(remote.lastDecision, 'disetujui');
    expect(remote.lastNote, 'Prestasi telah diverifikasi.');
    expect(tester.takeException(), isNull);
  });
}

class _FakePointReductionRemoteDataSource
    implements PointReductionRemoteDataSource {
  int decisionCalls = 0;
  String? lastDecision;
  String? lastNote;

  @override
  Future<PointReductionPage> fetch({
    required String query,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) async => PointReductionPage.fromJson(_pageJson());

  @override
  Future<PointReductionMutation> create(
    PointReductionCreatePayload payload,
  ) async => PointReductionMutation(
    message: 'Pengajuan berhasil dibuat.',
    item: PointReductionItem.fromJson(_itemJson()),
  );

  @override
  Future<PointReductionMutation> decide({
    required int id,
    required String decision,
    required String? note,
  }) async {
    decisionCalls++;
    lastDecision = decision;
    lastNote = note;
    return PointReductionMutation(
      message: 'Pengurangan disetujui.',
      item: PointReductionItem.fromJson({
        ..._itemJson(),
        'status': decision,
        'label_status': decision == 'disetujui' ? 'Disetujui' : 'Ditolak',
        'dapat_diputuskan': false,
      }),
    );
  }

  @override
  Future<ReductionEvidenceDownload> download(PointReductionItem item) async =>
      ReductionEvidenceDownload(
        fileName: item.evidence?.fileName ?? 'bukti.png',
        mimeType: item.evidence?.mimeType ?? 'image/png',
        bytes: Uint8List.fromList([1, 2, 3]),
      );
}

Map<String, dynamic> _pageJson() => {
  'items': [_itemJson()],
  'ringkasan': {
    'semua': 4,
    'diajukan': 1,
    'disetujui': 2,
    'ditolak': 1,
    'poin_disetujui': 30,
  },
  'pilihan': {
    'status': const [
      {'kode': 'semua', 'label': 'Semua Status'},
      {'kode': 'diajukan', 'label': 'Diajukan'},
      {'kode': 'disetujui', 'label': 'Disetujui'},
      {'kode': 'ditolak', 'label': 'Ditolak'},
    ],
    'tahun_pelajaran': const [
      {'id': 1, 'nama': '2026/2027', 'aktif': true},
    ],
    'kelas': const [
      {'id': 3, 'tahun_pelajaran_id': 1, 'nama': 'VIII.A', 'tingkat': 8},
    ],
    'siswa': const [
      {
        'id': 1,
        'nama': 'Siswa Penghargaan Native',
        'nis': 'NUSA-01',
        'nisn': '0088331001',
        'saldo_poin': 25,
        'kelas': {'id': 3, 'nama': 'VIII.A'},
      },
    ],
    'kegiatan': const ['Teladan disiplin', 'Aktif organisasi'],
    'poin': const [10, 15, 20, 30],
  },
  'filter': {
    'kata_kunci': '',
    'status': 'semua',
    'tahun_pelajaran_id': 1,
    'kelas_id': null,
  },
  'tahun_pelajaran_aktif': {'id': 1, 'nama': '2026/2027'},
  'paginasi': {
    'halaman': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
  'hak_akses': {'dapat_mengajukan': true, 'dapat_memutuskan': true},
};

Map<String, dynamic> _itemJson() => {
  'id': 7,
  'siswa': {
    'id': 1,
    'nama': 'Siswa Penghargaan Native',
    'nis': 'NUSA-01',
    'nisn': '0088331001',
  },
  'kelas': {'id': 3, 'nama': 'VIII.A'},
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'tanggal_kegiatan': '2026-09-01',
  'jenis_kegiatan': 'Teladan disiplin',
  'deskripsi': 'Menjadi teladan kedisiplinan kelas.',
  'poin_pengurangan': 10,
  'status': 'diajukan',
  'label_status': 'Diajukan',
  'bukti': {
    'nama_file': 'Bukti penghargaan.png',
    'tipe_file': 'image/png',
    'ukuran_file': 2048,
  },
  'diajukan_oleh': 'Admin NUSA',
  'disetujui_oleh': null,
  'diputuskan_pada': null,
  'catatan_keputusan': null,
  'dapat_diputuskan': true,
};
