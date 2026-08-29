import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/employee_attendance_report/data/employee_attendance_report_remote_data_source.dart';
import 'package:nusa/features/employee_attendance_report/domain/employee_attendance_report.dart';

class EmployeeAttendanceReportRepository {
  const EmployeeAttendanceReportRepository(this._remote);
  final EmployeeAttendanceReportRemoteDataSource _remote;

  Future<EmployeeAttendanceReportPage> fetch(Map<String, dynamic> query) =>
      _remote.fetch(query);

  Future<EmployeeAttendanceReportDetail> detail(
    int employeeId,
    Map<String, dynamic> query,
  ) => _remote.detail(employeeId, query);
}

final employeeAttendanceReportRepositoryProvider =
    Provider<EmployeeAttendanceReportRepository>(
      (ref) => EmployeeAttendanceReportRepository(
        ref.watch(employeeAttendanceReportRemoteDataSourceProvider),
      ),
    );
