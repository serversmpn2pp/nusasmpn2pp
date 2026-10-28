import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/auth/presentation/login_view.dart';
import 'package:nusa/shared/widgets/nusa_account_deletion_link.dart';

void main() {
  test('alamat mengikuti origin dan prefix instalasi API', () {
    for (final entry in {
      'https://nusa.smpn2padangpanjang.sch.id/api/v1/':
          'https://nusa.smpn2padangpanjang.sch.id/penghapusan-akun',
      'https://sekolah.example/nusa/api/v1/':
          'https://sekolah.example/nusa/penghapusan-akun',
      'http://10.0.2.2:8000/api/v1/': 'http://10.0.2.2:8000/penghapusan-akun',
    }.entries) {
      final config = AppConfig(
        environment: AppEnvironment.development,
        apiBaseUri: Uri.parse(entry.key),
      );
      expect(config.accountDeletionUri.toString(), entry.value);
    }
  });

  test('alamat publik tidak membawa query fragment atau kredensial', () {
    final config = AppConfig(
      environment: AppEnvironment.production,
      apiBaseUri: Uri.parse(
        'https://user:secret@sekolah.example/api/v1/?token=rahasia#siswa-7',
      ),
    );
    expect(
      config.accountDeletionUri.toString(),
      'https://sekolah.example/penghapusan-akun',
    );
  });

  testWidgets('tautan terbuka tanpa sesi autentikasi', (tester) async {
    Uri? opened;
    await tester.pumpWidget(
      _app((uri) async {
        opened = uri;
        return true;
      }),
    );
    await tester.tap(find.text('Permintaan Penghapusan Akun/Data'));
    await tester.pumpAndSettle();
    expect(opened.toString(), 'https://sekolah.example/penghapusan-akun');
    expect(tester.takeException(), isNull);
  });

  testWidgets('tombol mencegah pembukaan berulang', (tester) async {
    final completion = Completer<bool>();
    var calls = 0;
    await tester.pumpWidget(
      _app((uri) {
        calls++;
        return completion.future;
      }),
    );
    await tester.tap(find.text('Permintaan Penghapusan Akun/Data'));
    await tester.pump();
    expect(
      tester.widget<TextButton>(find.byType(TextButton)).onPressed,
      isNull,
    );
    expect(calls, 1);
    completion.complete(true);
    await tester.pumpAndSettle();
    expect(
      tester.widget<TextButton>(find.byType(TextButton)).onPressed,
      isNotNull,
    );
  });

  for (final throwsError in [false, true]) {
    testWidgets(
      'kegagalan browser ditangani tanpa detail internal ($throwsError)',
      (tester) async {
        await tester.pumpWidget(
          _app((uri) async {
            if (throwsError) throw StateError('rincian internal rahasia');
            return false;
          }),
        );
        await tester.tap(find.text('Permintaan Penghapusan Akun/Data'));
        await tester.pumpAndSettle();
        expect(
          find.text(
            'Halaman permintaan penghapusan belum dapat dibuka. Silakan coba lagi melalui browser Anda.',
          ),
          findsOneWidget,
        );
        expect(find.textContaining('rincian internal'), findsNothing);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets('aman jika widget ditutup ketika browser masih dibuka', (
    tester,
  ) async {
    final completion = Completer<bool>();
    await tester.pumpWidget(_app((uri) => completion.future));
    await tester.tap(find.text('Permintaan Penghapusan Akun/Data'));
    await tester.pumpWidget(const SizedBox());
    completion.complete(false);
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('tautan tidak overflow pada layar kecil dan teks besar', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 568));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(_app((uri) async => true, textScale: 2));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('tautan tersedia di login tanpa masuk pada layar kecil', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 568));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    Uri? opened;
    await tester.pumpWidget(
      _app(
        (uri) async {
          opened = uri;
          return true;
        },
        home: const LoginView(),
        textScale: 1.5,
      ),
    );
    await tester.pumpAndSettle();
    final link = find.byKey(const Key('login-account-deletion'));
    await tester.ensureVisible(link);
    await tester.pumpAndSettle();
    await tester.tap(link);
    await tester.pumpAndSettle();
    expect(opened.toString(), 'https://sekolah.example/penghapusan-akun');
    expect(tester.takeException(), isNull);
  });
}

Widget _app(
  AccountDeletionOpener opener, {
  double textScale = 1,
  Widget? home,
}) {
  return ProviderScope(
    overrides: [
      appConfigProvider.overrideWithValue(
        AppConfig(
          environment: AppEnvironment.production,
          apiBaseUri: Uri.parse('https://sekolah.example/api/v1/'),
        ),
      ),
      accountDeletionOpenerProvider.overrideWithValue(opener),
      authControllerProvider.overrideWith(_LoggedOutAuthController.new),
    ],
    child: MaterialApp(
      theme: AppTheme.light,
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context)
            .copyWith(textScaler: TextScaler.linear(textScale)),
        child: child!,
      ),
      home:
          home ??
          const Scaffold(
            body: Padding(
              padding: EdgeInsets.all(24),
              child: NusaAccountDeletionLink(),
            ),
          ),
    ),
  );
}

class _LoggedOutAuthController extends AuthController {
  @override
  Future<AuthState> build() async => const AuthState();
}
