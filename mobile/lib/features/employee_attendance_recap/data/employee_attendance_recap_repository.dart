import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/employee_attendance_recap/data/employee_attendance_recap_remote_data_source.dart';
import 'package:nusa/features/employee_attendance_recap/domain/employee_attendance_recap.dart';

class EmployeeAttendanceRecapRepository {
  EmployeeAttendanceRecapRepository(this._remote);
  final EmployeeAttendanceRecapRemoteDataSource _remote;

  Future<EmployeeAttendanceRecapPage> fetch({
    required String date,
    String? employeeType,
    int? employeeId,
    required String employeeStatus,
    required String status,
    required String query,
    required int page,
  }) => _remote.fetch(
    date: date,
    employeeType: employeeType,
    employeeId: employeeId,
    employeeStatus: employeeStatus,
    status: status,
    query: query,
    page: page,
  );

  Future<EmployeeAttendanceDetail> detail({
    required int employeeId,
    required String date,
  }) => _remote.detail(employeeId: employeeId, date: date);

  Future<void> correct({
    required int employeeId,
    required String date,
    required EmployeeAttendanceCorrectionValue value,
  }) => _remote.correct(employeeId: employeeId, date: date, value: value);
}

final employeeAttendanceRecapRepositoryProvider =
    Provider<EmployeeAttendanceRecapRepository>(
      (ref) => EmployeeAttendanceRecapRepository(
        ref.watch(employeeAttendanceRecapRemoteDataSourceProvider),
      ),
    );
