import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/exam_supervision/data/exam_supervision_remote_data_source.dart';
import 'package:nusa/features/exam_supervision/domain/exam_supervision.dart';
import 'package:nusa/features/exam_supervision/presentation/exam_supervision_detail_view.dart';

void main() {
  test('domain tugas pengawas membaca operasional ruang dan Mode Aman', () {
    final detail = ExamSupervisionDetail.fromJson(_response());

    expect(detail.room.name, 'Labor Komputer 1');
    expect(detail.summary.blocked, 1);
    expect(detail.participants.single.deviceBound, isTrue);
    expect(detail.participants.single.appSwitches, 3);
    expect(detail.evidence.single.type, 'daftar_hadir');
    expect(detail.capabilities.unlockSafeMode, isTrue);
  });

  testWidgets(
    'detail tugas pengawas rapi di layar kecil dan dapat memulai ruang',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final remote = _FakeExamSupervisionRemoteDataSource();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            examSupervisionRemoteDataSourceProvider.overrideWithValue(remote),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const ExamSupervisionDetailView(roomId: 11),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Ujian Sekolah'), findsOneWidget);
      expect(find.byKey(const Key('supervision-start-room')), findsOneWidget);

      await tester.scrollUntilVisible(
        find.text('Hadir / Total'),
        120,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      expect(find.text('Hadir / Total'), findsOneWidget);

      await tester.scrollUntilVisible(
        find.byKey(const Key('supervision-start-room')),
        -120,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('supervision-start-room')));
      await tester.pumpAndSettle();
      expect(find.text('Mulai pelaksanaan?'), findsOneWidget);
      await tester.tap(find.widgetWithText(FilledButton, 'Mulai'));
      await tester.pumpAndSettle();
      expect(remote.statusChanges, 1);

      await tester.drag(find.byType(ListView), const Offset(0, -650));
      await tester.pumpAndSettle();
      expect(find.text('Siswa Satu'), findsOneWidget);
      expect(find.text('3× pindah aplikasi'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}

class _FakeExamSupervisionRemoteDataSource
    implements ExamSupervisionRemoteDataSource {
  int statusChanges = 0;

  @override
  Future<ExamSupervisionDetail> fetchDetail(int roomId) async =>
      ExamSupervisionDetail.fromJson(_response());

  @override
  Future<ExamSupervisionDetail> changeRoomStatus(
    int roomId,
    String action,
  ) async {
    statusChanges++;
    return ExamSupervisionDetail.fromJson(_response());
  }

  @override
  Future<ExamSupervisionDetail> saveNotes({
    required int roomId,
    required String minutes,
    required String obstacles,
    required String followUp,
    required String notes,
  }) async => ExamSupervisionDetail.fromJson(_response());

  @override
  Future<ExamSupervisionDetail> changeAttendance({
    required int roomId,
    required int participantId,
    required String status,
    required String? notes,
  }) async => ExamSupervisionDetail.fromJson(_response());

  @override
  Future<ExamSupervisionDetail> resetDevice({
    required int roomId,
    required int participantId,
  }) async => ExamSupervisionDetail.fromJson(_response());

  @override
  Future<void> unlockSafeMode(int participantId) async {}

  @override
  Future<ExamSupervisionDetail> uploadEvidence({
    required int roomId,
    required String type,
    required SupervisionPickedFile file,
  }) async => ExamSupervisionDetail.fromJson(_response());

  @override
  Future<ExamSupervisionDetail> deleteEvidence({
    required int roomId,
    required int evidenceId,
  }) async => ExamSupervisionDetail.fromJson(_response());

  @override
  Future<ExamSupervisionDetail> submitEvidence(int roomId) async =>
      ExamSupervisionDetail.fromJson(_response());
}

Map<String, dynamic> _response() => {
  'ruang': {
    'id': 11,
    'kode': 'LAB-1',
    'nama': 'Labor Komputer 1',
    'lokasi': 'Lantai 1',
    'status': 'siap',
    'label_status': 'Siap',
    'terkunci': true,
    'kegiatan': 'Ujian Sekolah',
    'jenis_ujian': 'Ujian Sekolah',
    'mata_pelajaran': 'Matematika',
    'tingkat': 9,
    'tanggal': '2026-09-05',
    'waktu': '07:30 - 09:00',
    'pengawas_utama': 'Guru Pengawas',
    'pengawas_pendamping': null,
    'peran_saya': 'Pengawas utama',
    'status_bukti': 'sebagian',
    'label_status_bukti': 'Sebagian',
    'berita_acara': null,
    'hambatan': null,
    'tindak_lanjut': null,
    'catatan': null,
  },
  'ringkasan': {
    'total': 1,
    'hadir': 1,
    'belum_hadir': 0,
    'tidak_hadir': 0,
    'hadir_belum_mulai': 0,
    'sedang_mengerjakan': 0,
    'selesai': 0,
    'terblokir': 1,
    'jumlah_pindah_aplikasi': 3,
  },
  'status_kehadiran': [
    {'kode': 'belum_absen', 'label': 'Belum hadir'},
    {'kode': 'hadir', 'label': 'Hadir'},
    {'kode': 'terlambat', 'label': 'Terlambat'},
    {'kode': 'sakit', 'label': 'Sakit'},
    {'kode': 'izin', 'label': 'Izin'},
    {'kode': 'alfa', 'label': 'Alfa'},
  ],
  'peserta': [
    {
      'id': 31,
      'nama': 'Siswa Satu',
      'nisn': '0099000001',
      'kelas': 'IX.A',
      'nomor_peserta': 'UT-001',
      'nomor_meja': 1,
      'status': 'terblokir',
      'label_status': 'Terblokir',
      'kehadiran': 'hadir',
      'label_kehadiran': 'Hadir',
      'jawaban_tersimpan': 18,
      'perangkat_terikat': true,
      'perangkat': 'android-device-001',
      'jumlah_pindah_aplikasi': 3,
      'durasi_di_luar_aplikasi_detik': 16,
      'heartbeat_terakhir_pada': '2026-09-05T08:15:00+07:00',
      'ditahan_mode_aman_pada': '2026-09-05T08:14:55+07:00',
    },
  ],
  'bukti': [
    {
      'id': 51,
      'jenis': 'daftar_hadir',
      'label_jenis': 'Daftar hadir',
      'nama_file': 'daftar-hadir.jpg',
      'tipe_file': 'image/jpeg',
      'ukuran': 120000,
      'ukuran_ringkas': '117 KB',
      'diunggah_pada': '2026-09-05T09:05:00+07:00',
      'diunggah_oleh': 'Guru Pengawas',
    },
  ],
  'kemampuan': {
    'mengelola_ruang': true,
    'mengubah_kehadiran': true,
    'mereset_perangkat': true,
    'membuka_mode_aman': true,
    'mengubah_bukti': true,
    'mengirim_bukti': true,
  },
  'dihasilkan_pada': '2026-09-05T08:15:00+07:00',
};
