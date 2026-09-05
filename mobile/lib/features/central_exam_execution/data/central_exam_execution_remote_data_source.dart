import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/central_exam_execution/domain/central_exam_execution.dart';

abstract interface class CentralExamExecutionRemoteDataSource {
  Future<CentralExamExecutionPage> fetchEvents({
    required String query,
    required String status,
    required int page,
  });
  Future<CentralExamExecutionDetail> fetchDetail(
    CentralExamExecutionRequest request,
  );
  Future<String> assignSupervisor({
    required int eventId,
    required int scheduleId,
    required int sourceRoomId,
    required String role,
    required int employeeId,
    required String? reason,
  });
  Future<void> unlockSafeMode(int participantId);
}

final class DioCentralExamExecutionRemoteDataSource
    implements CentralExamExecutionRemoteDataSource {
  const DioCentralExamExecutionRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<CentralExamExecutionPage> fetchEvents({
    required String query,
    required String status,
    required int page,
  }) => _request(
    () => _dio.get<Map<String, dynamic>>(
      'pelaksanaan-ujian-terpusat',
      queryParameters: {
        if (query.isNotEmpty) 'kata_kunci': query,
        'status': status,
        'halaman': page,
      },
    ),
    CentralExamExecutionPage.fromJson,
  );

  @override
  Future<CentralExamExecutionDetail> fetchDetail(
    CentralExamExecutionRequest request,
  ) => _request(
    () => _dio.get<Map<String, dynamic>>(
      'pelaksanaan-ujian-terpusat/${request.eventId}',
      queryParameters: {
        'status_peserta': request.status,
        if (request.scheduleId != null) 'jadwal_id': request.scheduleId,
        if (request.roomId != null) 'ruang_id': request.roomId,
        if (request.query.isNotEmpty) 'kata_kunci_peserta': request.query,
        'halaman_peserta': request.page,
      },
    ),
    CentralExamExecutionDetail.fromJson,
  );

  @override
  Future<String> assignSupervisor({
    required int eventId,
    required int scheduleId,
    required int sourceRoomId,
    required String role,
    required int employeeId,
    required String? reason,
  }) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(
        'pelaksanaan-ujian-terpusat/$eventId/jadwal/$scheduleId/ruang/$sourceRoomId/pengawas',
        data: {
          'peran': role,
          'pegawai_id': employeeId,
          if (reason != null && reason.trim().isNotEmpty)
            'alasan': reason.trim(),
        },
      );
      return response.data?['pesan'] as String? ??
          'Pengawas berhasil diperbarui.';
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> unlockSafeMode(int participantId) async {
    try {
      await _dio.post<Map<String, dynamic>>(
        'keamanan-ujian/peserta/$participantId/buka',
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<T> _request<T>(
    Future<Response<Map<String, dynamic>>> Function() request,
    T Function(Map<String, dynamic>) parser,
  ) async {
    try {
      final response = await request();
      return parser(
        response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{},
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final centralExamExecutionRemoteDataSourceProvider =
    Provider<CentralExamExecutionRemoteDataSource>(
      (ref) => DioCentralExamExecutionRemoteDataSource(ref.watch(dioProvider)),
    );
