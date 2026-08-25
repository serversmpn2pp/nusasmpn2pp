import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/login_activity/domain/login_activity.dart';

abstract interface class LoginActivityRemoteDataSource {
  Future<LoginActivityPage> fetchActivities({
    required String view,
    required String query,
    required String accountType,
    required String loginStatus,
    required String attemptStatus,
    required String device,
    required String? startDate,
    required String? endDate,
    required int page,
    int perPage = 15,
  });

  Future<LoginAttemptDetail> fetchAttempt(int attemptId);
}

final class DioLoginActivityRemoteDataSource
    implements LoginActivityRemoteDataSource {
  DioLoginActivityRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<LoginActivityPage> fetchActivities({
    required String view,
    required String query,
    required String accountType,
    required String loginStatus,
    required String attemptStatus,
    required String device,
    required String? startDate,
    required String? endDate,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'aktivitas-login',
        queryParameters: {
          'tampilan': view,
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'jenis_akun': accountType,
          if (view == 'pengguna') 'status_login': loginStatus,
          if (view == 'riwayat') ...{
            'status_percobaan': attemptStatus,
            'perangkat': device,
            'tanggal_mulai': ?startDate,
            'tanggal_selesai': ?endDate,
          },
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return LoginActivityPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<LoginAttemptDetail> fetchAttempt(int attemptId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'aktivitas-login/$attemptId',
      );
      return LoginAttemptDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final loginActivityRemoteDataSourceProvider =
    Provider<LoginActivityRemoteDataSource>(
      (ref) => DioLoginActivityRemoteDataSource(ref.watch(dioProvider)),
    );
