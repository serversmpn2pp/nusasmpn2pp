import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/private_worship_scan/domain/private_worship_scan.dart';

abstract interface class PrivateWorshipScanRemoteDataSource {
  Future<PrivateWorshipScanDashboard> fetch({int? scheduleId});

  Future<PrivateWorshipScanResult> submit({
    required int scheduleId,
    required String rawValue,
  });
}

final class DioPrivateWorshipScanRemoteDataSource
    implements PrivateWorshipScanRemoteDataSource {
  DioPrivateWorshipScanRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<PrivateWorshipScanDashboard> fetch({int? scheduleId}) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'scan-berhalangan-ibadah',
        queryParameters: {'jadwal_id': ?scheduleId},
      );
      return PrivateWorshipScanDashboard.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<PrivateWorshipScanResult> submit({
    required int scheduleId,
    required String rawValue,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'scan-berhalangan-ibadah',
        data: {'jadwal_kegiatan_ibadah_id': scheduleId, 'isi_scan': rawValue},
      );
      return _result(response.data);
    } on DioException catch (exception) {
      final data = exception.response?.data;
      if (exception.response?.statusCode == 422 &&
          data is Map &&
          data['data'] is Map) {
        return PrivateWorshipScanResult.fromJson(
          Map<String, dynamic>.from(data['data'] as Map),
        );
      }
      throw mapDioException(exception);
    }
  }

  PrivateWorshipScanResult _result(Map<String, dynamic>? response) =>
      PrivateWorshipScanResult.fromJson(
        response?['data'] as Map<String, dynamic>? ?? const {},
      );
}

final privateWorshipScanRemoteDataSourceProvider =
    Provider<PrivateWorshipScanRemoteDataSource>(
      (ref) => DioPrivateWorshipScanRemoteDataSource(ref.watch(dioProvider)),
    );
