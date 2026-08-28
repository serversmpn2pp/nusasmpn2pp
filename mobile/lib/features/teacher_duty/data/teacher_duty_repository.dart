import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/teacher_duty/data/teacher_duty_remote_data_source.dart';
import 'package:nusa/features/teacher_duty/domain/teacher_duty.dart';

class TeacherDutyRepository {
  TeacherDutyRepository(this._remote);
  final TeacherDutyRemoteDataSource _remote;
  Future<DutyScheduleCatalog> schedules({
    int? academicYearId,
    required String day,
    required String status,
    required String query,
  }) => _remote.fetchSchedules(
    academicYearId: academicYearId,
    day: day,
    status: status,
    query: query,
  );
  Future<DutyScheduleReference> reference([int? academicYearId]) =>
      _remote.fetchReference(academicYearId);
  Future<void> create(DutyScheduleFormValue value) =>
      _remote.createSchedule(value);
  Future<void> update(int id, DutyScheduleFormValue value) =>
      _remote.updateSchedule(id, value);
  Future<void> delete(int id) => _remote.deleteSchedule(id);
  Future<MyDutyDashboard> myDuty({
    int? classId,
    required String status,
    required String query,
    required int page,
  }) => _remote.fetchMyDuty(
    classId: classId,
    status: status,
    query: query,
    page: page,
  );
  Future<void> record({
    required int classMemberId,
    required String status,
    required String notes,
  }) => _remote.recordAttendance(
    classMemberId: classMemberId,
    status: status,
    notes: notes,
  );
}

final teacherDutyRepositoryProvider = Provider<TeacherDutyRepository>(
  (ref) =>
      TeacherDutyRepository(ref.watch(teacherDutyRemoteDataSourceProvider)),
);
