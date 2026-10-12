import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/sts_reports/data/sts_reports_remote_data_source.dart';
import 'package:nusa/features/sts_reports/domain/sts_reports.dart';
import 'package:nusa/features/sts_reports/presentation/sts_report_widgets.dart';
import 'package:nusa/features/sts_reports/presentation/sts_reports_view.dart';

import 'fixtures/sts_report_fixture.dart';

void main() {
  testWidgets('report opens student grades, attendance and readiness', (
    tester,
  ) async {
    final remote = _Fake();
    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();
    expect(find.text('Rapor STS'), findsOneWidget);
    expect(find.byKey(const Key('sts-reports-summary')), findsOneWidget);
    expect(find.textContaining('Tanggal rapor: 16 Okt 2026'), findsOneWidget);
    await _tap(tester, 'sts-reports-student-71');
    expect(find.text('Rincian Rapor STS'), findsOneWidget);
    expect(find.text('Alya Demo STS'), findsOneWidget);
    await _visible(tester, find.text('Kehadiran & Lampiran'));
    expect(find.text('Terlambat 2 kali · 18 menit'), findsOneWidget);
    await _visible(tester, find.text('Matematika'));
    expect(find.text('88,5'), findsWidgets);
    await _visible(tester, find.text('Bahasa Indonesia'));
    expect(find.text('Belum tersedia'), findsOneWidget);
    expect(remote.calls.single.kind, StsReportKind.report);
    expect(tester.takeException(), isNull);
  });

  testWidgets('report keeps zero distinct from missing and shows exemption', (
    tester,
  ) async {
    await tester.pumpWidget(_app(_Fake()));
    await tester.pumpAndSettle();
    await _tap(tester, 'sts-reports-student-72');
    await _visible(tester, find.text('Matematika'));
    expect(find.text('0'), findsWidgets);
    await tester.pageBack();
    await tester.pumpAndSettle();
    await _tap(tester, 'sts-reports-student-73');
    await _visible(tester, find.text('Bahasa Indonesia'));
    expect(find.text('Tidak mengikuti STS'), findsOneWidget);
    expect(find.textContaining('sesuai pemeriksaan'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('search and readiness filter run locally, not as mutations', (
    tester,
  ) async {
    final remote = _Fake();
    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();
    await _visible(tester, find.byKey(const Key('sts-reports-search')));
    await tester.enterText(find.byKey(const Key('sts-reports-search')), 'Bima');
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pumpAndSettle();
    await _visible(tester, find.byKey(const Key('sts-reports-student-72')));
    expect(find.byKey(const Key('sts-reports-student-71')), findsNothing);
    await _pick(tester, 'sts-reports-status', 'Siap cetak', delta: -180);
    expect(find.textContaining('Tidak ada siswa yang cocok'), findsOneWidget);
    expect(remote.calls.length, 1);
  });

  testWidgets(
    'activity change clears stale class and search; refresh keeps new scope',
    (tester) async {
      final remote = _Fake();
      await tester.pumpWidget(_app(remote));
      await tester.pumpAndSettle();
      await _visible(tester, find.byKey(const Key('sts-reports-search')));
      await tester.enterText(
        find.byKey(const Key('sts-reports-search')),
        'Tidak cocok',
      );
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pumpAndSettle();
      await _pick(
        tester,
        'sts-reports-activity',
        'STS Semester Genap',
        delta: -250,
      );
      expect(remote.calls.last.filter.activityId, 2);
      expect(remote.calls.last.filter.classId, isNull);
      expect(remote.calls.last.filter.grade, isNull);
      await tester.tap(find.byKey(const Key('sts-reports-refresh')));
      await tester.pumpAndSettle();
      expect(remote.calls.last.filter.activityId, 2);
      expect(remote.calls.last.filter.classId, 8);
      await _visible(tester, find.byKey(const Key('sts-reports-search')));
      expect(
        tester
            .widget<TextField>(find.byKey(const Key('sts-reports-search')))
            .controller!
            .text,
        isEmpty,
      );
    },
  );

  testWidgets(
    'ledger grade mode, limited scope, temporary ranking and server statistics',
    (tester) async {
      final remote = _Fake();
      await tester.pumpWidget(_app(remote, ledger: true));
      await tester.pumpAndSettle();
      expect(find.text('Leger STS'), findsOneWidget);
      await _pick(tester, 'sts-reports-mode', 'Per tingkat');
      expect(remote.calls.last.filter.mode, StsLedgerMode.grade);
      expect(find.byKey(const Key('sts-reports-class')), findsNothing);
      expect(find.byKey(const Key('sts-reports-grade')), findsOneWidget);
      expect(find.textContaining('bukan ranking paralel'), findsOneWidget);
      await _visible(tester, find.textContaining('Ranking masih sementara'));
      await _tap(tester, 'sts-reports-subject-stats');
      expect(find.textContaining('2 nilai · 1 final · 1 draf'), findsOneWidget);
      await _tap(tester, 'sts-reports-class-stats');
      expect(find.textContaining('2/3 siswa masuk ranking'), findsOneWidget);
      await _tap(tester, 'sts-reports-student-71');
      expect(find.text('Nilai Leger STS'), findsOneWidget);
      expect(
        find.text('44,25'),
        findsOneWidget,
      ); // fixed server divisor, not 88.5.
      await _visible(tester, find.text('Matematika'));
      expect(find.textContaining('Draf ·'), findsOneWidget);
      expect(
        remote.calls.every((call) => call.kind == StsReportKind.ledger),
        isTrue,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('grade change and class mode send the selected scope', (
    tester,
  ) async {
    final remote = _Fake();
    await tester.pumpWidget(_app(remote, ledger: true));
    await tester.pumpAndSettle();
    await _pick(tester, 'sts-reports-mode', 'Per tingkat');
    await _pick(tester, 'sts-reports-grade', 'Tingkat 8');
    expect(remote.calls.last.filter.grade, 8);
    expect(remote.calls.last.filter.classId, isNull);
    await _pick(tester, 'sts-reports-mode', 'Per kelas');
    await _pick(tester, 'sts-reports-class', 'VIII.B');
    expect(remote.calls.last.filter.classId, 8);
    expect(remote.calls.last.filter.mode, StsLedgerMode.classGroup);
    expect(remote.calls.last.filter.query(StsReportKind.ledger), {
      'kegiatan_id': 1,
      'kelas_id': 8,
      'mode': 'kelas',
    });
  });

  testWidgets(
    'failed class switch hides previous records and retries exact request',
    (tester) async {
      final remote = _Fake();
      await tester.pumpWidget(_app(remote));
      await tester.pumpAndSettle();
      await _visible(tester, find.byKey(const Key('sts-reports-student-71')));
      remote.error = const NetworkException(
        'Anda tidak memiliki hak akses.',
        statusCode: 403,
      );
      await _pick(tester, 'sts-reports-class', 'VIII.B', delta: -240);
      expect(find.byKey(const Key('sts-reports-student-71')), findsNothing);
      expect(find.text('Anda tidak memiliki hak akses.'), findsOneWidget);
      final failedFilter = remote.calls.last.filter;
      remote.error = null;
      await tester.tap(find.text('Coba lagi'));
      await tester.pumpAndSettle();
      expect(remote.calls.last.filter, same(failedFilter));
      expect(remote.calls.last.filter.classId, 8);
      await _visible(tester, find.byKey(const Key('sts-reports-student-71')));
      expect(find.text('VIII.B · Absen 1'), findsOneWidget);
    },
  );

  testWidgets(
    'empty scope and info explicitly describe mobile read-only scope',
    (tester) async {
      final remote = _Fake()..empty = true;
      await tester.pumpWidget(_app(remote));
      await tester.pumpAndSettle();
      expect(find.textContaining('Belum ada kegiatan STS'), findsOneWidget);
      expect(find.byType(StsReportStudentCard), findsNothing);
      await tester.tap(find.byKey(const Key('sts-reports-info')));
      await tester.pumpAndSettle();
      expect(find.text('Tentang Rapor & Leger STS'), findsOneWidget);
      expect(
        find.textContaining('resmi rapor/leger tetap melalui web'),
        findsOneWidget,
      );
    },
  );

  testWidgets(
    'unready period shows missing setup without claiming print readiness',
    (tester) async {
      await tester.pumpWidget(_app(_Fake()..readyPeriod = false));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('Periode rapor belum disimpan.'),
        findsOneWidget,
      );
      expect(
        find.textContaining('Periode presensi belum berakhir.'),
        findsOneWidget,
      );
      expect(
        find.textContaining('Wali kelas belum ditetapkan.'),
        findsOneWidget,
      );
      await _tap(tester, 'sts-reports-student-71');
      expect(find.textContaining('Belum siap cetak.'), findsOneWidget);
      expect(find.textContaining('Siap cetak melalui web.'), findsNothing);
    },
  );

  testWidgets('report card refuses a draft score from a mismatched API', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: StsReportGradeCard(
            kind: StsReportKind.report,
            subjectName: 'Matematika',
            grade: StsReportGrade.fromJson({
              'mapel_id': 11,
              'nilai': 88.5,
              'draf': true,
              'keterangan': 'Nilai manual draf',
            }),
          ),
        ),
      ),
    );
    expect(find.text('88,5'), findsNothing);
    expect(find.text('—'), findsOneWidget);
    expect(find.textContaining('nilai belum final'), findsOneWidget);
  });

  for (final ledger in [false, true]) {
    for (final scale in [1.0, 1.5, 2.0]) {
      testWidgets(
        '${ledger ? 'ledger' : 'report'} handles 320px and text scale $scale',
        (tester) async {
          await tester.binding.setSurfaceSize(const Size(320, 700));
          addTearDown(() => tester.binding.setSurfaceSize(null));
          await tester.pumpWidget(_app(_Fake(), ledger: ledger, scale: scale));
          await tester.pumpAndSettle();
          await _tap(tester, 'sts-reports-student-71');
          await _visible(tester, find.text('Bahasa Indonesia'));
          expect(tester.takeException(), isNull);
        },
      );
    }
  }

  if (Platform.environment['NUSA_REPORTS_PREVIEW'] == '1') {
    testWidgets('export report and ledger previews using synthetic data', (
      tester,
    ) async {
      final fonts = Platform.environment['NUSA_UI_FONT_DIR']!;
      await tester.runAsync(() async {
        final loader = FontLoader('Roboto');
        for (final weight in ['regular', 'medium', 'bold', 'black']) {
          loader.addFont(
            Future.value(
              ByteData.sublistView(
                await File('$fonts/roboto-$weight.ttf').readAsBytes(),
              ),
            ),
          );
        }
        await loader.load();
        await (FontLoader('MaterialIcons')..addFont(
              Future.value(
                ByteData.sublistView(
                  await File('$fonts/materialicons-regular.otf').readAsBytes(),
                ),
              ),
            ))
            .load();
      });
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      const key = Key('reports-preview');
      for (final ledger in [false, true]) {
        await tester.pumpWidget(const SizedBox.shrink());
        await tester.pumpWidget(
          RepaintBoundary(
            key: key,
            child: _app(_Fake(), ledger: ledger),
          ),
        );
        await tester.pumpAndSettle();
        await _export(tester, key, ledger ? 'sts-leger' : 'sts-rapor');
        await _tap(tester, 'sts-reports-student-71');
        await _export(
          tester,
          key,
          ledger ? 'sts-leger-detail' : 'sts-rapor-detail',
        );
      }
      expect(tester.takeException(), isNull);
    });
  }
}

Future<void> _export(WidgetTester tester, Key key, String name) async {
  final boundary = tester.renderObject<RenderRepaintBoundary>(find.byKey(key));
  await tester.runAsync(() async {
    final image = await boundary.toImage(pixelRatio: 2);
    final data = (await image.toByteData(format: ui.ImageByteFormat.png))!;
    await Directory('build/ui-preview').create(recursive: true);
    await File('build/ui-preview/$name.png')
        .writeAsBytes(data.buffer.asUint8List());
    image.dispose();
  });
}

Widget _app(_Fake remote, {bool ledger = false, double scale = 1}) =>
    ProviderScope(
      overrides: [stsReportsRemoteDataSourceProvider.overrideWithValue(remote)],
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light,
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context)
              .copyWith(textScaler: TextScaler.linear(scale)),
          child: child!,
        ),
        home: ledger ? const LegerStsView() : const RaporStsView(),
      ),
    );

Future<void> _visible(
  WidgetTester tester,
  Finder finder, {
  double delta = 220,
}) async {
  await tester.scrollUntilVisible(
    finder,
    delta,
    scrollable: find
        .descendant(
          of: find.byType(ListView).last,
          matching: find.byType(Scrollable),
        )
        .first,
    maxScrolls: 35,
  );
  await tester.ensureVisible(finder);
  await tester.pumpAndSettle();
}

Future<void> _tap(WidgetTester tester, String key, {double delta = 220}) async {
  await _visible(tester, find.byKey(Key(key)), delta: delta);
  await tester.tap(find.byKey(Key(key)));
  await tester.pumpAndSettle();
}

Future<void> _pick(
  WidgetTester tester,
  String key,
  String label, {
  double delta = 220,
}) async {
  await _tap(tester, key, delta: delta);
  await tester.tap(find.text(label).last);
  await tester.pumpAndSettle();
}

class _Fake implements StsReportsRemoteDataSource {
  final calls = <({StsReportKind kind, StsReportFilter filter})>[];
  Object? error;
  bool empty = false;
  bool readyPeriod = true;
  @override
  Future<StsReportPage> fetch(
    StsReportKind kind,
    StsReportFilter filter,
  ) async {
    calls.add((kind: kind, filter: filter));
    if (error != null) throw error!;
    return StsReportPage.fromJson(
      stsReportFixture(
        kind: kind,
        filter: filter,
        empty: empty,
        readyPeriod: readyPeriod,
      ),
    );
  }
}
