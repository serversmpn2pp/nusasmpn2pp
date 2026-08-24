import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/app/app.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:nusa/core/storage/device_identity.dart';
import 'package:nusa/core/storage/token_storage.dart';
import 'package:nusa/features/auth/data/auth_remote_data_source.dart';
import 'package:nusa/features/auth/domain/auth_session.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';

void main() {
  testWidgets('menampilkan formulir login NUSA saat belum memiliki sesi', (
    tester,
  ) async {
    await _pumpApp(tester, remote: _FakeAuthRemoteDataSource());

    expect(find.text('NUSA'), findsOneWidget);
    expect(find.text('SMP Negeri 2 Padang Panjang'), findsOneWidget);
    expect(find.byKey(const Key('login-username')), findsOneWidget);
    expect(find.byKey(const Key('login-password')), findsOneWidget);
    expect(find.text('Masuk'), findsOneWidget);
  });

  testWidgets('login berhasil menyimpan token dan membuka beranda', (
    tester,
  ) async {
    final storage = _MemoryTokenStorage();
    await _pumpApp(
      tester,
      remote: _FakeAuthRemoteDataSource(),
      storage: storage,
    );

    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.ensureVisible(find.byKey(const Key('login-submit')));
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(find.text('Selamat datang,'), findsOneWidget);
    expect(find.text('Pengguna Mobile Uji'), findsOneWidget);
    expect(storage.token, 'token-uji');
  });

  testWidgets('akun dengan kata sandi awal diarahkan untuk menggantinya', (
    tester,
  ) async {
    await _pumpApp(
      tester,
      remote: _FakeAuthRemoteDataSource(wajibGantiKataSandi: true),
    );

    await tester.enterText(
      find.byKey(const Key('login-username')),
      'mobile.uji',
    );
    await tester.enterText(
      find.byKey(const Key('login-password')),
      'RahasiaNusa123',
    );
    await tester.ensureVisible(find.byKey(const Key('login-submit')));
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(find.text('Amankan akun Anda'), findsOneWidget);
    expect(find.text('Simpan kata sandi'), findsOneWidget);
  });
}

Future<void> _pumpApp(
  WidgetTester tester, {
  required AuthRemoteDataSource remote,
  _MemoryTokenStorage? storage,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(
          AppConfig(
            environment: AppEnvironment.development,
            apiBaseUri: Uri.parse('http://10.0.2.2:8000/api/v1/'),
          ),
        ),
        tokenStorageProvider.overrideWithValue(
          storage ?? _MemoryTokenStorage(),
        ),
        deviceIdentityProvider.overrideWithValue(_FakeDeviceIdentity()),
        authRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: const NusaApp(),
    ),
  );

  await tester.pumpAndSettle();
}

final class _MemoryTokenStorage implements TokenStorage {
  String? token;

  @override
  Future<void> delete() async => token = null;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(String token) async => this.token = token;
}

final class _FakeDeviceIdentity implements DeviceIdentity {
  @override
  Future<String> readName() async => 'NUSA Android TEST';
}

final class _FakeAuthRemoteDataSource implements AuthRemoteDataSource {
  _FakeAuthRemoteDataSource({this.wajibGantiKataSandi = false});

  final bool wajibGantiKataSandi;

  Pengguna get _pengguna => Pengguna(
    id: 2,
    nama: 'Pengguna Mobile Uji',
    username: 'mobile.uji',
    jenisAkun: 'Pegawai',
    administrator: false,
    wajibGantiKataSandi: wajibGantiKataSandi,
    peran: const ['pegawai'],
    izin: const ['beranda.akses'],
  );

  @override
  Future<AuthSession> login({
    required String username,
    required String password,
    required String deviceName,
  }) async {
    return AuthSession(token: 'token-uji', pengguna: _pengguna);
  }

  @override
  Future<void> logout() async {}

  @override
  Future<Pengguna> saya() async => _pengguna;

  @override
  Future<Pengguna> ubahKataSandi({
    required String kataSandiLama,
    required String kataSandiBaru,
    required String konfirmasiKataSandiBaru,
  }) async {
    return Pengguna(
      id: _pengguna.id,
      nama: _pengguna.nama,
      username: _pengguna.username,
      jenisAkun: _pengguna.jenisAkun,
      administrator: _pengguna.administrator,
      wajibGantiKataSandi: false,
      peran: _pengguna.peran,
      izin: _pengguna.izin,
    );
  }
}
