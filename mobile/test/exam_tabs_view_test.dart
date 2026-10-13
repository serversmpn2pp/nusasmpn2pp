import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/cbt_center/data/cbt_center_remote_data_source.dart';
import 'package:nusa/features/cbt_center/domain/cbt_center.dart';
import 'package:nusa/features/cbt_center/presentation/cbt_center_view.dart';
import 'package:nusa/features/parent_child_exams/data/parent_child_exams_remote_data_source.dart';
import 'package:nusa/features/parent_child_exams/domain/parent_child_exams.dart';
import 'package:nusa/features/parent_child_exams/presentation/parent_child_exams_view.dart';
import 'package:nusa/shared/widgets/nusa_exam_tabs.dart';

final _capture = GlobalKey();

void main() {
  setUpAll(() async {
    final directory = Platform.environment['NUSA_UI_FONT_DIR'];
    if (directory == null) return;
    for (final (family, file) in [
      ('Roboto', 'roboto-regular.ttf'),
      ('MaterialIcons', 'materialicons-regular.otf'),
    ]) {
      await (FontLoader(family)..addFont(
            Future.value(
              ByteData.sublistView(
                await File('$directory/$file').readAsBytes(),
              ),
            ),
          ))
          .load();
    }
  });
  for (final parent in [false, true]) {
    final role = parent ? 'parent' : 'student';
    testWidgets('$role shows schedule first and history only on request', (
      t,
    ) async {
      await _mount(t, parent: parent);
      expect(
        t.widget<NusaExamTabs>(find.byType(NusaExamTabs)).selected,
        NusaExamTab.schedule,
      );
      expect(find.text('Ujian kedaluwarsa'), findsNothing);
      expect(find.text('Ujian tuntas'), findsNothing);
      final prefix = parent ? 'parent' : 'student';
      await _visible(t, find.byKey(Key('$prefix-exam-10')));
      expect(find.text('Ditahan Mode Aman'), findsOneWidget);
      expect(
        t.getTopLeft(find.byKey(Key('$prefix-exam-12'))).dy,
        lessThan(t.getTopLeft(find.byKey(Key('$prefix-exam-13'))).dy),
      );
      expect(
        t.getTopLeft(find.byKey(Key('$prefix-exam-13'))).dy,
        lessThan(t.getTopLeft(find.byKey(Key('$prefix-exam-20'))).dy),
      );
      await _tap(t, find.byKey(const Key('exam-tab-history')));
      expect(find.text('Ujian tambahan'), findsNothing);
      await _visible(t, find.byKey(Key('$prefix-exam-11')));
      expect(
        t.getTopLeft(find.byKey(Key('$prefix-exam-11'))).dy,
        lessThan(t.getTopLeft(find.byKey(Key('$prefix-exam-14'))).dy),
      );
      await _tap(t, find.byKey(const Key('exam-tab-schedule')));
      expect(find.text('Ujian tuntas'), findsNothing);
      expect(t.takeException(), isNull);
    });
    testWidgets(
      '$role with only old exams has empty schedule and history shortcut',
      (t) async {
        await _mount(t, parent: parent, historyOnly: true);
        expect(find.text('Belum ada jadwal aktif'), findsOneWidget);
        expect(find.text('Ujian tuntas'), findsNothing);
        await _tap(t, find.byKey(const Key('exam-open-history')));
        expect(find.text('Riwayat ujian'), findsOneWidget);
        expect(find.text('Ujian tuntas'), findsOneWidget);
        expect(t.takeException(), isNull);
      },
    );
    testWidgets(
      '$role refresh keeps history selected and replaces groups from server',
      (t) async {
        final remotes = await _mount(t, parent: parent);
        await _tap(t, find.byKey(const Key('exam-tab-history')));
        remotes.$1.finishUpcoming = true;
        remotes.$2.finishUpcoming = true;
        await t.tap(find.byTooltip('Perbarui'));
        await t.pumpAndSettle();
        expect(
          t.widget<NusaExamTabs>(find.byType(NusaExamTabs)).selected,
          NusaExamTab.history,
        );
        await _visible(t, find.text('Ujian terdekat'));
        expect(find.text('Ujian terdekat'), findsOneWidget);
        expect(parent ? remotes.$2.reads : remotes.$1.reads, 2);
        expect(t.takeException(), isNull);
      },
    );
    for (final scale in [1.0, 1.5, 2.0]) {
      testWidgets('$role tabs and cards fit 320px at ${scale}x text', (
        t,
      ) async {
        await _mount(
          t,
          parent: parent,
          scale: scale,
          size: const Size(320, 780),
        );
        await _visible(
          t,
          find.byKey(Key('${parent ? 'parent' : 'student'}-exam-20')),
        );
        expect(t.takeException(), isNull);
        await _tap(t, find.byKey(const Key('exam-tab-history')));
        await _visible(
          t,
          find.byKey(Key('${parent ? 'parent' : 'student'}-exam-15')),
        );
        expect(t.takeException(), isNull);
      });
    }
  }
  testWidgets(
    'student history still opens native exam route and preserves tab on return',
    (t) async {
      final remotes = await _mount(t);
      await _tap(t, find.byKey(const Key('exam-tab-history')));
      await _tap(t, find.byKey(const Key('student-exam-11')));
      expect(find.text('Native exam 11'), findsOneWidget);
      await t.pageBack();
      await t.pumpAndSettle();
      expect(
        t.widget<NusaExamTabs>(find.byType(NusaExamTabs)).selected,
        NusaExamTab.history,
      );
      expect(remotes.$1.reads, 2);
      expect(t.takeException(), isNull);
    },
  );
  testWidgets(
    'returning from student runner moves newly completed exam to history',
    (t) async {
      final remotes = await _mount(t);
      await _tap(t, find.byKey(const Key('student-exam-12')));
      expect(find.text('Native exam 12'), findsOneWidget);
      remotes.$1.finishActive = true;
      await t.pageBack();
      await t.pumpAndSettle();
      expect(remotes.$1.reads, 2);
      expect(
        t.widget<NusaExamTabs>(find.byType(NusaExamTabs)).selected,
        NusaExamTab.schedule,
      );
      expect(find.byKey(const Key('student-exam-12')), findsNothing);
      await _tap(t, find.byKey(const Key('exam-tab-history')));
      await _visible(t, find.byKey(const Key('student-exam-12')));
      expect(find.text('Ujian tambahan'), findsOneWidget);
      expect(t.takeException(), isNull);
    },
  );
  testWidgets(
    'parent history remains monitoring-only and shows only published score',
    (t) async {
      await _mount(t, parent: true);
      await _tap(t, find.byKey(const Key('exam-tab-history')));
      await _tap(t, find.byKey(const Key('parent-exam-11')));
      expect(find.text('Hasil dipublikasikan'), findsOneWidget);
      expect(find.text('88'), findsOneWidget);
      expect(find.text('Mulai ujian'), findsNothing);
      expect(find.text('Token ujian'), findsNothing);
      expect(t.takeException(), isNull);
    },
  );
  if (Platform.environment['NUSA_EXAM_TABS_PREVIEW'] == '1') {
    testWidgets('capture student and parent schedule and history previews', (
      t,
    ) async {
      final oldShadows = debugDisableShadows;
      debugDisableShadows = false;
      try {
        for (final parent in [false, true]) {
          await _mount(t, parent: parent);
          await _screenshot(t, '${parent ? 'parent' : 'student'}-schedule');
          await _tap(t, find.byKey(const Key('exam-tab-history')));
          await _screenshot(t, '${parent ? 'parent' : 'student'}-history');
        }
        await _mount(t, historyOnly: true);
        await _screenshot(t, 'student-empty-schedule');
      } finally {
        debugDisableShadows = oldShadows;
      }
    });
  }
}

Future<(_StudentRemote, _ParentRemote)> _mount(
  WidgetTester t, {
  bool parent = false,
  bool historyOnly = false,
  Size size = const Size(390, 844),
  double scale = 1,
}) async {
  await t.pumpWidget(const SizedBox());
  t.view.physicalSize = size;
  t.view.devicePixelRatio = 1;
  t.platformDispatcher.textScaleFactorTestValue = scale;
  addTearDown(t.view.resetPhysicalSize);
  addTearDown(t.view.resetDevicePixelRatio);
  addTearDown(t.platformDispatcher.clearTextScaleFactorTestValue);
  final student = _StudentRemote(historyOnly);
  final guardian = _ParentRemote(historyOnly);
  final router = GoRouter(
    routes: [
      GoRoute(
        path: '/',
        builder: (_, _) => parent
            ? const ParentChildExamsView()
            : const CbtCenterView(focus: CbtCenterFocus.management),
      ),
      GoRoute(
        path: '/ujian-saya/:id',
        builder: (_, state) => Scaffold(
          appBar: AppBar(title: const Text('Detail ujian')),
          body: Text('Native exam ${state.pathParameters['id']}'),
        ),
      ),
    ],
  );
  addTearDown(router.dispose);
  await t.pumpWidget(
    ProviderScope(
      overrides: [
        cbtCenterRemoteDataSourceProvider.overrideWithValue(student),
        parentChildExamsRemoteDataSourceProvider.overrideWithValue(guardian),
      ],
      child: RepaintBoundary(
        key: _capture,
        child: MaterialApp.router(
          theme: AppTheme.light,
          debugShowCheckedModeBanner: false,
          routerConfig: router,
        ),
      ),
    ),
  );
  await t.pumpAndSettle();
  return (student, guardian);
}

Future<void> _visible(WidgetTester t, Finder target) async {
  final scroll = find
      .descendant(
        of: find.byType(ListView).first,
        matching: find.byType(Scrollable),
      )
      .first;
  if (target.evaluate().isEmpty) {
    t.state<ScrollableState>(scroll).position.jumpTo(0);
    await t.pumpAndSettle();
    if (target.evaluate().isEmpty) {
      await t.scrollUntilVisible(target, 200, scrollable: scroll);
    }
  }
  await t.ensureVisible(target);
  await t.pumpAndSettle();
}

Future<void> _tap(WidgetTester t, Finder target) async {
  await _visible(t, target);
  await t.tap(target);
  await t.pumpAndSettle();
}

Future<void> _screenshot(WidgetTester t, String name) async {
  await t.runAsync(() async {
    final boundary =
        _capture.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    final picture = await boundary.toImage();
    final bytes = await picture.toByteData(format: ui.ImageByteFormat.png);
    picture.dispose();
    final directory = Directory('build/exam-tabs-qa');
    await directory.create(recursive: true);
    await File('${directory.path}/$name.png')
        .writeAsBytes(bytes!.buffer.asUint8List());
  });
}

class _StudentRemote implements CbtCenterRemoteDataSource {
  _StudentRemote(this.historyOnly);
  final bool historyOnly;
  bool finishUpcoming = false;
  bool finishActive = false;
  int reads = 0;
  @override
  Future<CbtCenterData> fetch() async {
    reads++;
    final exams = _exams(
      historyOnly: historyOnly,
      finishUpcoming: finishUpcoming,
      finishActive: finishActive,
    );
    return CbtCenterData.fromJson({
      'akses': {
        'dapat_mengelola': false,
        'memiliki_tugas_pengawas': false,
        'akun_siswa': true,
      },
      'siswa': {
        'ringkasan': _summary(exams),
        'items': exams,
        'pengerjaan_native': true,
      },
    });
  }
}

class _ParentRemote implements ParentChildExamsRemoteDataSource {
  _ParentRemote(this.historyOnly);
  final bool historyOnly;
  bool finishUpcoming = false;
  int reads = 0;
  @override
  Future<ParentChildExamsPage> fetch({int? studentId}) async {
    reads++;
    final exams = _exams(
      historyOnly: historyOnly,
      finishUpcoming: finishUpcoming,
    );
    return ParentChildExamsPage.fromJson({
      'hanya_pemantauan': true,
      'siswa_id': 101,
      'siswa': {'id': 101, 'nama': 'Alya Demo', 'nisn': '0012345678'},
      'pilihan_siswa': [
        {'id': 101, 'nama': 'Alya Demo'},
      ],
      'ringkasan': _summary(exams),
      'items': exams,
      'catatan': 'Hanya pemantauan. Nilai ditampilkan setelah sekolah mempublikasikan hasil.',
    });
  }
}

Map<String, dynamic> _summary(List<Map<String, dynamic>> exams) => {
  'aktif': exams.where((e) => e['kelompok'] == 'aktif').length,
  'akan_datang': exams.where((e) => e['kelompok'] == 'akan_datang').length,
  'selesai': exams.where((e) => e['kelompok'] == 'selesai').length,
  'total': exams.length,
};
List<Map<String, dynamic>> _exams({
  required bool historyOnly,
  required bool finishUpcoming,
  bool finishActive = false,
}) => [
  for (final (id, name, group, day, label, status) in [
    (
      14,
      'Ujian kedaluwarsa',
      'selesai',
      '2026-10-12',
      'Waktu berakhir',
      'aktif',
    ),
    (
      20,
      'Ujian minggu depan',
      'akan_datang',
      '2026-10-20',
      'Belum dibuka',
      'aktif',
    ),
    (
      12,
      'Ujian tambahan',
      finishActive ? 'selesai' : 'aktif',
      '2026-09-25',
      finishActive ? 'Selesai dikerjakan' : 'Waktu tambahan aktif',
      finishActive ? 'selesai' : 'sedang_mengerjakan',
    ),
    (
      13,
      'Ujian terdekat',
      finishUpcoming ? 'selesai' : 'akan_datang',
      '2026-10-14',
      finishUpcoming ? 'Waktu berakhir' : 'Belum dibuka',
      'aktif',
    ),
    (
      11,
      'Ujian tuntas',
      'selesai',
      '2026-10-15',
      'Selesai dikerjakan',
      'selesai',
    ),
    (
      10,
      'Ujian tertahan',
      'aktif',
      '2026-10-13',
      'Ditahan Mode Aman',
      'terblokir',
    ),
    (
      15,
      'Ujian tanpa jadwal',
      'selesai',
      null,
      'Selesai dikerjakan',
      'selesai',
    ),
  ])
    if (!historyOnly || group == 'selesai')
      {
        'id': id,
        'ujian_id': id,
        'nama': name,
        'mata_pelajaran': 'Matematika',
        'kelompok': group,
        'label_status': label,
        'nada_status': status == 'terblokir'
            ? 'bahaya'
            : group == 'selesai'
            ? 'selesai'
            : group == 'aktif'
            ? 'aktif'
            : 'menunggu',
        'status_pengerjaan': status,
        'dapat_dibuka': status != 'terblokir',
        'waktu_mulai': day == null ? null : '${day}T07:30:00+07:00',
        'waktu_selesai': day == null ? null : '${day}T08:30:00+07:00',
        'tanggal': day,
        'waktu': '07:30 - 08:30',
        'durasi_menit': 60,
        'kemajuan': {
          'jumlah_soal': 30,
          'terjawab': status == 'selesai' ? 30 : 10,
          'belum_dijawab': status == 'selesai' ? 0 : 20,
        },
        'hasil': {
          'ditampilkan': id == 11,
          'nilai': id == 11 ? 88 : null,
          'kkm': 75,
          'tuntas': id == 11 ? true : null,
        },
      },
];
