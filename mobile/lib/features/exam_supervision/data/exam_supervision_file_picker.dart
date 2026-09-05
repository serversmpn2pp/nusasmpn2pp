import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:nusa/features/exam_supervision/domain/exam_supervision.dart';

abstract interface class ExamSupervisionFilePicker {
  Future<SupervisionPickedFile?> camera();
  Future<SupervisionPickedFile?> file();
}

class DeviceExamSupervisionFilePicker implements ExamSupervisionFilePicker {
  DeviceExamSupervisionFilePicker([ImagePicker? imagePicker])
    : _imagePicker = imagePicker ?? ImagePicker();

  final ImagePicker _imagePicker;

  @override
  Future<SupervisionPickedFile?> camera() async {
    final result = await _imagePicker.pickImage(
      source: ImageSource.camera,
      imageQuality: 86,
      maxWidth: 2200,
    );
    if (result == null) return null;
    return SupervisionPickedFile(
      name: result.name,
      bytes: await result.readAsBytes(),
    );
  }

  @override
  Future<SupervisionPickedFile?> file() async {
    final files = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    );
    final selected = files.firstOrNull;
    if (selected == null) return null;
    return SupervisionPickedFile(
      name: selected.name,
      bytes: await selected.readAsBytes(),
    );
  }
}

final examSupervisionFilePickerProvider = Provider<ExamSupervisionFilePicker>(
  (ref) => DeviceExamSupervisionFilePicker(),
);
