import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/worship_monthly_summary/domain/worship_monthly_summary.dart';

abstract interface class WorshipMonthlySummaryRemoteDataSource {
  Future<WorshipMonthlySummaryPage> fetch({
    required String? month,
    required int? activityId,
    required int? classId,
  });
}

final class DioWorshipMonthlySummaryRemoteDataSource
    implements WorshipMonthlySummaryRemoteDataSource {
  DioWorshipMonthlySummaryRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<WorshipMonthlySummaryPage> fetch({
    required String? month,
    required int? activityId,
    required int? classId,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'ringkasan-kegiatan-ibadah-bulanan',
        queryParameters: {
          'bulan': ?month,
          'kegiatan_ibadah_id': ?activityId,
          'kelas_id': ?classId,
        },
      );
      return WorshipMonthlySummaryPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final worshipMonthlySummaryRemoteDataSourceProvider =
    Provider<WorshipMonthlySummaryRemoteDataSource>(
      (ref) => DioWorshipMonthlySummaryRemoteDataSource(ref.watch(dioProvider)),
    );
