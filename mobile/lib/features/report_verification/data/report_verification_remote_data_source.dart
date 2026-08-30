import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/report_verification/domain/report_verification.dart';

abstract interface class ReportVerificationRemoteDataSource {
  Future<ReportVerificationPage> fetch({
    required String query,
    required String queue,
    required int page,
  });

  Future<ReportVerificationDetail> fetchDetail(int reportId);

  Future<ReportVerificationMutation> review({
    required int reportId,
    required String result,
    required List<int> violationIds,
    required String? note,
  });

  Future<ReportVerificationMutation> approve({
    required int reportId,
    required String decision,
    required String? note,
  });
}

final class DioReportVerificationRemoteDataSource
    implements ReportVerificationRemoteDataSource {
  DioReportVerificationRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<ReportVerificationPage> fetch({
    required String query,
    required String queue,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pemeriksaan-pengesahan',
        queryParameters: {
          'kata_kunci': query,
          'antrean': queue,
          'halaman': page,
          'per_halaman': 15,
        },
      );
      return ReportVerificationPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<ReportVerificationDetail> fetchDetail(int reportId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pemeriksaan-pengesahan/$reportId',
      );
      return ReportVerificationDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<ReportVerificationMutation> review({
    required int reportId,
    required String result,
    required List<int> violationIds,
    required String? note,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'pemeriksaan-pengesahan/$reportId/verifikasi-bk',
        data: {
          'hasil': result,
          if (result == 'sanksi_poin') 'jenis_pelanggaran_ids': violationIds,
          'catatan': _clean(note),
        },
      );
      return ReportVerificationMutation.fromJson(response.data!);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<ReportVerificationMutation> approve({
    required int reportId,
    required String decision,
    required String? note,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'pemeriksaan-pengesahan/$reportId/pengesahan-wakil',
        data: {'keputusan': decision, 'catatan': _clean(note)},
      );
      return ReportVerificationMutation.fromJson(response.data!);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

String? _clean(String? value) {
  final cleaned = value?.trim();
  return cleaned == null || cleaned.isEmpty ? null : cleaned;
}

final reportVerificationRemoteDataSourceProvider =
    Provider<ReportVerificationRemoteDataSource>(
      (ref) => DioReportVerificationRemoteDataSource(ref.watch(dioProvider)),
    );
