import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/point_reduction/domain/point_reduction.dart';

abstract interface class PointReductionFilePicker {
  Future<ReductionPickedFile?> pick();
}

class DevicePointReductionFilePicker implements PointReductionFilePicker {
  @override
  Future<ReductionPickedFile?> pick() async {
    final files = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png'],
    );
    final file = files.firstOrNull;
    if (file == null) return null;
    final bytes = await file.readAsBytes();
    return ReductionPickedFile(name: file.name, bytes: bytes);
  }
}

abstract interface class PointReductionFileSaver {
  Future<bool> save(ReductionEvidenceDownload download);
}

class DevicePointReductionFileSaver implements PointReductionFileSaver {
  @override
  Future<bool> save(ReductionEvidenceDownload download) async =>
      await FilePicker.saveFile(
        dialogTitle: 'Simpan bukti penghargaan',
        fileName: download.fileName,
        bytes: download.bytes,
        mimeType: download.mimeType,
      ) !=
      null;
}

final pointReductionFilePickerProvider = Provider<PointReductionFilePicker>(
  (ref) => DevicePointReductionFilePicker(),
);
final pointReductionFileSaverProvider = Provider<PointReductionFileSaver>(
  (ref) => DevicePointReductionFileSaver(),
);
