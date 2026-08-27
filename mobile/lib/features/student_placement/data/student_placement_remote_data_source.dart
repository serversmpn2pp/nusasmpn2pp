import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_placement/domain/student_placement.dart';

abstract interface class StudentPlacementRemoteDataSource {
  Future<StudentPlacementPage> fetch({
    int? academicYearId,
    int? classId,
    required String query,
  });

  Future<int> place(StudentPlacementFormValue value);
}

final class DioStudentPlacementRemoteDataSource
    implements StudentPlacementRemoteDataSource {
  DioStudentPlacementRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<StudentPlacementPage> fetch({
    int? academicYearId,
    int? classId,
    required String query,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'penempatan-siswa',
        queryParameters: {
          'tahun_pelajaran_id': ?academicYearId,
          'kelas_id': ?classId,
          if (query.trim().isNotEmpty) 'cari': query.trim(),
        },
      );
      return StudentPlacementPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<int> place(StudentPlacementFormValue value) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'penempatan-siswa/masukkan',
        data: {
          'kelas_id': value.classId,
          'siswa_ids': value.studentIds,
          'tanggal_masuk': _dateValue(value.entryDate),
          'keterangan': _text(value.notes),
        },
      );
      final data = response.data!['data'] as Map<String, dynamic>;
      return (data['jumlah_ditempatkan'] as num?)?.toInt() ?? 0;
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

final studentPlacementRemoteDataSourceProvider =
    Provider<StudentPlacementRemoteDataSource>(
      (ref) => DioStudentPlacementRemoteDataSource(ref.watch(dioProvider)),
    );
