import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/auth/domain/auth_session.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';

abstract interface class AuthRemoteDataSource {
  Future<AuthSession> login({
    required String username,
    required String password,
    required String deviceName,
  });

  Future<Pengguna> saya();

  Future<Pengguna> ubahKataSandi({
    required String kataSandiLama,
    required String kataSandiBaru,
    required String konfirmasiKataSandiBaru,
  });

  Future<void> logout();
}

final class DioAuthRemoteDataSource implements AuthRemoteDataSource {
  DioAuthRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<AuthSession> login({
    required String username,
    required String password,
    required String deviceName,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'auth/login',
        data: {
          'username': username,
          'password': password,
          'device_name': deviceName,
        },
      );
      final data = response.data!;

      return AuthSession(
        token: data['token'] as String,
        pengguna: Pengguna.fromJson(data['pengguna'] as Map<String, dynamic>),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<Pengguna> saya() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('auth/saya');

      return Pengguna.fromJson(
        response.data!['pengguna'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<Pengguna> ubahKataSandi({
    required String kataSandiLama,
    required String kataSandiBaru,
    required String konfirmasiKataSandiBaru,
  }) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        'auth/kata-sandi',
        data: {
          'kata_sandi_lama': kataSandiLama,
          'kata_sandi_baru': kataSandiBaru,
          'kata_sandi_baru_confirmation': konfirmasiKataSandiBaru,
        },
      );

      return Pengguna.fromJson(
        response.data!['pengguna'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> logout() async {
    try {
      await _dio.post<void>('auth/logout');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final authRemoteDataSourceProvider = Provider<AuthRemoteDataSource>((ref) {
  return DioAuthRemoteDataSource(ref.watch(dioProvider));
});
