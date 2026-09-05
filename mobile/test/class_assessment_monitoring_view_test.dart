import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/class_assessment/data/class_assessment_monitoring_remote_data_source.dart';
import 'package:nusa/features/class_assessment/data/class_assessment_operations_remote_data_source.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_correction.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_monitoring.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_correction_view.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_monitoring_view.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_results_view.dart';

void main() {
  test('domain membaca monitoring dan hasil asesmen kelas', () {
    final monitoring = AssessmentMonitoringData.fromJson(_monitoringJson());
    final results = AssessmentResultsData.fromJson(_resultsJson());
    final corrections = AssessmentCorrectionData.fromJson(_correctionJson());

    expect(monitoring.summary.finishedPercent, 50);
    expect(monitoring.items.single.remainingMinutes, 25);
    expect(results.summary.average, 90);
    expect(results.items.single.status, 'tuntas');
    expect(results.items.single.appliedToGrades, isTrue);
    expect(corrections.summary.pending, 1);
    expect(corrections.items.single.question.weight, 50);
    expect(corrections.items.single.answer, 'Langkah jawaban siswa');
  });

  testWidgets('monitoring asesmen rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          classAssessmentMonitoringRemoteDataSourceProvider.overrideWithValue(
            _FakeMonitoringRemoteDataSource(),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const ClassAssessmentMonitoringView(assessmentId: 9),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Monitoring Asesmen'), findsOneWidget);
    await tester.tap(
      find.byKey(const Key('assessment-monitoring-auto-refresh')),
    );
    await tester.pump();
    expect(find.text('Pembaruan otomatis sedang dimatikan.'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.byKey(const Key('assessment-monitoring-class-filter')),
      300,
      scrollable: find.byType(Scrollable).last,
    );
    expect(
      find.byKey(const Key('assessment-monitoring-class-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('assessment-monitoring-status-filter')),
      findsOneWidget,
    );

    await tester.scrollUntilVisible(
      find.byKey(const Key('assessment-monitoring-participant-41')),
      300,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Siswa NUSA'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('hasil asesmen menampilkan nilai dan status koreksi', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final operations = _FakeOperationsRemoteDataSource();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          classAssessmentMonitoringRemoteDataSourceProvider.overrideWithValue(
            _FakeMonitoringRemoteDataSource(),
          ),
          classAssessmentOperationsRemoteDataSourceProvider.overrideWithValue(
            operations,
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const ClassAssessmentResultsView(assessmentId: 9),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Hasil Asesmen'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('assessment-results-class-filter')),
      300,
      scrollable: find.byType(Scrollable).last,
    );
    expect(
      find.byKey(const Key('assessment-results-class-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('assessment-results-status-filter')),
      findsOneWidget,
    );
    await tester.scrollUntilVisible(
      find.byKey(const Key('assessment-result-41')),
      300,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('90'), findsWidgets);
    expect(find.text('Sudah masuk nilai'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('assessment-results-apply')),
      -300,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(find.byKey(const Key('assessment-results-apply')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('assessment-results-confirm-apply')));
    await tester.pumpAndSettle();
    expect(operations.applyCalls, 1);
    expect(find.textContaining('1 hasil asesmen berhasil'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('guru menyimpan skor koreksi uraian secara native', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final operations = _FakeOperationsRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          classAssessmentOperationsRemoteDataSourceProvider.overrideWithValue(
            operations,
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const ClassAssessmentCorrectionView(assessmentId: 9),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Koreksi Uraian'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('assessment-correction-jawaban-71')),
      350,
      scrollable: find.byType(Scrollable).last,
    );
    final scoreField = find.byKey(
      const Key('assessment-correction-score-71-null'),
    );
    await tester.scrollUntilVisible(
      scoreField,
      250,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.enterText(scoreField, '45');
    await tester.pump();
    await tester.tap(find.byKey(const Key('assessment-correction-save')));
    await tester.pumpAndSettle();

    expect(operations.saveCalls, 1);
    expect(operations.lastScores.single.answerId, 71);
    expect(operations.lastScores.single.score, 45);
    expect(find.textContaining('1 koreksi jawaban berhasil'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _FakeMonitoringRemoteDataSource
    implements ClassAssessmentMonitoringRemoteDataSource {
  @override
  Future<void> unlockParticipant(int participantId) async {}

  @override
  Future<AssessmentMonitoringData> monitoring({
    required int assessmentId,
    required int? classId,
    required String status,
  }) async => AssessmentMonitoringData.fromJson(_monitoringJson());

  @override
  Future<AssessmentResultsData> results({
    required int assessmentId,
    required int? classId,
    required String status,
  }) async => AssessmentResultsData.fromJson(_resultsJson());
}

class _FakeOperationsRemoteDataSource
    implements ClassAssessmentOperationsRemoteDataSource {
  int saveCalls = 0;
  int applyCalls = 0;
  List<AssessmentScorePayload> lastScores = [];

  @override
  Future<AssessmentCorrectionData> corrections({
    required int assessmentId,
    required int? classId,
    required String status,
  }) async => AssessmentCorrectionData.fromJson(_correctionJson());

  @override
  Future<AssessmentCorrectionData> saveCorrections({
    required int assessmentId,
    required int? classId,
    required String status,
    required List<AssessmentScorePayload> scores,
  }) async {
    saveCalls++;
    lastScores = scores;
    return AssessmentCorrectionData.fromJson(_correctionJson(score: 45));
  }

  @override
  Future<AssessmentApplyResult> applyResults(int assessmentId) async {
    applyCalls++;
    return const AssessmentApplyResult(
      message: '1 hasil asesmen berhasil dimasukkan ke nilai siswa.',
      applied: 1,
      notFinished: 0,
      automaticCorrectionPending: 0,
      manualCorrectionPending: 0,
      invalidTarget: 0,
    );
  }
}

Map<String, dynamic> _monitoringJson() => {
  'dihasilkan_pada': '2026-09-10T08:25:00+07:00',
  'pembaruan_berikutnya_detik': 15,
  'asesmen': _assessmentJson(),
  'kesiapan': {'paket_dibuka': true, 'soal_siap': true, 'peserta_siap': true},
  'ringkasan': {
    'total': 2,
    'belum_hadir': 0,
    'hadir_belum_mulai': 0,
    'tidak_hadir': 0,
    'sedang_mengerjakan': 1,
    'selesai': 1,
    'nonaktif': 0,
    'terblokir': 0,
  },
  'referensi': {
    'kelas': [
      {'id': 3, 'label': 'VIII.A'},
    ],
    'status': [
      {'kode': 'semua', 'label': 'Semua status'},
      {'kode': 'sedang_mengerjakan', 'label': 'Sedang mengerjakan'},
    ],
  },
  'filter': {'kelas_id': null, 'status': 'semua'},
  'items': [
    {
      'id': 41,
      'siswa': _studentJson(),
      'kelas': 'VIII.A',
      'status': 'sedang_mengerjakan',
      'label_status': 'Sedang mengerjakan',
      'nada_status': 'aktif',
      'kehadiran': 'hadir',
      'label_kehadiran': 'Hadir',
      'nada_kehadiran': 'aktif',
      'jawaban_tersimpan': 2,
      'jawaban_ragu': 1,
      'persen_jawaban': 100,
      'waktu_mulai': '2026-09-10T08:10:00+07:00',
      'waktu_selesai': null,
      'sisa_menit': 25,
    },
  ],
};

Map<String, dynamic> _resultsJson() => {
  'dihasilkan_pada': '2026-09-10T08:25:00+07:00',
  'asesmen': _assessmentJson(),
  'jumlah_soal': 2,
  'bobot_total': 100,
  'ringkasan': {
    'total_peserta': 1,
    'selesai': 1,
    'hasil_final': 1,
    'rata_rata_final': 90,
    'nilai_tertinggi_final': 90,
    'nilai_terendah_final': 90,
    'tuntas': 1,
    'belum_tuntas': 0,
    'perlu_koreksi': 0,
    'belum_selesai': 0,
    'sudah_masuk_nilai': 1,
  },
  'referensi': {
    'kelas': [
      {'id': 3, 'label': 'VIII.A'},
    ],
    'status': [
      {'kode': 'semua', 'label': 'Semua hasil'},
      {'kode': 'tuntas', 'label': 'Tuntas'},
    ],
  },
  'filter': {'kelas_id': null, 'status': 'semua'},
  'items': [
    {
      'id': 41,
      'siswa': _studentJson(),
      'kelas': 'VIII.A',
      'status_pengerjaan': 'selesai',
      'label_status_pengerjaan': 'Selesai',
      'waktu_selesai': '2026-09-10T08:20:00+07:00',
      'jawaban_tersimpan': 2,
      'jawaban_dikoreksi': 2,
      'benar': 2,
      'salah': 0,
      'belum_jawab': 0,
      'perlu_koreksi_otomatis': 0,
      'perlu_koreksi_manual': 0,
      'skor_total': 90,
      'nilai': 90,
      'status_nilai': 'akhir',
      'status': 'tuntas',
      'label_status': 'Tuntas',
      'nada_status': 'aktif',
      'nilai_sudah_diterapkan': true,
      'nilai_diterapkan_pada': '2026-09-10T08:24:00+07:00',
    },
  ],
};

Map<String, dynamic> _correctionJson({double? score}) => {
  'dihasilkan_pada': '2026-09-10T08:25:00+07:00',
  'asesmen': _assessmentJson(),
  'jumlah_soal_manual': 1,
  'ringkasan': {
    'total': 1,
    'terjawab': 1,
    'belum_dijawab': 0,
    'belum_dikoreksi': score == null ? 1 : 0,
    'sudah_dikoreksi': score == null ? 0 : 1,
  },
  'referensi': {
    'kelas': [
      {'id': 3, 'label': 'VIII.A'},
    ],
    'status': [
      {'kode': 'semua', 'label': 'Semua jawaban'},
      {'kode': 'belum_dikoreksi', 'label': 'Belum dikoreksi'},
      {'kode': 'sudah_dikoreksi', 'label': 'Sudah dikoreksi'},
    ],
  },
  'filter': {'kelas_id': null, 'status': 'semua'},
  'items': [
    {
      'id': 'jawaban-71',
      'jawaban_id': 71,
      'peserta_id': 41,
      'siswa': _studentJson(),
      'kelas': 'VIII.A',
      'soal': {
        'id': 22,
        'nomor': 2,
        'kode': 'SOAL-AKM-002',
        'jenis': 'uraian',
        'label_jenis': 'Uraian',
        'pertanyaan': 'Jelaskan langkah penyelesaian persamaan.',
        'rubrik': 'Langkah dan hasil harus benar.',
        'bobot': 50,
      },
      'jawaban': 'Langkah jawaban siswa',
      'sudah_dijawab': true,
      'sudah_dikoreksi': score != null,
      'skor': score,
    },
  ],
};

Map<String, dynamic> _assessmentJson() => {
  'id': 9,
  'nama': 'Sumatif Bab Persamaan',
  'kode': 'AKM-009',
  'mata_pelajaran': 'Matematika',
  'tahun_pelajaran': '2026/2027',
  'semester': 'ganjil',
  'tingkat': 8,
  'status': 'berlangsung',
  'label_status': 'Berlangsung',
  'tanggal_mulai': '2026-09-10T08:00:00+07:00',
  'tanggal_selesai': '2026-09-10T09:00:00+07:00',
  'durasi_menit': 40,
  'kkm': 78,
  'jumlah_soal_paket': 2,
  'jumlah_soal_tampil': 2,
  'kelas': [
    {'id': 3, 'nama': 'VIII.A', 'komponen_nilai': 'Sumatif Bab Persamaan'},
  ],
};

Map<String, dynamic> _studentJson() => {
  'id': 17,
  'nama': 'Siswa NUSA',
  'nis': '260017',
  'nisn': '0099000017',
  'nomor_absen': 1,
};
