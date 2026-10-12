import 'package:file_picker/file_picker.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/persuratan/domain/persuratan.dart';

abstract interface class SuratFilePicker {
  Future<SuratUpload?> pick(SuratFileSource source, {required bool primary});
}

class DeviceSuratFilePicker implements SuratFilePicker {
  DeviceSuratFilePicker([ImagePicker? picker])
    : _picker = picker ?? ImagePicker();
  final ImagePicker _picker;
  @override
  Future<SuratUpload?> pick(
    SuratFileSource source, {
    required bool primary,
  }) async {
    try {
      if (source == SuratFileSource.file) {
        final result = await FilePicker.pickFiles(
          type: FileType.custom,
          allowedExtensions: primary
              ? ['pdf', 'jpg', 'jpeg', 'png']
              : [
                  'pdf',
                  'jpg',
                  'jpeg',
                  'png',
                  'doc',
                  'docx',
                  'xls',
                  'xlsx',
                  'ppt',
                  'pptx',
                ],
        );
        if (result.isEmpty) return null;
        final f = result.first;
        if (await f.length() > SuratUpload.maxBytes) {
          throw const ValidationException('Berkas maksimal 10 MB.');
        }
        final ext = f.name.split('.').last.toLowerCase();
        final allowed = [
          'pdf',
          'jpg',
          'jpeg',
          'png',
          if (!primary) ...['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        ];
        if (!allowed.contains(ext)) {
          throw const ValidationException('Format berkas tidak didukung.');
        }
        return validateSuratUpload(
          SuratUpload(
            name: safeSuratFileName(f.name),
            bytes: await f.readAsBytes(),
            image: ['jpg', 'jpeg', 'png'].contains(ext),
          ),
        );
      }
      XFile? f;
      if (source == SuratFileSource.camera) {
        // Android can kill the activity while its camera app is open. Recovery still requires the user's preview confirmation.
        final lost = await _picker.retrieveLostData();
        if (lost.exception != null) throw lost.exception!;
        f = lost.files?.firstOrNull;
      }
      f ??= await _picker.pickImage(
        source: source == SuratFileSource.camera
            ? ImageSource.camera
            : ImageSource.gallery,
        maxWidth: 2400,
        maxHeight: 3200,
        imageQuality: 90,
        requestFullMetadata: false,
      );
      if (f == null) return null;
      if (await f.length() > SuratUpload.maxBytes) {
        throw const ValidationException(
          'Foto maksimal 10 MB. Ambil ulang atau pilih foto lain.',
        );
      }
      final bytes = await f.readAsBytes();
      final png =
          bytes.length >= 8 &&
          bytes[0] == 0x89 &&
          bytes[1] == 0x50 &&
          bytes[2] == 0x4e &&
          bytes[3] == 0x47;
      final jpg =
          bytes.length >= 3 &&
          bytes[0] == 0xff &&
          bytes[1] == 0xd8 &&
          bytes[2] == 0xff;
      if (!png && !jpg) {
        throw const ValidationException(
          'Gunakan foto JPG atau PNG. Foto ini belum dapat diunggah.',
        );
      }
      return validateSuratUpload(
        SuratUpload(
          name:
              'foto-surat-${DateTime.now().millisecondsSinceEpoch}.${png ? 'png' : 'jpg'}',
          bytes: bytes,
          image: true,
        ),
      );
    } on PlatformException catch (e) {
      throw ValidationException(
        e.code.toLowerCase().contains('denied') ||
                e.code.toLowerCase().contains('restricted')
            ? 'Izin kamera atau foto belum diberikan. Izinkan melalui pengaturan HP atau pilih berkas.'
            : 'Kamera atau pemilih berkas belum dapat dibuka. Coba lagi atau pilih sumber lain.',
      );
    }
  }
}

SuratUpload validateSuratUpload(SuratUpload f) {
  if (f.bytes.isEmpty) {
    throw const ValidationException('Berkas kosong tidak dapat diunggah.');
  }
  if (f.bytes.length > SuratUpload.maxBytes) {
    throw const ValidationException('Berkas maksimal 10 MB.');
  }
  return f;
}

String safeSuratFileName(String v) {
  final name = v.replaceAll(RegExp(r'[\\/:*?"<>|\x00-\x1F]'), '_');
  return name.isEmpty ? 'berkas.pdf' : name;
}

final suratFilePickerProvider = Provider<SuratFilePicker>(
  (ref) => DeviceSuratFilePicker(),
);
