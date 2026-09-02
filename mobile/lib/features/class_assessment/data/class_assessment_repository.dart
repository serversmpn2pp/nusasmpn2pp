import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/class_assessment/data/class_assessment_remote_data_source.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment.dart';

class ClassAssessmentRepository {
  const ClassAssessmentRepository(this._remote);
  final ClassAssessmentRemoteDataSource _remote;

  Future<ClassAssessmentPage> fetch({
    required String query,
    required String status,
    required int page,
  }) => _remote.fetch(query: query, status: status, page: page);
  Future<ClassAssessmentDetail> detail(int id) => _remote.detail(id);
  Future<ClassAssessmentDetail> create(ClassAssessmentPayload payload) =>
      _remote.create(payload);
  Future<ClassAssessmentDetail> update(
    int id,
    ClassAssessmentPayload payload,
  ) => _remote.update(id, payload);
  Future<void> deactivate(int id) => _remote.deactivate(id);
  Future<AssessmentQuestions> questions(int id) => _remote.questions(id);
  Future<AssessmentQuestions> saveQuestions(
    int id,
    List<AssessmentQuestionPayload> questions,
  ) => _remote.saveQuestions(id, questions);
}

final classAssessmentRepositoryProvider = Provider<ClassAssessmentRepository>(
  (ref) => ClassAssessmentRepository(
    ref.watch(classAssessmentRemoteDataSourceProvider),
  ),
);
