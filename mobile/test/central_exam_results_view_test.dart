import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/features/central_exam_results/application/central_exam_results_controller.dart';
import 'package:nusa/features/central_exam_results/domain/central_exam_results.dart';
import 'package:nusa/features/central_exam_results/presentation/central_exam_results_detail_view.dart';

void main() {
  test('domain membaca hasil final ujian terpusat', () {
    final data = CentralExamResultsDetail.fromJson(_payload());

    expect(data.event.name, 'STS Semester Ganjil');
    expect(data.selectedScheduleId, 11);
    expect(data.results.summary.average, 82.5);
    expect(data.results.items.single.status, 'tuntas');
    expect(data.canApply, isTrue);
    expect(data.finalization.status, 'final');
    expect(data.finalization.canPublish, isTrue);
  });

  testWidgets('hasil ujian terpusat rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 740);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    const request = (
      eventId: 7,
      scheduleId: null,
      classId: null,
      status: 'semua',
    );
    final data = CentralExamResultsDetail.fromJson(_payload());

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          centralExamResultsDetailProvider(request)
              .overrideWith((ref) async => data),
        ],
        child: const MaterialApp(
          home: CentralExamResultsDetailView(eventId: 7),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('STS Semester Ganjil'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('central-results-publish')),
      180,
    );
    expect(find.byKey(const Key('central-results-publish')), findsOneWidget);
    await tester.ensureVisible(
      find.byKey(const Key('central-results-publish')),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('central-results-publish')));
    await tester.pumpAndSettle();
    expect(find.text('Publikasikan hasil?'), findsOneWidget);
    await tester.tap(find.text('Batal'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('central-results-apply')),
      180,
    );
    expect(find.byKey(const Key('central-results-apply')), findsOneWidget);
    expect(find.byKey(const Key('central-results-correction')), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.ensureVisible(find.byKey(const Key('central-results-apply')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('central-results-apply')));
    await tester.pumpAndSettle();
    expect(find.text('Terapkan hasil ke nilai?'), findsOneWidget);
    await tester.tap(find.text('Batal'));
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView), const Offset(0, -560));
    await tester.pumpAndSettle();
    expect(find.text('Nilai Peserta'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Map<String, dynamic> _payload() => {
  'kegiatan': {
    'id': 7,
    'kode': 'STS-2627',
    'nama': 'STS Semester Ganjil',
    'jenis': 'Sumatif Tengah Semester',
    'tahun_pelajaran': '2026/2027',
    'semester': 'Ganjil',
    'periode': '10-09-2026 sampai 15-09-2026',
    'status': 'aktif',
    'label_status': 'Aktif',
  },
  'jadwal': [
    {
      'id': 11,
      'label': '10-09-2026 · 07:30 · Matematika · Kelas 8',
      'mata_pelajaran': 'Matematika',
      'tanggal': '2026-09-10',
      'waktu': '07:30 - 09:00',
      'tingkat': 8,
      'jumlah_peserta': 1,
      'dapat_menerapkan_nilai': true,
      'status_hasil': 'final',
      'paket_tersedia': true,
    },
  ],
  'jadwal_terpilih_id': 11,
  'dapat_menerapkan_nilai': true,
  'finalisasi': {
    'status': 'final',
    'label_status': 'Final',
    'dapat_mengelola': true,
    'siap_difinalisasi': true,
    'dapat_finalisasi': false,
    'dapat_batalkan_finalisasi': true,
    'dapat_publikasi': true,
    'dapat_batalkan_publikasi': false,
    'difinalisasi_pada': '2026-09-10T10:00:00+07:00',
    'difinalisasi_oleh': 'Administrator NUSA',
    'dipublikasikan_pada': null,
    'dipublikasikan_oleh': null,
    'kesiapan': {
      'siap': true,
      'total_peserta': 1,
      'peserta_wajib_selesai': 1,
      'peserta_selesai': 1,
      'peserta_belum_selesai': 0,
      'peserta_tidak_hadir': 0,
      'perlu_koreksi_manual': 0,
      'jumlah_soal': 40,
    },
  },
  'hasil': {
    'asesmen': {
      'id': 21,
      'nama': 'Paket Matematika VIII',
      'kode': 'PKT-MTK-8',
      'mata_pelajaran': 'Matematika',
      'tahun_pelajaran': '2026/2027',
      'semester': 'ganjil',
      'tingkat': 8,
      'status': 'selesai',
      'label_status': 'Selesai',
      'durasi_menit': 90,
      'kkm': 75,
      'jumlah_soal_paket': 40,
      'jumlah_soal_tampil': 40,
      'kelas': [
        {'id': 3, 'nama': 'VIII.A', 'komponen_nilai': 'STS Ganjil'},
      ],
    },
    'jumlah_soal': 40,
    'bobot_total': 100,
    'ringkasan': {
      'total_peserta': 1,
      'selesai': 1,
      'hasil_final': 1,
      'rata_rata_final': 82.5,
      'nilai_tertinggi_final': 82.5,
      'nilai_terendah_final': 82.5,
      'tuntas': 1,
      'belum_tuntas': 0,
      'perlu_koreksi': 0,
      'belum_selesai': 0,
      'sudah_masuk_nilai': 0,
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
        'id': 31,
        'siswa': {
          'id': 4,
          'nama': 'Alya Nusa',
          'nis': '26001',
          'nisn': '009900001',
          'nomor_absen': 1,
        },
        'kelas': 'VIII.A',
        'status_pengerjaan': 'selesai',
        'label_status_pengerjaan': 'Selesai',
        'jawaban_tersimpan': 40,
        'jawaban_dikoreksi': 40,
        'benar': 33,
        'salah': 7,
        'belum_jawab': 0,
        'perlu_koreksi_otomatis': 0,
        'perlu_koreksi_manual': 0,
        'skor_total': 82.5,
        'nilai': 82.5,
        'status_nilai': 'akhir',
        'status': 'tuntas',
        'label_status': 'Tuntas',
        'nada_status': 'aktif',
        'nilai_sudah_diterapkan': false,
      },
    ],
  },
};
