import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/profile/domain/my_profile.dart';

abstract interface class MyProfilePhotoPicker {
  Future<MyProfilePhotoFile?> pick(MyProfilePhotoSource source);
}

final class DeviceMyProfilePhotoPicker implements MyProfilePhotoPicker {
  DeviceMyProfilePhotoPicker([ImagePicker? picker])
    : _picker = picker ?? ImagePicker();

  static const _maxBytes = 1536 * 1024;
  final ImagePicker _picker;

  @override
  Future<MyProfilePhotoFile?> pick(MyProfilePhotoSource source) async {
    final lostData = await _picker.retrieveLostData();
    XFile? selected;
    if (!lostData.isEmpty && lostData.files?.isNotEmpty == true) {
      selected = lostData.files!.first;
    } else {
      selected = await _picker.pickImage(
        source: source == MyProfilePhotoSource.camera
            ? ImageSource.camera
            : ImageSource.gallery,
        maxWidth: 1200,
        maxHeight: 1600,
        imageQuality: 82,
        requestFullMetadata: false,
      );
    }
    if (selected == null) return null;

    final bytes = await selected.readAsBytes();
    if (bytes.length > _maxBytes) {
      throw const ValidationException(
        'Foto setelah diproses masih lebih dari 1,5 MB. Pilih foto lain atau gunakan kamera dengan resolusi lebih rendah.',
      );
    }

    return MyProfilePhotoFile(name: _fileName(selected.name), bytes: bytes);
  }
}

String _fileName(String value) {
  final base = value.trim().isEmpty ? 'foto-profil' : value.trim();
  final lower = base.toLowerCase();
  if (lower.endsWith('.jpg') ||
      lower.endsWith('.jpeg') ||
      lower.endsWith('.png') ||
      lower.endsWith('.webp')) {
    return base;
  }
  return '$base.jpg';
}

final myProfilePhotoPickerProvider = Provider<MyProfilePhotoPicker>(
  (ref) => DeviceMyProfilePhotoPicker(),
);
