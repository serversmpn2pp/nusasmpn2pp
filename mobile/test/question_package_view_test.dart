import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/question_package/data/question_package_remote_data_source.dart';
import 'package:nusa/features/question_package/domain/question_package.dart';
import 'package:nusa/features/question_package/presentation/question_package_detail_view.dart';
import 'package:nusa/features/question_package/presentation/question_package_list_view.dart';

void main() {
  test('domain membaca jadwal paket, urutan, bobot, dan akses', () {
    final page = QuestionPackagePage.fromJson(_pageJson());
    final detail = QuestionPackageDetail.fromJson(_detailJson());
    expect(page.summary.unbuilt, 1);
    expect(page.items.single.event.name, 'STS Ganjil');
    expect(detail.questions.single.order, 1);
    expect(detail.questions.single.weight, 2.5);
    expect(detail.access.canEdit, isTrue);
  });

  testWidgets('daftar paket rapi pada layar Android sempit', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          questionPackageRemoteDataSourceProvider.overrideWithValue(
            _FakeQuestionPackageRemoteDataSource(),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const QuestionPackageListView(),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Paket Soal'), findsOneWidget);
    expect(find.text('Matematika · Tingkat 8'), findsOneWidget);
    expect(
      find.byKey(const Key('question-package-event-filter')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('guru menerbitkan susunan paket dengan konfirmasi', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeQuestionPackageRemoteDataSource();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          questionPackageRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const QuestionPackageDetailView(scheduleId: 11),
        ),
      ),
    );
    await tester.pumpAndSettle();

    tester
        .widget<FilledButton>(
          find.byKey(const Key('question-package-save-primary')),
        )
        .onPressed
        ?.call();
    await tester.pumpAndSettle();
    expect(find.text('Terbitkan paket?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('question-package-confirm-publish')));
    await tester.pumpAndSettle();

    expect(remote.saveCalls, 1);
    expect(remote.lastPayload?.action, 'terbitkan');
    expect(remote.lastPayload?.questions.single.id, 21);
    expect(remote.lastPayload?.questions.single.weight, 2.5);
    expect(tester.takeException(), isNull);
  });
}

class _FakeQuestionPackageRemoteDataSource
    implements QuestionPackageRemoteDataSource {
  int saveCalls = 0;
  QuestionPackagePayload? lastPayload;
  @override
  Future<QuestionPackagePage> fetch({
    required String query,
    required int? eventId,
    required String status,
    required int page,
  }) async => QuestionPackagePage.fromJson(_pageJson());
  @override
  Future<QuestionPackageDetail> detail(int scheduleId) async =>
      QuestionPackageDetail.fromJson(_detailJson());
  @override
  Future<QuestionPackageDetail> save(
    int scheduleId,
    QuestionPackagePayload payload,
  ) async {
    saveCalls++;
    lastPayload = payload;
    return QuestionPackageDetail.fromJson(_detailJson(ready: true));
  }
}

Map<String, dynamic> _scheduleJson() => {
  'id': 11,
  'kegiatan': {
    'id': 4,
    'nama': 'STS Ganjil',
    'jenis': 'Sumatif Tengah Semester',
    'tahun_pelajaran': '2026/2027',
    'semester': 'ganjil',
  },
  'mata_pelajaran': 'Matematika',
  'tingkat': 8,
  'kelas': ['VIII.A'],
  'tanggal': '2026-09-15',
  'waktu': '07:30 - 09:30',
  'sesi': 'Sesi Pagi',
  'status': 'draft',
  'label_status': 'Masih draf',
  'jumlah_soal': 1,
  'total_bobot': 2.5,
  'dapat_kelola': true,
};

Map<String, dynamic> _pageJson() => {
  'ringkasan': {'total': 2, 'siap': 0, 'draft': 1, 'belum_disusun': 1},
  'items': [_scheduleJson()],
  'referensi': {
    'kegiatan': [
      {
        'id': 4,
        'nama': 'STS Ganjil',
        'jenis': 'Sumatif Tengah Semester',
        'tahun_pelajaran': '2026/2027',
      },
    ],
    'status': [
      {'kode': 'semua', 'label': 'Semua status'},
      {'kode': 'draft', 'label': 'Masih draf'},
    ],
  },
  'filter': {'kata_kunci': '', 'kegiatan_id': null, 'status': 'semua'},
  'paginasi': {
    'halaman': 1,
    'halaman_terakhir': 1,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
};

Map<String, dynamic> _detailJson({bool ready = false}) => {
  'jadwal': {..._scheduleJson(), if (ready) 'status': 'siap'},
  'paket': ready
      ? {
          'id': 9,
          'kode': 'UT-4-JADWAL-11',
          'nama': 'STS Ganjil - Matematika Tingkat 8',
          'status': 'terjadwal',
          'label_status': 'Terjadwal',
          'acak_soal': true,
          'acak_jawaban': true,
          'token': '123456',
          'durasi_menit': 120,
          'kkm': 78,
        }
      : null,
  'soal': [
    {
      'id': 21,
      'kode': 'SOAL-21',
      'jenis_soal': 'pilihan_ganda',
      'label_jenis_soal': 'Pilihan Ganda',
      'tingkat_kesulitan': 'sedang',
      'label_tingkat_kesulitan': 'Sedang',
      'topik': 'Bilangan',
      'materi': 'Penjumlahan',
      'pertanyaan': 'Berapakah hasil 2 + 2?',
      'skor_maksimal': 2.5,
      'dipilih': true,
      'bobot': 2.5,
      'nomor_urut': 1,
      'dapat_dipilih': true,
      'jawaban': 'B',
      'gambar_url': null,
    },
  ],
  'referensi': {
    'jenis_soal': [
      {'kode': 'pilihan_ganda', 'label': 'Pilihan Ganda'},
    ],
    'tingkat_kesulitan': [
      {'kode': 'sedang', 'label': 'Sedang'},
    ],
  },
  'hak_akses': {
    'dapat_kelola': true,
    'dapat_ubah': true,
    'sudah_dikerjakan': false,
  },
};
