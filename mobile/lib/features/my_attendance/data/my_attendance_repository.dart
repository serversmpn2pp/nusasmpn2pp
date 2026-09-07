import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/my_attendance/data/my_attendance_remote_data_source.dart';
import 'package:nusa/features/my_attendance/domain/my_attendance.dart';

class MyAttendanceRepository {
  MyAttendanceRepository(this._remote);

  final MyAttendanceRemoteDataSource _remote;

  Future<MyAttendancePage> fetch({
    required int? studentId,
    required int? academicYearId,
    required String? month,
  }) => _remote.fetch(
    studentId: studentId,
    academicYearId: academicYearId,
    month: month,
  );
}

final myAttendanceRepositoryProvider = Provider<MyAttendanceRepository>(
  (ref) =>
      MyAttendanceRepository(ref.watch(myAttendanceRemoteDataSourceProvider)),
);
