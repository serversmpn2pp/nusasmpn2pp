import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/personal_worship/domain/personal_worship.dart';

abstract interface class PersonalWorshipRemoteDataSource {
  Future<PersonalWorshipPage> fetch({
    required int? studentId,
    required int? academicYearId,
    required String? month,
  });
}

final class DioPersonalWorshipRemoteDataSource
    implements PersonalWorshipRemoteDataSource {
  DioPersonalWorshipRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<PersonalWorshipPage> fetch({
    required int? studentId,
    required int? academicYearId,
    required String? month,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'ibadah-saya',
        queryParameters: {
          'siswa_id': ?studentId,
          'tahun_pelajaran_id': ?academicYearId,
          'bulan': ?month,
        },
      );
      return PersonalWorshipPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final personalWorshipRemoteDataSourceProvider =
    Provider<PersonalWorshipRemoteDataSource>(
      (ref) => DioPersonalWorshipRemoteDataSource(ref.watch(dioProvider)),
    );
