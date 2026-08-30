import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/violation_process_deadline/domain/violation_process_deadline.dart';

abstract interface class ViolationProcessDeadlineRemoteDataSource {
  Future<ViolationProcessDeadlinePage> fetch({
    required String query,
    required String status,
  });

  Future<void> update({
    required int academicYearId,
    required ViolationProcessDeadlineFormValue value,
  });
}

final class DioViolationProcessDeadlineRemoteDataSource
    implements ViolationProcessDeadlineRemoteDataSource {
  DioViolationProcessDeadlineRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<ViolationProcessDeadlinePage> fetch({
    required String query,
    required String status,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengaturan-batas-proses-pelanggaran',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
        },
      );
      return ViolationProcessDeadlinePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> update({
    required int academicYearId,
    required ViolationProcessDeadlineFormValue value,
  }) async {
    try {
      await _dio.put<Map<String, dynamic>>(
        'pengaturan-batas-proses-pelanggaran/$academicYearId',
        data: {
          'batas_hari_pemeriksaan_bk': value.counselingDays,
          'batas_hari_persetujuan': value.approvalDays,
          'pengingat_hari_sebelum_batas': value.reminderDaysBefore,
          'notifikasi_pengingat_aktif': value.reminderNotificationActive,
          'notifikasi_terlambat_aktif': value.overdueNotificationActive,
        },
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final violationProcessDeadlineRemoteDataSourceProvider =
    Provider<ViolationProcessDeadlineRemoteDataSource>(
      (ref) =>
          DioViolationProcessDeadlineRemoteDataSource(ref.watch(dioProvider)),
    );
