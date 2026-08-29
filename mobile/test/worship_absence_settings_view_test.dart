import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_absence_settings/data/worship_absence_settings_remote_data_source.dart';
import 'package:nusa/features/worship_absence_settings/domain/worship_absence_settings.dart';
import 'package:nusa/features/worship_absence_settings/presentation/worship_absence_settings_view.dart';

void main() {
  testWidgets('pengaturan berhalangan rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeWorshipAbsenceSettingsRemoteDataSource());

    expect(find.text('Berhalangan Ibadah'), findsOneWidget);
    expect(find.text('2035/2036'), findsOneWidget);
    expect(find.byKey(const Key('edit-worship-absence-limit')), findsOneWidget);
    await tester.ensureVisible(find.byKey(const Key('add-worship-companion')));
    expect(find.byKey(const Key('add-worship-companion')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin mengatur batas dan CRUD pendamping secara native', (
    tester,
  ) async {
    final remote = _FakeWorshipAbsenceSettingsRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('edit-worship-absence-limit')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('worship-absence-limit-days')),
      '6',
    );
    await tester.tap(find.byKey(const Key('save-worship-absence-limit')));
    await tester.pumpAndSettle();

    expect(remote.updateSettingsCalls, 1);
    expect(find.textContaining('6 hari kalender'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('add-worship-companion')));
    await tester.tap(find.byKey(const Key('add-worship-companion')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('worship-companion-employee')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Guru Pendamping Dua').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('save-worship-companion')));
    await tester.pumpAndSettle();

    expect(remote.saveCompanionCalls, 1);
    expect(find.text('Guru Pendamping Dua'), findsOneWidget);

    await tester.ensureVisible(
      find.byKey(const Key('worship-companion-menu-1')),
    );
    await tester.tap(find.byKey(const Key('worship-companion-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Atur').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('worship-companion-all-classes')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('save-worship-companion')));
    await tester.pumpAndSettle();

    expect(remote.saveCompanionCalls, 2);

    await tester.ensureVisible(
      find.byKey(const Key('worship-companion-menu-1')),
    );
    await tester.tap(find.byKey(const Key('worship-companion-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Nonaktifkan').last);
    await tester.pumpAndSettle();
    await tester.tap(
      find.byKey(const Key('confirm-worship-companion-deactivate')),
    );
    await tester.pumpAndSettle();

    expect(remote.deactivateCompanionCalls, 1);
    expect(find.text('Guru Pendamping Satu'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  WorshipAbsenceSettingsRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        worshipAbsenceSettingsRemoteDataSourceProvider.overrideWithValue(
          remote,
        ),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const WorshipAbsenceSettingsView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeWorshipAbsenceSettingsRemoteDataSource
    implements WorshipAbsenceSettingsRemoteDataSource {
  WorshipAbsenceSettings settings = const WorshipAbsenceSettings(
    confirmationDayLimit: 7,
    active: true,
  );
  final List<WorshipCompanionAssignment> assignments = [
    const WorshipCompanionAssignment(
      id: 1,
      employeeId: 1,
      allClasses: false,
      active: true,
      employeeName: 'Guru Pendamping Satu',
      employeeNip: '198001012010012001',
      classes: [WorshipCompanionClass(id: 1, name: 'VII.A', grade: 7)],
      assignedBy: 'Administrator',
    ),
  ];

  int updateSettingsCalls = 0;
  int saveCompanionCalls = 0;
  int deactivateCompanionCalls = 0;

  static const employees = [
    WorshipCompanionEmployee(
      id: 1,
      name: 'Guru Pendamping Satu',
      nip: '198001012010012001',
      accountActive: true,
    ),
    WorshipCompanionEmployee(
      id: 2,
      name: 'Guru Pendamping Dua',
      nip: '198101012011012002',
      accountActive: true,
    ),
  ];

  static const classes = [
    WorshipCompanionClass(id: 1, name: 'VII.A', grade: 7),
    WorshipCompanionClass(id: 2, name: 'VIII.A', grade: 8),
  ];

  @override
  Future<WorshipAbsenceSettingsPage> fetch() async =>
      WorshipAbsenceSettingsPage(
        available: true,
        academicYear: const WorshipAbsenceAcademicYear(
          id: 1,
          name: '2035/2036',
        ),
        settings: settings,
        summary: WorshipAbsenceSummary(
          activeCompanions: assignments.length,
          coveredClasses: assignments.any((item) => item.allClasses)
              ? classes.length
              : assignments
                    .expand((item) => item.classes)
                    .map((item) => item.id)
                    .toSet()
                    .length,
          classCount: classes.length,
        ),
        employees: employees,
        classes: classes,
        assignments: List.unmodifiable(assignments),
      );

  @override
  Future<void> updateSettings(WorshipAbsenceSettingsValue value) async {
    updateSettingsCalls++;
    settings = WorshipAbsenceSettings(
      confirmationDayLimit: value.confirmationDayLimit,
      active: value.active,
    );
  }

  @override
  Future<void> saveCompanion(WorshipCompanionAssignmentValue value) async {
    saveCompanionCalls++;
    final employee = employees.firstWhere(
      (item) => item.id == value.employeeId,
    );
    final index = assignments.indexWhere(
      (item) => item.employeeId == value.employeeId,
    );
    final item = WorshipCompanionAssignment(
      id: index < 0 ? assignments.length + 1 : assignments[index].id,
      employeeId: employee.id,
      allClasses: value.allClasses,
      active: true,
      employeeName: employee.name,
      employeeNip: employee.nip,
      classes: value.allClasses
          ? const []
          : classes
                .where((item) => value.classIds.contains(item.id))
                .toList(growable: false),
      assignedBy: 'Administrator',
    );
    if (index < 0) {
      assignments.add(item);
    } else {
      assignments[index] = item;
    }
  }

  @override
  Future<void> deactivateCompanion(int id) async {
    deactivateCompanionCalls++;
    assignments.removeWhere((item) => item.id == id);
  }
}
