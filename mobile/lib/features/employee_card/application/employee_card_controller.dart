import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/employee_card/data/employee_card_repository.dart';
import 'package:nusa/features/employee_card/domain/employee_card.dart';

class EmployeeCardController extends AsyncNotifier<EmployeeCardPage> {
  String _status = 'aktif';
  String _employeeType = '';
  String _query = '';
  int _requestVersion = 0;

  @override
  Future<EmployeeCardPage> build() => _fetch(page: 1);

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> filterEmployeeType(String value) async {
    if (_employeeType == value) return;
    _employeeType = value;
    await refresh();
  }

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch(page: 1);
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(page: current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<EmployeeCardPage> _fetch({required int page}) async {
    try {
      final result = await ref
          .read(employeeCardRepositoryProvider)
          .fetch(
            status: _status,
            employeeType: _employeeType,
            query: _query,
            page: page,
          );
      _status = result.status;
      _employeeType = result.employeeType;
      _query = result.query;
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final employeeCardControllerProvider =
    AsyncNotifierProvider.autoDispose<EmployeeCardController, EmployeeCardPage>(
      EmployeeCardController.new,
    );
