import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_attendance_settings/data/student_attendance_settings_remote_data_source.dart';
import 'package:nusa/features/student_attendance_settings/domain/student_attendance_settings.dart';

final class StudentAttendanceSettingsRepository {
  StudentAttendanceSettingsRepository(this._remote);

  final StudentAttendanceSettingsRemoteDataSource _remote;

  Future<StudentAttendanceSettingsCatalog> fetch({
    required String day,
    required String status,
  }) => _remote.fetch(day: day, status: status);

  Future<void> create(StudentAttendanceSettingsFormValue value) =>
      _remote.create(value);

  Future<void> update({
    required int id,
    required StudentAttendanceSettingsFormValue value,
  }) => _remote.update(id: id, value: value);
}

final studentAttendanceSettingsRepositoryProvider =
    Provider<StudentAttendanceSettingsRepository>(
      (ref) => StudentAttendanceSettingsRepository(
        ref.watch(studentAttendanceSettingsRemoteDataSourceProvider),
      ),
    );
