import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/exam_supervision/data/exam_supervision_repository.dart';
import 'package:nusa/features/exam_supervision/domain/exam_supervision.dart';

final examSupervisionDetailProvider = FutureProvider.autoDispose
    .family<ExamSupervisionDetail, int>((ref, roomId) async {
      try {
        return await ref
            .read(examSupervisionRepositoryProvider)
            .fetchDetail(roomId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

class ExamSupervisionActions {
  const ExamSupervisionActions(this.ref);

  final Ref ref;

  Future<ExamSupervisionDetail> changeRoomStatus(int roomId, String action) =>
      _guard(
        () => ref
            .read(examSupervisionRepositoryProvider)
            .changeRoomStatus(roomId, action),
      );

  Future<ExamSupervisionDetail> saveNotes({
    required int roomId,
    required String minutes,
    required String obstacles,
    required String followUp,
    required String notes,
  }) => _guard(
    () => ref
        .read(examSupervisionRepositoryProvider)
        .saveNotes(
          roomId: roomId,
          minutes: minutes,
          obstacles: obstacles,
          followUp: followUp,
          notes: notes,
        ),
  );

  Future<ExamSupervisionDetail> changeAttendance({
    required int roomId,
    required int participantId,
    required String status,
    required String? notes,
  }) => _guard(
    () => ref
        .read(examSupervisionRepositoryProvider)
        .changeAttendance(
          roomId: roomId,
          participantId: participantId,
          status: status,
          notes: notes,
        ),
  );

  Future<ExamSupervisionDetail> resetDevice({
    required int roomId,
    required int participantId,
  }) => _guard(
    () => ref
        .read(examSupervisionRepositoryProvider)
        .resetDevice(roomId: roomId, participantId: participantId),
  );

  Future<void> unlockSafeMode(int participantId) => _guard(
    () => ref
        .read(examSupervisionRepositoryProvider)
        .unlockSafeMode(participantId),
  );

  Future<ExamSupervisionDetail> uploadEvidence({
    required int roomId,
    required String type,
    required SupervisionPickedFile file,
  }) => _guard(
    () => ref
        .read(examSupervisionRepositoryProvider)
        .uploadEvidence(roomId: roomId, type: type, file: file),
  );

  Future<ExamSupervisionDetail> deleteEvidence({
    required int roomId,
    required int evidenceId,
  }) => _guard(
    () => ref
        .read(examSupervisionRepositoryProvider)
        .deleteEvidence(roomId: roomId, evidenceId: evidenceId),
  );

  Future<ExamSupervisionDetail> submitEvidence(int roomId) => _guard(
    () => ref.read(examSupervisionRepositoryProvider).submitEvidence(roomId),
  );

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final examSupervisionActionsProvider = Provider(ExamSupervisionActions.new);
