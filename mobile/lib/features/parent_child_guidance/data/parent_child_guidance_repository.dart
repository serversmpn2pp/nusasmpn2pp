import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/parent_child_guidance/data/parent_child_guidance_remote_data_source.dart';
import 'package:nusa/features/parent_child_guidance/domain/parent_child_guidance.dart';
import 'package:nusa/features/student_case_progress/domain/student_case_progress.dart';

class ParentChildGuidanceRepository {
  const ParentChildGuidanceRepository(this._remote);

  final ParentChildGuidanceRemoteDataSource _remote;

  Future<ParentChildGuidancePage> fetch({
    required ParentChildGuidanceTab tab,
    required int page,
    int? studentId,
    int? academicYearId,
  }) => _remote.fetch(
    tab: tab,
    page: page,
    studentId: studentId,
    academicYearId: academicYearId,
  );

  Future<StudentCaseDetail> fetchDetail(int id) => _remote.fetchDetail(id);
}

final parentChildGuidanceRepositoryProvider =
    Provider<ParentChildGuidanceRepository>(
      (ref) => ParentChildGuidanceRepository(
        ref.watch(parentChildGuidanceRemoteDataSourceProvider),
      ),
    );
