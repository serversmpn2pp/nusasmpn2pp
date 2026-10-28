import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/auth/presentation/login_view.dart';
import 'package:nusa/features/home/presentation/widgets/home_components.dart';
import 'package:nusa/shared/widgets/nusa_illustrations.dart';
import 'package:nusa/shared/widgets/nusa_school_identity.dart';

class _PhotoAuth extends AuthController {
  int calls = 0;
  String? username;

  @override
  Future<AuthState> build() async => const AuthState();

  @override
  Future<void> login({
    required String username,
    required String password,
  }) async {
    calls++;
    this.username = username;
    state = const AsyncData(
      AuthState(errorMessage: 'Akun contoh tidak ditemukan.'),
    );
  }
}

void _screen(WidgetTester tester, Size size, {double keyboard = 0}) {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  tester.view.padding = const FakeViewPadding(top: 24, bottom: 24);
  tester.view.viewInsets = FakeViewPadding(bottom: keyboard);
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  addTearDown(tester.view.resetPadding);
  addTearDown(tester.view.resetViewInsets);
}

Future<void> _login(
  WidgetTester tester,
  _PhotoAuth auth, {
  double scale = 1,
  GlobalKey? capture,
}) async {
  await tester.pumpWidget(
    RepaintBoundary(
      key: capture,
      child: ProviderScope(
        overrides: [authControllerProvider.overrideWith(() => auth)],
        child: MaterialApp(
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light,
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: TextScaler.linear(scale)),
            child: child!,
          ),
          home: const LoginView(),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
  await tester.runAsync(() async {
    final context = tester.element(find.byType(LoginView));
    await Future.wait([
      precacheImage(const AssetImage(NusaSchoolPhoto.assetPath), context),
      precacheImage(const AssetImage(NusaSchoolPhoto.loginAssetPath), context),
      precacheImage(const AssetImage('assets/images/logo-nusa.png'), context),
    ]);
  });
  await tester.pumpAndSettle();
}

void main() {
  setUpAll(() async {
    await (FontLoader('Roboto')
          ..addFont(rootBundle.load('assets/fonts/roboto-regular.ttf'))
          ..addFont(rootBundle.load('assets/fonts/roboto-bold.ttf')))
        .load();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
  });

  for (final asset in [
    NusaSchoolPhoto.assetPath,
    NusaSchoolPhoto.loginAssetPath,
  ]) {
    testWidgets('foto sekolah $asset dapat didekode tanpa jaringan', (
      tester,
    ) async {
      await tester.runAsync(() async {
        final data = await rootBundle.load(asset);
        final codec = await ui.instantiateImageCodec(data.buffer.asUint8List());
        final frame = await codec.getNextFrame();
        expect(frame.image.width, greaterThan(0));
        expect(frame.image.height, greaterThan(0));
        frame.image.dispose();
        codec.dispose();
      });
    });
  }

  for (final (size, scale, keyboard) in [
    (const Size(320, 568), 1.0, 0.0),
    (const Size(390, 844), 2.0, 0.0),
    (const Size(844, 390), 1.0, 0.0),
    (const Size(320, 568), 1.0, 280.0),
    (const Size(390, 844), 1.0, 330.0),
  ]) {
    testWidgets(
      'login foto tetap dapat digunakan pada $size skala $scale keyboard $keyboard',
      (tester) async {
        _screen(tester, size, keyboard: keyboard);
        final auth = _PhotoAuth();
        await _login(tester, auth, scale: scale);
        expect(find.byType(NusaSchoolPhoto), findsNWidgets(2));
        expect(find.byType(NusaSchoolIllustration), findsNothing);
        expect(find.byKey(const Key('login-privacy-policy')), findsOneWidget);
        expect(find.byKey(const Key('login-account-deletion')), findsOneWidget);
        final username = find.byKey(const Key('login-username'));
        final password = find.byKey(const Key('login-password'));
        await tester.ensureVisible(username);
        await tester.enterText(username, 'akun.contoh');
        await tester.pumpAndSettle();
        await tester.ensureVisible(password);
        await tester.enterText(password, 'Contoh#123');
        await tester.pumpAndSettle();
        final submit = find.byKey(const Key('login-submit'));
        await tester.ensureVisible(submit);
        await tester.pumpAndSettle();
        await tester.tap(submit);
        await tester.pumpAndSettle();
        expect(auth.calls, 1);
        expect(auth.username, 'akun.contoh');
        expect(find.text('Akun contoh tidak ditemukan.'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'judul beranda menyesuaikan nama panjang dan huruf besar tanpa memotongnya',
    (tester) async {
      _screen(tester, const Size(320, 568));
      const name = 'Pengguna Sekolah Dengan Nama Panjang Sekali';
      var notificationTaps = 0;
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.light,
          home: Scaffold(
            body: MediaQuery(
              data: const MediaQueryData(textScaler: TextScaler.linear(2)),
              child: SingleChildScrollView(
                child: HomeSchoolHero(
                  unreadCount: 1,
                  onNotifications: () => notificationTaps++,
                  greeting: 'Selamat pagi',
                  name: name,
                  dayName: 'Selasa',
                  dateLabel: '27 Oktober 2026',
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      final label = tester.widget<Text>(find.text(name));
      expect(label.maxLines, isNull);
      expect(label.overflow, isNot(TextOverflow.ellipsis));
      await tester.tap(find.byTooltip('Notifikasi'));
      await tester.pump();
      expect(notificationTaps, 1);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('render login dengan foto sekolah dan formulir produksi', (
    tester,
  ) async {
    _screen(tester, const Size(390, 844));
    final capture = GlobalKey();
    await _login(tester, _PhotoAuth(), capture: capture);
    expect(tester.takeException(), isNull);
    await tester.runAsync(() async {
      final boundary =
          capture.currentContext!.findRenderObject()! as RenderRepaintBoundary;
      final picture = await boundary.toImage(pixelRatio: 2);
      final bytes = await picture.toByteData(format: ui.ImageByteFormat.png);
      await File('build/login-school-photo.png')
          .writeAsBytes(bytes!.buffer.asUint8List());
      picture.dispose();
    });
  });
}
