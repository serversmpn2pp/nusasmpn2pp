import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/exam_attendance/data/exam_attendance_remote_data_source.dart';
import 'package:nusa/features/exam_attendance/domain/exam_attendance.dart';
import 'package:nusa/features/exam_attendance/presentation/exam_attendance_detail_view.dart';
import 'package:nusa/features/exam_attendance/presentation/exam_attendance_list_view.dart';

void main() {
  test('domain presensi ujian membaca ringkasan dan status peserta', () {
    final dashboard = ExamAttendanceDashboard.fromJson(_dashboardResponse());
    final detail = ExamAttendanceDetail.fromJson(_detailResponse());

    expect(dashboard.summary.rooms, 1);
    expect(dashboard.todayRooms.single.presentPercentage, 50);
    expect(detail.room.myRole, 'Pengawas utama');
    expect(detail.participants.single.status, 'belum_absen');
  });

  testWidgets('daftar ruang tetap rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          examAttendanceRemoteDataSourceProvider.overrideWithValue(
            _FakeExamAttendanceRemoteDataSource(),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const ExamAttendanceListView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Presensi Peserta Ujian CBT'), findsOneWidget);
    expect(find.textContaining('Labor Komputer 1'), findsOneWidget);
    expect(find.text('1/2 hadir'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('scan kartu menampilkan hasil dan memuat ulang ruang', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeExamAttendanceRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          examAttendanceRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: ExamAttendanceDetailView(
            roomId: 11,
            cameraBuilder: (onDetected, processing) => SizedBox(
              height: 160,
              child: Center(
                child: FilledButton(
                  key: const Key('fake-exam-attendance-camera'),
                  onPressed: processing
                      ? null
                      : () => onDetected('NISN: 0099000001'),
                  child: const Text('Pindai kartu'),
                ),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('Labor Komputer 1'), findsOneWidget);
    await tester.tap(find.byKey(const Key('fake-exam-attendance-camera')));
    await tester.pumpAndSettle();

    expect(remote.scans, 1);
    await tester.scrollUntilVisible(
      find.byKey(const Key('exam-attendance-scan-result')),
      180,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    expect(
      find.byKey(const Key('exam-attendance-scan-result')),
      findsOneWidget,
    );
    expect(find.text('Presensi berhasil'), findsOneWidget);
    expect(find.textContaining('meja nomor 1'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _FakeExamAttendanceRemoteDataSource
    implements ExamAttendanceRemoteDataSource {
  int scans = 0;

  @override
  Future<ExamAttendanceDashboard> fetch() async =>
      ExamAttendanceDashboard.fromJson(_dashboardResponse());

  @override
  Future<ExamAttendanceDetail> fetchDetail(int roomId) async =>
      ExamAttendanceDetail.fromJson(_detailResponse());

  @override
  Future<ExamAttendanceScanResult> scan({
    required int roomId,
    required String rawValue,
  }) async {
    scans++;
    return ExamAttendanceScanResult.fromJson({
      'berhasil': true,
      'baru': true,
      'status': 'hadir',
      'pesan': 'Presensi ujian berhasil dicatat. Silakan menuju meja nomor 1.',
      'waktu_server': '07:15:30',
      'siswa': {
        'nama_lengkap': 'Siswa Satu',
        'nisn': '0099000001',
        'kelas': 'IX.A',
        'nomor_meja': 1,
      },
    });
  }

  @override
  Future<ExamAttendanceDetail> changeAttendance({
    required int roomId,
    required int participantId,
    required String status,
    required String? note,
  }) async => ExamAttendanceDetail.fromJson(_detailResponse());
}

Map<String, dynamic> _dashboardResponse() => {
  'ringkasan': {'jumlah_ruang': 1, 'jumlah_peserta': 2, 'jumlah_hadir': 1},
  'ruang_hari_ini': [
    {
      'id': 11,
      'ujian_id': 21,
      'kode': 'LAB-1',
      'nama': 'Labor Komputer 1',
      'lokasi': 'Lantai 1',
      'kegiatan': 'Ujian Sekolah',
      'mata_pelajaran': 'Matematika',
      'tanggal': '2026-09-06',
      'tanggal_label': 'Minggu, 06 September 2026',
      'waktu': '07:30 - 09:00',
      'sesi': 'Sesi Pagi',
      'status': 'siap',
      'label_status': 'Siap',
      'pengawas_utama': 'Guru Pengawas',
      'jumlah_peserta': 2,
      'jumlah_hadir': 1,
      'jumlah_belum_absen': 1,
      'jumlah_tidak_hadir': 0,
      'persentase_hadir': 50,
    },
  ],
  'ruang_lain': <Map<String, dynamic>>[],
  'dapat_kelola_semua': false,
  'dihasilkan_pada': '2026-09-06T07:15:00+07:00',
};

Map<String, dynamic> _detailResponse() => {
  'ruang': {
    'id': 11,
    'ujian_id': 21,
    'kode': 'LAB-1',
    'nama': 'Labor Komputer 1',
    'lokasi': 'Lantai 1',
    'kegiatan': 'Ujian Sekolah',
    'jenis_ujian': 'Ujian Sekolah',
    'mata_pelajaran': 'Matematika',
    'tanggal': '2026-09-06',
    'tanggal_label': 'Minggu, 06 September 2026',
    'waktu': '07:30 - 09:00',
    'sesi': 'Sesi Pagi',
    'status': 'siap',
    'label_status': 'Siap',
    'pengawas_utama': 'Guru Pengawas',
    'pengawas_pendamping': null,
    'peran_saya': 'Pengawas utama',
    'dapat_mengubah': true,
  },
  'ringkasan': {'peserta': 1, 'hadir': 0, 'belum_absen': 1, 'tidak_hadir': 0},
  'status_kehadiran': [
    {'kode': 'belum_absen', 'label': 'Belum hadir'},
    {'kode': 'hadir', 'label': 'Hadir'},
    {'kode': 'terlambat', 'label': 'Terlambat'},
    {'kode': 'sakit', 'label': 'Sakit'},
    {'kode': 'izin', 'label': 'Izin'},
    {'kode': 'alfa', 'label': 'Alfa'},
  ],
  'presensi_terbaru': <Map<String, dynamic>>[],
  'peserta': [
    {
      'id': 31,
      'nama_lengkap': 'Siswa Satu',
      'nisn': '0099000001',
      'kelas': 'IX.A',
      'nomor_peserta': 'UT-001',
      'nomor_meja': 1,
      'status': 'belum_absen',
      'label_status': 'Belum hadir',
    },
  ],
  'waktu_server': '2026-09-06T07:15:00+07:00',
};
