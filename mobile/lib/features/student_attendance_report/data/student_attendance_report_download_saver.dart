import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_attendance_report/domain/student_attendance_report.dart';

abstract interface class StudentAttendanceReportDownloadSaver {
  Future<bool> save(AttendanceReportDownload download);
}

final class DeviceStudentAttendanceReportDownloadSaver
    implements StudentAttendanceReportDownloadSaver {
  @override
  Future<bool> save(AttendanceReportDownload download) async =>
      await FilePicker.saveFile(
        dialogTitle: 'Simpan laporan presensi siswa',
        fileName: download.fileName,
        bytes: download.bytes,
        type: FileType.custom,
        allowedExtensions: const ['xlsx'],
        mimeType:
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      ) !=
      null;
}

final studentAttendanceReportDownloadSaverProvider =
    Provider<StudentAttendanceReportDownloadSaver>(
      (ref) => DeviceStudentAttendanceReportDownloadSaver(),
    );
