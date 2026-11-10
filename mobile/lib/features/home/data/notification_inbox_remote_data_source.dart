import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/home/domain/notification_inbox.dart';

abstract interface class NotificationInboxRemoteDataSource {
  Future<NotificationInbox> fetch({required String status, int? beforeId});
  Future<int> markRead(int id);
  Future<int> markAllRead();
}

final class DioNotificationInboxRemoteDataSource
    implements NotificationInboxRemoteDataSource {
  DioNotificationInboxRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<NotificationInbox> fetch({
    required String status,
    int? beforeId,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'notifikasi',
        queryParameters: {'status': status, 'sebelum_id': ?beforeId},
      );
      return NotificationInbox.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<int> _mark(String path) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(path);
      return (response.data!['data']['jumlah_belum_dibaca'] as num).toInt();
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<int> markRead(int id) => _mark('notifikasi/$id/baca');
  @override
  Future<int> markAllRead() => _mark('notifikasi/baca-semua');
}

final notificationInboxRemoteDataSourceProvider =
    Provider<NotificationInboxRemoteDataSource>(
      (ref) => DioNotificationInboxRemoteDataSource(ref.watch(dioProvider)),
    );
