import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_card/data/student_card_remote_data_source.dart';
import 'package:nusa/features/student_card/domain/student_card.dart';
import 'package:nusa/features/student_card/presentation/student_card_view.dart';

void main() {
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
    expect(find.text('PRESENSI SISWA NUSA'), findsOneWidget);
    expect(find.byKey(const Key('student-card-qr')), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('save-student-card-png')),
      300,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.textContaining('Simpan PNG Sisi Belakang'), findsOneWidget);
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

    expect(find.byKey(const Key('save-student-card-png')), findsNothing);
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

    expect(find.text('QR BELUM TERSEDIA'), findsOneWidget);
    expect(find.text('NISN harus berupa angka'), findsOneWidget);
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
}

Future<void> _pumpView(
  WidgetTester tester,
  StudentCardRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        studentCardRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(theme: AppTheme.light, home: const StudentCardView()),
    ),
  );
  await tester.pumpAndSettle();
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
      nisn: '0131201150',
      gender: 'L',
      birthLabel: 'Padang Panjang, 3 November 2012',
      className: 'VIII.KP Mobile',
      academicYear: '2030/2031',
      rollNumber: 1,
      photoUrl: null,
      hasPhoto: true,
      qrData: '0131201150',
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
