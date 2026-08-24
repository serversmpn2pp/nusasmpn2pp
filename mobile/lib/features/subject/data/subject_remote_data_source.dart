import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/subject/domain/subject.dart';

abstract interface class SubjectRemoteDataSource {
  Future<SubjectPage> fetch({
    required String query,
    required String status,
    required String level,
    required int page,
    int? academicYearId,
    int perPage = 15,
  });

  Future<SubjectReference> fetchReference();

  Future<void> create(SubjectFormValue value);

  Future<void> update({required int id, required SubjectFormValue value});
}

final class DioSubjectRemoteDataSource implements SubjectRemoteDataSource {
  DioSubjectRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<SubjectPage> fetch({
    required String query,
    required String status,
    required String level,
    required int page,
    int? academicYearId,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'mata-pelajaran',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'tingkat': level,
          'tahun_pelajaran_id': ?academicYearId,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return SubjectPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<SubjectReference> fetchReference() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'mata-pelajaran/referensi',
      );
      return SubjectReference.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(SubjectFormValue value) =>
      _send(path: 'mata-pelajaran', method: 'POST', value: value);

  @override
  Future<void> update({required int id, required SubjectFormValue value}) =>
      _send(path: 'mata-pelajaran/$id', method: 'PATCH', value: value);

  Future<void> _send({
    required String path,
    required String method,
    required SubjectFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: _payload(value),
        options: Options(method: method),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

Map<String, dynamic> _payload(SubjectFormValue value) => {
  'tahun_pelajaran_id': value.academicYearId,
  'nama': value.name.trim(),
  'kelompok': _text(value.group),
  'urutan': value.order,
  'aktif': value.active,
  'keterangan': _text(value.notes),
  'pengaturan': {
    for (final setting in value.settings)
      '${setting.level}': {
        'aktif': setting.active,
        'kode': _text(setting.code),
        'kkm': setting.minimumScore,
      },
  },
};

String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

final subjectRemoteDataSourceProvider = Provider<SubjectRemoteDataSource>(
  (ref) => DioSubjectRemoteDataSource(ref.watch(dioProvider)),
);
