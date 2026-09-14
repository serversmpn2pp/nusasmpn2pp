import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';

abstract interface class StudentExamFilePicker {
  Future<StudentExamPickedFile?> pick();
}

class DeviceStudentExamFilePicker implements StudentExamFilePicker {
  @override
  Future<StudentExamPickedFile?> pick() async {
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
      ],
    );
    final selected = result.firstOrNull;
    if (selected == null) return null;

    return StudentExamPickedFile(
      name: selected.name,
      bytes: await selected.readAsBytes(),
    );
  }
}

final studentExamFilePickerProvider = Provider<StudentExamFilePicker>(
  (ref) => DeviceStudentExamFilePicker(),
);
