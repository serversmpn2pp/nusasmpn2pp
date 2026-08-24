import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/teaching_assignment/domain/teaching_assignment.dart';

abstract interface class TeachingAssignmentRemoteDataSource {
  Future<TeachingAssignmentPage> fetch({
    required String query,
    required String status,
    required int page,
    int? academicYearId,
    int perPage = 15,
  });

  Future<TeachingAssignmentReference> fetchReference();

  Future<void> create({
    required int academicYearId,
    required List<int> classIds,
    required int subjectId,
    required int employeeId,
    required String assignmentType,
    required bool active,
    String? notes,
  });

  Future<void> update({
    required int id,
    required int academicYearId,
    required int classId,
    required int subjectId,
    required int employeeId,
    required String assignmentType,
    required bool active,
    String? notes,
  });
}

final class DioTeachingAssignmentRemoteDataSource
    implements TeachingAssignmentRemoteDataSource {
  DioTeachingAssignmentRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<TeachingAssignmentPage> fetch({
    required String query,
    required String status,
    required int page,
    int? academicYearId,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'guru-mata-pelajaran',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'tahun_pelajaran_id': ?academicYearId,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return TeachingAssignmentPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<TeachingAssignmentReference> fetchReference() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'guru-mata-pelajaran/referensi',
      );
      return TeachingAssignmentReference.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create({
    required int academicYearId,
    required List<int> classIds,
    required int subjectId,
    required int employeeId,
    required String assignmentType,
    required bool active,
    String? notes,
  }) => _send(
    path: 'guru-mata-pelajaran',
    method: 'POST',
    data: _payload(
      academicYearId: academicYearId,
      classIds: classIds,
      subjectId: subjectId,
      employeeId: employeeId,
      assignmentType: assignmentType,
      active: active,
      notes: notes,
    ),
  );

  @override
  Future<void> update({
    required int id,
    required int academicYearId,
    required int classId,
    required int subjectId,
    required int employeeId,
    required String assignmentType,
    required bool active,
    String? notes,
  }) => _send(
    path: 'guru-mata-pelajaran/$id',
    method: 'PATCH',
    data: {
      'tahun_pelajaran_id': academicYearId,
      'kelas_id': classId,
      'mata_pelajaran_id': subjectId,
      'pegawai_id': employeeId,
      'jenis_penugasan': assignmentType,
      'aktif': active,
      'keterangan': _text(notes),
    },
  );

  Future<void> _send({
    required String path,
    required String method,
    required Map<String, dynamic> data,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: data,
        options: Options(method: method),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

Map<String, dynamic> _payload({
  required int academicYearId,
  required List<int> classIds,
  required int subjectId,
  required int employeeId,
  required String assignmentType,
  required bool active,
  String? notes,
}) => {
  'tahun_pelajaran_id': academicYearId,
  'kelas_ids': classIds,
  'mata_pelajaran_id': subjectId,
  'pegawai_id': employeeId,
  'jenis_penugasan': assignmentType,
  'aktif': active,
  'keterangan': _text(notes),
};

String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

final teachingAssignmentRemoteDataSourceProvider =
    Provider<TeachingAssignmentRemoteDataSource>(
      (ref) => DioTeachingAssignmentRemoteDataSource(ref.watch(dioProvider)),
    );
