import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/class_assessment/data/class_assessment_monitoring_repository.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_monitoring.dart';

typedef AssessmentOperationRequest = ({
  int assessmentId,
  int? classId,
  String status,
});

final classAssessmentMonitoringProvider = FutureProvider.autoDispose
    .family<AssessmentMonitoringData, AssessmentOperationRequest>((
      ref,
      request,
    ) async {
      try {
        return await ref
            .read(classAssessmentMonitoringRepositoryProvider)
            .monitoring(
              assessmentId: request.assessmentId,
              classId: request.classId,
              status: request.status,
            );
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final classAssessmentResultsProvider = FutureProvider.autoDispose
    .family<AssessmentResultsData, AssessmentOperationRequest>((
      ref,
      request,
    ) async {
      try {
        return await ref
            .read(classAssessmentMonitoringRepositoryProvider)
            .results(
              assessmentId: request.assessmentId,
              classId: request.classId,
              status: request.status,
            );
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

class ClassAssessmentMonitoringActions {
  const ClassAssessmentMonitoringActions(this.ref);

  final Ref ref;

  Future<void> unlockParticipant(int participantId) async {
    try {
      await ref
          .read(classAssessmentMonitoringRepositoryProvider)
          .unlockParticipant(participantId);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final classAssessmentMonitoringActionsProvider =
    Provider<ClassAssessmentMonitoringActions>(
      ClassAssessmentMonitoringActions.new,
    );
