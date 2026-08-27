import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/identity_photo/domain/identity_photo.dart';

abstract interface class IdentityPhotoPicker {
  Future<IdentityPhotoPickedFile?> pick(IdentityPhotoSource source);
}

final class DeviceIdentityPhotoPicker implements IdentityPhotoPicker {
  DeviceIdentityPhotoPicker([ImagePicker? picker])
    : _picker = picker ?? ImagePicker();

  static const _maxBytes = 1536 * 1024;
  final ImagePicker _picker;

  @override
  Future<IdentityPhotoPickedFile?> pick(IdentityPhotoSource source) async {
    final lostData = await _picker.retrieveLostData();
    XFile? file;
    if (!lostData.isEmpty && lostData.files?.isNotEmpty == true) {
      file = lostData.files!.first;
    } else {
      file = await _picker.pickImage(
        source: source == IdentityPhotoSource.camera
            ? ImageSource.camera
            : ImageSource.gallery,
        maxWidth: 1200,
        maxHeight: 1600,
        imageQuality: 82,
        requestFullMetadata: false,
      );
    }
    if (file == null) return null;
    final bytes = await file.readAsBytes();
    if (bytes.length > _maxBytes) {
      throw const ValidationException(
        'Foto setelah diproses masih lebih dari 1,5 MB. Pilih foto lain atau gunakan kamera dengan resolusi lebih rendah.',
      );
    }

    return IdentityPhotoPickedFile(name: _fileName(file.name), bytes: bytes);
  }
}

String _fileName(String value) {
  final base = value.trim().isEmpty ? 'foto-identitas' : value.trim();
  final lower = base.toLowerCase();
  if (lower.endsWith('.jpg') ||
      lower.endsWith('.jpeg') ||
      lower.endsWith('.png') ||
      lower.endsWith('.webp')) {
    return base;
  }
  return '$base.jpg';
}

final identityPhotoPickerProvider = Provider<IdentityPhotoPicker>(
  (ref) => DeviceIdentityPhotoPicker(),
);
