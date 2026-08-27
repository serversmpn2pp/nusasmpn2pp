import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_card/data/employee_card_remote_data_source.dart';
import 'package:nusa/features/employee_card/domain/employee_card.dart';
import 'package:nusa/features/employee_card/presentation/employee_card_view.dart';

void main() {
  testWidgets('daftar kartu pegawai rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeEmployeeCardRemoteDataSource());

    expect(find.text('Kartu Pegawai'), findsOneWidget);
    expect(find.byKey(const Key('employee-card-type-filter')), findsOneWidget);
    expect(
      find.byKey(const Key('employee-card-status-filter')),
      findsOneWidget,
    );
    await tester.scrollUntilVisible(
      find.text('Antonius Kartu Mobile'),
      250,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Foto siap'), findsOneWidget);
    expect(find.text('QR siap'), findsAtLeast(1));
    expect(tester.takeException(), isNull);
  });

  testWidgets('pratinjau menampilkan sisi depan dan QR sisi belakang', (
    tester,
  ) async {
    await _pumpView(tester, _FakeEmployeeCardRemoteDataSource());

    final card = find.byKey(const Key('employee-card-31'));
    await tester.scrollUntilVisible(
      card,
      250,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(card);
    await tester.pumpAndSettle();

    expect(find.text('Pratinjau Kartu'), findsOneWidget);
    expect(find.text('KARTU PEGAWAI'), findsOneWidget);
    await tester.tap(find.text('Belakang'));
    await tester.pumpAndSettle();

    expect(find.text('PRESENSI PEGAWAI NUSA'), findsOneWidget);
    expect(find.byKey(const Key('employee-card-qr')), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('save-employee-card-png')),
      300,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.textContaining('Simpan PNG Sisi Belakang'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('kartu tanpa NIP numerik memberi penjelasan yang benar', (
    tester,
  ) async {
    await _pumpView(
      tester,
      _FakeEmployeeCardRemoteDataSource(invalidOnly: true),
    );

    final card = find.byKey(const Key('employee-card-32'));
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
    expect(find.text('NIP harus berupa angka'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.textContaining('Foto identitas dan NIP numerik'),
      300,
      scrollable: find.byType(Scrollable).last,
    );
    expect(
      find.textContaining('Foto identitas dan NIP numerik'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  EmployeeCardRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        employeeCardRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(theme: AppTheme.light, home: const EmployeeCardView()),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeEmployeeCardRemoteDataSource
    implements EmployeeCardRemoteDataSource {
  _FakeEmployeeCardRemoteDataSource({this.invalidOnly = false});

  final bool invalidOnly;

  @override
  Future<EmployeeCardPage> fetch({
    required String status,
    required String employeeType,
    required String query,
    required int page,
    int perPage = 12,
  }) async {
    final ready = const EmployeeCardPerson(
      id: 31,
      name: 'Antonius Kartu Mobile',
      nip: '199211032019021001',
      employeeType: 'Guru',
      position: 'Guru Mata Pelajaran',
      photoUrl: null,
      hasPhoto: true,
      active: true,
      qrData: '199211032019021001',
      canMakeQr: true,
    );
    final invalid = const EmployeeCardPerson(
      id: 32,
      name: 'Pegawai Identitas Belum Lengkap',
      nip: 'NIP-BELUM-VALID',
      employeeType: 'Guru',
      position: 'Tenaga Administrasi',
      photoUrl: null,
      hasPhoto: false,
      active: true,
      qrData: null,
      canMakeQr: false,
    );
    final items = invalidOnly ? [invalid] : [ready, invalid];
    return EmployeeCardPage(
      items: items,
      summary: EmployeeCardSummary(
        total: items.length,
        qrReady: invalidOnly ? 0 : 1,
        withPhoto: invalidOnly ? 0 : 1,
      ),
      employeeTypes: const ['Guru', 'Tenaga Kependidikan'],
      pagination: EmployeeCardPagination(
        page: 1,
        total: items.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
      employeeType: employeeType,
      cardSize: const EmployeeCardSize(
        widthMillimeter: 53.98,
        heightMillimeter: 85.6,
        orientation: 'portrait',
      ),
      canManagePhoto: true,
    );
  }
}
