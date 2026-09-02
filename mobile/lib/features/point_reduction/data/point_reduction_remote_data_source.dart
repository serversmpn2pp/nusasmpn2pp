import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/point_reduction/domain/point_reduction.dart';

abstract interface class PointReductionRemoteDataSource {
  Future<PointReductionPage> fetch({
    required String query,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  });
  Future<PointReductionMutation> create(PointReductionCreatePayload payload);
  Future<PointReductionMutation> decide({
    required int id,
    required String decision,
    required String? note,
  });
  Future<ReductionEvidenceDownload> download(PointReductionItem item);
}

final class DioPointReductionRemoteDataSource
    implements PointReductionRemoteDataSource {
  DioPointReductionRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<PointReductionPage> fetch({
    required String query,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengurangan-poin-siswa',
        queryParameters: {
          'kata_kunci': query,
          'status': status,
          'tahun_pelajaran_id': ?academicYearId,
          'kelas_id': ?classId,
          'halaman': page,
          'per_halaman': 15,
        },
      );
      return PointReductionPage.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<PointReductionMutation> create(
    PointReductionCreatePayload payload,
  ) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'pengurangan-poin-siswa',
        data: FormData.fromMap({
          'siswa_id': payload.studentId,
          'tanggal_kegiatan': payload.activityDate,
          'jenis_kegiatan': payload.activity,
          'deskripsi': ?payload.description,
          'poin_pengurangan': payload.points,
          if (payload.evidence != null)
            'bukti': MultipartFile.fromBytes(
              payload.evidence!.bytes,
              filename: payload.evidence!.name,
            ),
        }),
      );
      return _mutation(response);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<PointReductionMutation> decide({
    required int id,
    required String decision,
    required String? note,
  }) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(
        'pengurangan-poin-siswa/$id/putusan',
        data: {'keputusan': decision, 'catatan_keputusan': ?note},
      );
      return _mutation(response);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<ReductionEvidenceDownload> download(PointReductionItem item) async {
    try {
      final response = await _dio.get<List<int>>(
        'pengurangan-poin-siswa/${item.id}/bukti',
        options: Options(responseType: ResponseType.bytes),
      );
      return ReductionEvidenceDownload(
        fileName: item.evidence?.fileName ?? 'Bukti penghargaan',
        mimeType: item.evidence?.mimeType ?? 'application/octet-stream',
        bytes: Uint8List.fromList(response.data ?? const []),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  PointReductionMutation _mutation(Response<Map<String, dynamic>> response) =>
      PointReductionMutation(
        message: response.data?['message'] as String? ?? 'Berhasil disimpan.',
        item: PointReductionItem.fromJson(_data(response)),
      );

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final pointReductionRemoteDataSourceProvider =
    Provider<PointReductionRemoteDataSource>(
      (ref) => DioPointReductionRemoteDataSource(ref.watch(dioProvider)),
    );
