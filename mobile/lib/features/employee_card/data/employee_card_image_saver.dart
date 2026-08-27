import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

abstract interface class EmployeeCardImageSaver {
  Future<bool> save({required String fileName, required Uint8List bytes});
}

final class DeviceEmployeeCardImageSaver implements EmployeeCardImageSaver {
  @override
  Future<bool> save({
    required String fileName,
    required Uint8List bytes,
  }) async {
    final uri = await FilePicker.saveFile(
      fileName: fileName,
      bytes: bytes,
      mimeType: 'image/png',
      type: FileType.custom,
      allowedExtensions: const ['png'],
      dialogTitle: 'Simpan kartu pegawai',
    );
    return uri != null;
  }
}

final employeeCardImageSaverProvider = Provider<EmployeeCardImageSaver>(
  (ref) => DeviceEmployeeCardImageSaver(),
);
