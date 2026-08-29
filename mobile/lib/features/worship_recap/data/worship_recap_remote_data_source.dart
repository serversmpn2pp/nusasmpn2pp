import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/worship_recap/domain/worship_recap.dart';

abstract interface class WorshipRecapRemoteDataSource {
  Future<WorshipRecapPage> fetch({
    required String? date,
    required int? activityId,
    required int? classId,
    required String status,
    required String query,
    required int page,
  });

  Future<WorshipCorrectionDetail> fetchCorrection(WorshipCorrectionQuery query);

  Future<WorshipCorrectionResult> updateCorrection({
    required WorshipCorrectionQuery query,
    required String status,
    required String? time,
    required String reason,
  });
}

final class DioWorshipRecapRemoteDataSource
    implements WorshipRecapRemoteDataSource {
  DioWorshipRecapRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<WorshipRecapPage> fetch({
    required String? date,
    required int? activityId,
    required int? classId,
    required String status,
    required String query,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'rekap-kegiatan-ibadah',
        queryParameters: {
          'tanggal': ?date,
          'kegiatan_ibadah_id': ?activityId,
          'kelas_id': ?classId,
          'status': status,
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'halaman': page,
        },
      );
      return WorshipRecapPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<WorshipCorrectionDetail> fetchCorrection(
    WorshipCorrectionQuery query,
  ) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'rekap-kegiatan-ibadah/koreksi/${query.memberId}',
        queryParameters: {
          'tanggal': query.date,
          'kegiatan_ibadah_id': query.activityId,
        },
      );
      return WorshipCorrectionDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<WorshipCorrectionResult> updateCorrection({
    required WorshipCorrectionQuery query,
    required String status,
    required String? time,
    required String reason,
  }) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        'rekap-kegiatan-ibadah/koreksi/${query.memberId}',
        data: {
          'tanggal': query.date,
          'kegiatan_ibadah_id': query.activityId,
          'status_presensi': status,
          if (status == 'sudah') 'waktu_presensi': time,
          'alasan': reason.trim(),
        },
      );
      return WorshipCorrectionResult(
        message:
            response.data?['message'] as String? ??
            'Perubahan presensi ibadah berhasil disimpan.',
        detail: WorshipCorrectionDetail.fromJson(
          response.data!['data'] as Map<String, dynamic>,
        ),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final worshipRecapRemoteDataSourceProvider =
    Provider<WorshipRecapRemoteDataSource>(
      (ref) => DioWorshipRecapRemoteDataSource(ref.watch(dioProvider)),
    );
