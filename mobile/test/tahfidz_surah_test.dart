import 'dart:io';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/tahfidz/data/quran_reading_repository.dart';
import 'package:nusa/features/tahfidz/data/tahfidz_repository.dart';
import 'package:nusa/features/tahfidz/domain/tahfidz.dart';
import 'package:nusa/features/tahfidz/presentation/tahfidz_surah_view.dart';
import 'package:nusa/features/tahfidz/presentation/tahfidz_views.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  late QuranReadingRepository reader;
  late QuranCatalog catalog;
  setUpAll(() async {
    reader = QuranReadingRepository();
    catalog = await reader.load();
    await (FontLoader(
      'AmiriQuran',
    )..addFont(rootBundle.load('assets/fonts/AmiriQuran-Regular.ttf'))).load();
    await (FontLoader(
      'Roboto',
    )..addFont(rootBundle.load('assets/fonts/roboto-regular.ttf'))).load();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
  });

  Future<void> pump(WidgetTester tester, Widget view, [_Fake? backend]) async {
    await tester.pumpWidget(
      RepaintBoundary(
        key: const Key('tahfidz-capture'),
        child: ProviderScope(
          overrides: [
            quranReadingRepositoryProvider.overrideWithValue(reader),
            tahfidzRepositoryProvider.overrideWithValue(backend ?? _Fake()),
          ],
          child: MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: AppTheme.light,
            home: view,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  test('aset lengkap 37 surah dan 564 ayat dengan karakter Arab pembuka dipertahankan', () {
    expect(
      catalog.surahs.map((s) => s.number),
      orderedEquals(List.generate(37, (i) => 78 + i)),
    );
    expect(catalog.surahs.fold<int>(0, (sum, s) => sum + s.verses.length), 564);
    expect(catalog.surahs.first.verses.length, 40);
    expect(catalog.surahs.last.verses.length, 6);
    expect(
      catalog.copyrightNotice,
      contains('Tanzil Quran Text (Uthmani, Version 1.1)'),
    );
    for (final s in catalog.surahs) {
      expect(
        '${s.opening} ${s.verseText(s.verses.first)}',
        s.verses.first.text,
      );
      expect(
        s.verses.map((v) => v.number),
        orderedEquals(List.generate(s.verses.length, (i) => i + 1)),
      );
      expect(s.verses.every((v) => v.text.isNotEmpty), isTrue);
    }
    // These two openings contain a source shadda that must not be normalized away.
    for (final n in [95, 97]) {
      expect(
        catalog.surahs.firstWhere((s) => s.number == n).opening.codeUnits[1],
        0x0651,
      );
    }
  });

  testWidgets('tombol utama besar dan cetak rapor kelompok hilang pada HP', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 760));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await pump(tester, const TfGroupView(1));
    expect(find.textContaining('Cetak Rapor'), findsNothing);
    for (final key in ['tahfidz-open-meeting', 'tahfidz-surah-list']) {
      final size = tester.getSize(find.byKey(Key(key)));
      expect(size.width, greaterThanOrEqualTo(288));
      expect(size.height, greaterThanOrEqualTo(68));
    }
    expect(tester.takeException(), isNull);
    await _capture(tester, 'kelompok-hp');
  });

  testWidgets('daftar surah dicari dan dibuka tanpa memanggil API', (
    tester,
  ) async {
    final backend = _Fake();
    await pump(tester, const TahfidzSurahView(), backend);
    await tester.enterText(
      find.byKey(const Key('tahfidz-surah-search')),
      '114',
    );
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('tahfidz-surah-114')), findsOneWidget);
    expect(find.byKey(const Key('tahfidz-surah-78')), findsNothing);
    await tester.tap(find.byKey(const Key('tahfidz-surah-114')));
    await tester.pumpAndSettle();
    expect(find.byType(TahfidzSurahReader), findsOneWidget);
    expect(backend.reads, isEmpty);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'ayat Arab RTL terbaca, huruf dapat diperbesar dan ayat terakhir dapat dituju',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(360, 820));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await pump(tester, TahfidzSurahReader(surah: catalog.surahs.last));
      final verse = find.byKey(const ValueKey('tahfidz-verse-1'));
      expect(
        tester.widget<SelectableText>(verse).textDirection,
        TextDirection.rtl,
      );
      expect(
        tester.widget<SelectableText>(verse).style?.fontFamily,
        'AmiriQuran',
      );
      expect(find.byType(SelectableText), findsNWidgets(7));
      await _capture(tester, 'surah-an-nas-hp');
      await tester.tap(find.byKey(const Key('tahfidz-arabic-larger')));
      await tester.pumpAndSettle();
      expect(tester.widget<SelectableText>(verse).style?.fontSize, 32);
      await tester.tap(find.byKey(const Key('tahfidz-jump-verse')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ayat 6').hitTestable().last);
      await tester.pumpAndSettle();
      expect(
        tester.getTopLeft(find.byKey(const ValueKey('tahfidz-verse-6'))).dy,
        lessThan(820),
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('surah panjang utuh pada tablet dan ukuran huruf besar', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(800, 1000));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await pump(tester, TahfidzSurahReader(surah: catalog.surahs.first));
    expect(find.byType(SelectableText), findsNWidgets(41));
    await _capture(tester, 'surah-an-naba-tablet');
    for (var i = 0; i < 7; i++) {
      await tester.tap(find.byKey(const Key('tahfidz-arabic-larger')));
      await tester.pump();
    }
    expect(
      tester
          .widget<SelectableText>(find.byKey(const ValueKey('tahfidz-verse-1')))
          .style
          ?.fontSize,
      44,
    );
    await tester.ensureVisible(find.byKey(const ValueKey('tahfidz-verse-40')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'membaca surah dari formulir setoran mempertahankan siswa, nilai, dan token',
    (tester) async {
      final backend = _Fake();
      await pump(tester, const TfMeetingView(1, 2), backend);
      await tester.tap(find.text('Catat Setoran'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.byKey(const Key('tahfidz-field-anggota_tahfidz_id')),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Alya').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('tahfidz-field-nomor_surah')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('114. An-Nas').last);
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const Key('tahfidz-field-nilai')),
        '88,5',
      );
      await tester.ensureVisible(find.text('Lihat Ayat Surah'));
      await tester.tap(find.text('Lihat Ayat Surah'));
      await tester.pumpAndSettle();
      expect(find.byType(TahfidzSurahReader), findsOneWidget);
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<TextFormField>(find.byKey(const Key('tahfidz-field-nilai')))
            .controller
            ?.text,
        '88,5',
      );
      await tester.ensureVisible(find.text('Simpan'));
      await tester.tap(find.text('Simpan'));
      await tester.pumpAndSettle();
      expect(backend.writes.single['anggota_tahfidz_id'], 7);
      expect(backend.writes.single['nomor_surah'], 114);
      expect(backend.writes.single['nilai'], '88,5');
      expect(backend.writes.single['token_setoran'], isNotEmpty);
      expect(tester.takeException(), isNull);
    },
  );
}

Future<void> _capture(WidgetTester tester, String name) async {
  if (Platform.environment['NUSA_CAPTURE_TAHFIDZ_MOBILE'] != '1') return;
  final boundary = tester.renderObject<RenderRepaintBoundary>(
    find.byKey(const Key('tahfidz-capture')),
  );
  await tester.runAsync(() async {
    final image = await boundary.toImage(pixelRatio: 2);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    final dir = Directory('build/tahfidz-preview');
    await dir.create(recursive: true);
    await File('${dir.path}/$name.png')
        .writeAsBytes(bytes!.buffer.asUint8List());
    image.dispose();
  });
}

class _Fake extends TahfidzRepository {
  _Fake() : super(Dio());
  final reads = <String>[];
  final writes = <TfData>[];
  @override
  Future<TfData> get(String path, [TfData? query]) async {
    reads.add(path);
    if (path == 'tahfidz/1') {
      return {
        'kelompok': {
          'id': 1,
          'nama': 'Kelompok Al-Ikhlas',
          'tahun': '2026/2027',
          'pembimbing': 'Pembimbing Tahfidz',
          'aktif': true,
        },
        'catat': true,
        'kelola': false,
        'ringkasan': {'mulai': 1, 'lengkap': 0, 'target': 37},
        'anggota': [
          {'id': 7, 'nama': 'Alya', 'kelas': 'VII.A', 'sudah': 1, 'setoran': 1},
        ],
        'pertemuan': [],
      };
    }
    if (path == 'tahfidz/1/pertemuan/2') {
      return {
        'kelompok': {'nama': 'Kelompok Al-Ikhlas'},
        'pertemuan': {'id': 2, 'tanggal': '2026-11-05', 'selesai': false},
        'catat': true,
        'peserta': [
          {'id': 7, 'nama': 'Alya'},
        ],
        'surah': [
          {'nomor': 114, 'nama': 'An-Nas'},
        ],
        'items': [],
      };
    }
    throw StateError('Pembaca surah tidak boleh meminta API: $path');
  }

  @override
  Future<TfData> call(
    String method,
    String path, {
    TfData? data,
    TfData? query,
  }) async {
    writes.add({...?data});
    return {'id': 2};
  }
}
