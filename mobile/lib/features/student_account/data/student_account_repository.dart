import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_account/data/student_account_remote_data_source.dart';
import 'package:nusa/features/student_account/domain/student_account.dart';

final class StudentAccountRepository {
  StudentAccountRepository(this._remote);

  final StudentAccountRemoteDataSource _remote;

  Future<StudentAccountPage> fetchAccounts({
    required String query,
    required String status,
    required int? classId,
    required int page,
  }) => _remote.fetchAccounts(
    query: query,
    status: status,
    classId: classId,
    page: page,
  );

  Future<StudentAccountDetail> fetchAccount(int studentId) =>
      _remote.fetchAccount(studentId);

  Future<void> createAccount(int studentId) => _remote.createAccount(studentId);

  Future<BulkStudentAccountResult> createClassAccounts(int classId) =>
      _remote.createClassAccounts(classId);

  Future<void> resetPassword(int studentId) => _remote.resetPassword(studentId);

  Future<void> updateStatus({required int studentId, required bool active}) =>
      _remote.updateStatus(studentId: studentId, active: active);
}

final studentAccountRepositoryProvider = Provider<StudentAccountRepository>(
  (ref) => StudentAccountRepository(
    ref.watch(studentAccountRemoteDataSourceProvider),
  ),
);
