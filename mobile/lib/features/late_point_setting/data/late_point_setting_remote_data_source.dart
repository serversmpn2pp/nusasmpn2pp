import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/late_point_setting/domain/late_point_setting.dart';

abstract interface class LatePointSettingRemoteDataSource {
  Future<LatePointSettingPage> fetch({
    required String query,
    required String status,
  });

  Future<void> update({
    required int academicYearId,
    required LatePointSettingFormValue value,
  });
}

final class DioLatePointSettingRemoteDataSource
    implements LatePointSettingRemoteDataSource {
  DioLatePointSettingRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<LatePointSettingPage> fetch({
    required String query,
    required String status,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengaturan-poin-keterlambatan',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
        },
      );
      return LatePointSettingPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> update({
    required int academicYearId,
    required LatePointSettingFormValue value,
  }) async {
    try {
      await _dio.put<Map<String, dynamic>>(
        'pengaturan-poin-keterlambatan/$academicYearId',
        data: {
          'aktif': value.active,
          'rentang': [
            for (final range in value.ranges)
              {
                'menit_mulai': range.startMinute,
                'menit_selesai': range.endMinute,
                'poin': range.points,
              },
          ],
        },
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final latePointSettingRemoteDataSourceProvider =
    Provider<LatePointSettingRemoteDataSource>(
      (ref) => DioLatePointSettingRemoteDataSource(ref.watch(dioProvider)),
    );
