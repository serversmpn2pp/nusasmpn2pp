import 'package:file_picker/file_picker.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/humas/domain/humas.dart';

enum HumasPhotoSource { camera, gallery, files }

abstract interface class HumasPhotoPicker {
  Future<List<HumasPickedFile>> pick(HumasPhotoSource source);
}

class DeviceHumasPhotoPicker implements HumasPhotoPicker {
  DeviceHumasPhotoPicker([ImagePicker? picker])
    : _picker = picker ?? ImagePicker();
  final ImagePicker _picker;

  @override
  Future<List<HumasPickedFile>> pick(HumasPhotoSource source) async {
    try {
      final result = <HumasPickedFile>[];
      Future<void> add(int length, Future<Uint8List> Function() read) async {
        if (length > 2 * 1024 * 1024) {
          throw const ValidationException('Foto maksimal 2 MB per berkas.');
        }
        final bytes = await read();
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
        final webp =
            bytes.length >= 12 &&
            String.fromCharCodes(bytes.take(4)) == 'RIFF' &&
            String.fromCharCodes(bytes.skip(8).take(4)) == 'WEBP';
        if (!png && !jpg && !webp) {
          throw const ValidationException('Pilih foto JPG, PNG, atau WebP.');
        }
        result.add(
          HumasPickedFile(
            'foto-publikasi-${DateTime.now().microsecondsSinceEpoch}-${result.length}.${png
                ? 'png'
                : webp
                ? 'webp'
                : 'jpg'}',
            bytes,
          ),
        );
      }

      if (source == HumasPhotoSource.files) {
        final files = await FilePicker.pickFiles(
          type: FileType.custom,
          allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
        );
        if (files.length > 5) {
          throw const ValidationException(
            'Maksimal 5 foto aktif per publikasi.',
          );
        }
        for (final file in files) {
          await add(await file.length(), file.readAsBytes);
        }
      } else {
        final image = await _picker.pickImage(
          source: source == HumasPhotoSource.camera
              ? ImageSource.camera
              : ImageSource.gallery,
          maxWidth: 2400,
          maxHeight: 2400,
          imageQuality: 85,
          requestFullMetadata: false,
        );
        if (image != null) await add(await image.length(), image.readAsBytes);
      }
      return result;
    } on PlatformException catch (error) {
      throw NetworkException(
        error.code.toLowerCase().contains('access') ||
                error.code.toLowerCase().contains('permission')
            ? 'Izin kamera atau foto belum diberikan. Periksa pengaturan aplikasi.'
            : 'Foto belum dapat dipilih. Silakan coba lagi.',
      );
    }
  }
}

final humasPhotoPickerProvider = Provider<HumasPhotoPicker>(
  (ref) => DeviceHumasPhotoPicker(),
);
