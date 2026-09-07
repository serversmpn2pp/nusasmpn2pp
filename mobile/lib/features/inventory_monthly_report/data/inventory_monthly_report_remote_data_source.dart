import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/inventory_monthly_report/domain/inventory_monthly_report.dart';

abstract interface class InventoryMonthlyReportRemoteDataSource {
  Future<InventoryMonthlyReport> fetch(InventoryReportFilter filter);
}

final class DioInventoryMonthlyReportRemoteDataSource
    implements InventoryMonthlyReportRemoteDataSource {
  DioInventoryMonthlyReportRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<InventoryMonthlyReport> fetch(InventoryReportFilter filter) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'laporan-inventaris-bulanan',
        queryParameters: {
          'periode': filter.period,
          'lokasi_barang_id': ?filter.locationId,
        },
      );
      return InventoryMonthlyReport.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final inventoryMonthlyReportRemoteDataSourceProvider =
    Provider<InventoryMonthlyReportRemoteDataSource>(
      (ref) =>
          DioInventoryMonthlyReportRemoteDataSource(ref.watch(dioProvider)),
    );
