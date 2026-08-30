import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/incident_reporting/domain/incident_reporting.dart';

abstract interface class IncidentEvidencePicker {
  Future<List<IncidentEvidenceFile>> pick();
}

final class FilePickerIncidentEvidencePicker implements IncidentEvidencePicker {
  @override
  Future<List<IncidentEvidenceFile>> pick() async {
    final files = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    );
    final picked = <IncidentEvidenceFile>[];
    for (final file in files) {
      picked.add(
        IncidentEvidenceFile(name: file.name, bytes: await file.readAsBytes()),
      );
    }
    return picked;
  }
}

final incidentEvidencePickerProvider = Provider<IncidentEvidencePicker>(
  (ref) => FilePickerIncidentEvidencePicker(),
);
