import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/inventory_monthly_report/data/inventory_monthly_report_remote_data_source.dart';
import 'package:nusa/features/inventory_monthly_report/domain/inventory_monthly_report.dart';

class InventoryMonthlyReportRepository {
  const InventoryMonthlyReportRepository(this._remote);
  final InventoryMonthlyReportRemoteDataSource _remote;
  Future<InventoryMonthlyReport> fetch(InventoryReportFilter filter) =>
      _remote.fetch(filter);
}

final inventoryMonthlyReportRepositoryProvider =
    Provider<InventoryMonthlyReportRepository>(
      (ref) => InventoryMonthlyReportRepository(
        ref.watch(inventoryMonthlyReportRemoteDataSourceProvider),
      ),
    );
