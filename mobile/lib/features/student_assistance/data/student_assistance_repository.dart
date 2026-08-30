import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_assistance/data/student_assistance_remote_data_source.dart';
import 'package:nusa/features/student_assistance/domain/student_assistance.dart';

final class StudentAssistanceRepository {
  const StudentAssistanceRepository(this._remote);
  final StudentAssistanceRemoteDataSource _remote;

  Future<StudentAssistancePage> fetch({
    required String query,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) => _remote.fetch(
    query: query,
    status: status,
    academicYearId: academicYearId,
    classId: classId,
    page: page,
  );

  Future<StudentAssistanceReference> fetchReference({
    required String query,
    required int? academicYearId,
    required int? classId,
  }) => _remote.fetchReference(
    query: query,
    academicYearId: academicYearId,
    classId: classId,
  );

  Future<StudentAssistanceDetail> fetchDetail(int id) =>
      _remote.fetchDetail(id);
  Future<StudentAssistanceDetail> create(StudentAssistancePayload payload) =>
      _remote.create(payload);
  Future<StudentAssistanceDetail> update(
    int id,
    StudentAssistancePayload payload,
  ) => _remote.update(id, payload);
}

final studentAssistanceRepositoryProvider =
    Provider<StudentAssistanceRepository>(
      (ref) => StudentAssistanceRepository(
        ref.watch(studentAssistanceRemoteDataSourceProvider),
      ),
    );
