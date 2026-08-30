import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/early_warning_setting/data/early_warning_setting_remote_data_source.dart';
import 'package:nusa/features/early_warning_setting/domain/early_warning_setting.dart';
import 'package:nusa/features/early_warning_setting/presentation/early_warning_setting_view.dart';

void main() {
  test('domain membaca nilai bawaan, ringkasan, dan pemicu per tahun', () {
    final page = EarlyWarningSettingPage.fromJson(_pageJson());

    expect(page.summary.academicYearCount, 1);
    expect(page.summary.detectionActiveCount, 1);
    expect(page.items.first.saved, isFalse);
    expect(page.items.first.nearThresholdPercentage, 80);
    expect(page.items.first.repeatedViolationCount, 3);
    expect(page.items.first.latePeriodDays, 30);
  });

  testWidgets('daftar peringatan dini rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeEarlyWarningSettingRemoteDataSource());

    expect(find.text('Peringatan Dini Poin'), findsOneWidget);
    expect(
      find.byKey(const Key('early-warning-status-filter')),
      findsOneWidget,
    );
    expect(find.text('2026/2027'), findsOneWidget);
    expect(find.text('80% dari ambang'), findsOneWidget);
    expect(find.text('3 kejadian / 30 hari'), findsOneWidget);
    expect(find.text('Selalu dipantau'), findsOneWidget);
    expect(find.byKey(const Key('configure-early-warning-1')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin mengubah ambang dan notifikasi secara native', (
    tester,
  ) async {
    final remote = _FakeEarlyWarningSettingRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('configure-early-warning-1')));
    await tester.pumpAndSettle();
    await tester.tap(
      find.byKey(const Key('early-warning-form-notification-active')),
    );
    await tester.enterText(
      find.byKey(const Key('early-warning-percentage')),
      '85',
    );
    await tester.tap(find.byKey(const Key('save-early-warning-setting')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(remote.lastValue?.detectionActive, isTrue);
    expect(remote.lastValue?.notificationActive, isFalse);
    expect(remote.lastValue?.nearThresholdPercentage, 85);
    expect(find.text('85% dari ambang'), findsOneWidget);
    expect(find.text('Notifikasi penerima nonaktif'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  EarlyWarningSettingRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        earlyWarningSettingRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const EarlyWarningSettingView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Map<String, dynamic> _pageJson() => {
  'ringkasan': {
    'jumlah_tahun': 1,
    'tahun_aktif_id': 1,
    'sudah_diatur': 0,
    'deteksi_aktif': 1,
    'notifikasi_aktif': 1,
  },
  'filter': {'cari': '', 'status': 'semua'},
  'hak_akses': {'dapat_kelola': true},
  'items': [
    {
      'tahun_pelajaran': {
        'id': 1,
        'nama': '2026/2027',
        'tanggal_mulai': '2026-07-01',
        'tanggal_selesai': '2027-06-30',
        'aktif': true,
      },
      'tersimpan': false,
      'deteksi_aktif': true,
      'notifikasi_aktif': true,
      'persentase_mendekati_ambang': 80,
      'jumlah_pelanggaran_berulang': 3,
      'periode_pelanggaran_hari': 30,
      'jumlah_keterlambatan_berulang': 3,
      'periode_keterlambatan_hari': 30,
      'diperbarui_oleh': null,
      'diperbarui_pada': null,
    },
  ],
};

final class _FakeEarlyWarningSettingRemoteDataSource
    implements EarlyWarningSettingRemoteDataSource {
  EarlyWarningSetting _setting = EarlyWarningSettingPage.fromJson(_pageJson())
      .items
      .first;
  int updateCalls = 0;
  EarlyWarningSettingFormValue? lastValue;

  @override
  Future<EarlyWarningSettingPage> fetch({
    required String query,
    required String status,
  }) async {
    final matchesQuery =
        query.isEmpty || _setting.academicYear.name.contains(query);
    final matchesStatus =
        status == 'semua' ||
        (status == 'aktif' && _setting.detectionActive) ||
        (status == 'nonaktif' && !_setting.detectionActive);
    final items = matchesQuery && matchesStatus
        ? [_setting]
        : <EarlyWarningSetting>[];

    return EarlyWarningSettingPage(
      items: items,
      summary: EarlyWarningSettingSummary(
        academicYearCount: 1,
        activeAcademicYearId: 1,
        configuredCount: _setting.saved ? 1 : 0,
        detectionActiveCount: _setting.detectionActive ? 1 : 0,
        notificationActiveCount: _setting.notificationActive ? 1 : 0,
      ),
      access: const EarlyWarningSettingAccess(canManage: true),
      query: query,
      status: status,
    );
  }

  @override
  Future<void> update({
    required int academicYearId,
    required EarlyWarningSettingFormValue value,
  }) async {
    updateCalls++;
    lastValue = value;
    _setting = EarlyWarningSetting(
      academicYear: _setting.academicYear,
      saved: true,
      detectionActive: value.detectionActive,
      notificationActive: value.notificationActive,
      nearThresholdPercentage: value.nearThresholdPercentage,
      repeatedViolationCount: value.repeatedViolationCount,
      violationPeriodDays: value.violationPeriodDays,
      repeatedLateCount: value.repeatedLateCount,
      latePeriodDays: value.latePeriodDays,
      updatedBy: 'Administrator',
      updatedAt: DateTime(2026, 8, 30, 12),
    );
  }
}
