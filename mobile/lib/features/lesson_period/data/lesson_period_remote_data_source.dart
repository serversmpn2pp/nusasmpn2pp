import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/lesson_period/domain/lesson_period.dart';

abstract interface class LessonPeriodRemoteDataSource {
  Future<LessonPeriodCatalog> fetch({
    required String day,
    required String status,
  });

  Future<void> create({
    required List<String> days,
    required String insertionPosition,
    required String? label,
    required String startTime,
    required String endTime,
    required String type,
    required bool active,
    String? notes,
  });

  Future<void> update({
    required int id,
    required String? label,
    required String startTime,
    required String endTime,
    required String type,
    required bool active,
    String? notes,
  });
}

final class DioLessonPeriodRemoteDataSource
    implements LessonPeriodRemoteDataSource {
  DioLessonPeriodRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<LessonPeriodCatalog> fetch({
    required String day,
    required String status,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'jam-pelajaran',
        queryParameters: {'hari': day, 'status': status},
      );
      return LessonPeriodCatalog.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create({
    required List<String> days,
    required String insertionPosition,
    required String? label,
    required String startTime,
    required String endTime,
    required String type,
    required bool active,
    String? notes,
  }) async {
    try {
      await _dio.post<Map<String, dynamic>>(
        'jam-pelajaran',
        data: {
          'hari': days,
          'posisi_sisip': insertionPosition,
          'label': _text(label),
          'jam_mulai': startTime,
          'jam_selesai': endTime,
          'jenis': type,
          'aktif': active,
          'keterangan': _text(notes),
        },
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> update({
    required int id,
    required String? label,
    required String startTime,
    required String endTime,
    required String type,
    required bool active,
    String? notes,
  }) async {
    try {
      await _dio.patch<Map<String, dynamic>>(
        'jam-pelajaran/$id',
        data: {
          'label': _text(label),
          'jam_mulai': startTime,
          'jam_selesai': endTime,
          'jenis': type,
          'aktif': active,
          'keterangan': _text(notes),
        },
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

final lessonPeriodRemoteDataSourceProvider =
    Provider<LessonPeriodRemoteDataSource>(
      (ref) => DioLessonPeriodRemoteDataSource(ref.watch(dioProvider)),
    );
