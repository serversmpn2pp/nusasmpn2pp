import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student/data/student_remote_data_source.dart';
import 'package:nusa/features/student/domain/student.dart';

final class StudentRepository {
  StudentRepository(this._remote);

  final StudentRemoteDataSource _remote;

  Future<StudentPage> fetchStudents({
    required String query,
    required String status,
    required int page,
  }) {
    return _remote.fetchStudents(query: query, status: status, page: page);
  }

  Future<StudentDetail> fetchStudent(int id) => _remote.fetchStudent(id);
}

final studentRepositoryProvider = Provider<StudentRepository>((ref) {
  return StudentRepository(ref.watch(studentRemoteDataSourceProvider));
});
