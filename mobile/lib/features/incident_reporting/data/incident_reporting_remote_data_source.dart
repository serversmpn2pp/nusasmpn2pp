import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/incident_reporting/domain/incident_reporting.dart';

abstract interface class IncidentReportingRemoteDataSource {
  Future<IncidentReportReference> fetchReference();

  Future<IncidentReportResult> submit(IncidentReportFormValue value);
}

final class DioIncidentReportingRemoteDataSource
    implements IncidentReportingRemoteDataSource {
  DioIncidentReportingRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<IncidentReportReference> fetchReference() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'laporkan-kejadian/referensi',
      );
      return IncidentReportReference.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<IncidentReportResult> submit(IncidentReportFormValue value) async {
    final form = FormData();
    void add(String key, Object? item) {
      if (item != null && item.toString().trim().isNotEmpty) {
        form.fields.add(MapEntry(key, item.toString()));
      }
    }

    add('tanggal_kejadian', value.date);
    add('waktu_kejadian', value.time);
    add('tempat_kejadian', value.place);
    add('tahun_pelajaran_id', value.academicYearId);
    add('kelas_id', value.classId);
    add('kronologi', value.chronology);
    add('tindakan_awal', value.initialAction);
    add('keterangan_bukti', value.evidenceDescription);
    for (final studentId in value.studentIds) {
      add('siswa_ids[]', studentId);
    }
    for (var index = 0; index < value.witnesses.length; index++) {
      final witness = value.witnesses[index];
      add('daftar_saksi[$index][jenis_saksi]', witness.type);
      add('daftar_saksi[$index][nama_saksi]', witness.name);
      add('daftar_saksi[$index][pernyataan]', witness.statement);
    }
    for (final evidence in value.evidence) {
      form.files.add(
        MapEntry(
          'bukti_laporan[]',
          MultipartFile.fromBytes(evidence.bytes, filename: evidence.name),
        ),
      );
    }

    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'laporkan-kejadian',
        data: form,
      );
      return IncidentReportResult.fromJson(response.data!);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final incidentReportingRemoteDataSourceProvider =
    Provider<IncidentReportingRemoteDataSource>(
      (ref) => DioIncidentReportingRemoteDataSource(ref.watch(dioProvider)),
    );
