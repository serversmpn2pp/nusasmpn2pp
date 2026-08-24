import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/academic_year/domain/academic_year.dart';

abstract interface class AcademicYearRemoteDataSource {
  Future<AcademicYearPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  });

  Future<void> create(AcademicYearFormValue value);

  Future<void> update({required int id, required AcademicYearFormValue value});
}

final class DioAcademicYearRemoteDataSource
    implements AcademicYearRemoteDataSource {
  DioAcademicYearRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<AcademicYearPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'tahun-pelajaran',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return AcademicYearPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(AcademicYearFormValue value) =>
      _send(path: 'tahun-pelajaran', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required AcademicYearFormValue value,
  }) => _send(path: 'tahun-pelajaran/$id', method: 'PATCH', value: value);

  Future<void> _send({
    required String path,
    required String method,
    required AcademicYearFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'nama': value.name.trim(),
          'tanggal_mulai': _dateValue(value.startDate),
          'tanggal_selesai': _dateValue(value.endDate),
          'aktif': value.active,
          'keterangan': _text(value.notes),
        },
        options: Options(method: method),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

String? _dateValue(DateTime? value) {
  if (value == null) return null;
  final month = value.month.toString().padLeft(2, '0');
  final day = value.day.toString().padLeft(2, '0');
  return '${value.year}-$month-$day';
}

String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

final academicYearRemoteDataSourceProvider =
    Provider<AcademicYearRemoteDataSource>(
      (ref) => DioAcademicYearRemoteDataSource(ref.watch(dioProvider)),
    );
