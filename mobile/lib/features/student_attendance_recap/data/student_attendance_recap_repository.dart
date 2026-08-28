import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_attendance_recap/data/student_attendance_recap_remote_data_source.dart';
import 'package:nusa/features/student_attendance_recap/domain/student_attendance_recap.dart';

class StudentAttendanceRecapRepository {
  StudentAttendanceRecapRepository(this._remote);
  final StudentAttendanceRecapRemoteDataSource _remote;
  Future<StudentAttendanceRecapPage> fetch({
    required String date,
    int? academicYearId,
    int? classId,
    required String status,
    required String query,
    required int page,
  }) => _remote.fetch(
    date: date,
    academicYearId: academicYearId,
    classId: classId,
    status: status,
    query: query,
    page: page,
  );
  Future<StudentAttendanceDetail> detail({
    required int classMemberId,
    required String date,
  }) => _remote.detail(classMemberId: classMemberId, date: date);
  Future<void> correct({
    required int classMemberId,
    required String date,
    required AttendanceCorrectionValue value,
  }) => _remote.correct(classMemberId: classMemberId, date: date, value: value);
}

final studentAttendanceRecapRepositoryProvider =
    Provider<StudentAttendanceRecapRepository>(
      (ref) => StudentAttendanceRecapRepository(
        ref.watch(studentAttendanceRecapRemoteDataSourceProvider),
      ),
    );
