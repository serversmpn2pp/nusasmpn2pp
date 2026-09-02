import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_early_warning/data/student_early_warning_remote_data_source.dart';
import 'package:nusa/features/student_early_warning/domain/student_early_warning.dart';

final class StudentEarlyWarningRepository {
  const StudentEarlyWarningRepository(this._remote);
  final StudentEarlyWarningRemoteDataSource _remote;

  Future<StudentEarlyWarningPage> fetch({
    required String query,
    required String type,
    required String level,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) => _remote.fetch(
    query: query,
    type: type,
    level: level,
    status: status,
    academicYearId: academicYearId,
    classId: classId,
    page: page,
  );

  Future<StudentEarlyWarningDetail> fetchDetail(int id) =>
      _remote.fetchDetail(id);
  Future<StudentWarningProcessResult> process(int? academicYearId) =>
      _remote.process(academicYearId);
}

final studentEarlyWarningRepositoryProvider =
    Provider<StudentEarlyWarningRepository>(
      (ref) => StudentEarlyWarningRepository(
        ref.watch(studentEarlyWarningRemoteDataSourceProvider),
      ),
    );
