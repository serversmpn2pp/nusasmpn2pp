import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/class_assessment/data/class_assessment_monitoring_remote_data_source.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_monitoring.dart';

class ClassAssessmentMonitoringRepository {
  const ClassAssessmentMonitoringRepository(this._remote);

  final ClassAssessmentMonitoringRemoteDataSource _remote;

  Future<AssessmentMonitoringData> monitoring({
    required int assessmentId,
    required int? classId,
    required String status,
  }) => _remote.monitoring(
    assessmentId: assessmentId,
    classId: classId,
    status: status,
  );

  Future<AssessmentResultsData> results({
    required int assessmentId,
    required int? classId,
    required String status,
  }) => _remote.results(
    assessmentId: assessmentId,
    classId: classId,
    status: status,
  );

  Future<void> unlockParticipant(int participantId) =>
      _remote.unlockParticipant(participantId);
}

final classAssessmentMonitoringRepositoryProvider =
    Provider<ClassAssessmentMonitoringRepository>(
      (ref) => ClassAssessmentMonitoringRepository(
        ref.watch(classAssessmentMonitoringRemoteDataSourceProvider),
      ),
    );
