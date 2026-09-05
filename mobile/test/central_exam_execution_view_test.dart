import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_execution/data/central_exam_execution_remote_data_source.dart';
import 'package:nusa/features/central_exam_execution/domain/central_exam_execution.dart';
import 'package:nusa/features/central_exam_execution/presentation/central_exam_execution_detail_view.dart';

void main() {
  test('domain pelaksanaan membaca token, ruang, dan peringatan Mode Aman', () {
    final detail = CentralExamExecutionDetail.fromJson(_detailJson());

    expect(detail.event.name, 'Ujian Sekolah');
    expect(detail.schedules.single.package?.token, '654321');
    expect(detail.schedules.single.rooms.single.summary.blocked, 1);
    expect(detail.participants.items.first.staleHeartbeat, isTrue);
    expect(detail.alerts.single.type, 'mode_aman');
    expect(detail.capabilities.canManageSupervisors, isTrue);
  });

  testWidgets('pusat pelaksanaan rapi di layar sempit dan membuka Mode Aman', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeCentralExamExecutionRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          centralExamExecutionRemoteDataSourceProvider.overrideWithValue(
            remote,
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const CentralExamExecutionDetailView(eventId: 7),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Ujian Sekolah'), findsWidgets);
    await tester.tap(find.byKey(const Key('central-exam-auto-refresh')));
    await tester.pump();
    expect(find.text('Pembaruan otomatis sedang dimatikan.'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('copy-token-27')),
      350,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('654321'), findsOneWidget);

    final participant = find.byKey(const Key('central-exam-participant-31'));
    await tester.scrollUntilVisible(
      participant,
      500,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Siswa Satu'), findsOneWidget);
    final unlock = find.byKey(const Key('unlock-central-exam-31'));
    await tester.scrollUntilVisible(
      unlock,
      220,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(unlock);
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Buka Ujian'));
    await tester.pumpAndSettle();

    expect(remote.unlockCalls, 1);
    await tester.tap(find.byKey(const Key('central-exam-auto-refresh')));
    await tester.pump(const Duration(seconds: 16));
    await tester.pumpAndSettle();
    final scrollable = find.byType(SingleChildScrollView);
    await tester.fling(scrollable, const Offset(0, -3500), 3000);
    await tester.pumpAndSettle();
    expect(
      find.byKey(const Key('central-exam-participant-57')),
      findsOneWidget,
    );
    await tester.fling(scrollable, const Offset(0, 3500), 3000);
    await tester.pumpAndSettle();
    expect(find.text('Jadwal & Ruang'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _FakeCentralExamExecutionRemoteDataSource
    implements CentralExamExecutionRemoteDataSource {
  int unlockCalls = 0;

  @override
  Future<CentralExamExecutionPage> fetchEvents({
    required String query,
    required String status,
    required int page,
  }) async => CentralExamExecutionPage.fromJson({
    'ringkasan': {'total': 1, 'aktif': 1, 'persiapan': 0, 'selesai': 0},
    'items': const [],
    'referensi': {'status': const []},
    'filter': {'kata_kunci': query, 'status': status},
    'paginasi': {
      'halaman': 1,
      'halaman_terakhir': 1,
      'total': 0,
      'ada_halaman_berikutnya': false,
    },
  });

  @override
  Future<CentralExamExecutionDetail> fetchDetail(
    CentralExamExecutionRequest request,
  ) async => CentralExamExecutionDetail.fromJson(_detailJson());

  @override
  Future<String> assignSupervisor({
    required int eventId,
    required int scheduleId,
    required int sourceRoomId,
    required String role,
    required int employeeId,
    required String? reason,
  }) async => 'Pengawas berhasil diperbarui.';

  @override
  Future<void> unlockSafeMode(int participantId) async {
    unlockCalls++;
  }
}

Map<String, dynamic> _detailJson() => {
  'kegiatan': {
    'id': 7,
    'kode': 'UT-01',
    'nama': 'Ujian Sekolah',
    'jenis': 'Ujian Sekolah',
    'tahun_pelajaran': '2026/2027',
    'semester': 'Ganjil',
    'periode': '05-09-2026 sampai 12-09-2026',
    'status': 'aktif',
    'label_status': 'Aktif',
  },
  'ringkasan': {
    'total': 27,
    'belum_hadir': 0,
    'hadir_belum_mulai': 0,
    'tidak_hadir': 0,
    'sedang_mengerjakan': 0,
    'selesai': 0,
    'terblokir': 1,
    'jumlah_jadwal': 1,
    'jumlah_ruang': 1,
    'ruang_berlangsung': 1,
    'bukti_menunggu': 0,
  },
  'jadwal': [
    {
      'id': 17,
      'mata_pelajaran': 'Matematika',
      'tingkat': 9,
      'kelas': ['IX.A'],
      'tanggal': '2026-09-05',
      'waktu': '07:30 - 09:00',
      'sesi': 'Sesi Pagi',
      'status': 'siap',
      'label_status': 'Siap',
      'paket': {
        'id': 27,
        'nama': 'Paket Matematika',
        'status': 'berlangsung',
        'label_status': 'Berlangsung',
        'token': '654321',
        'memerlukan_token': true,
      },
      'ruang': [
        {
          'id': 11,
          'ruang_kegiatan_id': 4,
          'kode': 'LAB-1',
          'nama': 'Labor Komputer 1',
          'lokasi': 'Lantai 1',
          'status': 'berlangsung',
          'label_status': 'Berlangsung',
          'status_bukti': 'sebagian',
          'label_status_bukti': 'Sebagian',
          'pengawas_utama': {'id': 2, 'nama': 'Guru Pengawas'},
          'pengawas_pendamping': null,
          'ringkasan': {
            'total': 1,
            'belum_hadir': 0,
            'hadir_belum_mulai': 0,
            'tidak_hadir': 0,
            'sedang_mengerjakan': 0,
            'selesai': 0,
            'terblokir': 1,
          },
          'dapat_mengatur_pengawas': true,
        },
      ],
    },
  ],
  'peserta': {
    'items': List.generate(
      27,
      (index) => {
        'id': 31 + index,
        'nama': index == 0 ? 'Siswa Satu' : 'Siswa ${index + 1}',
        'nisn': '00990000${(index + 1).toString().padLeft(2, '0')}',
        'nomor_peserta': 'UT-${(index + 1).toString().padLeft(3, '0')}',
        'kelas': 'IX.A',
        'ruang': 'Labor Komputer 1',
        'ruang_id': 11,
        'jadwal_id': 17,
        'mata_pelajaran': 'Matematika',
        'status': index == 0 ? 'terblokir' : 'aktif',
        'label_status': index == 0 ? 'Terblokir' : 'Belum mulai',
        'jawaban_tersimpan': index == 0 ? 18 : 0,
        'jumlah_pindah_aplikasi': index == 0 ? 3 : 0,
        'heartbeat_terakhir_pada': index == 0
            ? '2026-09-05T08:15:00+07:00'
            : null,
        'heartbeat_terlambat': index == 0,
        'dapat_dibuka_mode_aman': index == 0,
      },
    ),
    'filter': {
      'status': 'semua',
      'jadwal_id': null,
      'ruang_id': null,
      'kata_kunci': '',
    },
    'paginasi': {
      'halaman': 1,
      'halaman_terakhir': 1,
      'total': 27,
      'ada_halaman_berikutnya': false,
    },
  },
  'peringatan': [
    {
      'jenis': 'mode_aman',
      'judul': 'Peserta ditahan Mode Aman',
      'keterangan': 'Siswa Satu · Labor Komputer 1',
      'peserta_id': 31,
      'ruang_id': 11,
    },
  ],
  'referensi': {
    'status_peserta': [
      {'kode': 'semua', 'label': 'Semua status'},
      {'kode': 'terblokir', 'label': 'Terblokir'},
    ],
    'pegawai': [
      {'id': 2, 'nama': 'Guru Pengawas', 'nip': '1988001'},
      {'id': 3, 'nama': 'Guru Pengganti', 'nip': '1988002'},
    ],
  },
  'kemampuan': {
    'mengatur_pengawas': true,
    'membuka_mode_aman': true,
    'melihat_ruang': true,
  },
  'dihasilkan_pada': '2026-09-05T08:15:00+07:00',
};
