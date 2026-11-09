import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/features/central_exam_execution/data/central_exam_execution_remote_data_source.dart';
import 'package:nusa/features/class_assessment/data/class_assessment_monitoring_remote_data_source.dart';
import 'package:nusa/features/exam_supervision/data/exam_supervision_remote_data_source.dart';

void main() {
  test(
    'semua jalur pembukaan Android mengirim alasan petugas ke API',
    () async {
      final adapter = _CaptureAdapter();
      final dio = Dio(BaseOptions(baseUrl: 'https://example.test/api/v1/'))
        ..httpClientAdapter = adapter;
      addTearDown(dio.close);
      const reason = 'Perangkat dan kondisi siswa diperiksa petugas.';
      await DioClassAssessmentMonitoringRemoteDataSource(dio)
          .unlockParticipant(41, reason: '  $reason  ');
      await DioExamSupervisionRemoteDataSource(dio)
          .unlockSafeMode(42, reason: '  $reason  ');
      await DioCentralExamExecutionRemoteDataSource(dio)
          .unlockSafeMode(43, reason: '  $reason  ');
      expect(adapter.requests.map((r) => r.path), [
        'keamanan-ujian/peserta/41/buka',
        'keamanan-ujian/peserta/42/buka',
        'keamanan-ujian/peserta/43/buka',
      ]);
      for (final request in adapter.requests) {
        expect(request.method, 'POST');
        expect(request.data, {'alasan_pembukaan': reason});
      }
    },
  );
}

class _CaptureAdapter implements HttpClientAdapter {
  final requests = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    return ResponseBody.fromString(
      '{"data":{}}',
      200,
      headers: {
        Headers.contentTypeHeader: ['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}
