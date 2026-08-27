import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/teaching_document/domain/teaching_document.dart';

abstract interface class TeachingDocumentRemoteDataSource {
  Future<TeachingDocumentPage> fetch({
    int? academicYearId,
    required int semester,
  });

  Future<TeachingDocumentDetail> fetchDetail(int id);

  Future<void> create(TeachingDocumentFormValue value);

  Future<void> update({
    required int id,
    required TeachingDocumentFormValue value,
  });
}

final class DioTeachingDocumentRemoteDataSource
    implements TeachingDocumentRemoteDataSource {
  DioTeachingDocumentRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<TeachingDocumentPage> fetch({
    int? academicYearId,
    required int semester,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'perangkat-ajar-saya',
        queryParameters: {
          'tahun_pelajaran_id': ?academicYearId,
          'semester': semester,
        },
      );
      return TeachingDocumentPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<TeachingDocumentDetail> fetchDetail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'perangkat-ajar-saya/$id',
      );
      return TeachingDocumentDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(TeachingDocumentFormValue value) =>
      _send(path: 'perangkat-ajar-saya', value: value, creating: true);

  @override
  Future<void> update({
    required int id,
    required TeachingDocumentFormValue value,
  }) => _send(path: 'perangkat-ajar-saya/$id', value: value, creating: false);

  Future<void> _send({
    required String path,
    required TeachingDocumentFormValue value,
    required bool creating,
  }) async {
    try {
      final data = <String, dynamic>{
        'tingkat': value.grade,
        'judul': value.title.trim(),
        'catatan_guru': value.teacherNote?.trim().isEmpty == true
            ? null
            : value.teacherNote?.trim(),
        if (creating) ...{
          'tahun_pelajaran_id': value.academicYearId,
          'semester': value.semester,
          'mata_pelajaran_id': value.subjectId,
          'jenis_perangkat_ajar_id': value.typeId,
        },
        if (value.file case final file?)
          'file_pdf': MultipartFile.fromBytes(
            file.bytes,
            filename: file.name,
            contentType: DioMediaType('application', 'pdf'),
          ),
      };
      await _dio.post<Map<String, dynamic>>(path, data: FormData.fromMap(data));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final teachingDocumentRemoteDataSourceProvider =
    Provider<TeachingDocumentRemoteDataSource>(
      (ref) => DioTeachingDocumentRemoteDataSource(ref.watch(dioProvider)),
    );
