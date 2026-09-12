import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/parent_child_academics/domain/parent_child_academics.dart';

abstract interface class ParentChildAcademicsRemoteDataSource {
  Future<ParentChildAcademicsPage> fetch({
    required ParentChildAcademicsTab tab,
    required String semester,
    int? studentId,
    int? academicYearId,
  });
}

final class DioParentChildAcademicsRemoteDataSource
    implements ParentChildAcademicsRemoteDataSource {
  DioParentChildAcademicsRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<ParentChildAcademicsPage> fetch({
    required ParentChildAcademicsTab tab,
    required String semester,
    int? studentId,
    int? academicYearId,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'akademik-anak',
        queryParameters: {
          'tab': tab.apiValue,
          'semester': semester,
          'siswa_id': ?studentId,
          'tahun_pelajaran_id': ?academicYearId,
        },
      );
      return ParentChildAcademicsPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final parentChildAcademicsRemoteDataSourceProvider =
    Provider<ParentChildAcademicsRemoteDataSource>(
      (ref) => DioParentChildAcademicsRemoteDataSource(ref.watch(dioProvider)),
    );
