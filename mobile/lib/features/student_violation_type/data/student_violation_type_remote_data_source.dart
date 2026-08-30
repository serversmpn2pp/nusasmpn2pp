import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_violation_type/domain/student_violation_type.dart';

abstract interface class StudentViolationTypeRemoteDataSource {
  Future<StudentViolationTypePage> fetch({
    required String query,
    required String status,
    required String level,
    required int? categoryId,
    required int page,
    int perPage = 15,
  });

  Future<void> create(StudentViolationTypeFormValue value);

  Future<void> update({
    required int id,
    required StudentViolationTypeFormValue value,
  });

  Future<void> deactivate(int id);
}

final class DioStudentViolationTypeRemoteDataSource
    implements StudentViolationTypeRemoteDataSource {
  DioStudentViolationTypeRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<StudentViolationTypePage> fetch({
    required String query,
    required String status,
    required String level,
    required int? categoryId,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'jenis-pelanggaran-siswa',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'tingkat': level,
          'kategori_id': ?categoryId,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return StudentViolationTypePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(StudentViolationTypeFormValue value) =>
      _save(path: 'jenis-pelanggaran-siswa', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required StudentViolationTypeFormValue value,
  }) =>
      _save(path: 'jenis-pelanggaran-siswa/$id', method: 'PATCH', value: value);

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('jenis-pelanggaran-siswa/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required StudentViolationTypeFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'kategori_pembinaan_siswa_id': value.categoryId,
          'kode': value.code.trim(),
          'nama': value.name.trim(),
          'tingkat': value.level,
          'poin': value.points,
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

final studentViolationTypeRemoteDataSourceProvider =
    Provider<StudentViolationTypeRemoteDataSource>(
      (ref) => DioStudentViolationTypeRemoteDataSource(ref.watch(dioProvider)),
    );
