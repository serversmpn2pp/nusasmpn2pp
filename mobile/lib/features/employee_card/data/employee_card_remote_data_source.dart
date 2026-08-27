import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/employee_card/domain/employee_card.dart';

abstract interface class EmployeeCardRemoteDataSource {
  Future<EmployeeCardPage> fetch({
    required String status,
    required String employeeType,
    required String query,
    required int page,
    int perPage = 12,
  });
}

final class DioEmployeeCardRemoteDataSource
    implements EmployeeCardRemoteDataSource {
  DioEmployeeCardRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<EmployeeCardPage> fetch({
    required String status,
    required String employeeType,
    required String query,
    required int page,
    int perPage = 12,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'kartu-pegawai',
        queryParameters: {
          'status': status,
          if (employeeType.trim().isNotEmpty)
            'jenis_pegawai': employeeType.trim(),
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return EmployeeCardPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final employeeCardRemoteDataSourceProvider =
    Provider<EmployeeCardRemoteDataSource>(
      (ref) => DioEmployeeCardRemoteDataSource(ref.watch(dioProvider)),
    );
