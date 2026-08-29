import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/worship_activity/domain/worship_activity.dart';

abstract interface class WorshipActivityRemoteDataSource {
  Future<WorshipActivityPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  });

  Future<void> create(WorshipActivityFormValue value);

  Future<void> update({
    required int id,
    required WorshipActivityFormValue value,
  });

  Future<void> deactivate(int id);
}

final class DioWorshipActivityRemoteDataSource
    implements WorshipActivityRemoteDataSource {
  DioWorshipActivityRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<WorshipActivityPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'kegiatan-ibadah',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return WorshipActivityPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(WorshipActivityFormValue value) =>
      _save(path: 'kegiatan-ibadah', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required WorshipActivityFormValue value,
  }) => _save(path: 'kegiatan-ibadah/$id', method: 'PATCH', value: value);

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('kegiatan-ibadah/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required WorshipActivityFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'kode': value.code.trim(),
          'nama': value.name.trim(),
          'aktif': value.active,
          'keterangan': _text(value.notes),
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

final worshipActivityRemoteDataSourceProvider =
    Provider<WorshipActivityRemoteDataSource>(
      (ref) => DioWorshipActivityRemoteDataSource(ref.watch(dioProvider)),
    );
