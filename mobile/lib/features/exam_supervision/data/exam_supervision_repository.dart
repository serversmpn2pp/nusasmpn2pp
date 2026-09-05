import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/exam_supervision/data/exam_supervision_remote_data_source.dart';
import 'package:nusa/features/exam_supervision/domain/exam_supervision.dart';

class ExamSupervisionRepository {
  const ExamSupervisionRepository(this._remote);

  final ExamSupervisionRemoteDataSource _remote;

  Future<ExamSupervisionDetail> fetchDetail(int roomId) =>
      _remote.fetchDetail(roomId);

  Future<ExamSupervisionDetail> changeRoomStatus(int roomId, String action) =>
      _remote.changeRoomStatus(roomId, action);

  Future<ExamSupervisionDetail> saveNotes({
    required int roomId,
    required String minutes,
    required String obstacles,
    required String followUp,
    required String notes,
  }) => _remote.saveNotes(
    roomId: roomId,
    minutes: minutes,
    obstacles: obstacles,
    followUp: followUp,
    notes: notes,
  );

  Future<ExamSupervisionDetail> changeAttendance({
    required int roomId,
    required int participantId,
    required String status,
    required String? notes,
  }) => _remote.changeAttendance(
    roomId: roomId,
    participantId: participantId,
    status: status,
    notes: notes,
  );

  Future<ExamSupervisionDetail> resetDevice({
    required int roomId,
    required int participantId,
  }) => _remote.resetDevice(roomId: roomId, participantId: participantId);

  Future<void> unlockSafeMode(int participantId) =>
      _remote.unlockSafeMode(participantId);

  Future<ExamSupervisionDetail> uploadEvidence({
    required int roomId,
    required String type,
    required SupervisionPickedFile file,
  }) => _remote.uploadEvidence(roomId: roomId, type: type, file: file);

  Future<ExamSupervisionDetail> deleteEvidence({
    required int roomId,
    required int evidenceId,
  }) => _remote.deleteEvidence(roomId: roomId, evidenceId: evidenceId);

  Future<ExamSupervisionDetail> submitEvidence(int roomId) =>
      _remote.submitEvidence(roomId);
}

final examSupervisionRepositoryProvider = Provider<ExamSupervisionRepository>(
  (ref) => ExamSupervisionRepository(
    ref.watch(examSupervisionRemoteDataSourceProvider),
  ),
);
