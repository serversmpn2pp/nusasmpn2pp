import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/my_guardian_students/data/my_guardian_student_remote_data_source.dart';
import 'package:nusa/features/my_guardian_students/domain/my_guardian_student.dart';

class MyGuardianStudentRepository {
  const MyGuardianStudentRepository(this._remote);
  final MyGuardianStudentRemoteDataSource _remote;

  Future<MyGuardianStudentPage> fetch({
    required String query,
    required int? grade,
    required int? classId,
    required int page,
  }) => _remote.fetch(query: query, grade: grade, classId: classId, page: page);

  Future<MyGuardianStudentDetail> detail(int studentId) =>
      _remote.detail(studentId);
}

final myGuardianStudentRepositoryProvider =
    Provider<MyGuardianStudentRepository>(
      (ref) => MyGuardianStudentRepository(
        ref.watch(myGuardianStudentRemoteDataSourceProvider),
      ),
    );
