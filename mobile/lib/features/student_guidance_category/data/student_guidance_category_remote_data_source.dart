import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_guidance_category/domain/student_guidance_category.dart';

abstract interface class StudentGuidanceCategoryRemoteDataSource {
  Future<StudentGuidanceCategoryPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  });

  Future<void> create(StudentGuidanceCategoryFormValue value);

  Future<void> update({
    required int id,
    required StudentGuidanceCategoryFormValue value,
  });

  Future<void> deactivate(int id);
}

final class DioStudentGuidanceCategoryRemoteDataSource
    implements StudentGuidanceCategoryRemoteDataSource {
  DioStudentGuidanceCategoryRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<StudentGuidanceCategoryPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'kategori-pembinaan-siswa',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return StudentGuidanceCategoryPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(StudentGuidanceCategoryFormValue value) =>
      _save(path: 'kategori-pembinaan-siswa', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required StudentGuidanceCategoryFormValue value,
  }) => _save(
    path: 'kategori-pembinaan-siswa/$id',
    method: 'PATCH',
    value: value,
  );

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('kategori-pembinaan-siswa/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required StudentGuidanceCategoryFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'nama': value.name.trim(),
          'kode': value.code.trim(),
          'deskripsi': _text(value.description),
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

final studentGuidanceCategoryRemoteDataSourceProvider =
    Provider<StudentGuidanceCategoryRemoteDataSource>(
      (ref) =>
          DioStudentGuidanceCategoryRemoteDataSource(ref.watch(dioProvider)),
    );
