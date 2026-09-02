import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/guardian_assignment/domain/guardian_assignment.dart';

abstract interface class GuardianAssignmentRemoteDataSource {
  Future<GuardianAssignmentPage> fetch({
    required String query,
    required int? guardianId,
    required int page,
  });
  Future<GuardianAssignmentResult> create(GuardianAssignmentPayload payload);
  Future<GuardianAssignmentMutation> end(int id);
}

final class DioGuardianAssignmentRemoteDataSource
    implements GuardianAssignmentRemoteDataSource {
  DioGuardianAssignmentRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<GuardianAssignmentPage> fetch({
    required String query,
    required int? guardianId,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'penugasan-guru-wali',
        queryParameters: {
          'kata_kunci': query,
          'guru_wali_pegawai_id': ?guardianId,
          'halaman': page,
          'per_halaman': 15,
        },
      );
      return GuardianAssignmentPage.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GuardianAssignmentResult> create(
    GuardianAssignmentPayload payload,
  ) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'penugasan-guru-wali',
        data: payload.toJson(),
      );
      return GuardianAssignmentResult.fromJson(response.data ?? const {});
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GuardianAssignmentMutation> end(int id) async {
    try {
      final response = await _dio.delete<Map<String, dynamic>>(
        'penugasan-guru-wali/$id',
      );
      return GuardianAssignmentMutation(
        message:
            response.data?['message'] as String? ??
            'Penugasan berhasil diakhiri.',
        item: GuardianAssignmentItem.fromJson(_data(response)),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final guardianAssignmentRemoteDataSourceProvider =
    Provider<GuardianAssignmentRemoteDataSource>(
      (ref) => DioGuardianAssignmentRemoteDataSource(ref.watch(dioProvider)),
    );
