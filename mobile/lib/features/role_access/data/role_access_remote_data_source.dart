import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/role_access/domain/role_access.dart';

abstract interface class RoleAccessRemoteDataSource {
  Future<RoleAccessPage> fetch({
    required String query,
    required String status,
    required int page,
  });

  Future<RoleAccessReference> fetchReference();
  Future<RoleAccessDetail> fetchDetail(int roleId);
  Future<int> create(RoleAccessFormValue value);
  Future<void> update({
    required int roleId,
    required RoleAccessFormValue value,
  });
  Future<void> deactivate(int roleId);
}

final class DioRoleAccessRemoteDataSource
    implements RoleAccessRemoteDataSource {
  DioRoleAccessRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<RoleAccessPage> fetch({
    required String query,
    required String status,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'peran',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'halaman': page,
        },
      );
      return RoleAccessPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<RoleAccessReference> fetchReference() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('peran/referensi');
      return RoleAccessReference.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<RoleAccessDetail> fetchDetail(int roleId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('peran/$roleId');
      return RoleAccessDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<int> create(RoleAccessFormValue value) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'peran',
        data: _payload(value),
      );
      return (response.data!['data'] as Map<String, dynamic>)['id'] as int;
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> update({
    required int roleId,
    required RoleAccessFormValue value,
  }) => _request(path: 'peran/$roleId', method: 'PATCH', data: _payload(value));

  @override
  Future<void> deactivate(int roleId) =>
      _request(path: 'peran/$roleId', method: 'DELETE');

  Future<void> _request({
    required String path,
    required String method,
    Map<String, dynamic>? data,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: data,
        options: Options(method: method),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

Map<String, dynamic> _payload(RoleAccessFormValue value) => {
  'nama': value.name.trim(),
  'kode': _text(value.code),
  'deskripsi': _text(value.description),
  'aktif': value.active,
  'izin_ids': value.permissionIds,
};

String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

final roleAccessRemoteDataSourceProvider = Provider<RoleAccessRemoteDataSource>(
  (ref) => DioRoleAccessRemoteDataSource(ref.watch(dioProvider)),
);
