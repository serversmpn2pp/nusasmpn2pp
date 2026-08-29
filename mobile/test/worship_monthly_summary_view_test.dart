import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_monthly_summary/data/worship_monthly_summary_remote_data_source.dart';
import 'package:nusa/features/worship_monthly_summary/domain/worship_monthly_summary.dart';
import 'package:nusa/features/worship_monthly_summary/presentation/worship_monthly_summary_view.dart';

void main() {
  test('domain membaca ringkasan bulanan dan angka desimal', () {
    final page = WorshipMonthlySummaryPage.fromJson(_pageJson());

    expect(page.monthLabel, 'Agustus 2026');
    expect(page.summary.target, 4);
    expect(page.summary.percentage, 50.0);
    expect(page.activityDates.length, 2);
    expect(page.classSummaries.single.recorded, 2);
    expect(_pageJson().toString(), isNot(contains('catatan_privat')));
  });

  testWidgets('ringkasan bulanan rapi dan membuka rincian kelas', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeWorshipMonthlySummaryRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          worshipMonthlySummaryRemoteDataSourceProvider.overrideWithValue(
            remote,
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const WorshipMonthlySummaryView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Ringkasan Ibadah Bulanan'), findsOneWidget);
    expect(find.byKey(const Key('worship-monthly-month')), findsOneWidget);
    expect(find.byKey(const Key('worship-monthly-activity')), findsOneWidget);
    expect(find.text('50%'), findsWidgets);
    await tester.scrollUntilVisible(
      find.byKey(const Key('worship-monthly-class-1')),
      260,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('worship-monthly-class-1')));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('worship-monthly-student-1')),
      280,
      scrollable: find.byType(Scrollable).first,
    );

    expect(find.textContaining('Siswa A Hadir'), findsOneWidget);
    expect(find.text('Manual'), findsWidgets);
    expect(find.text('Informasi privat'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('pemilih bulan membatasi bulan laporan', (tester) async {
    final remote = _FakeWorshipMonthlySummaryRemoteDataSource();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          worshipMonthlySummaryRemoteDataSourceProvider.overrideWithValue(
            remote,
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const WorshipMonthlySummaryView(),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('worship-monthly-month')));
    await tester.pumpAndSettle();

    expect(find.text('Pilih Bulan'), findsOneWidget);
    expect(find.byKey(const Key('worship-month-2026-8')), findsOneWidget);
    expect(
      tester
          .widget<IconButton>(
            find.byKey(const Key('worship-monthly-next-year')),
          )
          .onPressed,
      isNull,
    );
    expect(tester.takeException(), isNull);
  });
}

Map<String, dynamic> _pageJson({int? classId}) => {
  'tersedia': true,
  'bulan': '2026-08',
  'bulan_label': 'Agustus 2026',
  'bulan_minimum': '2026-07',
  'bulan_maksimum': '2026-08',
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'kegiatan_dipilih': {
    'id': 1,
    'nama': 'Sholat Duhur Berjamaah',
    'kode': 'sholat_duhur',
    'aktif': true,
  },
  'kelas_dipilih': classId == null
      ? null
      : {'id': 1, 'nama': 'VII.A', 'tingkat': 7},
  'referensi': {
    'kegiatan': [
      {'id': 1, 'nama': 'Sholat Duhur Berjamaah', 'aktif': true},
    ],
    'kelas': [
      {'id': 1, 'nama': 'VII.A', 'tingkat': 7},
    ],
  },
  'tanggal_kegiatan': [
    {'tanggal': '2026-08-06', 'label': '06 Agt'},
    {'tanggal': '2026-08-13', 'label': '13 Agt'},
  ],
  'ringkasan': {
    'kelas': 1,
    'siswa': 2,
    'hari_kegiatan': 2,
    'target': 4,
    'tercatat': 2,
    'belum': 2,
    'persentase': 50.0,
  },
  'ringkasan_kelas': [
    {
      'kelas': {'id': 1, 'nama': 'VII.A', 'tingkat': 7},
      'siswa': 2,
      'target': 4,
      'tercatat': 2,
      'belum': 2,
      'persentase': 50.0,
    },
  ],
  'items': classId == null
      ? const []
      : [
          {
            'anggota_kelas_id': 1,
            'nomor_absen': 1,
            'siswa': {
              'id': 1,
              'nama': 'Siswa A Hadir',
              'nis': '26001',
              'nisn': '0131201150',
              'foto_url': null,
            },
            'target': 2,
            'tercatat': 2,
            'belum': 0,
            'manual': 1,
            'terakhir': '2026-08-13',
            'terakhir_label': '13 Agt 2026',
            'persentase': 100.0,
          },
          {
            'anggota_kelas_id': 2,
            'nomor_absen': 2,
            'siswa': {
              'id': 2,
              'nama': 'Siswa A Belum',
              'nis': '26002',
              'nisn': '0131201151',
              'foto_url': null,
            },
            'target': 2,
            'tercatat': 0,
            'belum': 2,
            'manual': 0,
            'terakhir': null,
            'terakhir_label': null,
            'persentase': 0.0,
          },
        ],
  'catatan_perhitungan': 'Tanggal kegiatan dihitung sampai hari ini. Tanggal libur belum dikecualikan.',
  'pesan_privasi':
      'Status dan catatan berhalangan tidak ditampilkan pada ringkasan umum.',
};

final class _FakeWorshipMonthlySummaryRemoteDataSource
    implements WorshipMonthlySummaryRemoteDataSource {
  @override
  Future<WorshipMonthlySummaryPage> fetch({
    required String? month,
    required int? activityId,
    required int? classId,
  }) async => WorshipMonthlySummaryPage.fromJson(_pageJson(classId: classId));
}
