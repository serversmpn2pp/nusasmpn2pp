import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/employee_account/data/employee_account_remote_data_source.dart';
import 'package:nusa/features/employee_account/domain/employee_account.dart';

final class EmployeeAccountRepository {
  EmployeeAccountRepository(this._remote);

  final EmployeeAccountRemoteDataSource _remote;

  Future<EmployeeAccountPage> fetchAccounts({
    required String query,
    required String status,
    required int page,
  }) => _remote.fetchAccounts(query: query, status: status, page: page);

  Future<EmployeeAccountDetail> fetchAccount(int employeeId) =>
      _remote.fetchAccount(employeeId);

  Future<void> createAccount(int employeeId) =>
      _remote.createAccount(employeeId);

  Future<BulkAccountResult> createAllAccounts() => _remote.createAllAccounts();

  Future<void> resetPassword(int employeeId) =>
      _remote.resetPassword(employeeId);

  Future<void> updateStatus({required int employeeId, required bool active}) =>
      _remote.updateStatus(employeeId: employeeId, active: active);

  Future<void> updateRoles({
    required int employeeId,
    required List<int> roleIds,
  }) => _remote.updateRoles(employeeId: employeeId, roleIds: roleIds);
}

final employeeAccountRepositoryProvider = Provider<EmployeeAccountRepository>(
  (ref) => EmployeeAccountRepository(
    ref.watch(employeeAccountRemoteDataSourceProvider),
  ),
);
