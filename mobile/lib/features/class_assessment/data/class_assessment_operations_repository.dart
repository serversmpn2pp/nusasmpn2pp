import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/class_assessment/data/class_assessment_operations_remote_data_source.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_correction.dart';

class ClassAssessmentOperationsRepository {
  const ClassAssessmentOperationsRepository(this._remote);

  final ClassAssessmentOperationsRemoteDataSource _remote;

  Future<AssessmentCorrectionData> corrections({
    required int assessmentId,
    required int? classId,
    required String status,
  }) => _remote.corrections(
    assessmentId: assessmentId,
    classId: classId,
    status: status,
  );

  Future<AssessmentCorrectionData> saveCorrections({
    required int assessmentId,
    required int? classId,
    required String status,
    required List<AssessmentScorePayload> scores,
  }) => _remote.saveCorrections(
    assessmentId: assessmentId,
    classId: classId,
    status: status,
    scores: scores,
  );

  Future<AssessmentApplyResult> applyResults(int assessmentId) =>
      _remote.applyResults(assessmentId);
}

final classAssessmentOperationsRepositoryProvider =
    Provider<ClassAssessmentOperationsRepository>(
      (ref) => ClassAssessmentOperationsRepository(
        ref.watch(classAssessmentOperationsRemoteDataSourceProvider),
      ),
    );
