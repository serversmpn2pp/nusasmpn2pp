import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_report/domain/student_report.dart';

abstract interface class StudentReportEvidenceSaver {
  Future<bool> save(StudentReportEvidenceDownload download);
}

final class DeviceStudentReportEvidenceSaver
    implements StudentReportEvidenceSaver {
  @override
  Future<bool> save(StudentReportEvidenceDownload download) async {
    final uri = await FilePicker.saveFile(
      fileName: download.fileName,
      bytes: download.bytes,
      mimeType: download.mimeType,
      dialogTitle: 'Simpan bukti laporan',
    );
    return uri != null;
  }
}

final studentReportEvidenceSaverProvider = Provider<StudentReportEvidenceSaver>(
  (ref) => DeviceStudentReportEvidenceSaver(),
);
