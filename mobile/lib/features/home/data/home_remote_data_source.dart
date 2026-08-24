import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/home/domain/home_dashboard.dart';

abstract interface class HomeRemoteDataSource {
  Future<HomeDashboard> fetchDashboard();
}

final class DioHomeRemoteDataSource implements HomeRemoteDataSource {
  DioHomeRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<HomeDashboard> fetchDashboard() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('beranda');

      return HomeDashboard.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final homeRemoteDataSourceProvider = Provider<HomeRemoteDataSource>((ref) {
  return DioHomeRemoteDataSource(ref.watch(dioProvider));
});
