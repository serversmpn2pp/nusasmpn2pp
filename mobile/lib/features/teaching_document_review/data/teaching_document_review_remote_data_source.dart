import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/teaching_document_review/domain/teaching_document_review.dart';

abstract interface class TeachingDocumentReviewRemoteDataSource {
  Future<TeachingDocumentReviewPage> fetch({
    required String query,
    required int? academicYearId,
    required int semester,
    required String completeness,
    required String documentStatus,
    required int page,
    int perPage = 15,
  });

  Future<TeachingDocumentTeacherDetail> fetchTeacher(
    TeachingDocumentTeacherQuery query,
  );

  Future<TeachingDocumentReviewDetail> fetchDocument(int id);

  Future<TeachingDocumentDownload> download({
    required int id,
    required String fileName,
  });

  Future<void> review({
    required int id,
    required TeachingDocumentReviewValue value,
  });
}

final class DioTeachingDocumentReviewRemoteDataSource
    implements TeachingDocumentReviewRemoteDataSource {
  DioTeachingDocumentReviewRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<TeachingDocumentReviewPage> fetch({
    required String query,
    required int? academicYearId,
    required int semester,
    required String completeness,
    required String documentStatus,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pemeriksaan-perangkat-ajar',
        queryParameters: {
          'kata_kunci': query.trim().isEmpty ? null : query.trim(),
          'tahun_pelajaran_id': ?academicYearId,
          'semester': semester,
          'kelengkapan': completeness,
          'status_dokumen': documentStatus,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return TeachingDocumentReviewPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<TeachingDocumentTeacherDetail> fetchTeacher(
    TeachingDocumentTeacherQuery query,
  ) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pemeriksaan-perangkat-ajar/guru/${query.teacherId}',
        queryParameters: {
          'tahun_pelajaran_id': ?query.academicYearId,
          'semester': query.semester,
        },
      );
      return TeachingDocumentTeacherDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<TeachingDocumentReviewDetail> fetchDocument(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pemeriksaan-perangkat-ajar/dokumen/$id',
      );
      return TeachingDocumentReviewDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<TeachingDocumentDownload> download({
    required int id,
    required String fileName,
  }) async {
    try {
      final response = await _dio.get<List<int>>(
        'pemeriksaan-perangkat-ajar/dokumen/$id/file',
        options: Options(responseType: ResponseType.bytes),
      );
      return TeachingDocumentDownload(
        fileName: fileName,
        bytes: Uint8List.fromList(response.data ?? const []),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> review({
    required int id,
    required TeachingDocumentReviewValue value,
  }) async {
    try {
      await _dio.patch<Map<String, dynamic>>(
        'pemeriksaan-perangkat-ajar/dokumen/$id',
        data: value.toJson(),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final teachingDocumentReviewRemoteDataSourceProvider =
    Provider<TeachingDocumentReviewRemoteDataSource>(
      (ref) =>
          DioTeachingDocumentReviewRemoteDataSource(ref.watch(dioProvider)),
    );
