import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/shared/widgets/nusa_privacy_policy_link.dart';

void main() {
  test('alamat privasi mengikuti server produksi dan instalasi subfolder', () {
    for (final entry in {
      'https://nusa.smpn2padangpanjang.sch.id/api/v1/':
          'https://nusa.smpn2padangpanjang.sch.id/kebijakan-privasi',
      'https://sekolah.example/nusa/api/v1/':
          'https://sekolah.example/nusa/kebijakan-privasi',
      'http://10.0.2.2:8000/api/v1/': 'http://10.0.2.2:8000/kebijakan-privasi',
    }.entries) {
      final config = AppConfig(
        environment: AppEnvironment.development,
        apiBaseUri: Uri.parse(entry.key),
      );
      expect(config.privacyPolicyUri.toString(), entry.value);
    }
  });

  test('alamat publik tidak membawa query, fragment, atau kredensial API', () {
    final config = AppConfig(
      environment: AppEnvironment.development,
      apiBaseUri: Uri.parse(
        'https://user:secret@sekolah.example/api/v1/?token=rahasia#siswa-7',
      ),
    );
    expect(
      config.privacyPolicyUri.toString(),
      'https://sekolah.example/kebijakan-privasi',
    );
  });

  testWidgets('tautan dapat dibuka tanpa sesi autentikasi', (tester) async {
    Uri? opened;
    await tester.pumpWidget(
      _app((uri) async {
        opened = uri;
        return true;
      }),
    );
    await tester.tap(find.text('Kebijakan Privasi'));
    await tester.pumpAndSettle();

    expect(opened.toString(), 'https://sekolah.example/kebijakan-privasi');
    expect(tester.takeException(), isNull);
  });

  testWidgets('tombol mencegah pembukaan berulang saat masih diproses', (
    tester,
  ) async {
    final completion = Completer<bool>();
    var calls = 0;
    await tester.pumpWidget(
      _app((uri) {
        calls++;
        return completion.future;
      }),
    );
    await tester.tap(find.text('Kebijakan Privasi'));
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
    testWidgets('kegagalan browser memberi pesan umum ($throwsError)', (
      tester,
    ) async {
      await tester.pumpWidget(
        _app((uri) async {
          if (throwsError) throw StateError('rincian internal');
          return false;
        }),
      );
      await tester.tap(find.text('Kebijakan Privasi'));
      await tester.pumpAndSettle();
      expect(
        find.text(
          'Halaman kebijakan privasi belum dapat dibuka. Silakan coba lagi melalui browser Anda.',
        ),
        findsOneWidget,
      );
      expect(find.textContaining('rincian internal'), findsNothing);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('tautan tidak overflow pada layar kecil dan teks besar', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 568));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(_app((uri) async => true, textScale: 2));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });
}

Widget _app(PrivacyPolicyOpener opener, {double textScale = 1}) {
  return ProviderScope(
    overrides: [
      appConfigProvider.overrideWithValue(
        AppConfig(
          environment: AppEnvironment.production,
          apiBaseUri: Uri.parse('https://sekolah.example/api/v1/'),
        ),
      ),
      privacyPolicyOpenerProvider.overrideWithValue(opener),
    ],
    child: MaterialApp(
      theme: AppTheme.light,
      home: MediaQuery(
        data: MediaQueryData(textScaler: TextScaler.linear(textScale)),
        child: const Scaffold(
          body: Padding(
            padding: EdgeInsets.all(24),
            child: NusaPrivacyPolicyLink(),
          ),
        ),
      ),
    ),
  );
}
