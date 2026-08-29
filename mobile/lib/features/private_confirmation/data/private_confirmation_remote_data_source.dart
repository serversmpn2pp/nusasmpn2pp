import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/private_confirmation/domain/private_confirmation.dart';

abstract interface class PrivateConfirmationRemoteDataSource {
  Future<PrivateConfirmationPage> fetch({
    required String query,
    required int? classId,
    required int page,
  });

  Future<PrivateConfirmationDetail> fetchDetail(int periodId);

  Future<PrivateConfirmationUpdateResult> update({
    required int periodId,
    required String result,
    required int? reminderDays,
    required String? privateNote,
  });
}

final class DioPrivateConfirmationRemoteDataSource
    implements PrivateConfirmationRemoteDataSource {
  DioPrivateConfirmationRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<PrivateConfirmationPage> fetch({
    required String query,
    required int? classId,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'konfirmasi-berhalangan-ibadah',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'kelas_id': ?classId,
          'halaman': page,
        },
      );
      return PrivateConfirmationPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<PrivateConfirmationDetail> fetchDetail(int periodId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'konfirmasi-berhalangan-ibadah/$periodId',
      );
      return PrivateConfirmationDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<PrivateConfirmationUpdateResult> update({
    required int periodId,
    required String result,
    required int? reminderDays,
    required String? privateNote,
  }) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        'konfirmasi-berhalangan-ibadah/$periodId',
        data: {
          'hasil': result,
          if (result == 'masih_berhalangan')
            'jeda_konfirmasi_hari': reminderDays,
          'catatan_privat': privateNote?.trim().isEmpty == true
              ? null
              : privateNote?.trim(),
        },
      );
      return PrivateConfirmationUpdateResult(
        message:
            response.data?['message'] as String? ??
            'Konfirmasi privat berhasil disimpan.',
        detail: PrivateConfirmationDetail.fromJson(
          response.data!['data'] as Map<String, dynamic>,
        ),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final privateConfirmationRemoteDataSourceProvider =
    Provider<PrivateConfirmationRemoteDataSource>(
      (ref) => DioPrivateConfirmationRemoteDataSource(ref.watch(dioProvider)),
    );
