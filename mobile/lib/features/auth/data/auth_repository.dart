import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/storage/device_identity.dart';
import 'package:nusa/core/storage/token_storage.dart';
import 'package:nusa/features/auth/data/auth_remote_data_source.dart';
import 'package:nusa/features/auth/domain/auth_session.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';

final class AuthRepository {
  AuthRepository(this._remote, this._tokenStorage, this._deviceIdentity);

  final AuthRemoteDataSource _remote;
  final TokenStorage _tokenStorage;
  final DeviceIdentity _deviceIdentity;

  Future<AuthSession?> restoreSession() async {
    final token = await _tokenStorage.read();

    if (token == null || token.isEmpty) {
      return null;
    }

    try {
      final pengguna = await _remote.saya();

      return AuthSession(token: token, pengguna: pengguna);
    } on UnauthorizedException {
      await _tokenStorage.delete();
      return null;
    }
  }

  Future<AuthSession> login({
    required String username,
    required String password,
  }) async {
    final session = await _remote.login(
      username: username,
      password: password,
      deviceName: await _deviceIdentity.readName(),
    );

    await _tokenStorage.write(session.token);

    return session;
  }

  Future<Pengguna> ubahKataSandi({
    required String kataSandiLama,
    required String kataSandiBaru,
    required String konfirmasiKataSandiBaru,
  }) async {
    try {
      return await _remote.ubahKataSandi(
        kataSandiLama: kataSandiLama,
        kataSandiBaru: kataSandiBaru,
        konfirmasiKataSandiBaru: konfirmasiKataSandiBaru,
      );
    } on UnauthorizedException {
      await _tokenStorage.delete();
      rethrow;
    }
  }

  Future<void> logout() async {
    try {
      await _remote.logout();
    } on AppException {
      // Logout lokal harus tetap selesai ketika server tidak dapat dijangkau.
    } finally {
      await _tokenStorage.delete();
    }
  }
}

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    ref.watch(authRemoteDataSourceProvider),
    ref.watch(tokenStorageProvider),
    ref.watch(deviceIdentityProvider),
  );
});
