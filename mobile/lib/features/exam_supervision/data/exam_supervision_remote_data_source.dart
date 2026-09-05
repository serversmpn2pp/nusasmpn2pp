import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/exam_supervision/domain/exam_supervision.dart';

abstract interface class ExamSupervisionRemoteDataSource {
  Future<ExamSupervisionDetail> fetchDetail(int roomId);
  Future<ExamSupervisionDetail> changeRoomStatus(int roomId, String action);
  Future<ExamSupervisionDetail> saveNotes({
    required int roomId,
    required String minutes,
    required String obstacles,
    required String followUp,
    required String notes,
  });
  Future<ExamSupervisionDetail> changeAttendance({
    required int roomId,
    required int participantId,
    required String status,
    required String? notes,
  });
  Future<ExamSupervisionDetail> resetDevice({
    required int roomId,
    required int participantId,
  });
  Future<void> unlockSafeMode(int participantId);
  Future<ExamSupervisionDetail> uploadEvidence({
    required int roomId,
    required String type,
    required SupervisionPickedFile file,
  });
  Future<ExamSupervisionDetail> deleteEvidence({
    required int roomId,
    required int evidenceId,
  });
  Future<ExamSupervisionDetail> submitEvidence(int roomId);
}

final class DioExamSupervisionRemoteDataSource
    implements ExamSupervisionRemoteDataSource {
  DioExamSupervisionRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<ExamSupervisionDetail> fetchDetail(int roomId) => _request(
    () => _dio.get<Map<String, dynamic>>('tugas-pengawas-ujian/$roomId'),
  );

  @override
  Future<ExamSupervisionDetail> changeRoomStatus(int roomId, String action) =>
      _request(
        () => _dio.patch<Map<String, dynamic>>(
          'tugas-pengawas-ujian/$roomId/status',
          data: {'aksi': action},
        ),
      );

  @override
  Future<ExamSupervisionDetail> saveNotes({
    required int roomId,
    required String minutes,
    required String obstacles,
    required String followUp,
    required String notes,
  }) => _request(
    () => _dio.patch<Map<String, dynamic>>(
      'tugas-pengawas-ujian/$roomId/catatan',
      data: {
        'berita_acara': minutes,
        'hambatan': obstacles,
        'tindak_lanjut': followUp,
        'catatan': notes,
      },
    ),
  );

  @override
  Future<ExamSupervisionDetail> changeAttendance({
    required int roomId,
    required int participantId,
    required String status,
    required String? notes,
  }) => _request(
    () => _dio.patch<Map<String, dynamic>>(
      'tugas-pengawas-ujian/$roomId/peserta/$participantId/kehadiran',
      data: {'status': status, 'catatan': ?notes},
    ),
  );

  @override
  Future<ExamSupervisionDetail> resetDevice({
    required int roomId,
    required int participantId,
  }) => _request(
    () => _dio.post<Map<String, dynamic>>(
      'tugas-pengawas-ujian/$roomId/peserta/$participantId/reset-perangkat',
    ),
  );

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

  @override
  Future<ExamSupervisionDetail> uploadEvidence({
    required int roomId,
    required String type,
    required SupervisionPickedFile file,
  }) => _request(
    () => _dio.post<Map<String, dynamic>>(
      'tugas-pengawas-ujian/$roomId/bukti',
      data: FormData.fromMap({
        'jenis': type,
        'berkas': MultipartFile.fromBytes(file.bytes, filename: file.name),
      }),
    ),
  );

  @override
  Future<ExamSupervisionDetail> deleteEvidence({
    required int roomId,
    required int evidenceId,
  }) => _request(
    () => _dio.delete<Map<String, dynamic>>(
      'tugas-pengawas-ujian/$roomId/bukti/$evidenceId',
    ),
  );

  @override
  Future<ExamSupervisionDetail> submitEvidence(int roomId) => _request(
    () => _dio.patch<Map<String, dynamic>>(
      'tugas-pengawas-ujian/$roomId/kirim-bukti',
    ),
  );

  Future<ExamSupervisionDetail> _request(
    Future<Response<Map<String, dynamic>>> Function() request,
  ) async {
    try {
      final response = await request();
      final data =
          response.data?['data'] as Map<String, dynamic>? ??
          <String, dynamic>{};
      return ExamSupervisionDetail.fromJson(data);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final examSupervisionRemoteDataSourceProvider =
    Provider<ExamSupervisionRemoteDataSource>(
      (ref) => DioExamSupervisionRemoteDataSource(ref.watch(dioProvider)),
    );
