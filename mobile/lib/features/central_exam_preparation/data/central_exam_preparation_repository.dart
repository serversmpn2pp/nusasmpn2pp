import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/central_exam_preparation/data/central_exam_preparation_remote_data_source.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';

final class CentralExamPreparationRepository {
  const CentralExamPreparationRepository(this._remote);
  final CentralExamPreparationRemoteDataSource _remote;

  Future<CentralExamPreparationPage> fetch({
    required String query,
    required String status,
    required int page,
  }) => _remote.fetch(query: query, status: status, page: page);
  Future<CentralExamPreparationDetail> detail(int eventId) =>
      _remote.detail(eventId);
  Future<int> createEvent(CentralExamEventFormValue value) =>
      _remote.createEvent(value);
  Future<void> updateEvent(int id, CentralExamEventFormValue value) =>
      _remote.updateEvent(id, value);
  Future<void> deleteEvent(int id) => _remote.deleteEvent(id);
  Future<void> saveCommittee(
    int eventId,
    CentralExamCommitteeFormValue value,
  ) => _remote.saveCommittee(eventId, value);
  Future<void> deleteCommittee(int eventId, int memberId) =>
      _remote.deleteCommittee(eventId, memberId);
  Future<void> createSession(int eventId, CentralExamSessionFormValue value) =>
      _remote.createSession(eventId, value);
  Future<void> updateSession(
    int eventId,
    int sessionId,
    CentralExamSessionFormValue value,
  ) => _remote.updateSession(eventId, sessionId, value);
  Future<void> deleteSession(int eventId, int sessionId) =>
      _remote.deleteSession(eventId, sessionId);
  Future<void> createRoom(int eventId, CentralExamRoomFormValue value) =>
      _remote.createRoom(eventId, value);
  Future<void> updateRoom(
    int eventId,
    int roomId,
    CentralExamRoomFormValue value,
  ) => _remote.updateRoom(eventId, roomId, value);
  Future<void> deleteRoom(int eventId, int roomId) =>
      _remote.deleteRoom(eventId, roomId);
  Future<void> saveRoomAssignment(
    int eventId,
    CentralExamRoomAssignmentFormValue value,
  ) => _remote.saveRoomAssignment(eventId, value);
  Future<void> generateParticipants(int eventId, int groupId) =>
      _remote.generateParticipants(eventId, groupId);
  Future<void> deleteRoomAssignment(int eventId, int groupId) =>
      _remote.deleteRoomAssignment(eventId, groupId);
  Future<CentralExamDistributionDetail> distributionDetail(
    int eventId,
    int groupId,
  ) => _remote.distributionDetail(eventId, groupId);
  Future<void> createSchedule(
    int eventId,
    CentralExamScheduleFormValue value,
  ) => _remote.createSchedule(eventId, value);
  Future<void> updateSchedule(
    int eventId,
    int scheduleId,
    CentralExamScheduleFormValue value,
  ) => _remote.updateSchedule(eventId, scheduleId, value);
  Future<void> deleteSchedule(int eventId, int scheduleId) =>
      _remote.deleteSchedule(eventId, scheduleId);
}

final centralExamPreparationRepositoryProvider =
    Provider<CentralExamPreparationRepository>(
      (ref) => CentralExamPreparationRepository(
        ref.watch(centralExamPreparationRemoteDataSourceProvider),
      ),
    );
