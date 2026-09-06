import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/central_exam_correction/data/central_exam_correction_repository.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_correction.dart';

typedef CentralExamCorrectionRequest = ({
  int eventId,
  int scheduleId,
  int? classId,
  String status,
});

final centralExamCorrectionsProvider = FutureProvider.autoDispose
    .family<AssessmentCorrectionData, CentralExamCorrectionRequest>((
      ref,
      request,
    ) async {
      try {
        return await ref
            .read(centralExamCorrectionRepositoryProvider)
            .corrections(
              eventId: request.eventId,
              scheduleId: request.scheduleId,
              classId: request.classId,
              status: request.status,
            );
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final centralExamCorrectionSaveProvider = Provider(
  (ref) =>
      ({
        required CentralExamCorrectionRequest request,
        required List<AssessmentScorePayload> scores,
      }) async {
        try {
          return await ref
              .read(centralExamCorrectionRepositoryProvider)
              .saveCorrections(
                eventId: request.eventId,
                scheduleId: request.scheduleId,
                classId: request.classId,
                status: request.status,
                scores: scores,
              );
        } on UnauthorizedException {
          await ref.read(authControllerProvider.notifier).logout();
          rethrow;
        }
      },
);
