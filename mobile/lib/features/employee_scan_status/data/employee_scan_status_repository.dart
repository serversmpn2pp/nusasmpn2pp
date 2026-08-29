import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/employee_scan_status/data/employee_scan_status_remote_data_source.dart';
import 'package:nusa/features/employee_scan_status/domain/employee_scan_status.dart';

final class EmployeeScanStatusRepository {
  EmployeeScanStatusRepository(this._remote);

  final EmployeeScanStatusRemoteDataSource _remote;

  Future<EmployeeScanStatusDashboard> fetch({
    required String? employeeType,
    required String status,
    required String query,
  }) => _remote.fetch(employeeType: employeeType, status: status, query: query);
}

final employeeScanStatusRepositoryProvider =
    Provider<EmployeeScanStatusRepository>(
      (ref) => EmployeeScanStatusRepository(
        ref.watch(employeeScanStatusRemoteDataSourceProvider),
      ),
    );
