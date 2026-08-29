import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/worship_absence_settings/domain/worship_absence_settings.dart';

abstract interface class WorshipAbsenceSettingsRemoteDataSource {
  Future<WorshipAbsenceSettingsPage> fetch();

  Future<void> updateSettings(WorshipAbsenceSettingsValue value);

  Future<void> saveCompanion(WorshipCompanionAssignmentValue value);

  Future<void> deactivateCompanion(int id);
}

final class DioWorshipAbsenceSettingsRemoteDataSource
    implements WorshipAbsenceSettingsRemoteDataSource {
  DioWorshipAbsenceSettingsRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<WorshipAbsenceSettingsPage> fetch() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengaturan-berhalangan-ibadah',
      );
      return WorshipAbsenceSettingsPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> updateSettings(WorshipAbsenceSettingsValue value) async {
    try {
      await _dio.put<Map<String, dynamic>>(
        'pengaturan-berhalangan-ibadah',
        data: {
          'batas_hari_konfirmasi': value.confirmationDayLimit,
          'aktif': value.active,
        },
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> saveCompanion(WorshipCompanionAssignmentValue value) async {
    try {
      await _dio.post<Map<String, dynamic>>(
        'pengaturan-berhalangan-ibadah/pendamping',
        data: {
          'pegawai_id': value.employeeId,
          'semua_kelas': value.allClasses,
          'kelas_ids': value.allClasses ? <int>[] : value.classIds,
        },
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> deactivateCompanion(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>(
        'pengaturan-berhalangan-ibadah/pendamping/$id',
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final worshipAbsenceSettingsRemoteDataSourceProvider =
    Provider<WorshipAbsenceSettingsRemoteDataSource>(
      (ref) =>
          DioWorshipAbsenceSettingsRemoteDataSource(ref.watch(dioProvider)),
    );
