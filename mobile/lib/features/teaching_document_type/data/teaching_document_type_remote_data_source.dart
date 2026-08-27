import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/teaching_document_type/domain/teaching_document_type.dart';

abstract interface class TeachingDocumentTypeRemoteDataSource {
  Future<TeachingDocumentTypePage> fetch({
    required String query,
    required String status,
    required String requirement,
    required int page,
    int perPage = 15,
  });

  Future<void> create(TeachingDocumentTypeFormValue value);

  Future<void> update({
    required int id,
    required TeachingDocumentTypeFormValue value,
  });

  Future<void> deactivate(int id);
}

final class DioTeachingDocumentTypeRemoteDataSource
    implements TeachingDocumentTypeRemoteDataSource {
  DioTeachingDocumentTypeRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<TeachingDocumentTypePage> fetch({
    required String query,
    required String status,
    required String requirement,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'jenis-perangkat-ajar',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'kewajiban': requirement,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return TeachingDocumentTypePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(TeachingDocumentTypeFormValue value) =>
      _save(path: 'jenis-perangkat-ajar', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required TeachingDocumentTypeFormValue value,
  }) => _save(path: 'jenis-perangkat-ajar/$id', method: 'PATCH', value: value);

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('jenis-perangkat-ajar/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required TeachingDocumentTypeFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'kode': value.code.trim(),
          'nama': value.name.trim(),
          'deskripsi': _text(value.description),
          'wajib': value.mandatory,
          'urutan': value.order,
          'aktif': value.active,
        },
        options: Options(method: method),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

final teachingDocumentTypeRemoteDataSourceProvider =
    Provider<TeachingDocumentTypeRemoteDataSource>(
      (ref) => DioTeachingDocumentTypeRemoteDataSource(ref.watch(dioProvider)),
    );
