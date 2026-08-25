import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/class_promotion/domain/class_promotion.dart';

abstract interface class ClassPromotionRemoteDataSource {
  Future<ClassPromotionPage> fetch({
    required int? sourceYearId,
    required int? destinationYearId,
    required int? sourceClassId,
  });

  Future<PromotionResult> process({
    required int sourceYearId,
    required int destinationYearId,
    required int sourceClassId,
    required List<PromotionAssignment> assignments,
  });
}

final class DioClassPromotionRemoteDataSource
    implements ClassPromotionRemoteDataSource {
  DioClassPromotionRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<ClassPromotionPage> fetch({
    required int? sourceYearId,
    required int? destinationYearId,
    required int? sourceClassId,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'kenaikan-kelas',
        queryParameters: {
          'tahun_asal_id': ?sourceYearId,
          'tahun_tujuan_id': ?destinationYearId,
          'kelas_asal_id': ?sourceClassId,
        },
      );
      return ClassPromotionPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<PromotionResult> process({
    required int sourceYearId,
    required int destinationYearId,
    required int sourceClassId,
    required List<PromotionAssignment> assignments,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'kenaikan-kelas/proses',
        data: {
          'tahun_asal_id': sourceYearId,
          'tahun_tujuan_id': destinationYearId,
          'kelas_asal_id': sourceClassId,
          'penempatan': assignments.map((item) => item.toJson()).toList(),
        },
      );
      return PromotionResult.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final classPromotionRemoteDataSourceProvider =
    Provider<ClassPromotionRemoteDataSource>(
      (ref) => DioClassPromotionRemoteDataSource(ref.watch(dioProvider)),
    );
