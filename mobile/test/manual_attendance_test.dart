import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_attendance/domain/my_attendance.dart';
import 'package:nusa/features/student_attendance_recap/data/student_attendance_recap_remote_data_source.dart';
import 'package:nusa/features/student_attendance_recap/domain/student_attendance_recap.dart';
import 'package:nusa/features/student_attendance_recap/presentation/widgets/student_attendance_recap_sheets.dart';
import 'package:nusa/features/teacher_duty/data/teacher_duty_remote_data_source.dart';
import 'package:nusa/shared/domain/manual_attendance.dart';

void main() {
  test('aturan dari server dan keterangan sanksi dibaca tanpa menimpa catatan petugas', () {
    final detail = StudentAttendanceDetail.fromJson({
      'aturan_hadir_manual': {'menit_minimal': 5, 'poin': 20},
      'item': {
        'presensi': {
          'sumber': 'scan',
          'ada_scan_masuk': false,
          'alasan_hadir_manual': 'kartu_hilang',
          'keterangan_sanksi_manual': 'Kartu hilang; 20 poin.',
          'catatan': 'Dikonfirmasi petugas.',
        },
      },
    });
    expect(detail.manualRule.points, 20);
    expect(detail.record.hasScannerEntry, isFalse);
    expect(detail.record.manualReason, 'kartu_hilang');
    expect(detail.record.notes, 'Dikonfirmasi petugas.');
    expect(detail.record.manualSanctionNote, 'Kartu hilang; 20 poin.');
    expect(
      MyAttendanceToday.fromJson({
        'keterangan_sanksi_manual': 'Kartu hilang; 20 poin.',
      }).manualSanctionNote,
      'Kartu hilang; 20 poin.',
    );
  });

  test(
    'API mobile mengirim alasan untuk Hadir dan mengabaikannya untuk Sakit',
    () async {
      final requests = <RequestOptions>[];
      final dio = Dio(BaseOptions(baseUrl: 'https://example.test/api/v1/'));
      addTearDown(dio.close);
      dio.interceptors.add(
        InterceptorsWrapper(
          onRequest: (options, handler) {
            requests.add(options);
            handler.resolve(
              Response<dynamic>(
                requestOptions: options,
                statusCode: 200,
                data: <String, dynamic>{},
              ),
            );
          },
        ),
      );
      await TeacherDutyRemoteDataSource(dio).recordAttendance(
        classMemberId: 1,
        status: 'hadir',
        notes: 'Dikonfirmasi.',
        manualReason: 'kartu_hilang',
      );
      await TeacherDutyRemoteDataSource(dio).recordAttendance(
        classMemberId: 1,
        status: 'sakit',
        notes: 'Surat sakit.',
        manualReason: 'kartu_hilang',
      );
      final recap = DioStudentAttendanceRecapRemoteDataSource(dio);
      await recap.correct(
        classMemberId: 1,
        date: '2026-11-05',
        value: const AttendanceCorrectionValue(
          status: 'hadir',
          notes: 'Dikonfirmasi.',
          checkInTime: '06:55:00',
          manualReason: 'tidak_membawa_kartu',
        ),
      );
      await recap.correct(
        classMemberId: 1,
        date: '2026-11-05',
        value: const AttendanceCorrectionValue(
          status: 'izin',
          notes: 'Surat izin.',
          manualReason: 'kartu_hilang',
        ),
      );
      expect((requests[0].data as Map)['alasan_hadir_manual'], 'kartu_hilang');
      expect(
        (requests[1].data as Map).containsKey('alasan_hadir_manual'),
        isFalse,
      );
      expect(
        (requests[2].data as Map)['alasan_hadir_manual'],
        'tidak_membawa_kartu',
      );
      expect(
        (requests[3].data as Map).containsKey('alasan_hadir_manual'),
        isFalse,
      );
      for (final request in requests) {
        expect(
          (request.data as Map).containsKey('sanksi_manual_menit'),
          isFalse,
        );
        expect(
          (request.data as Map).containsKey('poin_keterlambatan_terhitung'),
          isFalse,
        );
      }
    },
  );

  testWidgets(
    'koreksi tanpa scan menampilkan aturan dan menyimpan alasan kartu pada layar sempit',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      AttendanceCorrectionValue? result;
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.light,
          home: Scaffold(
            body: Builder(
              builder: (context) => TextButton(
                onPressed: () async {
                  result =
                      await showModalBottomSheet<AttendanceCorrectionValue>(
                        context: context,
                        isScrollControlled: true,
                        builder: (_) => StudentAttendanceCorrectionSheet(
                          detail: StudentAttendanceDetail.fromJson({
                            'tanggal': '2026-11-05',
                            'tanggal_label': 'Kamis, 5 November 2026',
                            'aturan_hadir_manual': {
                              'menit_minimal': 5,
                              'poin': 20,
                            },
                            'item': {
                              'siswa': {'nama': 'Siswa Uji'},
                              'presensi': {
                                'status': 'hadir',
                                'sumber': 'guru_piket',
                                'alasan_hadir_manual': 'input_manual_petugas',
                                'jam_masuk': '06:55',
                                'catatan': 'Dikonfirmasi petugas.',
                              },
                            },
                          }),
                        ),
                      );
                },
                child: const Text('Koreksi'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Koreksi'));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('5 menit keterlambatan dan 20 poin'),
        findsOneWidget,
      );
      final reason = find.byKey(
        const Key('attendance-correction-manual-reason'),
      );
      await tester.ensureVisible(reason);
      await tester.tap(reason);
      await tester.pumpAndSettle();
      await tester.tap(
        find.text(ManualAttendanceRule.reasons['kartu_hilang']!).last,
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Simpan Koreksi'));
      await tester.tap(find.text('Simpan Koreksi'));
      await tester.pumpAndSettle();
      expect(result?.manualReason, 'kartu_hilang');
      expect(result?.checkInTime, '06:55');
      expect(tester.takeException(), isNull);
    },
  );
}
