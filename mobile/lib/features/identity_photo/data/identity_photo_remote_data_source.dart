import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/identity_photo/domain/identity_photo.dart';

abstract interface class IdentityPhotoRemoteDataSource {
  Future<IdentityPhotoPage> fetch({
    required String tab,
    int? academicYearId,
    int? classId,
    required String photoStatus,
    required String employeeStatus,
    required String employeeType,
    required String query,
    required int page,
    int perPage = 20,
  });

  Future<String> upload({
    required String tab,
    required int personId,
    required IdentityPhotoPickedFile file,
  });
}

final class DioIdentityPhotoRemoteDataSource
    implements IdentityPhotoRemoteDataSource {
  DioIdentityPhotoRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<IdentityPhotoPage> fetch({
    required String tab,
    int? academicYearId,
    int? classId,
    required String photoStatus,
    required String employeeStatus,
    required String employeeType,
    required String query,
    required int page,
    int perPage = 20,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'foto-identitas',
        queryParameters: {
          'tab': tab,
          'tahun_pelajaran_id': ?academicYearId,
          'kelas_id': ?classId,
          'status_foto': photoStatus,
          'status_pegawai': employeeStatus,
          if (employeeType.trim().isNotEmpty)
            'jenis_pegawai': employeeType.trim(),
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return IdentityPhotoPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<String> upload({
    required String tab,
    required int personId,
    required IdentityPhotoPickedFile file,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'foto-identitas/$tab/$personId',
        data: FormData.fromMap({
          'foto': MultipartFile.fromBytes(file.bytes, filename: file.name),
        }),
      );
      final data = response.data!['data'] as Map<String, dynamic>;
      return data['foto_url'] as String? ?? '';
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final identityPhotoRemoteDataSourceProvider =
    Provider<IdentityPhotoRemoteDataSource>(
      (ref) => DioIdentityPhotoRemoteDataSource(ref.watch(dioProvider)),
    );
