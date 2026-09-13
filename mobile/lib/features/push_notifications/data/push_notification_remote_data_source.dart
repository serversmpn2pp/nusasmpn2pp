import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';

abstract interface class PushNotificationRemoteDataSource {
  Future<void> registerDevice({
    required String token,
    required String platform,
    required String deviceName,
  });

  Future<void> unregisterDevice(String token);
}

final class DioPushNotificationRemoteDataSource
    implements PushNotificationRemoteDataSource {
  DioPushNotificationRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<void> registerDevice({
    required String token,
    required String platform,
    required String deviceName,
  }) async {
    try {
      await _dio.post<void>(
        'notifikasi/perangkat',
        data: {
          'token': token,
          'platform': platform,
          'nama_perangkat': deviceName,
        },
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> unregisterDevice(String token) async {
    try {
      await _dio.delete<void>('notifikasi/perangkat', data: {'token': token});
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final pushNotificationRemoteDataSourceProvider =
    Provider<PushNotificationRemoteDataSource>((ref) {
      return DioPushNotificationRemoteDataSource(ref.watch(dioProvider));
    });
