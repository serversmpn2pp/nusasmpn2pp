import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_sanction/domain/student_sanction.dart';

abstract interface class StudentSanctionRemoteDataSource {
  Future<StudentSanctionPage> fetch({
    required String query,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  });
  Future<StudentSanctionDetail> fetchDetail(int id);
  Future<StudentSanctionDetail> update(int id, StudentSanctionPayload payload);
  Future<StudentSanctionDetail> uploadEvidence({
    required int id,
    required List<SanctionPickedFile> files,
    required String? description,
  });
  Future<StudentSanctionDetail> deleteEvidence(int evidenceId);
  Future<SanctionEvidenceDownload> downloadEvidence(SanctionEvidence evidence);
}

final class DioStudentSanctionRemoteDataSource
    implements StudentSanctionRemoteDataSource {
  DioStudentSanctionRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<StudentSanctionPage> fetch({
    required String query,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pelaksanaan-sanksi-siswa',
        queryParameters: {
          'kata_kunci': query,
          'status': status,
          'tahun_pelajaran_id': ?academicYearId,
          'kelas_id': ?classId,
          'halaman': page,
          'per_halaman': 15,
        },
      );
      return StudentSanctionPage.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentSanctionDetail> fetchDetail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pelaksanaan-sanksi-siswa/$id',
      );
      return StudentSanctionDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentSanctionDetail> update(
    int id,
    StudentSanctionPayload payload,
  ) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        'pelaksanaan-sanksi-siswa/$id',
        data: payload.toJson(),
      );
      return StudentSanctionDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentSanctionDetail> uploadEvidence({
    required int id,
    required List<SanctionPickedFile> files,
    required String? description,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'pelaksanaan-sanksi-siswa/$id/bukti',
        data: FormData.fromMap({
          'bukti_sanksi': [
            for (final file in files)
              MultipartFile.fromBytes(file.bytes, filename: file.name),
          ],
          'keterangan_bukti': ?description,
        }),
      );
      return StudentSanctionDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentSanctionDetail> deleteEvidence(int evidenceId) async {
    try {
      final response = await _dio.delete<Map<String, dynamic>>(
        'pelaksanaan-sanksi-siswa/bukti/$evidenceId',
      );
      return StudentSanctionDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<SanctionEvidenceDownload> downloadEvidence(
    SanctionEvidence evidence,
  ) async {
    try {
      final response = await _dio.get<List<int>>(
        'pelaksanaan-sanksi-siswa/bukti/${evidence.id}/file',
        options: Options(responseType: ResponseType.bytes),
      );
      return SanctionEvidenceDownload(
        fileName: evidence.fileName,
        mimeType: evidence.mimeType ?? 'application/octet-stream',
        bytes: Uint8List.fromList(response.data ?? const []),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final studentSanctionRemoteDataSourceProvider =
    Provider<StudentSanctionRemoteDataSource>(
      (ref) => DioStudentSanctionRemoteDataSource(ref.watch(dioProvider)),
    );
