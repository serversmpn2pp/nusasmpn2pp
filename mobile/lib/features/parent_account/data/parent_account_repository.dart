import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/parent_account/data/parent_account_remote_data_source.dart';
import 'package:nusa/features/parent_account/domain/parent_account.dart';

final class ParentAccountRepository {
  ParentAccountRepository(this._remote);

  final ParentAccountRemoteDataSource _remote;

  Future<ParentAccountPage> fetchAccounts({
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

  Future<ParentAccountDetail> fetchAccount(int studentId) =>
      _remote.fetchAccount(studentId);

  Future<void> createAccount(int studentId) => _remote.createAccount(studentId);

  Future<BulkParentAccountResult> createClassAccounts(int classId) =>
      _remote.createClassAccounts(classId);

  Future<void> resetPassword(int studentId) => _remote.resetPassword(studentId);

  Future<void> updateStatus({required int studentId, required bool active}) =>
      _remote.updateStatus(studentId: studentId, active: active);
}

final parentAccountRepositoryProvider = Provider<ParentAccountRepository>(
  (ref) =>
      ParentAccountRepository(ref.watch(parentAccountRemoteDataSourceProvider)),
);
