import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/sts_reports/domain/sts_reports.dart';

abstract interface class StsReportsRemoteDataSource {
  Future<StsReportPage> fetch(StsReportKind kind, StsReportFilter filter);
}

final class DioStsReportsRemoteDataSource
    implements StsReportsRemoteDataSource {
  DioStsReportsRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<StsReportPage> fetch(
    StsReportKind kind,
    StsReportFilter filter,
  ) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        kind == StsReportKind.report ? 'rapor-sts' : 'leger-sts',
        queryParameters: filter.query(kind),
      );
      return StsReportPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final stsReportsRemoteDataSourceProvider = Provider<StsReportsRemoteDataSource>(
  (ref) => DioStsReportsRemoteDataSource(ref.watch(dioProvider)),
);
