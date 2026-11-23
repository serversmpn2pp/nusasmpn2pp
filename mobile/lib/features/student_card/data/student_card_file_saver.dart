import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

abstract interface class StudentCardFileSaver {
  Future<bool> save({required String fileName, required Uint8List bytes});
}

final class DeviceStudentCardFileSaver implements StudentCardFileSaver {
  @override
  Future<bool> save({
    required String fileName,
    required Uint8List bytes,
  }) async {
    final pdf = fileName.endsWith('.pdf');
    final uri = await FilePicker.saveFile(
      fileName: fileName,
      bytes: bytes,
      mimeType: pdf ? 'application/pdf' : 'image/png',
      type: FileType.custom,
      allowedExtensions: [pdf ? 'pdf' : 'png'],
      dialogTitle: 'Simpan kartu pelajar',
    );
    return uri != null;
  }
}

final studentCardFileSaverProvider = Provider<StudentCardFileSaver>(
  (ref) => DeviceStudentCardFileSaver(),
);
