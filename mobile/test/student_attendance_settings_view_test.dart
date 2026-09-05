import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_attendance_settings/data/student_attendance_settings_remote_data_source.dart';
import 'package:nusa/features/student_attendance_settings/domain/student_attendance_settings.dart';
import 'package:nusa/features/student_attendance_settings/presentation/student_attendance_settings_view.dart';

void main() {
  test('respons jadwal Jumat memetakan dua jam pulang', () {
    final setting = StudentAttendanceSetting.fromJson(const {
      'id': 5,
      'hari': 'jumat',
      'hari_label': 'Jumat',
      'urutan_hari': 5,
      'jam_scan_masuk_mulai': '05:30',
      'jam_masuk': '06:25',
      'jam_scan_masuk_selesai': '07:00',
      'jam_scan_pulang_mulai': '12:50',
      'jam_pulang': '12:50',
      'jam_scan_pulang_selesai': '14:00',
      'pulang_jumat_dibedakan': true,
      'jam_scan_pulang_perempuan_mulai': '11:50',
      'jam_pulang_perempuan': '11:50',
      'jam_scan_pulang_perempuan_selesai': '14:00',
      'aktif': true,
    });

    expect(setting.separateFridayCheckOut, isTrue);
    expect(setting.femaleCheckOutTime, '11:50');
    expect(setting.femaleCheckOutWindow, '11:50–14:00');
    expect(setting.checkOutTime, '12:50');
  });

  testWidgets('pengaturan presensi siswa rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeAttendanceSettingsRemoteDataSource());

    expect(find.text('Pengaturan Presensi Siswa'), findsOneWidget);
    expect(
      find.byKey(const Key('attendance-settings-day-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('attendance-settings-status-filter')),
      findsOneWidget,
    );
    expect(find.text('Senin'), findsOneWidget);
    expect(
      find.byKey(const Key('add-student-attendance-setting')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin dapat menambah dan mengubah pengaturan presensi siswa', (
    tester,
  ) async {
    final remote = _FakeAttendanceSettingsRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('add-student-attendance-setting')));
    await tester.pumpAndSettle();
    expect(find.text('Tambah Pengaturan Presensi'), findsOneWidget);
    await tester.tap(find.byKey(const Key('save-student-attendance-setting')));
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(find.text('Selasa'), findsOneWidget);

    final mondayCard = find.byKey(const Key('student-attendance-setting-1'));
    await tester.ensureVisible(mondayCard);
    await tester.tap(mondayCard);
    await tester.pumpAndSettle();
    expect(find.text('Ubah Pengaturan Presensi'), findsOneWidget);
    await tester.drag(
      find.byKey(const Key('student-attendance-setting-form-scroll')),
      const Offset(0, -520),
    );
    await tester.pumpAndSettle();
    await tester.tap(
      find.byKey(const Key('student-attendance-setting-active')),
    );
    await tester.tap(find.byKey(const Key('save-student-attendance-setting')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(find.text('Nonaktif'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('form Jumat menampilkan jadwal pulang siswi dan laki-laki', (
    tester,
  ) async {
    final remote = _FakeAttendanceSettingsRemoteDataSource(withFriday: true);
    await _pumpView(tester, remote);

    final fridayCard = find.byKey(const Key('student-attendance-setting-5'));
    await tester.ensureVisible(fridayCard);
    await tester.tap(fridayCard);
    await tester.pumpAndSettle();
    await tester.drag(
      find.byKey(const Key('student-attendance-setting-form-scroll')),
      const Offset(0, -520),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('separate-friday-check-out')), findsOneWidget);
    expect(find.text('Jam Pulang Siswa Laki-laki'), findsOneWidget);
    expect(find.text('Jadwal Pulang Siswi'), findsOneWidget);
    expect(
      find.byKey(const Key('attendance-female-check-out-time')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  StudentAttendanceSettingsRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        studentAttendanceSettingsRemoteDataSourceProvider.overrideWithValue(
          remote,
        ),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const StudentAttendanceSettingsView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeAttendanceSettingsRemoteDataSource
    implements StudentAttendanceSettingsRemoteDataSource {
  _FakeAttendanceSettingsRemoteDataSource({bool withFriday = false}) {
    if (withFriday) {
      _items.add(
        const StudentAttendanceSetting(
          id: 5,
          day: 'jumat',
          dayLabel: 'Jumat',
          dayOrder: 5,
          checkInScanStart: '05:30',
          checkInTime: '06:25',
          checkInScanEnd: '07:00',
          checkOutScanStart: '12:50',
          checkOutTime: '12:50',
          checkOutScanEnd: '14:00',
          separateFridayCheckOut: true,
          femaleCheckOutScanStart: '11:50',
          femaleCheckOutTime: '11:50',
          femaleCheckOutScanEnd: '14:00',
          active: true,
          notes: 'Jadwal pulang khusus Jumat',
        ),
      );
    }
  }

  final List<StudentAttendanceSetting> _items = [
    const StudentAttendanceSetting(
      id: 1,
      day: 'senin',
      dayLabel: 'Senin',
      dayOrder: 1,
      checkInScanStart: '06:00',
      checkInTime: '07:00',
      checkInScanEnd: '07:30',
      checkOutScanStart: '14:00',
      checkOutTime: '14:10',
      checkOutScanEnd: '15:00',
      active: true,
      notes: 'Jadwal reguler',
    ),
  ];

  static const _dayLabels = <String, String>{
    'senin': 'Senin',
    'selasa': 'Selasa',
    'rabu': 'Rabu',
    'kamis': 'Kamis',
    'jumat': 'Jumat',
    'sabtu': 'Sabtu',
    'minggu': 'Minggu',
  };

  int createCalls = 0;
  int updateCalls = 0;

  @override
  Future<StudentAttendanceSettingsCatalog> fetch({
    required String day,
    required String status,
  }) async {
    final filtered = _items.where((item) {
      final matchesDay = day == 'semua' || item.day == day;
      final matchesStatus =
          status == 'semua' ||
          (status == 'aktif' && item.active) ||
          (status == 'nonaktif' && !item.active);
      return matchesDay && matchesStatus;
    }).toList()..sort((a, b) => a.dayOrder.compareTo(b.dayOrder));
    final configured = _items.map((item) => item.day).toSet();

    return StudentAttendanceSettingsCatalog(
      items: filtered,
      summary: StudentAttendanceSettingsSummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
        unconfigured: _dayLabels.length - _items.length,
      ),
      days: [
        for (final entry in _dayLabels.entries.indexed)
          AttendanceDay(
            code: entry.$2.key,
            label: entry.$2.value,
            order: entry.$1 + 1,
            configured: configured.contains(entry.$2.key),
          ),
      ],
      selectedDay: day,
      status: status,
      canManage: true,
    );
  }

  @override
  Future<void> create(StudentAttendanceSettingsFormValue value) async {
    createCalls++;
    _items.add(_fromValue(_items.length + 1, value));
  }

  @override
  Future<void> update({
    required int id,
    required StudentAttendanceSettingsFormValue value,
  }) async {
    updateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    _items[index] = _fromValue(id, value);
  }

  StudentAttendanceSetting _fromValue(
    int id,
    StudentAttendanceSettingsFormValue value,
  ) => StudentAttendanceSetting(
    id: id,
    day: value.day,
    dayLabel: _dayLabels[value.day] ?? value.day,
    dayOrder: _dayLabels.keys.toList().indexOf(value.day) + 1,
    checkInScanStart: value.checkInScanStart,
    checkInTime: value.checkInTime,
    checkInScanEnd: value.checkInScanEnd,
    checkOutScanStart: value.checkOutScanStart,
    checkOutTime: value.checkOutTime,
    checkOutScanEnd: value.checkOutScanEnd,
    separateFridayCheckOut: value.separateFridayCheckOut,
    femaleCheckOutScanStart: value.femaleCheckOutScanStart,
    femaleCheckOutTime: value.femaleCheckOutTime,
    femaleCheckOutScanEnd: value.femaleCheckOutScanEnd,
    active: value.active,
    notes: value.notes,
  );
}
