import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/class_assessment/data/class_assessment_operations_repository.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_correction.dart';

typedef AssessmentCorrectionRequest = ({
  int assessmentId,
  int? classId,
  String status,
});

final classAssessmentCorrectionsProvider = FutureProvider.autoDispose
    .family<AssessmentCorrectionData, AssessmentCorrectionRequest>((
      ref,
      request,
    ) async {
      try {
        return await ref
            .read(classAssessmentOperationsRepositoryProvider)
            .corrections(
              assessmentId: request.assessmentId,
              classId: request.classId,
              status: request.status,
            );
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

class ClassAssessmentOperationsActions {
  const ClassAssessmentOperationsActions(this.ref);

  final Ref ref;

  Future<AssessmentCorrectionData> saveCorrections({
    required AssessmentCorrectionRequest request,
    required List<AssessmentScorePayload> scores,
  }) => _guard(
    () => ref
        .read(classAssessmentOperationsRepositoryProvider)
        .saveCorrections(
          assessmentId: request.assessmentId,
          classId: request.classId,
          status: request.status,
          scores: scores,
        ),
  );

  Future<AssessmentApplyResult> applyResults(int assessmentId) => _guard(
    () => ref
        .read(classAssessmentOperationsRepositoryProvider)
        .applyResults(assessmentId),
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

final classAssessmentOperationsActionsProvider =
    Provider<ClassAssessmentOperationsActions>(
      ClassAssessmentOperationsActions.new,
    );
