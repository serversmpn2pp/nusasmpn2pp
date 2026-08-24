import 'dart:math';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:nusa/core/storage/token_storage.dart';

abstract interface class DeviceIdentity {
  Future<String> readName();
}

final class SecureDeviceIdentity implements DeviceIdentity {
  SecureDeviceIdentity(this._storage);

  static const _deviceNameKey = 'nusa.device_name';

  final FlutterSecureStorage _storage;

  @override
  Future<String> readName() async {
    final savedName = await _storage.read(key: _deviceNameKey);

    if (savedName != null && savedName.isNotEmpty) {
      return savedName;
    }

    final random = Random.secure();
    final suffix = List.generate(
      4,
      (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0'),
    ).join().toUpperCase();
    final name = 'NUSA Android $suffix';

    await _storage.write(key: _deviceNameKey, value: name);

    return name;
  }
}

final deviceIdentityProvider = Provider<DeviceIdentity>((ref) {
  return SecureDeviceIdentity(ref.watch(secureStorageProvider));
});
