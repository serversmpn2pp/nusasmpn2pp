import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/late_point_setting/data/late_point_setting_remote_data_source.dart';
import 'package:nusa/features/late_point_setting/domain/late_point_setting.dart';
import 'package:nusa/features/late_point_setting/presentation/late_point_setting_view.dart';

void main() {
  test(
    'domain membaca tahun, nilai bawaan, ringkasan, dan rentang terbuka',
    () {
      final page = LatePointSettingPage.fromJson(_pageJson());

      expect(page.summary.academicYearCount, 1);
      expect(page.summary.activeAcademicYearId, 1);
      expect(page.items.first.saved, isFalse);
      expect(page.items.first.ranges, hasLength(2));
      expect(page.items.first.ranges.last.endMinute, isNull);
      expect(page.items.first.ranges.last.points, 15);
    },
  );

  testWidgets('daftar poin keterlambatan rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeLatePointSettingRemoteDataSource());

    expect(find.text('Poin Keterlambatan'), findsOneWidget);
    expect(find.byKey(const Key('late-point-status-filter')), findsOneWidget);
    expect(find.text('2026/2027'), findsOneWidget);
    expect(find.text('1-10 menit: 0 poin'), findsOneWidget);
    expect(find.text('11 menit atau lebih: 15 poin'), findsOneWidget);
    expect(find.byKey(const Key('configure-late-point-1')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin mengaktifkan dan menambah rentang poin secara native', (
    tester,
  ) async {
    final remote = _FakeLatePointSettingRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('configure-late-point-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('late-point-form-active')));
    await tester.drag(
      find.byKey(const Key('late-point-form-scroll')),
      const Offset(0, -520),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('add-late-point-range')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('late-point-range-2')), findsOneWidget);
    expect(
      (tester.widget<TextField>(
        find.byKey(const Key('late-point-range-start-2')),
      )).controller?.text,
      '21',
    );
    await tester.enterText(
      find.byKey(const Key('late-point-range-points-2')),
      '25',
    );
    await tester.ensureVisible(
      find.byKey(const Key('save-late-point-setting')),
    );
    await tester.tap(find.byKey(const Key('save-late-point-setting')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(remote.lastValue?.active, isTrue);
    expect(remote.lastValue?.ranges, hasLength(3));
    expect(remote.lastValue?.ranges[1].endMinute, 20);
    expect(remote.lastValue?.ranges.last.startMinute, 21);
    expect(remote.lastValue?.ranges.last.points, 25);
    expect(find.text('Otomatis aktif'), findsWidgets);
    expect(find.text('21 menit atau lebih: 25 poin'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  LatePointSettingRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        latePointSettingRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const LatePointSettingView(),
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
    'otomatis_aktif': 0,
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
      'otomatis_aktif': false,
      'rentang': [
        {
          'id': null,
          'menit_mulai': 1,
          'menit_selesai': 10,
          'poin': 0,
          'urutan': 1,
          'label': '1-10 menit',
        },
        {
          'id': null,
          'menit_mulai': 11,
          'menit_selesai': null,
          'poin': 15,
          'urutan': 2,
          'label': '11 menit atau lebih',
        },
      ],
      'diperbarui_oleh': null,
      'diperbarui_pada': null,
    },
  ],
};

final class _FakeLatePointSettingRemoteDataSource
    implements LatePointSettingRemoteDataSource {
  LatePointSetting _setting = LatePointSettingPage.fromJson(_pageJson())
      .items
      .first;
  int updateCalls = 0;
  LatePointSettingFormValue? lastValue;

  @override
  Future<LatePointSettingPage> fetch({
    required String query,
    required String status,
  }) async {
    final matchesQuery =
        query.isEmpty || _setting.academicYear.name.contains(query);
    final matchesStatus =
        status == 'semua' ||
        (status == 'aktif' && _setting.automaticActive) ||
        (status == 'nonaktif' && !_setting.automaticActive);
    final items = matchesQuery && matchesStatus
        ? [_setting]
        : <LatePointSetting>[];

    return LatePointSettingPage(
      items: items,
      summary: LatePointSettingSummary(
        academicYearCount: 1,
        activeAcademicYearId: 1,
        configuredCount: _setting.saved ? 1 : 0,
        automaticActiveCount: _setting.automaticActive ? 1 : 0,
      ),
      access: const LatePointSettingAccess(canManage: true),
      query: query,
      status: status,
    );
  }

  @override
  Future<void> update({
    required int academicYearId,
    required LatePointSettingFormValue value,
  }) async {
    updateCalls++;
    lastValue = value;
    _setting = LatePointSetting(
      academicYear: _setting.academicYear,
      saved: true,
      automaticActive: value.active,
      ranges: [
        for (var index = 0; index < value.ranges.length; index++)
          LatePointRange(
            startMinute: value.ranges[index].startMinute,
            endMinute: value.ranges[index].endMinute,
            points: value.ranges[index].points,
            order: index + 1,
            label: value.ranges[index].endMinute == null
                ? '${value.ranges[index].startMinute} menit atau lebih'
                : '${value.ranges[index].startMinute}-${value.ranges[index].endMinute} menit',
          ),
      ],
      updatedBy: 'Administrator',
      updatedAt: DateTime(2026, 8, 30, 12),
    );
  }
}
