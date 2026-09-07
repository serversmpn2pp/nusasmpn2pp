import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/inventory_monthly_report/data/inventory_monthly_report_repository.dart';
import 'package:nusa/features/inventory_monthly_report/domain/inventory_monthly_report.dart';

class InventoryMonthlyReportController
    extends AsyncNotifier<InventoryMonthlyReport> {
  late InventoryReportFilter _filter;
  int _version = 0;
  InventoryReportFilter get filter => _filter;

  @override
  Future<InventoryMonthlyReport> build() {
    final today = DateTime.now();
    _filter = InventoryReportFilter(
      period:
          '${today.year.toString().padLeft(4, '0')}-${today.month.toString().padLeft(2, '0')}',
    );
    return _fetch();
  }

  Future<void> apply(InventoryReportFilter filter) async {
    _filter = filter;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_version;
    state = const AsyncLoading();
    try {
      final value = await _fetch();
      if (version == _version) state = AsyncData(value);
    } catch (error, stack) {
      if (version == _version) state = AsyncError(error, stack);
    }
  }

  Future<InventoryMonthlyReport> _fetch() => _guard(
    () => ref.read(inventoryMonthlyReportRepositoryProvider).fetch(_filter),
  );

  Future<T> _guard<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final inventoryMonthlyReportControllerProvider =
    AsyncNotifierProvider.autoDispose<
      InventoryMonthlyReportController,
      InventoryMonthlyReport
    >(InventoryMonthlyReportController.new);
