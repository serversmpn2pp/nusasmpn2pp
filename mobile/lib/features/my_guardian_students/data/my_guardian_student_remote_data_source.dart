import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/my_guardian_students/domain/my_guardian_student.dart';

abstract interface class MyGuardianStudentRemoteDataSource {
  Future<MyGuardianStudentPage> fetch({
    required String query,
    required int? grade,
    required int? classId,
    required int page,
  });

  Future<MyGuardianStudentDetail> detail(int studentId);
}

final class DioMyGuardianStudentRemoteDataSource
    implements MyGuardianStudentRemoteDataSource {
  DioMyGuardianStudentRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<MyGuardianStudentPage> fetch({
    required String query,
    required int? grade,
    required int? classId,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'siswa-wali-saya',
        queryParameters: {
          'kata_kunci': query,
          'tingkat': ?grade,
          'kelas_id': ?classId,
          'halaman': page,
          'per_halaman': 15,
        },
      );
      return MyGuardianStudentPage.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<MyGuardianStudentDetail> detail(int studentId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'siswa-wali-saya/$studentId',
      );
      return MyGuardianStudentDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final myGuardianStudentRemoteDataSourceProvider =
    Provider<MyGuardianStudentRemoteDataSource>(
      (ref) => DioMyGuardianStudentRemoteDataSource(ref.watch(dioProvider)),
    );
