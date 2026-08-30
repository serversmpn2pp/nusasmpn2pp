import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/early_warning_setting/domain/early_warning_setting.dart';

abstract interface class EarlyWarningSettingRemoteDataSource {
  Future<EarlyWarningSettingPage> fetch({
    required String query,
    required String status,
  });

  Future<void> update({
    required int academicYearId,
    required EarlyWarningSettingFormValue value,
  });
}

final class DioEarlyWarningSettingRemoteDataSource
    implements EarlyWarningSettingRemoteDataSource {
  DioEarlyWarningSettingRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<EarlyWarningSettingPage> fetch({
    required String query,
    required String status,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengaturan-peringatan-dini-poin',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
        },
      );
      return EarlyWarningSettingPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> update({
    required int academicYearId,
    required EarlyWarningSettingFormValue value,
  }) async {
    try {
      await _dio.put<Map<String, dynamic>>(
        'pengaturan-peringatan-dini-poin/$academicYearId',
        data: {
          'aktif': value.detectionActive,
          'notifikasi_aktif': value.notificationActive,
          'persentase_mendekati_ambang': value.nearThresholdPercentage,
          'jumlah_pelanggaran_berulang': value.repeatedViolationCount,
          'periode_pelanggaran_hari': value.violationPeriodDays,
          'jumlah_keterlambatan_berulang': value.repeatedLateCount,
          'periode_keterlambatan_hari': value.latePeriodDays,
        },
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final earlyWarningSettingRemoteDataSourceProvider =
    Provider<EarlyWarningSettingRemoteDataSource>(
      (ref) => DioEarlyWarningSettingRemoteDataSource(ref.watch(dioProvider)),
    );
