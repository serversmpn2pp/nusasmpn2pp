import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_case_progress/data/student_case_progress_remote_data_source.dart';
import 'package:nusa/features/student_case_progress/domain/student_case_progress.dart';

class StudentCaseProgressRepository {
  const StudentCaseProgressRepository(this._remote);

  final StudentCaseProgressRemoteDataSource _remote;

  Future<StudentCaseProgressPage> fetch({required int page}) =>
      _remote.fetch(page: page);

  Future<StudentCaseDetail> fetchDetail(int id) => _remote.fetchDetail(id);
}

final studentCaseProgressRepositoryProvider =
    Provider<StudentCaseProgressRepository>(
      (ref) => StudentCaseProgressRepository(
        ref.watch(studentCaseProgressRemoteDataSourceProvider),
      ),
    );
