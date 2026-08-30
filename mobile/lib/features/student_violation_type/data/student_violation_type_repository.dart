import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_violation_type/data/student_violation_type_remote_data_source.dart';
import 'package:nusa/features/student_violation_type/domain/student_violation_type.dart';

final class StudentViolationTypeRepository {
  StudentViolationTypeRepository(this._remote);

  final StudentViolationTypeRemoteDataSource _remote;

  Future<StudentViolationTypePage> fetch({
    required String query,
    required String status,
    required String level,
    required int? categoryId,
    required int page,
  }) => _remote.fetch(
    query: query,
    status: status,
    level: level,
    categoryId: categoryId,
    page: page,
  );

  Future<void> create(StudentViolationTypeFormValue value) =>
      _remote.create(value);

  Future<void> update({
    required int id,
    required StudentViolationTypeFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final studentViolationTypeRepositoryProvider =
    Provider<StudentViolationTypeRepository>(
      (ref) => StudentViolationTypeRepository(
        ref.watch(studentViolationTypeRemoteDataSourceProvider),
      ),
    );
