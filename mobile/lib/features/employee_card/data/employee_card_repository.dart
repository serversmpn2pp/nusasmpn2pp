import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/employee_card/data/employee_card_remote_data_source.dart';
import 'package:nusa/features/employee_card/domain/employee_card.dart';

final class EmployeeCardRepository {
  const EmployeeCardRepository(this._remote);

  final EmployeeCardRemoteDataSource _remote;

  Future<EmployeeCardPage> fetch({
    required String status,
    required String employeeType,
    required String query,
    required int page,
  }) => _remote.fetch(
    status: status,
    employeeType: employeeType,
    query: query,
    page: page,
  );
}

final employeeCardRepositoryProvider = Provider<EmployeeCardRepository>(
  (ref) =>
      EmployeeCardRepository(ref.watch(employeeCardRemoteDataSourceProvider)),
);
