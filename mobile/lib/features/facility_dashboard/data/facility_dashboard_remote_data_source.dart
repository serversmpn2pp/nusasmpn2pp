import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/facility_dashboard/domain/facility_dashboard.dart';

abstract interface class FacilityDashboardRemoteDataSource {
  Future<FacilityDashboard> fetch();
}

final class DioFacilityDashboardRemoteDataSource
    implements FacilityDashboardRemoteDataSource {
  DioFacilityDashboardRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<FacilityDashboard> fetch() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'dashboard-sarpras',
      );
      final data =
          response.data?['data'] as Map<String, dynamic>? ??
          <String, dynamic>{};
      return FacilityDashboard.fromJson(data);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final facilityDashboardRemoteDataSourceProvider =
    Provider<FacilityDashboardRemoteDataSource>(
      (ref) => DioFacilityDashboardRemoteDataSource(ref.watch(dioProvider)),
    );
