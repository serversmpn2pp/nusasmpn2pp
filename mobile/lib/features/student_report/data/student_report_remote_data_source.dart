import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_report/domain/student_report.dart';

abstract interface class StudentReportRemoteDataSource {
  Future<StudentReportPage> fetch({
    required String query,
    required String status,
    required String level,
    required String type,
    required String verificationStatus,
    required int? academicYearId,
    required int? classId,
    required int page,
  });

  Future<StudentReportDetail> fetchDetail(int id);

  Future<StudentReportEvidenceDownload> downloadEvidence({
    required int id,
    required String fileName,
    required String mimeType,
  });
}

final class DioStudentReportRemoteDataSource
    implements StudentReportRemoteDataSource {
  DioStudentReportRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<StudentReportPage> fetch({
    required String query,
    required String status,
    required String level,
    required String type,
    required String verificationStatus,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) async {
    try {
      final parameters = <String, Object?>{
        'kata_kunci': query,
        'status': status,
        'tingkat': level,
        'jenis_laporan': type,
        'status_verifikasi': verificationStatus,
        'halaman': page,
        'per_halaman': 15,
      };
      if (academicYearId != null) {
        parameters['tahun_pelajaran_id'] = academicYearId;
      }
      if (classId != null) parameters['kelas_id'] = classId;
      final response = await _dio.get<Map<String, dynamic>>(
        'laporan-siswa',
        queryParameters: parameters,
      );
      return StudentReportPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentReportDetail> fetchDetail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'laporan-siswa/$id',
      );
      return StudentReportDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentReportEvidenceDownload> downloadEvidence({
    required int id,
    required String fileName,
    required String mimeType,
  }) async {
    try {
      final response = await _dio.get<List<int>>(
        'laporan-siswa/bukti/$id/file',
        options: Options(responseType: ResponseType.bytes),
      );
      return StudentReportEvidenceDownload(
        fileName: fileName,
        mimeType: mimeType,
        bytes: Uint8List.fromList(response.data ?? const []),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final studentReportRemoteDataSourceProvider =
    Provider<StudentReportRemoteDataSource>(
      (ref) => DioStudentReportRemoteDataSource(ref.watch(dioProvider)),
    );
