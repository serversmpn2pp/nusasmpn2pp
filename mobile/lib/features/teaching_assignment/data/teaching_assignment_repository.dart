import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/teaching_assignment/data/teaching_assignment_remote_data_source.dart';
import 'package:nusa/features/teaching_assignment/domain/teaching_assignment.dart';

final class TeachingAssignmentRepository {
  TeachingAssignmentRepository(this._remote);

  final TeachingAssignmentRemoteDataSource _remote;

  Future<TeachingAssignmentPage> fetch({
    required String query,
    required String status,
    required int page,
    int? academicYearId,
  }) => _remote.fetch(
    query: query,
    status: status,
    page: page,
    academicYearId: academicYearId,
  );

  Future<TeachingAssignmentReference> fetchReference() =>
      _remote.fetchReference();

  Future<void> create({
    required int academicYearId,
    required List<int> classIds,
    required int subjectId,
    required int employeeId,
    required String assignmentType,
    required bool active,
    String? notes,
  }) => _remote.create(
    academicYearId: academicYearId,
    classIds: classIds,
    subjectId: subjectId,
    employeeId: employeeId,
    assignmentType: assignmentType,
    active: active,
    notes: notes,
  );

  Future<void> update({
    required int id,
    required int academicYearId,
    required int classId,
    required int subjectId,
    required int employeeId,
    required String assignmentType,
    required bool active,
    String? notes,
  }) => _remote.update(
    id: id,
    academicYearId: academicYearId,
    classId: classId,
    subjectId: subjectId,
    employeeId: employeeId,
    assignmentType: assignmentType,
    active: active,
    notes: notes,
  );
}

final teachingAssignmentRepositoryProvider =
    Provider<TeachingAssignmentRepository>(
      (ref) => TeachingAssignmentRepository(
        ref.watch(teachingAssignmentRemoteDataSourceProvider),
      ),
    );
