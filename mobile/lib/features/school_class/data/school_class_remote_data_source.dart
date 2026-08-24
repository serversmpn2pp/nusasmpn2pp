import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/school_class/domain/school_class.dart';

abstract interface class SchoolClassRemoteDataSource {
  Future<SchoolClassPage> fetchClasses({
    required String query,
    required String status,
    required int page,
    int? academicYearId,
    int perPage = 15,
  });

  Future<SchoolClassDetail> fetchClass(int id);

  Future<SchoolClassCandidatePage> fetchCandidates({
    required int classId,
    required String query,
  });

  Future<void> addMember({
    required int classId,
    required int studentId,
    DateTime? joinDate,
    String? notes,
  });

  Future<void> updateMember({
    required int classId,
    required int memberId,
    DateTime? joinDate,
    String? notes,
  });

  Future<void> deleteMember({required int classId, required int memberId});

  Future<ScheduleChoiceCatalog> fetchScheduleChoices({required int classId});

  Future<void> updateScheduleSlot({
    required int classId,
    required int slotId,
    required String? scheduleChoice,
    String? notes,
  });
}

final class DioSchoolClassRemoteDataSource
    implements SchoolClassRemoteDataSource {
  DioSchoolClassRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<SchoolClassPage> fetchClasses({
    required String query,
    required String status,
    required int page,
    int? academicYearId,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'kelas',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'tahun_pelajaran_id': ?academicYearId,
          'halaman': page,
          'per_halaman': perPage,
        },
      );

      return SchoolClassPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<SchoolClassDetail> fetchClass(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('kelas/$id');

      return SchoolClassDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<SchoolClassCandidatePage> fetchCandidates({
    required int classId,
    required String query,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'kelas/$classId/calon-anggota',
        queryParameters: {if (query.trim().isNotEmpty) 'cari': query.trim()},
      );

      return SchoolClassCandidatePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> addMember({
    required int classId,
    required int studentId,
    DateTime? joinDate,
    String? notes,
  }) async {
    try {
      await _dio.post<Map<String, dynamic>>(
        'kelas/$classId/anggota',
        data: {
          'siswa_id': studentId,
          'tanggal_masuk': _dateValue(joinDate),
          'keterangan': notes?.trim().isEmpty == true ? null : notes?.trim(),
        },
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> updateMember({
    required int classId,
    required int memberId,
    DateTime? joinDate,
    String? notes,
  }) async {
    try {
      await _dio.patch<Map<String, dynamic>>(
        'kelas/$classId/anggota/$memberId',
        data: {
          'tanggal_masuk': _dateValue(joinDate),
          'keterangan': notes?.trim().isEmpty == true ? null : notes?.trim(),
        },
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> deleteMember({
    required int classId,
    required int memberId,
  }) async {
    try {
      await _dio.delete<Map<String, dynamic>>(
        'kelas/$classId/anggota/$memberId',
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<ScheduleChoiceCatalog> fetchScheduleChoices({
    required int classId,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'kelas/$classId/jadwal/pilihan',
      );

      return ScheduleChoiceCatalog.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> updateScheduleSlot({
    required int classId,
    required int slotId,
    required String? scheduleChoice,
    String? notes,
  }) async {
    try {
      await _dio.put<Map<String, dynamic>>(
        'kelas/$classId/jadwal/$slotId',
        data: {
          'pilihan_jadwal': scheduleChoice,
          'keterangan': notes?.trim().isEmpty == true ? null : notes?.trim(),
        },
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

final schoolClassRemoteDataSourceProvider =
    Provider<SchoolClassRemoteDataSource>(
      (ref) => DioSchoolClassRemoteDataSource(ref.watch(dioProvider)),
    );
