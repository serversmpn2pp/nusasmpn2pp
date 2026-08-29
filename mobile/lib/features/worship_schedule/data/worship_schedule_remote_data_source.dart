import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/worship_schedule/domain/worship_schedule.dart';

abstract interface class WorshipScheduleRemoteDataSource {
  Future<WorshipSchedulePage> fetch({int? academicYearId, int? activityId});

  Future<void> create(WorshipScheduleFormValue value);

  Future<void> update({
    required int id,
    required WorshipScheduleFormValue value,
  });

  Future<void> deactivate(int id);
}

final class DioWorshipScheduleRemoteDataSource
    implements WorshipScheduleRemoteDataSource {
  DioWorshipScheduleRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<WorshipSchedulePage> fetch({
    int? academicYearId,
    int? activityId,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'jadwal-kegiatan-ibadah',
        queryParameters: {
          'tahun_pelajaran_id': ?academicYearId,
          'kegiatan_ibadah_id': ?activityId,
        },
      );
      return WorshipSchedulePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(WorshipScheduleFormValue value) => _save(
    path: 'jadwal-kegiatan-ibadah',
    method: 'POST',
    value: value,
    includeReferences: true,
  );

  @override
  Future<void> update({
    required int id,
    required WorshipScheduleFormValue value,
  }) => _save(
    path: 'jadwal-kegiatan-ibadah/$id',
    method: 'PATCH',
    value: value,
    includeReferences: false,
  );

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('jadwal-kegiatan-ibadah/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required WorshipScheduleFormValue value,
    required bool includeReferences,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          if (includeReferences) ...{
            'kegiatan_ibadah_id': value.activityId,
            'tahun_pelajaran_id': value.academicYearId,
            'hari': value.days,
          },
          'jam_scan_mulai': value.scanStart,
          'jam_pelaksanaan': value.eventTime,
          'jam_scan_selesai': value.scanEnd,
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

final worshipScheduleRemoteDataSourceProvider =
    Provider<WorshipScheduleRemoteDataSource>(
      (ref) => DioWorshipScheduleRemoteDataSource(ref.watch(dioProvider)),
    );
