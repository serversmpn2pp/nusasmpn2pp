import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/employee/domain/employee.dart';

abstract interface class EmployeeRemoteDataSource {
  Future<EmployeePage> fetchEmployees({
    required String query,
    required String status,
    required String type,
    required int page,
    int perPage = 15,
  });

  Future<EmployeeDetail> fetchEmployee(int id);

  Future<void> create(EmployeeFormValue value);

  Future<void> update({required int id, required EmployeeFormValue value});
}

final class DioEmployeeRemoteDataSource implements EmployeeRemoteDataSource {
  DioEmployeeRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<EmployeePage> fetchEmployees({
    required String query,
    required String status,
    required String type,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pegawai',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'jenis_pegawai': type,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return EmployeePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<EmployeeDetail> fetchEmployee(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('pegawai/$id');
      return EmployeeDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(EmployeeFormValue value) =>
      _send(path: 'pegawai', method: 'POST', value: value);

  @override
  Future<void> update({required int id, required EmployeeFormValue value}) =>
      _send(path: 'pegawai/$id', method: 'PATCH', value: value);

  Future<void> _send({
    required String path,
    required String method,
    required EmployeeFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'nama_lengkap': value.name.trim(),
          'nip': _text(value.nip),
          'nuptk': _text(value.nuptk),
          'nik': _text(value.nik),
          'jenis_kelamin': _text(value.gender),
          'tempat_lahir': _text(value.birthPlace),
          'tanggal_lahir': _dateValue(value.birthDate),
          'alamat': _text(value.address),
          'email': _text(value.email),
          'no_hp': _text(value.phone),
          'status_kepegawaian': _text(value.employmentStatus),
          'golongan': _text(value.rank),
          'tanggal_mulai_kerja': _dateValue(value.workStartDate),
          'tanggal_mulai_bertugas': _dateValue(value.dutyStartDate),
          'jenis_pegawai': _text(value.employeeType),
          'jabatan_utama': _text(value.primaryPosition),
          'sumber_gaji': _text(value.salarySource),
          'pendidikan_terakhir': _text(value.lastEducation),
          'jurusan_pendidikan': _text(value.educationMajor),
          'tahun_lulus': value.graduationYear,
          'keterangan': _text(value.notes),
          'aktif': value.active,
        },
        options: Options(method: method),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

String? _dateValue(DateTime? value) {
  if (value == null) return null;
  final month = value.month.toString().padLeft(2, '0');
  final day = value.day.toString().padLeft(2, '0');
  return '${value.year}-$month-$day';
}

final employeeRemoteDataSourceProvider = Provider<EmployeeRemoteDataSource>(
  (ref) => DioEmployeeRemoteDataSource(ref.watch(dioProvider)),
);
