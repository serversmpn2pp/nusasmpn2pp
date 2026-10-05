import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/central_exam_execution/data/central_exam_execution_remote_data_source.dart';
import 'package:nusa/features/central_exam_execution/domain/central_exam_execution.dart';

class CentralExamExecutionRepository {
  const CentralExamExecutionRepository(this._remote);
  final CentralExamExecutionRemoteDataSource _remote;

  Future<CentralExamExecutionPage> events({
    required String query,
    required String status,
    required int page,
  }) => _remote.fetchEvents(query: query, status: status, page: page);

  Future<CentralExamExecutionDetail> detail(
    CentralExamExecutionRequest request,
  ) => _remote.fetchDetail(request);

  Future<String> assignSupervisor({
    required int eventId,
    required int scheduleId,
    required int sourceRoomId,
    required String role,
    required int employeeId,
    required String? reason,
  }) => _remote.assignSupervisor(
    eventId: eventId,
    scheduleId: scheduleId,
    sourceRoomId: sourceRoomId,
    role: role,
    employeeId: employeeId,
    reason: reason,
  );

  Future<void> unlockSafeMode(int participantId) =>
      _remote.unlockSafeMode(participantId);

  Future<String> reopenForRetake({
    required int eventId,
    required int scheduleId,
    required int participantId,
    required String reason,
  }) => _remote.reopenForRetake(
    eventId: eventId,
    scheduleId: scheduleId,
    participantId: participantId,
    reason: reason,
  );
}

final centralExamExecutionRepositoryProvider =
    Provider<CentralExamExecutionRepository>(
      (ref) => CentralExamExecutionRepository(
        ref.watch(centralExamExecutionRemoteDataSourceProvider),
      ),
    );
