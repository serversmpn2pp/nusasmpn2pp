import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_attendance_report/data/student_attendance_report_remote_data_source.dart';
import 'package:nusa/features/student_attendance_report/domain/student_attendance_report.dart';

class StudentAttendanceReportRepository {
  const StudentAttendanceReportRepository(this._remote);
  final StudentAttendanceReportRemoteDataSource _remote;
  Future<StudentAttendanceReportPage> fetch(Map<String, dynamic> query) =>
      _remote.fetch(query);
  Future<StudentAttendanceReportDetail> detail(
    int id,
    Map<String, dynamic> query,
  ) => _remote.detail(id, query);
  Future<AttendanceReportDownload> download(Map<String, dynamic> query) =>
      _remote.download(query);
}

final studentAttendanceReportRepositoryProvider =
    Provider<StudentAttendanceReportRepository>(
      (ref) => StudentAttendanceReportRepository(
        ref.watch(studentAttendanceReportRemoteDataSourceProvider),
      ),
    );
