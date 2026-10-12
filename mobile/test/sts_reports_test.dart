import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/sts_reports/data/sts_reports_remote_data_source.dart';
import 'package:nusa/features/sts_reports/domain/sts_reports.dart';

import 'fixtures/sts_report_fixture.dart';

void main() {
  test(
    'parse report: final decimal, zero, missing, readiness and attendance',
    () {
      final page = StsReportPage.fromJson(stsReportFixture());
      final report = page.context!;
      expect(page.filter.activityId, 1);
      expect(page.filter.classId, 7);
      expect(report.students[0].grades[0].value, 88.5);
      expect(report.students[1].grades[0].value, 0);
      expect(report.students[2].grades[0].value, isNull);
      expect(report.students[2].grades[1].excluded, isTrue);
      expect(report.students[2].grades[1].exclusionReason, isNotEmpty);
      expect(report.students[0].ready, isTrue);
      expect(report.students[0].complete, isFalse);
      expect(report.students[0].absent, 2);
      expect(report.students[0].lateMinutes, 18);
      expect(report.students[1].sourceChanged, isTrue);
      expect(report.period!.ended, isTrue);
    },
  );

  test('ledger uses server totals, ranks, nullable averages and grade scope', () {
    final json = stsReportFixture(
      kind: StsReportKind.ledger,
      filter: const StsReportFilter(mode: StsLedgerMode.grade),
    );
    // Deliberately not inferable from local grades; preserve the server result.
    (json['konteks'] as Map)['siswa'][1]['ranking'] = 1;
    final report = StsReportPage.fromJson(json).context!;
    expect(report.students[0].average, 44.25);
    expect(report.students[0].total, 88.5);
    expect(report.students[0].draftCount, 1);
    expect(report.students[1].rank, 1); // Competition ties are not renumbered.
    expect(report.students[1].average, 0);
    expect(report.students[2].average, isNull);
    expect(report.students[2].rank, isNull);
    expect(report.completeGradeScope, isFalse);
    expect(report.summary.average, 22.13);
    expect(report.classStats.single.average, 22.13);
  });

  test('empty context remains empty, not a fabricated report', () {
    final page = StsReportPage.fromJson(stsReportFixture(empty: true));
    expect(page.context, isNull);
    expect(page.classes, isEmpty);
    expect(page.filter.activityId, isNull);
  });

  test(
    'query never sends stale class in grade mode or stale grade in class mode',
    () {
      const filter = StsReportFilter(
        activityId: 1,
        classId: 7,
        grade: 8,
        mode: StsLedgerMode.grade,
      );
      expect(filter.query(StsReportKind.ledger), {
        'kegiatan_id': 1,
        'mode': 'tingkat',
        'tingkat': 8,
      });
      expect(filter.query(StsReportKind.report), {
        'kegiatan_id': 1,
        'kelas_id': 7,
      });
      expect(
        const StsReportFilter(
          activityId: 1,
          classId: 7,
          grade: 8,
        ).query(StsReportKind.ledger),
        {'kegiatan_id': 1, 'mode': 'kelas', 'kelas_id': 7},
      );
    },
  );

  test('format keeps zero and decimals distinct from unavailable values', () {
    expect(stsNumber(null), '—');
    expect(stsNumber(0), '0');
    expect(stsNumber(100), '100');
    expect(stsNumber(88.5), '88,5');
    expect(stsNumber(44.25), '44,25');
    expect(stsDate('2026-10-16'), '16 Okt 2026');
  });

  test(
    'remote calls correct authenticated API paths with scoped query',
    () async {
      final dio = Dio(
        BaseOptions(
          baseUrl: 'https://example.invalid/api/v1/',
          headers: {'Authorization': 'Bearer test-only'},
        ),
      );
      final requests = <RequestOptions>[];
      dio.interceptors.add(
        InterceptorsWrapper(
          onRequest: (options, handler) {
            requests.add(options);
            handler.resolve(
              Response(
                requestOptions: options,
                statusCode: 200,
                data: {'data': stsReportFixture()},
              ),
            );
          },
        ),
      );
      final remote = DioStsReportsRemoteDataSource(dio);
      await remote.fetch(
        StsReportKind.report,
        const StsReportFilter(activityId: 1, classId: 7),
      );
      await remote.fetch(
        StsReportKind.ledger,
        const StsReportFilter(
          activityId: 1,
          classId: 7,
          grade: 8,
          mode: StsLedgerMode.grade,
        ),
      );
      expect(requests.first.path, 'rapor-sts');
      expect(requests.first.queryParameters, {'kegiatan_id': 1, 'kelas_id': 7});
      expect(requests.last.path, 'leger-sts');
      expect(requests.last.queryParameters, {
        'kegiatan_id': 1,
        'mode': 'tingkat',
        'tingkat': 8,
      });
      expect(requests.last.headers['Authorization'], 'Bearer test-only');
      expect(requests.last.uri.query, isNot(contains('Bearer')));
      expect(requests.every((request) => request.method == 'GET'), isTrue);
      dio.close();
    },
  );

  for (final status in [401, 403, 404, 422, 500]) {
    test('remote maps HTTP $status into user-facing error', () async {
      final dio = Dio();
      dio.interceptors.add(
        InterceptorsWrapper(
          onRequest: (options, handler) {
            handler.reject(
              DioException(
                requestOptions: options,
                type: DioExceptionType.badResponse,
                response: Response(
                  requestOptions: options,
                  statusCode: status,
                  data: {'message': 'SQLSTATE vendor/private-stack'},
                ),
              ),
            );
          },
        ),
      );
      await expectLater(
        DioStsReportsRemoteDataSource(dio)
            .fetch(StsReportKind.report, const StsReportFilter()),
        throwsA(
          isA<AppException>().having(
            (error) => error.message,
            'safe message',
            isNot(contains('SQLSTATE')),
          ),
        ),
      );
      dio.close();
    });
  }
}
