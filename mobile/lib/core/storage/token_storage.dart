import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

abstract interface class TokenStorage {
  Future<String?> read();

  Future<void> write(String token);

  Future<void> delete();
}

final secureStorageProvider = Provider<FlutterSecureStorage>((ref) {
  return const FlutterSecureStorage();
});

final class SecureTokenStorage implements TokenStorage {
  SecureTokenStorage(this._storage);

  static const _tokenKey = 'nusa.auth_token';

  final FlutterSecureStorage _storage;

  @override
  Future<String?> read() => _storage.read(key: _tokenKey);

  @override
  Future<void> write(String token) {
    return _storage.write(key: _tokenKey, value: token);
  }

  @override
  Future<void> delete() => _storage.delete(key: _tokenKey);
}

final tokenStorageProvider = Provider<TokenStorage>((ref) {
  return SecureTokenStorage(ref.watch(secureStorageProvider));
});
