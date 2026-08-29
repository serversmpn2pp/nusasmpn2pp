import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/employee_attendance_settings/data/employee_attendance_settings_remote_data_source.dart';
import 'package:nusa/features/employee_attendance_settings/domain/employee_attendance_settings.dart';

final class EmployeeAttendanceSettingsRepository {
  EmployeeAttendanceSettingsRepository(this._remote);

  final EmployeeAttendanceSettingsRemoteDataSource _remote;

  Future<EmployeeAttendanceSettingsCatalog> fetch({
    required String query,
    required String day,
    required String scope,
    required String status,
  }) => _remote.fetch(query: query, day: day, scope: scope, status: status);

  Future<void> create(EmployeeAttendanceSettingsFormValue value) =>
      _remote.create(value);

  Future<void> update({
    required int id,
    required EmployeeAttendanceSettingsFormValue value,
  }) => _remote.update(id: id, value: value);
}

final employeeAttendanceSettingsRepositoryProvider =
    Provider<EmployeeAttendanceSettingsRepository>(
      (ref) => EmployeeAttendanceSettingsRepository(
        ref.watch(employeeAttendanceSettingsRemoteDataSourceProvider),
      ),
    );
