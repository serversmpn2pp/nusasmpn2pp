import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_correction/data/central_exam_correction_repository.dart';
import 'package:nusa/features/central_exam_correction/presentation/central_exam_correction_view.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_correction.dart';

void main() {
  testWidgets('skor uraian terpusat tersimpan otomatis pada layar sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 720);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repository = _FakeRepository(canCorrect: true);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          centralExamCorrectionRepositoryProvider.overrideWithValue(repository),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const CentralExamCorrectionView(eventId: 7, scheduleId: 11),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Koreksi Uraian Terpusat'), findsOneWidget);
    expect(find.textContaining('disimpan otomatis'), findsOneWidget);
    final field = find.byKey(const Key('central-correction-score-71'));
    await tester.scrollUntilVisible(
      field,
      300,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.enterText(field, '8');
    await tester.pump(const Duration(milliseconds: 500));
    expect(repository.saveCalls, 0);
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pumpAndSettle();

    expect(repository.saveCalls, 1);
    expect(repository.lastScores.single.answerId, 71);
    expect(repository.lastScores.single.score, 8);
    expect(find.textContaining('Tersimpan otomatis'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('panitia hanya melihat koreksi tanpa kolom skor', (tester) async {
    tester.view.physicalSize = const Size(320, 720);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          centralExamCorrectionRepositoryProvider.overrideWithValue(
            _FakeRepository(canCorrect: false),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const CentralExamCorrectionView(eventId: 7, scheduleId: 11),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('Mode lihat'), findsOneWidget);
    expect(find.byKey(const Key('central-correction-score-71')), findsNothing);
    await tester.drag(find.byType(ListView), const Offset(0, -500));
    await tester.pumpAndSettle();
    expect(find.textContaining('Skor: Belum dikoreksi'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _FakeRepository implements CentralExamCorrectionRepository {
  _FakeRepository({required this.canCorrect});

  final bool canCorrect;
  int saveCalls = 0;
  List<AssessmentScorePayload> lastScores = [];

  @override
  Future<AssessmentCorrectionData> corrections({
    required int eventId,
    required int scheduleId,
    required int? classId,
    required String status,
  }) async => AssessmentCorrectionData.fromJson(
    _payload(canCorrect: canCorrect, corrected: false),
  );

  @override
  Future<AssessmentCorrectionData> saveCorrections({
    required int eventId,
    required int scheduleId,
    required int? classId,
    required String status,
    required List<AssessmentScorePayload> scores,
  }) async {
    saveCalls++;
    lastScores = scores;
    return AssessmentCorrectionData.fromJson(
      _payload(canCorrect: canCorrect, corrected: true),
    );
  }
}

Map<String, dynamic> _payload({
  required bool canCorrect,
  required bool corrected,
}) => {
  'dihasilkan_pada': '2026-09-05T13:00:00+07:00',
  'dapat_mengoreksi': canCorrect,
  'asesmen': {
    'id': 21,
    'nama': 'Paket Matematika IX',
    'kode': 'PKT-MTK-9',
    'mata_pelajaran': 'Matematika',
    'tahun_pelajaran': '2026/2027',
    'semester': 'ganjil',
    'tingkat': 9,
    'status': 'berlangsung',
    'label_status': 'Berlangsung',
    'durasi_menit': 90,
    'kkm': 75,
    'jumlah_soal_paket': 1,
    'jumlah_soal_tampil': 1,
    'kelas': [
      {'id': 3, 'nama': 'IX.A', 'komponen_nilai': 'STS Ganjil'},
    ],
  },
  'jumlah_soal_manual': 1,
  'ringkasan': {
    'total': 1,
    'terjawab': 1,
    'belum_dijawab': 0,
    'belum_dikoreksi': corrected ? 0 : 1,
    'sudah_dikoreksi': corrected ? 1 : 0,
  },
  'referensi': {
    'kelas': [
      {'id': 3, 'label': 'IX.A'},
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
      'peserta_id': 31,
      'siswa': {
        'id': 4,
        'nama': 'Alya Nusa',
        'nis': '26001',
        'nisn': '009900001',
        'nomor_absen': 1,
      },
      'kelas': 'IX.A',
      'soal': {
        'id': 61,
        'nomor': 1,
        'kode': 'URAIAN-001',
        'jenis': 'uraian',
        'label_jenis': 'Uraian',
        'pertanyaan': 'Jelaskan langkah penyelesaian persamaan.',
        'rubrik': 'Langkah dan hasil akhir harus benar.',
        'bobot': 10,
      },
      'jawaban': 'Pindahkan ruas lalu bagi koefisien.',
      'sudah_dijawab': true,
      'sudah_dikoreksi': corrected,
      'skor': corrected ? 8 : null,
    },
  ],
};
