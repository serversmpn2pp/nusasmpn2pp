import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/employee/data/employee_remote_data_source.dart';
import 'package:nusa/features/employee/domain/employee.dart';

final class EmployeeRepository {
  EmployeeRepository(this._remote);

  final EmployeeRemoteDataSource _remote;

  Future<EmployeePage> fetchEmployees({
    required String query,
    required String status,
    required String type,
    required int page,
  }) => _remote.fetchEmployees(
    query: query,
    status: status,
    type: type,
    page: page,
  );

  Future<EmployeeDetail> fetchEmployee(int id) => _remote.fetchEmployee(id);

  Future<void> create(EmployeeFormValue value) => _remote.create(value);

  Future<void> update({required int id, required EmployeeFormValue value}) =>
      _remote.update(id: id, value: value);
}

final employeeRepositoryProvider = Provider<EmployeeRepository>(
  (ref) => EmployeeRepository(ref.watch(employeeRemoteDataSourceProvider)),
);
