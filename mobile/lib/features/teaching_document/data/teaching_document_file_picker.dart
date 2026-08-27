import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/teaching_document/domain/teaching_document.dart';

abstract interface class TeachingDocumentFilePicker {
  Future<TeachingDocumentPickedFile?> pickPdf();
}

final class DeviceTeachingDocumentFilePicker
    implements TeachingDocumentFilePicker {
  @override
  Future<TeachingDocumentPickedFile?> pickPdf() async {
    final file = await FilePicker.pickFile(
      type: FileType.custom,
      allowedExtensions: const ['pdf'],
    );
    if (file == null) return null;
    final bytes = await file.readAsBytes();
    return TeachingDocumentPickedFile(name: file.name, bytes: bytes);
  }
}

final teachingDocumentFilePickerProvider = Provider<TeachingDocumentFilePicker>(
  (ref) => DeviceTeachingDocumentFilePicker(),
);
