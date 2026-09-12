import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/worship_absence_recap/domain/worship_absence_recap.dart';

abstract interface class WorshipAbsenceRecapRemoteDataSource {
  Future<WorshipAbsenceRecapPage> fetch({
    required String? month,
    required int? classId,
    required String status,
    required String query,
    required int page,
    bool exportAll = false,
  });
}

final class DioWorshipAbsenceRecapRemoteDataSource
    implements WorshipAbsenceRecapRemoteDataSource {
  DioWorshipAbsenceRecapRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<WorshipAbsenceRecapPage> fetch({
    required String? month,
    required int? classId,
    required String status,
    required String query,
    required int page,
    bool exportAll = false,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        exportAll
            ? 'rekap-berhalangan-ibadah/cetak'
            : 'rekap-berhalangan-ibadah',
        queryParameters: {
          'bulan': ?month,
          'kelas_id': ?classId,
          'status': status,
          'cari': query.isEmpty ? null : query,
          if (!exportAll) 'halaman': page,
        },
      );
      return WorshipAbsenceRecapPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final worshipAbsenceRecapRemoteDataSourceProvider =
    Provider<WorshipAbsenceRecapRemoteDataSource>(
      (ref) => DioWorshipAbsenceRecapRemoteDataSource(ref.watch(dioProvider)),
    );
