import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_activity/data/worship_activity_remote_data_source.dart';
import 'package:nusa/features/worship_activity/domain/worship_activity.dart';
import 'package:nusa/features/worship_activity/presentation/worship_activity_view.dart';

void main() {
  testWidgets('daftar kegiatan ibadah rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeWorshipActivityRemoteDataSource());

    expect(find.text('Kegiatan Ibadah'), findsOneWidget);
    expect(
      find.byKey(const Key('worship-activity-status-filter')),
      findsOneWidget,
    );
    expect(find.text('Sholat Duhur Berjamaah'), findsOneWidget);
    expect(find.byKey(const Key('add-worship-activity')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin dapat menambah, mengubah, dan menonaktifkan kegiatan', (
    tester,
  ) async {
    final remote = _FakeWorshipActivityRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('add-worship-activity')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('worship-activity-form-name')),
      'Tadarus Pagi Mobile',
    );
    await tester.enterText(
      find.byKey(const Key('worship-activity-form-code')),
      'tadarus pagi mobile',
    );
    await tester.tap(find.byKey(const Key('save-worship-activity')));
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(find.text('Tadarus Pagi Mobile'), findsOneWidget);

    await tester.tap(find.byKey(const Key('worship-activity-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ubah').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('worship-activity-form-name')),
      'Sholat Duhur Bersama',
    );
    await tester.tap(find.byKey(const Key('save-worship-activity')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(find.text('Sholat Duhur Bersama'), findsOneWidget);

    await tester.tap(find.byKey(const Key('worship-activity-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Nonaktifkan').last);
    await tester.pumpAndSettle();
    expect(find.textContaining('2 jadwal terkait'), findsOneWidget);
    await tester.tap(
      find.byKey(const Key('confirm-worship-activity-deactivate')),
    );
    await tester.pumpAndSettle();

    expect(remote.deactivateCalls, 1);
    expect(find.text('Nonaktif'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  WorshipActivityRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        worshipActivityRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const WorshipActivityView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeWorshipActivityRemoteDataSource
    implements WorshipActivityRemoteDataSource {
  final List<WorshipActivity> _items = [
    const WorshipActivity(
      id: 1,
      code: 'sholat_duhur',
      name: 'Sholat Duhur Berjamaah',
      notes: 'Presensi setelah pelaksanaan sholat berjamaah.',
      active: true,
      scheduleCount: 2,
      activeScheduleCount: 2,
    ),
    const WorshipActivity(
      id: 2,
      code: 'dhuha_mobile',
      name: 'Sholat Dhuha Mobile',
      active: true,
      scheduleCount: 0,
      activeScheduleCount: 0,
    ),
  ];

  int createCalls = 0;
  int updateCalls = 0;
  int deactivateCalls = 0;

  @override
  Future<WorshipActivityPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    final normalized = query.toLowerCase();
    final filtered = _items.where((item) {
      final matchesQuery =
          normalized.isEmpty ||
          item.name.toLowerCase().contains(normalized) ||
          item.code.toLowerCase().contains(normalized);
      final matchesStatus =
          status == 'semua' ||
          (status == 'aktif' && item.active) ||
          (status == 'nonaktif' && !item.active);
      return matchesQuery && matchesStatus;
    }).toList();

    return WorshipActivityPage(
      items: filtered,
      summary: WorshipActivitySummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
      ),
      pagination: WorshipActivityPagination(
        page: 1,
        total: filtered.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
    );
  }

  @override
  Future<void> create(WorshipActivityFormValue value) async {
    createCalls++;
    _items.add(
      WorshipActivity(
        id: _items.length + 1,
        code: value.code,
        name: value.name,
        notes: value.notes,
        active: value.active,
        scheduleCount: 0,
        activeScheduleCount: 0,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required WorshipActivityFormValue value,
  }) async {
    updateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    final current = _items[index];
    _items[index] = WorshipActivity(
      id: current.id,
      code: value.code,
      name: value.name,
      notes: value.notes,
      active: value.active,
      scheduleCount: current.scheduleCount,
      activeScheduleCount: current.activeScheduleCount,
    );
  }

  @override
  Future<void> deactivate(int id) async {
    deactivateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    final current = _items[index];
    _items[index] = WorshipActivity(
      id: current.id,
      code: current.code,
      name: current.name,
      notes: current.notes,
      active: false,
      scheduleCount: current.scheduleCount,
      activeScheduleCount: 0,
    );
  }
}
