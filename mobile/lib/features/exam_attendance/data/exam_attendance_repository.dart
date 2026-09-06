import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/exam_attendance/data/exam_attendance_remote_data_source.dart';
import 'package:nusa/features/exam_attendance/domain/exam_attendance.dart';

final class ExamAttendanceRepository {
  const ExamAttendanceRepository(this._remote);

  final ExamAttendanceRemoteDataSource _remote;

  Future<ExamAttendanceDashboard> fetch() => _remote.fetch();

  Future<ExamAttendanceDetail> fetchDetail(int roomId) =>
      _remote.fetchDetail(roomId);

  Future<ExamAttendanceScanResult> scan({
    required int roomId,
    required String rawValue,
  }) => _remote.scan(roomId: roomId, rawValue: rawValue);

  Future<ExamAttendanceDetail> changeAttendance({
    required int roomId,
    required int participantId,
    required String status,
    required String? note,
  }) => _remote.changeAttendance(
    roomId: roomId,
    participantId: participantId,
    status: status,
    note: note,
  );
}

final examAttendanceRepositoryProvider = Provider<ExamAttendanceRepository>(
  (ref) => ExamAttendanceRepository(
    ref.watch(examAttendanceRemoteDataSourceProvider),
  ),
);
