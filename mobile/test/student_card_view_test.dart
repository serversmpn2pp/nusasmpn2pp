import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_card/data/student_card_file_saver.dart';
import 'package:nusa/features/student_card/data/student_card_remote_data_source.dart';
import 'package:nusa/features/student_card/domain/student_card.dart';
import 'package:nusa/features/student_card/presentation/student_card_artwork.dart';
import 'package:nusa/features/student_card/presentation/student_card_view.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    final font = FontLoader('InstrumentSansCard')
      ..addFont(rootBundle.load('assets/fonts/InstrumentSans-Bold.ttf'));
    await font.load();
  });

  testWidgets('daftar kartu pelajar rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeStudentCardRemoteDataSource());

    expect(find.text('Kartu Pelajar'), findsOneWidget);
    expect(
      find.byKey(const Key('student-card-academic-year-filter')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('student-card-class-filter')), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Anugrah Kartu Pelajar Mobile'),
      250,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Foto siap'), findsOneWidget);
    expect(find.text('QR siap'), findsAtLeast(1));
    expect(tester.takeException(), isNull);
  });

  testWidgets('pencetak melihat kartu depan dan QR belakang', (tester) async {
    await _pumpView(tester, _FakeStudentCardRemoteDataSource());

    final card = find.byKey(const Key('student-card-41'));
    await tester.scrollUntilVisible(
      card,
      250,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(card);
    await tester.pumpAndSettle();

    expect(find.text('Pratinjau Kartu'), findsOneWidget);
    expect(find.text('KARTU PELAJAR'), findsOneWidget);
    await tester.tap(find.text('Belakang'));
    await tester.pumpAndSettle();
    expect(find.text('PRESENSI DIGITAL NUSA'), findsOneWidget);
    expect(find.byKey(const Key('student-card-qr')), findsOneWidget);
    expect(
      tester
          .widget<SegmentedButton<StudentCardSide>>(
            find.byKey(const Key('student-card-side-selector')),
          )
          .selected,
      {StudentCardSide.back},
    );
    expect(find.byKey(const Key('download-student-card')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('izin lihat tidak menampilkan tombol simpan', (tester) async {
    await _pumpView(tester, _FakeStudentCardRemoteDataSource(canPrint: false));

    final card = find.byKey(const Key('student-card-41'));
    await tester.scrollUntilVisible(
      card,
      250,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(card);
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('student-card-view-only-notice')),
      300,
      scrollable: find.byType(Scrollable).last,
    );

    expect(find.byKey(const Key('download-student-card')), findsNothing);
    expect(
      find.textContaining('memerlukan izin cetak kartu pelajar'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('NISN tidak valid ditandai pada sisi belakang', (tester) async {
    await _pumpView(
      tester,
      _FakeStudentCardRemoteDataSource(invalidOnly: true),
    );

    final card = find.byKey(const Key('student-card-42'));
    await tester.scrollUntilVisible(
      card,
      250,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(card);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Belakang'));
    await tester.pumpAndSettle();

    expect(find.text('QR belum tersedia'), findsOneWidget);
    expect(find.byKey(const Key('student-card-qr')), findsNothing);
    await tester.scrollUntilVisible(
      find.textContaining('Foto identitas dan NISN numerik'),
      300,
      scrollable: find.byType(Scrollable).last,
    );
    expect(
      find.textContaining('Foto identitas dan NISN numerik'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'unduhan membuat PNG per sisi dan PDF dua halaman dari desain baku',
    (tester) async {
      tester.view.physicalSize = const Size(320, 610);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final saver = _MemoryStudentCardFileSaver();
      final download = find.byKey(const Key('download-student-card'));
      await _pumpView(tester, _FakeStudentCardRemoteDataSource(), saver: saver);

      final card = find.byKey(const Key('student-card-41'));
      await tester.scrollUntilVisible(
        card,
        250,
        scrollable: find.byType(Scrollable).last,
      );
      await tester.runAsync(() async {
        await tester.tap(card);
        await _waitFor(
          tester,
          () =>
              download.evaluate().isNotEmpty &&
              tester.widget<FilledButton>(download).onPressed != null,
        );
      });
      await tester.pumpAndSettle();

      for (final format in [
        'PNG sisi belakang',
        'PNG sisi depan',
        'PDF dua sisi',
      ]) {
        final expected = saver.files.length + 1;
        await tester.tap(download);
        await tester.pumpAndSettle();
        await tester.runAsync(() async {
          await tester.tap(find.text(format));
          await _waitFor(tester, () => saver.files.length == expected);
        });
        await tester.pumpAndSettle();
        // The save notification is outside the art and does not affect subsequent exports.
        await tester.pump(const Duration(seconds: 5));
        await tester.pumpAndSettle();
      }

      final [back, front, pdf] = saver.files;
      expect(
        back.name,
        'kartu-pelajar-anugrah-kartu-pelajar-mobile-belakang.png',
      );
      expect(
        front.name,
        'kartu-pelajar-anugrah-kartu-pelajar-mobile-depan.png',
      );
      expect(pdf.name, 'kartu-pelajar-anugrah-kartu-pelajar-mobile.pdf');
      for (final file in [back, front]) {
        expect(file.bytes.take(8), [137, 80, 78, 71, 13, 10, 26, 10]);
        final header = ByteData.sublistView(file.bytes);
        final width = header.getUint32(16);
        final height = header.getUint32(20);
        expect(width, closeTo(816, 2));
        expect(height, closeTo(1294, 2));
        expect(file.bytes.length, greaterThan(10000));
      }
      expect(back.bytes, isNot(equals(front.bytes)));
      expect(ascii.decode(pdf.bytes.take(5).toList()), '%PDF-');
      expect(tester.takeException(), isNull);
      if (Platform.environment['NUSA_CAPTURE_STUDENT_CARD'] == '1') {
        final folder = Directory('../storage/framework/testing/student-card');
        folder.createSync(recursive: true);
        for (final (path, file) in [
          ('mobile-belakang.png', back),
          ('mobile-depan.png', front),
          ('mobile.pdf', pdf),
        ]) {
          File('${folder.path}/$path').writeAsBytesSync(file.bytes);
        }
      }
    },
  );
}

Future<void> _pumpView(
  WidgetTester tester,
  StudentCardRemoteDataSource remote, {
  StudentCardFileSaver? saver,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        studentCardRemoteDataSourceProvider.overrideWithValue(remote),
        if (saver != null)
          studentCardFileSaverProvider.overrideWithValue(saver),
      ],
      child: MaterialApp(theme: AppTheme.light, home: const StudentCardView()),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _waitFor(WidgetTester tester, bool Function() ready) async {
  for (var i = 0; i < 200; i++) {
    await tester.pump(const Duration(milliseconds: 16));
    if (ready()) return;
    await Future<void>.delayed(const Duration(milliseconds: 16));
  }
  fail('Pratinjau atau berkas kartu tidak siap.');
}

class _MemoryStudentCardFileSaver implements StudentCardFileSaver {
  final files = <({String name, Uint8List bytes})>[];

  @override
  Future<bool> save({
    required String fileName,
    required Uint8List bytes,
  }) async {
    files.add((name: fileName, bytes: bytes));
    return true;
  }
}

final class _FakeStudentCardRemoteDataSource
    implements StudentCardRemoteDataSource {
  _FakeStudentCardRemoteDataSource({
    this.canPrint = true,
    this.invalidOnly = false,
  });

  final bool canPrint;
  final bool invalidOnly;

  @override
  Future<StudentCardPage> fetch({
    int? academicYearId,
    int? classId,
    required String query,
    required int page,
    int perPage = 20,
  }) async {
    final ready = const StudentCardPerson(
      membershipId: 71,
      studentId: 41,
      name: 'Anugrah Kartu Pelajar Mobile',
      nis: '310001',
      nisn: '0000000001',
      gender: 'L',
      birthLabel: 'Padang Panjang, 03 November 2012',
      className: 'VIII.KP Mobile',
      academicYear: '2030/2031',
      rollNumber: 1,
      photoUrl: null,
      hasPhoto: true,
      qrData: '0000000001',
      canMakeQr: true,
    );
    final invalid = const StudentCardPerson(
      membershipId: 72,
      studentId: 42,
      name: 'Siswa Kartu Belum Siap',
      nis: '310002',
      nisn: 'NISN-BELUM-VALID',
      gender: 'P',
      birthLabel: '-',
      className: 'VIII.KP Mobile',
      academicYear: '2030/2031',
      rollNumber: 2,
      photoUrl: null,
      hasPhoto: false,
      qrData: null,
      canMakeQr: false,
    );
    final items = invalidOnly ? [invalid] : [ready, invalid];
    return StudentCardPage(
      items: items,
      summary: StudentCardSummary(
        total: items.length,
        qrReady: invalidOnly ? 0 : 1,
        withPhoto: invalidOnly ? 0 : 1,
      ),
      academicYears: const [
        StudentCardAcademicYear(id: 11, name: '2030/2031', active: true),
      ],
      classes: const [
        StudentCardClass(
          id: 12,
          name: 'VIII.KP Mobile',
          level: 8,
          active: true,
        ),
      ],
      pagination: StudentCardPagination(
        page: 1,
        total: items.length,
        hasNextPage: false,
      ),
      selectedAcademicYearId: academicYearId ?? 11,
      selectedClassId: classId ?? 12,
      query: query,
      cardSize: const StudentCardSize(
        widthMillimeter: 53.98,
        heightMillimeter: 85.6,
      ),
      canPrint: canPrint,
      canManagePhoto: true,
    );
  }
}
