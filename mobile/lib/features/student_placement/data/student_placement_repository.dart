import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_placement/data/student_placement_remote_data_source.dart';
import 'package:nusa/features/student_placement/domain/student_placement.dart';

final class StudentPlacementRepository {
  StudentPlacementRepository(this._remote);

  final StudentPlacementRemoteDataSource _remote;

  Future<StudentPlacementPage> fetch({
    int? academicYearId,
    int? classId,
    required String query,
  }) => _remote.fetch(
    academicYearId: academicYearId,
    classId: classId,
    query: query,
  );

  Future<int> place(StudentPlacementFormValue value) => _remote.place(value);
}

final studentPlacementRepositoryProvider = Provider<StudentPlacementRepository>(
  (ref) => StudentPlacementRepository(
    ref.watch(studentPlacementRemoteDataSourceProvider),
  ),
);
