import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/app/router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/auth/domain/auth_session.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';
import 'package:nusa/features/menu/data/menu_remote_data_source.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';
import 'package:nusa/features/sts_reports/data/sts_reports_remote_data_source.dart';
import 'package:nusa/features/sts_reports/domain/sts_reports.dart';
import 'package:nusa/features/sts_reports/presentation/sts_reports_view.dart';

import 'fixtures/sts_report_fixture.dart';

void main() {
  testWidgets('real app router opens both STS screens from Akademik menu', (
    tester,
  ) async {
    final remote = _Reports();
    final container = ProviderContainer(
      overrides: [
        authControllerProvider.overrideWith(_Auth.new),
        splashGateProvider.overrideWith((ref) async {}),
        menuRemoteDataSourceProvider.overrideWithValue(_Menu()),
        stsReportsRemoteDataSourceProvider.overrideWithValue(remote),
      ],
    );
    addTearDown(container.dispose);
    await container.read(authControllerProvider.future);
    await container.read(splashGateProvider.future);
    final router = container.read(appRouterProvider);
    router.go('/menu/akademik');
    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Penilaian'), findsOneWidget);
    await tester.tap(find.byKey(const Key('menu-item-rapor-sts')));
    await tester.pumpAndSettle();
    expect(find.byType(RaporStsView), findsOneWidget);
    expect(remote.requests.last, StsReportKind.report);
    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-item-leger-sts')));
    await tester.pumpAndSettle();
    expect(find.byType(LegerStsView), findsOneWidget);
    expect(remote.requests.last, StsReportKind.ledger);
    expect(tester.takeException(), isNull);
  });
}

class _Auth extends AuthController {
  @override
  Future<AuthState> build() async => const AuthState(
    session: AuthSession(
      token: 'synthetic-test-token',
      pengguna: Pengguna(
        id: 1,
        nama: 'Admin Demo',
        username: 'admin.demo',
        jenisAkun: 'pegawai',
        administrator: true,
        wajibGantiKataSandi: false,
        peran: ['administrator'],
        izin: ['nilai.rekap'],
      ),
    ),
  );
}

class _Menu implements MenuRemoteDataSource {
  @override
  Future<MenuCatalog> fetchCatalog() async => MenuCatalog.fromJson({
    'dihasilkan_pada': '2026-10-12T12:00:00+07:00',
    'kelompok': [
      {
        'kode': 'akademik',
        'label': 'Akademik',
            'deskripsi': 'Pembelajaran dan penilaian.',
        'ikon': 'academic',
            'items': [
          {
            'kode': 'rapor-sts',
            'label': 'Rapor STS',
            'inisial': 'RS',
            'subkelompok': 'Penilaian',
            'status': 'tersedia',
            'rute': AppRoutes.stsReport,
          },
          {
            'kode': 'leger-sts',
            'label': 'Leger STS',
            'inisial': 'LG',
            'subkelompok': 'Penilaian',
            'status': 'tersedia',
            'rute': AppRoutes.stsLedger,
          },
        ],
      },
    ],
  });
  @override
  Future<MenuCatalog> resetQuickAccess() => throw UnimplementedError();
  @override
  Future<MenuCatalog> saveQuickAccess(List<String> menuCodes) =>
      throw UnimplementedError();
}

class _Reports implements StsReportsRemoteDataSource {
  final requests = <StsReportKind>[];
  @override
  Future<StsReportPage> fetch(
    StsReportKind kind,
    StsReportFilter filter,
  ) async {
    requests.add(kind);
    return StsReportPage.fromJson(stsReportFixture(kind: kind, filter: filter));
  }
}
