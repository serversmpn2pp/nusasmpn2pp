import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/teaching_document_review/domain/teaching_document_review.dart';

abstract interface class TeachingDocumentDownloadSaver {
  Future<bool> save(TeachingDocumentDownload download);
}

final class DeviceTeachingDocumentDownloadSaver
    implements TeachingDocumentDownloadSaver {
  @override
  Future<bool> save(TeachingDocumentDownload download) async {
    final uri = await FilePicker.saveFile(
      fileName: download.fileName,
      bytes: download.bytes,
      mimeType: 'application/pdf',
      type: FileType.custom,
      allowedExtensions: const ['pdf'],
      dialogTitle: 'Simpan perangkat ajar',
    );
    return uri != null;
  }
}

final teachingDocumentDownloadSaverProvider =
    Provider<TeachingDocumentDownloadSaver>(
      (ref) => DeviceTeachingDocumentDownloadSaver(),
    );
