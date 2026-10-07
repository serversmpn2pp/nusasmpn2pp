import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';

final class PushNotificationTarget {
  const PushNotificationTarget({
    required this.id,
    this.destination,
    this.title,
    this.body,
  });
  final int id;
  final String? destination;
  final String? title;
  final String? body;
}

abstract interface class PushNotificationRemoteDataSource {
  Future<PushNotificationTarget> inspectNotification(int notificationId);
  Future<void> markNotificationRead(int notificationId);

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
  Future<PushNotificationTarget> inspectNotification(int notificationId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'notifikasi/$notificationId/tujuan',
      );
      final data = response.data?['data'];
      if (data is! Map || data['id'] != notificationId) {
        throw const FormatException('Respons tujuan notifikasi tidak valid.');
      }
      return PushNotificationTarget(
        id: notificationId,
        destination: data['tautan_mobile'] as String?,
        title: data['judul'] as String?,
        body: data['pesan'] as String?,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> markNotificationRead(int notificationId) async {
    try {
      await _dio.patch<void>('notifikasi/$notificationId/baca');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

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
