import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_schedule/data/worship_schedule_remote_data_source.dart';
import 'package:nusa/features/worship_schedule/domain/worship_schedule.dart';
import 'package:nusa/features/worship_schedule/presentation/worship_schedule_view.dart';

void main() {
  testWidgets('jadwal ibadah enam hari rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeWorshipScheduleRemoteDataSource());

    expect(find.text('Jadwal Ibadah'), findsOneWidget);
    expect(
      find.byKey(const Key('worship-schedule-academic-year-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('worship-schedule-activity-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('worship-schedule-day-card-senin')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('add-worship-schedule')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin dapat menerapkan mengubah dan menonaktifkan jadwal', (
    tester,
  ) async {
    final remote = _FakeWorshipScheduleRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('add-worship-schedule')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('worship-schedule-day-selasa')));
    await tester.tap(find.byKey(const Key('worship-schedule-day-rabu')));
    await tester.tap(find.byKey(const Key('save-worship-schedule')));
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(remote.items.length, 3);

    await tester.tap(find.byKey(const Key('worship-schedule-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ubah').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('save-worship-schedule')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);

    await tester.tap(find.byKey(const Key('worship-schedule-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Nonaktifkan').last);
    await tester.pumpAndSettle();
    await tester.tap(
      find.byKey(const Key('confirm-worship-schedule-deactivate')),
    );
    await tester.pumpAndSettle();

    expect(remote.deactivateCalls, 1);
    expect(find.text('Nonaktif'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  WorshipScheduleRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        worshipScheduleRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const WorshipScheduleView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeWorshipScheduleRemoteDataSource
    implements WorshipScheduleRemoteDataSource {
  final List<WorshipSchedule> items = [
    const WorshipSchedule(
      id: 1,
      activityId: 1,
      academicYearId: 1,
      day: 'senin',
      dayLabel: 'Senin',
      dayOrder: 1,
      scanStart: '11:45',
      eventTime: '12:15',
      scanEnd: '13:15',
      active: true,
      notes: 'Mushalla sekolah',
    ),
  ];

  int createCalls = 0;
  int updateCalls = 0;
  int deactivateCalls = 0;

  static const days = [
    WorshipDay(code: 'senin', label: 'Senin', order: 1),
    WorshipDay(code: 'selasa', label: 'Selasa', order: 2),
    WorshipDay(code: 'rabu', label: 'Rabu', order: 3),
    WorshipDay(code: 'kamis', label: 'Kamis', order: 4),
    WorshipDay(code: 'jumat', label: 'Jumat', order: 5),
    WorshipDay(code: 'sabtu', label: 'Sabtu', order: 6),
  ];

  @override
  Future<WorshipSchedulePage> fetch({
    int? academicYearId,
    int? activityId,
  }) async => WorshipSchedulePage(
    items: [...items]..sort((a, b) => a.dayOrder.compareTo(b.dayOrder)),
    summary: WorshipScheduleSummary(
      dayCount: 6,
      configured: items.length,
      active: items.where((item) => item.active).length,
    ),
    academicYears: const [
      AcademicYearOption(id: 1, name: '2026/2027', active: true),
      AcademicYearOption(id: 2, name: '2025/2026', active: false),
    ],
    activities: const [
      WorshipActivityOption(
        id: 1,
        code: 'sholat_duhur',
        name: 'Sholat Duhur Berjamaah',
        active: true,
      ),
    ],
    days: days,
    selectedAcademicYearId: academicYearId ?? 1,
    selectedActivityId: activityId ?? 1,
  );

  @override
  Future<void> create(WorshipScheduleFormValue value) async {
    createCalls++;
    for (final dayCode in value.days) {
      final day = days.firstWhere((item) => item.code == dayCode);
      final index = items.indexWhere((item) => item.day == dayCode);
      final schedule = WorshipSchedule(
        id: index < 0 ? items.length + 1 : items[index].id,
        activityId: value.activityId,
        academicYearId: value.academicYearId,
        day: day.code,
        dayLabel: day.label,
        dayOrder: day.order,
        scanStart: value.scanStart,
        eventTime: value.eventTime,
        scanEnd: value.scanEnd,
        active: value.active,
        notes: value.notes,
      );
      if (index < 0) {
        items.add(schedule);
      } else {
        items[index] = schedule;
      }
    }
  }

  @override
  Future<void> update({
    required int id,
    required WorshipScheduleFormValue value,
  }) async {
    updateCalls++;
    final index = items.indexWhere((item) => item.id == id);
    final current = items[index];
    items[index] = WorshipSchedule(
      id: current.id,
      activityId: current.activityId,
      academicYearId: current.academicYearId,
      day: current.day,
      dayLabel: current.dayLabel,
      dayOrder: current.dayOrder,
      scanStart: value.scanStart,
      eventTime: value.eventTime,
      scanEnd: value.scanEnd,
      active: value.active,
      notes: value.notes,
    );
  }

  @override
  Future<void> deactivate(int id) async {
    deactivateCalls++;
    final index = items.indexWhere((item) => item.id == id);
    final current = items[index];
    items[index] = WorshipSchedule(
      id: current.id,
      activityId: current.activityId,
      academicYearId: current.academicYearId,
      day: current.day,
      dayLabel: current.dayLabel,
      dayOrder: current.dayOrder,
      scanStart: current.scanStart,
      eventTime: current.eventTime,
      scanEnd: current.scanEnd,
      active: false,
      notes: current.notes,
    );
  }
}
