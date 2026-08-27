import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_card/domain/student_card.dart';

abstract interface class StudentCardRemoteDataSource {
  Future<StudentCardPage> fetch({
    int? academicYearId,
    int? classId,
    required String query,
    required int page,
    int perPage = 20,
  });
}

final class DioStudentCardRemoteDataSource
    implements StudentCardRemoteDataSource {
  DioStudentCardRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<StudentCardPage> fetch({
    int? academicYearId,
    int? classId,
    required String query,
    required int page,
    int perPage = 20,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'kartu-pelajar',
        queryParameters: {
          'tahun_pelajaran_id': ?academicYearId,
          'kelas_id': ?classId,
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return StudentCardPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final studentCardRemoteDataSourceProvider =
    Provider<StudentCardRemoteDataSource>(
      (ref) => DioStudentCardRemoteDataSource(ref.watch(dioProvider)),
    );
