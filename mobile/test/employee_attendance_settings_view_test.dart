import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_attendance_settings/data/employee_attendance_settings_remote_data_source.dart';
import 'package:nusa/features/employee_attendance_settings/domain/employee_attendance_settings.dart';
import 'package:nusa/features/employee_attendance_settings/presentation/employee_attendance_settings_view.dart';

void main() {
  test('respons API pengaturan presensi pegawai dapat dipetakan', () {
    final catalog = EmployeeAttendanceSettingsCatalog.fromJson({
      'items': [
        {
          'id': 7,
          'nama_jadwal': 'Jadwal Guru Senin',
          'cakupan': 'jenis_pegawai',
          'cakupan_label': 'Jenis Pegawai',
          'jenis_pegawai': 'Guru',
          'sasaran_label': 'Guru',
          'hari': 'senin',
          'hari_label': 'Senin',
          'urutan_hari': 1,
          'jam_scan_masuk_mulai': '06:00',
          'jam_masuk': '07:00',
          'jam_scan_masuk_selesai': '07:30',
          'jam_scan_pulang_mulai': '14:00',
          'jam_pulang': '14:15',
          'jam_scan_pulang_selesai': '15:00',
          'aktif': true,
        },
      ],
      'ringkasan': {'total': 1, 'aktif': 1, 'nonaktif': 0},
      'hari': [
        {'kode': 'senin', 'label': 'Senin', 'urutan': 1},
      ],
      'cakupan': [
        {'kode': 'jenis_pegawai', 'label': 'Jenis Pegawai'},
      ],
      'jenis_pegawai': ['Guru'],
      'pegawai': [
        {'id': 3, 'nama': 'Antonius', 'nip': '19860101'},
      ],
      'filter': {
        'q': '',
        'hari': 'semua',
        'cakupan': 'semua_cakupan',
        'status': 'semua_status',
      },
      'hak_akses': {'dapat_kelola': true},
    });

    expect(catalog.items.single.targetLabel, 'Guru');
    expect(catalog.items.single.checkInWindow, '06:00–07:30');
    expect(catalog.employees.single.label, 'Antonius · 19860101');
    expect(catalog.canManage, isTrue);
  });

  testWidgets('pengaturan presensi pegawai rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeEmployeeAttendanceSettingsRemote());

    expect(find.text('Pengaturan Presensi Pegawai'), findsOneWidget);
    expect(
      find.byKey(const Key('employee-attendance-settings-search')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('employee-attendance-settings-day-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('employee-attendance-settings-scope-filter')),
      findsOneWidget,
    );
    expect(find.text('Jadwal Semua Senin'), findsOneWidget);
    expect(
      find.byKey(const Key('add-employee-attendance-setting')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin dapat menambah dan mengubah jadwal presensi pegawai', (
    tester,
  ) async {
    final remote = _FakeEmployeeAttendanceSettingsRemote();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('add-employee-attendance-setting')));
    await tester.pumpAndSettle();
    expect(find.text('Tambah Jadwal Presensi Pegawai'), findsOneWidget);
    await tester.enterText(
      find.byKey(const Key('employee-attendance-setting-name')),
      'Jadwal Semua Selasa',
    );
    await tester.tap(find.byKey(const Key('employee-attendance-setting-day')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Selasa').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('save-employee-attendance-setting')));
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(find.text('Jadwal Semua Selasa'), findsOneWidget);

    final mondayCard = find.byKey(const Key('employee-attendance-setting-1'));
    await tester.ensureVisible(mondayCard);
    await tester.tap(mondayCard);
    await tester.pumpAndSettle();
    expect(find.text('Ubah Jadwal Presensi Pegawai'), findsOneWidget);
    await tester.drag(
      find.byKey(const Key('employee-attendance-setting-form-scroll')),
      const Offset(0, -720),
    );
    await tester.pumpAndSettle();
    await tester.tap(
      find.byKey(const Key('employee-attendance-setting-active')),
    );
    await tester.tap(find.byKey(const Key('save-employee-attendance-setting')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(find.text('Nonaktif'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  EmployeeAttendanceSettingsRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        employeeAttendanceSettingsRemoteDataSourceProvider.overrideWithValue(
          remote,
        ),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const EmployeeAttendanceSettingsView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeEmployeeAttendanceSettingsRemote
    implements EmployeeAttendanceSettingsRemoteDataSource {
  final List<EmployeeAttendanceSetting> _items = [
    const EmployeeAttendanceSetting(
      id: 1,
      name: 'Jadwal Semua Senin',
      scope: 'semua',
      scopeLabel: 'Semua Pegawai',
      targetLabel: 'Semua pegawai',
      day: 'senin',
      dayLabel: 'Senin',
      dayOrder: 1,
      checkInScanStart: '06:30',
      checkInTime: '07:15',
      checkInScanEnd: '08:00',
      checkOutScanStart: '14:00',
      checkOutTime: '14:15',
      checkOutScanEnd: '16:00',
      active: true,
      notes: 'Jadwal reguler',
    ),
  ];

  static const _days = [
    AttendanceReferenceOption(code: 'senin', label: 'Senin', order: 1),
    AttendanceReferenceOption(code: 'selasa', label: 'Selasa', order: 2),
    AttendanceReferenceOption(code: 'rabu', label: 'Rabu', order: 3),
    AttendanceReferenceOption(code: 'kamis', label: 'Kamis', order: 4),
    AttendanceReferenceOption(code: 'jumat', label: 'Jumat', order: 5),
    AttendanceReferenceOption(code: 'sabtu', label: 'Sabtu', order: 6),
    AttendanceReferenceOption(code: 'minggu', label: 'Minggu', order: 7),
  ];
  static const _scopes = [
    AttendanceReferenceOption(code: 'semua', label: 'Semua Pegawai'),
    AttendanceReferenceOption(code: 'jenis_pegawai', label: 'Jenis Pegawai'),
    AttendanceReferenceOption(code: 'pegawai', label: 'Pegawai Tertentu'),
  ];
  static const _employees = [
    AttendanceEmployeeReference(
      id: 11,
      name: 'Antonius',
      nip: '198601012026081001',
      type: 'Guru',
    ),
  ];

  int createCalls = 0;
  int updateCalls = 0;

  @override
  Future<EmployeeAttendanceSettingsCatalog> fetch({
    required String query,
    required String day,
    required String scope,
    required String status,
  }) async {
    final filtered = _items.where((item) {
      final matchesQuery =
          query.isEmpty ||
          item.name.toLowerCase().contains(query.toLowerCase());
      final matchesDay = day == 'semua' || item.day == day;
      final matchesScope = scope == 'semua_cakupan' || item.scope == scope;
      final matchesStatus =
          status == 'semua_status' ||
          (status == 'aktif' && item.active) ||
          (status == 'nonaktif' && !item.active);
      return matchesQuery && matchesDay && matchesScope && matchesStatus;
    }).toList()..sort((a, b) => a.dayOrder.compareTo(b.dayOrder));

    return EmployeeAttendanceSettingsCatalog(
      items: filtered,
      summary: EmployeeAttendanceSettingsSummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
      ),
      days: _days,
      scopes: _scopes,
      employeeTypes: const ['Guru', 'Tenaga Kependidikan'],
      employees: _employees,
      query: query,
      selectedDay: day,
      selectedScope: scope,
      status: status,
      canManage: true,
    );
  }

  @override
  Future<void> create(EmployeeAttendanceSettingsFormValue value) async {
    createCalls++;
    _items.add(_fromValue(_items.length + 1, value));
  }

  @override
  Future<void> update({
    required int id,
    required EmployeeAttendanceSettingsFormValue value,
  }) async {
    updateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    _items[index] = _fromValue(id, value);
  }

  EmployeeAttendanceSetting _fromValue(
    int id,
    EmployeeAttendanceSettingsFormValue value,
  ) {
    final day = _days.firstWhere((item) => item.code == value.day);
    final scope = _scopes.firstWhere((item) => item.code == value.scope);
    final employee = value.employeeId == null
        ? null
        : _employees.firstWhere((item) => item.id == value.employeeId);
    return EmployeeAttendanceSetting(
      id: id,
      name: value.name,
      scope: value.scope,
      scopeLabel: scope.label,
      targetLabel: value.scope == 'jenis_pegawai'
          ? value.employeeType ?? '-'
          : value.scope == 'pegawai'
          ? employee?.name ?? '-'
          : 'Semua pegawai',
      employeeType: value.employeeType,
      employeeId: value.employeeId,
      employee: employee,
      day: value.day,
      dayLabel: day.label,
      dayOrder: day.order,
      checkInScanStart: value.checkInScanStart,
      checkInTime: value.checkInTime,
      checkInScanEnd: value.checkInScanEnd,
      checkOutScanStart: value.checkOutScanStart,
      checkOutTime: value.checkOutTime,
      checkOutScanEnd: value.checkOutScanEnd,
      active: value.active,
      notes: value.notes,
    );
  }
}
