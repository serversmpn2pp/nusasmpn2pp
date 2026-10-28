import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';
import 'package:nusa/features/home/domain/home_dashboard.dart';
import 'package:nusa/features/home/presentation/home_dashboard_view.dart';
import 'package:nusa/features/home/presentation/widgets/home_components.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';
import 'package:nusa/shared/widgets/nusa_bottom_navigation.dart';
import 'package:nusa/shared/widgets/nusa_school_identity.dart';

const _labels = [
  'Jadwal Mengajar Saya',
  'Siswa Wali Saya',
  'Input Nilai',
  'Laporan Siswa Wali',
];
const _codes = [
  'jadwal-mengajar-saya',
  'siswa-wali-saya',
  'input-nilai',
  'laporan-siswa-wali',
];
const _shortLabels = ['Jadwal', 'Siswa Wali', 'Input Nilai', 'Laporan Wali'];
const _icons = [
  Icons.event_note_rounded,
  Icons.supervised_user_circle_rounded,
  Icons.edit_note_rounded,
  Icons.assignment_ind_rounded,
];
const _colors = [
  Color(0xFF2676C8),
  Color(0xFF7A56B3),
  Color(0xFF2676C8),
  Color(0xFF7A56B3),
];

List<NusaMenuAction> _actions(int count, ValueChanged<int> onTap) => [
  for (var i = 0; i < count; i++)
    NusaMenuAction(
      label: _labels[i],
      shortLabel: _shortLabels[i],
      icon: _icons[i],
      color: _colors[i],
      onTap: () => onTap(i),
    ),
];

void _size(WidgetTester tester, double width, {double height = 700}) {
  tester.view.physicalSize = Size(width, height);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Future<void> _pumpGrid(
  WidgetTester tester,
  double scale,
  List<NusaMenuAction> actions,
) => tester.pumpWidget(
  MaterialApp(
    theme: AppTheme.light,
    home: Scaffold(
      body: MediaQuery(
        data: MediaQueryData(textScaler: TextScaler.linear(scale)),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(18),
          child: QuickAccessGrid(actions: actions),
        ),
      ),
    ),
  ),
);

void main() {
  setUpAll(() async {
    await (FontLoader('Roboto')
          ..addFont(rootBundle.load('assets/fonts/roboto-regular.ttf'))
          ..addFont(rootBundle.load('assets/fonts/roboto-bold.ttf')))
        .load();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
  });

  for (final (width, scale, columns) in [
    (320.0, 1.0, 4),
    (360.0, 1.3, 2),
    (390.0, 2.0, 2),
    (600.0, 1.8, 4),
  ]) {
    testWidgets(
      'empat akses cepat terbaca utuh pada lebar $width dan skala $scale',
      (tester) async {
        _size(tester, width);
        final taps = <int>[];
        await _pumpGrid(tester, scale, _actions(4, taps.add));
        await tester.pumpAndSettle();
        final cards = find.byType(QuickMenuCard);
        expect(cards, findsNWidgets(4));
        final rects = [for (var i = 0; i < 4; i++) tester.getRect(cards.at(i))];
        expect(rects[0].top, rects[1].top);
        expect(rects[0].right, lessThan(rects[1].left));
        if (columns == 4) {
          expect(rects.every((rect) => rect.top == rects[0].top), isTrue);
          expect(rects[1].right, lessThan(rects[2].left));
          expect(rects[2].right, lessThan(rects[3].left));
        } else {
          expect(rects[2].top, rects[3].top);
          expect(rects[2].top, greaterThan(rects[0].bottom));
          expect(rects[0].left, rects[2].left);
        }
        expect(
          find.descendant(of: cards, matching: find.byType(FittedBox)),
          findsNothing,
        );
        for (var i = 0; i < _labels.length; i++) {
          final label = find.text(_shortLabels[i]);
          final text = tester.widget<Text>(label);
          final paragraph = tester.renderObject<RenderParagraph>(
            find.descendant(of: label, matching: find.byType(RichText)),
          );
          expect(text.style?.fontSize, 14);
          expect(text.semanticsLabel, _labels[i]);
          expect(paragraph.textScaler.scale(14), closeTo(14 * scale, 0.01));
          expect(paragraph.didExceedMaxLines, isFalse);
          expect(text.maxLines, isNull);
          await tester.ensureVisible(cards.at(i));
          await tester.tap(cards.at(i));
          await tester.pumpAndSettle();
        }
        expect(taps, [0, 1, 2, 3]);
        expect(tester.takeException(), isNull);
      },
    );
  }

  for (final count in [0, 1, 2, 3]) {
    testWidgets('akses cepat tetap rapi dengan $count pilihan pengguna', (
      tester,
    ) async {
      _size(tester, 320);
      final taps = <int>[];
      await _pumpGrid(tester, 1, _actions(count, taps.add));
      await tester.pumpAndSettle();
      final cards = find.byType(QuickMenuCard);
      expect(cards, findsNWidgets(count));
      if (count == 3) {
        expect(
          tester.getTopLeft(cards.at(2)).dy,
          tester.getTopLeft(cards.first).dy,
        );
      }
      for (var i = 0; i < count; i++) {
        await tester.ensureVisible(cards.at(i));
        await tester.tap(cards.at(i));
        await tester.pumpAndSettle();
      }
      expect(taps, List.generate(count, (i) => i));
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets(
    'baris ringkas memberi ruang untuk menu lain dan mengikuti ukuran huruf HP',
    (tester) async {
      _size(tester, 360);
      await _pumpGrid(tester, 1, _actions(4, (_) {}));
      final normalHeight = tester.getSize(find.byType(QuickAccessGrid)).height;
      expect(normalHeight, lessThanOrEqualTo(104));
      await _pumpGrid(tester, 2, _actions(4, (_) {}));
      expect(
        tester.getSize(find.byType(QuickAccessGrid)).height,
        greaterThan(normalHeight),
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('tekan lama menampilkan nama lengkap tanpa membuka menu', (
    tester,
  ) async {
    _size(tester, 390);
    final taps = <int>[];
    await _pumpGrid(tester, 1, _actions(4, taps.add));
    await tester.longPress(find.text('Jadwal'));
    await tester.pumpAndSettle();
    expect(find.text('Jadwal Mengajar Saya'), findsOneWidget);
    expect(taps, isEmpty);
    await tester.tap(find.text('Jadwal'));
    await tester.pumpAndSettle();
    expect(taps, [0]);
  });

  testWidgets('menu tanpa label pendek tetap menampilkan nama katalog utuh', (
    tester,
  ) async {
    _size(tester, 320);
    const label = 'Pengaturan Kegiatan Sekolah Lainnya';
    await _pumpGrid(tester, 1, [
      NusaMenuAction(
        label: label,
        icon: Icons.settings_rounded,
        color: NusaColors.primary,
        onTap: () {},
      ),
    ]);
    final text = tester.widget<Text>(find.text(label));
    expect(text.maxLines, isNull);
    expect(text.style?.fontSize, 14);
    expect(tester.takeException(), isNull);
  });

  testWidgets('contoh beranda memakai ikon asli dan tujuan menu tetap sama', (
    tester,
  ) async {
    _size(tester, 390, height: 844);
    final capture = GlobalKey();
    final opened = <String>[];
    await tester.pumpWidget(
      RepaintBoundary(
        key: capture,
        child: MaterialApp(
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light,
          home: Scaffold(
            body: HomeDashboardView(
              pengguna: const Pengguna(
                id: 1,
                nama: 'Pengguna Kesiswaan',
                username: 'pengguna.uji',
                jenisAkun: 'pegawai',
                administrator: false,
                wajibGantiKataSandi: false,
                peran: ['wakil_kesiswaan'],
                izin: [],
              ),
              dashboard: AsyncData(
                HomeDashboard.fromJson({
                  'dihasilkan_pada': '2026-10-27T07:11:00+07:00',
                  'salam': 'Selamat pagi',
                  'tanggal': {
                    'hari': 'Selasa',
                    'label': '27 Oktober 2026',
                    'bulan': 'Oktober 2026',
                  },
                  'presensi': {
                    'hari_ini': {
                      'tercatat': true,
                      'label_status': 'Hadir',
                      'jam_masuk': '06:20',
                    },
                    'bulan_ini': {},
                  },
                }),
              ),
              menu: AsyncData(_catalog()),
              onRefresh: () async {},
              onOpenActivity: () {},
              onOpenNusa: () {},
              onOpenNotifications: () {},
              onOpenMenuGroup: (_) {},
              onOpenMenuEntry: (entry) => opened.add(entry.code),
              onConfigureQuickAccess: (_) {},
              onUnavailable: (_) {},
            ),
            bottomNavigationBar: NusaBottomNavigation(
              selectedIndex: 0,
              onSelected: (_) {},
            ),
          ),
        ),
      ),
    );
    await tester.runAsync(() async {
      final context = tester.element(find.byType(HomeDashboardView));
      await Future.wait([
        precacheImage(const AssetImage('assets/images/logo-nusa.png'), context),
        precacheImage(const AssetImage(NusaSchoolPhoto.assetPath), context),
      ]);
    });
    await tester.pumpAndSettle();
    final shortcuts = find.byType(QuickMenuCard);
    expect(shortcuts, findsNWidgets(4));
    for (var index = 0; index < _codes.length; index++) {
      final icon = tester.widget<Icon>(
        find.descendant(of: shortcuts.at(index), matching: find.byType(Icon)),
      );
      expect(icon.icon, _icons[index]);
      expect(icon.color, _colors[index]);
      expect(icon.size, 32);
      final text = tester.widget<Text>(find.text(_shortLabels[index]));
      expect(text.semanticsLabel, _labels[index]);
      await tester.tap(shortcuts.at(index));
      await tester.pumpAndSettle();
    }
    expect(opened, _codes);
    expect(tester.takeException(), isNull);
    await tester.runAsync(() async {
      final boundary =
          capture.currentContext!.findRenderObject()! as RenderRepaintBoundary;
      final image = await boundary.toImage(pixelRatio: 2);
      final data = await image.toByteData(format: ui.ImageByteFormat.png);
      await File('build/home-quick-access-icon-row.png')
          .writeAsBytes(data!.buffer.asUint8List());
      image.dispose();
    });
  });
}

MenuCatalog _catalog() => MenuCatalog.fromJson({
  'dihasilkan_pada': '2026-10-27T07:11:00+07:00',
  'akses_cepat': {'dapat_diatur': true, 'kode_menu': _codes},
  'kelompok': [
    for (final (code, label, icon, indexes) in [
      ('data-sekolah', 'Data Sekolah', 'school', <int>[]),
      ('akademik', 'Akademik', 'academic', [0, 2]),
      ('ujian-asesmen', 'Ujian & Asesmen', 'quiz', <int>[]),
      ('kehadiran', 'Kehadiran', 'attendance', <int>[]),
      ('kesiswaan-bk', 'Kesiswaan & BK', 'counseling', [1, 3]),
      ('sarana-prasarana', 'Sarana Prasarana', 'inventory', <int>[]),
    ])
      {
        'kode': code,
        'label': label,
        'ikon': icon,
        'items': [
          for (final i in indexes)
            {
              'kode': _codes[i],
              'label': _labels[i],
              'status': 'tersedia',
              'rute': '/${_codes[i]}',
            },
        ],
      },
  ],
});
