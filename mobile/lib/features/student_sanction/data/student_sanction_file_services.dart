import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_sanction/domain/student_sanction.dart';

abstract interface class StudentSanctionFilePicker {
  Future<List<SanctionPickedFile>> pick();
}

class DeviceStudentSanctionFilePicker implements StudentSanctionFilePicker {
  @override
  Future<List<SanctionPickedFile>> pick() async {
    final files = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    );
    final picked = <SanctionPickedFile>[];
    for (final file in files.take(5)) {
      picked.add(
        SanctionPickedFile(name: file.name, bytes: await file.readAsBytes()),
      );
    }
    return picked;
  }
}

abstract interface class StudentSanctionFileSaver {
  Future<bool> save(SanctionEvidenceDownload download);
}

class DeviceStudentSanctionFileSaver implements StudentSanctionFileSaver {
  @override
  Future<bool> save(SanctionEvidenceDownload download) async =>
      await FilePicker.saveFile(
        dialogTitle: 'Simpan bukti pelaksanaan sanksi',
        fileName: download.fileName,
        bytes: download.bytes,
        mimeType: download.mimeType,
      ) !=
      null;
}

final studentSanctionFilePickerProvider = Provider<StudentSanctionFilePicker>(
  (ref) => DeviceStudentSanctionFilePicker(),
);
final studentSanctionFileSaverProvider = Provider<StudentSanctionFileSaver>(
  (ref) => DeviceStudentSanctionFileSaver(),
);
