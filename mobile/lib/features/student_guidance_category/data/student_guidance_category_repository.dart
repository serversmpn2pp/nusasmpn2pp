import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_guidance_category/data/student_guidance_category_remote_data_source.dart';
import 'package:nusa/features/student_guidance_category/domain/student_guidance_category.dart';

final class StudentGuidanceCategoryRepository {
  StudentGuidanceCategoryRepository(this._remote);

  final StudentGuidanceCategoryRemoteDataSource _remote;

  Future<StudentGuidanceCategoryPage> fetch({
    required String query,
    required String status,
    required int page,
  }) => _remote.fetch(query: query, status: status, page: page);

  Future<void> create(StudentGuidanceCategoryFormValue value) =>
      _remote.create(value);

  Future<void> update({
    required int id,
    required StudentGuidanceCategoryFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final studentGuidanceCategoryRepositoryProvider =
    Provider<StudentGuidanceCategoryRepository>(
      (ref) => StudentGuidanceCategoryRepository(
        ref.watch(studentGuidanceCategoryRemoteDataSourceProvider),
      ),
    );
