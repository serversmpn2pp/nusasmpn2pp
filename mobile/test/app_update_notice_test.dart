import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/app_update/data/app_update_gateway.dart';
import 'package:nusa/features/app_update/presentation/app_update_notice.dart';
import 'package:nusa/features/student_exam/application/exam_navigation_guard.dart';

void main() {
  testWidgets(
    'pemberitahuan tampil saat Play menyediakan versi baru dan tombol membuka toko',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final gateway = _Gateway(10);
      await _pump(tester, gateway);
      expect(find.byKey(const Key('nusa-app-update-notice')), findsOneWidget);
      await tester.tap(find.text('Perbarui'));
      await tester.pumpAndSettle();
      expect(gateway.opened, 1);
      await tester.tap(find.text('Nanti'));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('nusa-app-update-notice')), findsNothing);
      expect(find.text('Beranda uji'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'tidak ada pemberitahuan sebelum update tersedia dan pemeriksaan dibatasi',
    (tester) async {
      final gateway = _Gateway(null);
      await _pump(tester, gateway);
      expect(find.byKey(const Key('nusa-app-update-notice')), findsNothing);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pumpAndSettle();
      expect(gateway.checked, 1);
    },
  );

  testWidgets(
    'ujian aktif tidak memicu pemeriksaan atau pemberitahuan pembaruan',
    (tester) async {
      final gateway = _Gateway(10);
      final guard = ExamNavigationGuard()..protect(31, Object());
      await _pump(tester, gateway, guard: guard);
      expect(gateway.checked, 0);
      expect(gateway.opened, 0);
      expect(find.byKey(const Key('nusa-app-update-notice')), findsNothing);
    },
  );

  testWidgets('gateway memakai hasil Play dan mengabaikan kegagalan layanan', (
    tester,
  ) async {
    final messenger =
        TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    final calls = <String>[];
    messenger.setMockMethodCallHandler(PlayAppUpdateGateway.channel, (
      call,
    ) async {
      calls.add(call.method);
      return call.method == 'checkForUpdate' ? 10 : true;
    });
    addTearDown(
      () => messenger.setMockMethodCallHandler(
        PlayAppUpdateGateway.channel,
        null,
      ),
    );
    final gateway = PlayAppUpdateGateway();
    expect(await gateway.availableVersion(), 10);
    expect(await gateway.openStore(), isTrue);
    expect(calls, ['checkForUpdate', 'openPlayStore']);
    messenger.setMockMethodCallHandler(
      PlayAppUpdateGateway.channel,
      (_) async => throw PlatformException(code: 'UNAVAILABLE'),
    );
    expect(await gateway.availableVersion(), isNull);
    expect(await gateway.openStore(), isFalse);
  });
}

Future<void> _pump(
  WidgetTester tester,
  _Gateway gateway, {
  ExamNavigationGuard? guard,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appUpdateGatewayProvider.overrideWithValue(gateway),
        if (guard != null) examNavigationGuardProvider.overrideWithValue(guard),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const Scaffold(
          body: AppUpdateNotice(child: Center(child: Text('Beranda uji'))),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _Gateway implements AppUpdateGateway {
  _Gateway(this.version);
  final int? version;
  int checked = 0;
  int opened = 0;
  @override
  Future<int?> availableVersion() async {
    checked++;
    return version;
  }

  @override
  Future<bool> openStore() async {
    opened++;
    return true;
  }
}
