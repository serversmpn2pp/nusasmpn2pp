import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_exam/data/student_exam_repository.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';

final studentExamProvider = FutureProvider.autoDispose
    .family<StudentExamSession, int>((ref, participantId) async {
      try {
        return await ref
            .read(studentExamRepositoryProvider)
            .detail(participantId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

class StudentExamActions {
  const StudentExamActions(this.ref);

  final Ref ref;

  Future<StudentExamSession> start(int participantId, String? token) => _guard(
    () => ref.read(studentExamRepositoryProvider).start(participantId, token),
  );

  Future<StudentExamSession> resume(int participantId) => _guard(
    () => ref.read(studentExamRepositoryProvider).resume(participantId),
  );

  Future<StudentExamSaveResult> saveAnswer({
    required int participantId,
    required StudentExamQuestion question,
  }) => _guard(
    () => ref
        .read(studentExamRepositoryProvider)
        .saveAnswer(
          participantId: participantId,
          questionId: question.id,
          answer: question.answerPayload,
          doubtful: question.doubtful,
        ),
  );

  Future<StudentExamSession> finish(int participantId) => _guard(
    () => ref.read(studentExamRepositoryProvider).finish(participantId),
  );

  Future<StudentExamSecurityUpdate> securityEvent(
    int participantId,
    String event,
  ) => _guard(
    () => ref
        .read(studentExamRepositoryProvider)
        .securityEvent(participantId, event),
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

final studentExamActionsProvider = Provider<StudentExamActions>(
  StudentExamActions.new,
);
