import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/bk_grade_assignment/data/bk_grade_assignment_remote_data_source.dart';
import 'package:nusa/features/bk_grade_assignment/domain/bk_grade_assignment.dart';

class BkGradeAssignmentRepository {
  const BkGradeAssignmentRepository(this._remote);

  final BkGradeAssignmentRemoteDataSource _remote;

  Future<BkGradeAssignmentPage> fetch({int? academicYearId}) =>
      _remote.fetch(academicYearId: academicYearId);

  Future<BkGradeAssignmentMutation> create(BkGradeAssignmentPayload payload) =>
      _remote.create(payload);

  Future<BkGradeAssignmentMutation> end(int id) => _remote.end(id);
}

final bkGradeAssignmentRepositoryProvider =
    Provider<BkGradeAssignmentRepository>(
      (ref) => BkGradeAssignmentRepository(
        ref.watch(bkGradeAssignmentRemoteDataSourceProvider),
      ),
    );
