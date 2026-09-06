import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';

abstract interface class CentralExamPreparationRemoteDataSource {
  Future<CentralExamPreparationPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  });
  Future<CentralExamPreparationDetail> detail(int eventId);
  Future<int> createEvent(CentralExamEventFormValue value);
  Future<void> updateEvent(int eventId, CentralExamEventFormValue value);
  Future<void> deleteEvent(int eventId);
  Future<void> saveCommittee(int eventId, CentralExamCommitteeFormValue value);
  Future<void> deleteCommittee(int eventId, int memberId);
  Future<void> createSession(int eventId, CentralExamSessionFormValue value);
  Future<void> updateSession(
    int eventId,
    int sessionId,
    CentralExamSessionFormValue value,
  );
  Future<void> deleteSession(int eventId, int sessionId);
  Future<void> createRoom(int eventId, CentralExamRoomFormValue value);
  Future<void> updateRoom(
    int eventId,
    int roomId,
    CentralExamRoomFormValue value,
  );
  Future<void> deleteRoom(int eventId, int roomId);
  Future<void> saveRoomAssignment(
    int eventId,
    CentralExamRoomAssignmentFormValue value,
  );
  Future<void> generateParticipants(int eventId, int groupId);
  Future<void> deleteRoomAssignment(int eventId, int groupId);
  Future<CentralExamDistributionDetail> distributionDetail(
    int eventId,
    int groupId,
  );
  Future<void> createSchedule(int eventId, CentralExamScheduleFormValue value);
  Future<void> updateSchedule(
    int eventId,
    int scheduleId,
    CentralExamScheduleFormValue value,
  );
  Future<void> deleteSchedule(int eventId, int scheduleId);
}

final class DioCentralExamPreparationRemoteDataSource
    implements CentralExamPreparationRemoteDataSource {
  DioCentralExamPreparationRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<CentralExamPreparationPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'ujian-terpusat',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return CentralExamPreparationPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<CentralExamPreparationDetail> detail(int eventId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'ujian-terpusat/$eventId',
      );
      return CentralExamPreparationDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<int> createEvent(CentralExamEventFormValue value) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'ujian-terpusat',
        data: _eventData(value),
      );
      final data = response.data!['data'] as Map<String, dynamic>;
      return (data['id'] as num).toInt();
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> updateEvent(int eventId, CentralExamEventFormValue value) =>
      _request('ujian-terpusat/$eventId', 'PATCH', _eventData(value));

  @override
  Future<void> deleteEvent(int eventId) =>
      _request('ujian-terpusat/$eventId', 'DELETE');

  @override
  Future<void> saveCommittee(
    int eventId,
    CentralExamCommitteeFormValue value,
  ) => _request('ujian-terpusat/$eventId/panitia', 'POST', {
    'pegawai_id': value.employeeId,
    'jabatan': value.position,
    'catatan': _text(value.notes),
  });

  @override
  Future<void> deleteCommittee(int eventId, int memberId) =>
      _request('ujian-terpusat/$eventId/panitia/$memberId', 'DELETE');

  @override
  Future<void> createSession(int eventId, CentralExamSessionFormValue value) =>
      _request('ujian-terpusat/$eventId/sesi', 'POST', _sessionData(value));

  @override
  Future<void> updateSession(
    int eventId,
    int sessionId,
    CentralExamSessionFormValue value,
  ) => _request(
    'ujian-terpusat/$eventId/sesi/$sessionId',
    'PATCH',
    _sessionData(value),
  );

  @override
  Future<void> deleteSession(int eventId, int sessionId) =>
      _request('ujian-terpusat/$eventId/sesi/$sessionId', 'DELETE');

  @override
  Future<void> createRoom(int eventId, CentralExamRoomFormValue value) =>
      _request('ujian-terpusat/$eventId/ruang', 'POST', _roomData(value));

  @override
  Future<void> updateRoom(
    int eventId,
    int roomId,
    CentralExamRoomFormValue value,
  ) => _request(
    'ujian-terpusat/$eventId/ruang/$roomId',
    'PATCH',
    _roomData(value),
  );

  @override
  Future<void> deleteRoom(int eventId, int roomId) =>
      _request('ujian-terpusat/$eventId/ruang/$roomId', 'DELETE');

  @override
  Future<void> saveRoomAssignment(
    int eventId,
    CentralExamRoomAssignmentFormValue value,
  ) => _request('ujian-terpusat/$eventId/penetapan-ruang', 'POST', {
    'tingkat': value.grade,
    'sesi_kegiatan_ujian_cbt_id': value.sessionId,
    'kelas': value.classIds,
    'ruang': value.roomIds,
  });

  @override
  Future<void> generateParticipants(int eventId, int groupId) => _request(
    'ujian-terpusat/$eventId/pembagian-peserta/$groupId/bangkitkan',
    'POST',
  );

  @override
  Future<void> deleteRoomAssignment(int eventId, int groupId) =>
      _request('ujian-terpusat/$eventId/pembagian-peserta/$groupId', 'DELETE');

  @override
  Future<CentralExamDistributionDetail> distributionDetail(
    int eventId,
    int groupId,
  ) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'ujian-terpusat/$eventId/pembagian-peserta/$groupId',
      );
      return CentralExamDistributionDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> createSchedule(
    int eventId,
    CentralExamScheduleFormValue value,
  ) => _request(
    'ujian-terpusat/$eventId/jadwal',
    'POST',
    _scheduleData(value, includeGrades: true),
  );

  @override
  Future<void> updateSchedule(
    int eventId,
    int scheduleId,
    CentralExamScheduleFormValue value,
  ) => _request(
    'ujian-terpusat/$eventId/jadwal/$scheduleId',
    'PATCH',
    _scheduleData(value, includeGrades: false),
  );

  @override
  Future<void> deleteSchedule(int eventId, int scheduleId) =>
      _request('ujian-terpusat/$eventId/jadwal/$scheduleId', 'DELETE');

  Future<void> _request(
    String path,
    String method, [
    Map<String, dynamic>? data,
  ]) async {
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

  Map<String, dynamic> _eventData(CentralExamEventFormValue value) => {
    'jenis_ujian_cbt_id': value.examTypeId,
    'tahun_pelajaran_id': value.academicYearId,
    'nama': value.name.trim(),
    'semester': value.semester,
    'tanggal_mulai': _date(value.startsOn),
    'tanggal_selesai': _date(value.endsOn),
    'status': value.status,
    'keterangan': _text(value.notes),
  };

  Map<String, dynamic> _sessionData(CentralExamSessionFormValue value) => {
    'nama': value.name.trim(),
    'waktu_mulai': value.startsAt,
    'waktu_selesai': value.endsAt,
    'aktif': value.active,
    'keterangan': _text(value.notes),
  };

  Map<String, dynamic> _roomData(CentralExamRoomFormValue value) => {
    'nama': value.name.trim(),
    'lokasi': _text(value.location),
    'kapasitas': value.capacity,
    'aktif': value.active,
    'keterangan': _text(value.notes),
  };

  Map<String, dynamic> _scheduleData(
    CentralExamScheduleFormValue value, {
    required bool includeGrades,
  }) => {
    'tanggal': _date(value.date),
    'mata_pelajaran_id': value.subjectId,
    if (includeGrades) 'tingkat': value.grades,
    'keterangan': _text(value.notes),
  };
}

String _date(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

final centralExamPreparationRemoteDataSourceProvider =
    Provider<CentralExamPreparationRemoteDataSource>(
      (ref) =>
          DioCentralExamPreparationRemoteDataSource(ref.watch(dioProvider)),
    );
