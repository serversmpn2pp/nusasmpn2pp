import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/worship_scan/domain/worship_scan.dart';

abstract interface class WorshipScanRemoteDataSource {
  Future<WorshipScanDashboard> fetch({int? scheduleId});

  Future<WorshipScanResult> submit({
    required int scheduleId,
    required String rawValue,
  });
}

final class DioWorshipScanRemoteDataSource
    implements WorshipScanRemoteDataSource {
  DioWorshipScanRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<WorshipScanDashboard> fetch({int? scheduleId}) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'scan-kegiatan-ibadah',
        queryParameters: {'jadwal_id': ?scheduleId},
      );
      return WorshipScanDashboard.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<WorshipScanResult> submit({
    required int scheduleId,
    required String rawValue,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'scan-kegiatan-ibadah',
        data: {'jadwal_kegiatan_ibadah_id': scheduleId, 'isi_scan': rawValue},
      );
      return _result(response.data);
    } on DioException catch (exception) {
      final data = exception.response?.data;
      if (exception.response?.statusCode == 422 &&
          data is Map &&
          data['data'] is Map) {
        return WorshipScanResult.fromJson(
          Map<String, dynamic>.from(data['data'] as Map),
        );
      }
      throw mapDioException(exception);
    }
  }

  WorshipScanResult _result(Map<String, dynamic>? response) =>
      WorshipScanResult.fromJson(
        response?['data'] as Map<String, dynamic>? ?? const {},
      );
}

final worshipScanRemoteDataSourceProvider =
    Provider<WorshipScanRemoteDataSource>(
      (ref) => DioWorshipScanRemoteDataSource(ref.watch(dioProvider)),
    );
