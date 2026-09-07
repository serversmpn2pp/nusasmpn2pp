import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';
import 'package:nusa/features/profile/domain/my_profile.dart';

abstract interface class MyProfileRemoteDataSource {
  Future<MyProfile> fetch();

  Future<MyProfileUpdateResult> update(Map<String, dynamic> payload);

  Future<MyProfile> updatePhoto(MyProfilePhotoFile file);
}

final class DioMyProfileRemoteDataSource implements MyProfileRemoteDataSource {
  DioMyProfileRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<MyProfile> fetch() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('profil-saya');
      return MyProfile.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<MyProfileUpdateResult> update(Map<String, dynamic> payload) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        'profil-saya',
        data: payload,
      );
      final data = response.data!;
      return MyProfileUpdateResult(
        message: data['message'] as String? ?? 'Profil berhasil diperbarui.',
        profile: MyProfile.fromJson(data['data'] as Map<String, dynamic>),
        user: Pengguna.fromJson(data['pengguna'] as Map<String, dynamic>),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<MyProfile> updatePhoto(MyProfilePhotoFile file) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'profil-saya/foto',
        data: FormData.fromMap({
          'foto': MultipartFile.fromBytes(file.bytes, filename: file.name),
        }),
      );
      return MyProfile.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final myProfileRemoteDataSourceProvider = Provider<MyProfileRemoteDataSource>(
  (ref) => DioMyProfileRemoteDataSource(ref.watch(dioProvider)),
);
