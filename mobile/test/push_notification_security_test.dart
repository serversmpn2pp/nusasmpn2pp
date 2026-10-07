import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/app/app.dart';
import 'package:nusa/app/app_keys.dart';
import 'package:nusa/app/router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/security/password_change_gate.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/auth/domain/auth_session.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';
import 'package:nusa/features/push_notifications/application/push_notification_coordinator.dart';
import 'package:nusa/features/push_notifications/application/push_notification_runtime.dart';
import 'package:nusa/features/push_notifications/data/push_notification_remote_data_source.dart';

Future<void> flushPush() async {
  for (var i = 0; i < 6; i++) {
    await Future<void>.delayed(Duration.zero);
  }
}

RemoteMessage push(String id) => RemoteMessage(
  data: {'notifikasi_id': id, 'tujuan': '/role-hak-akses'},
  notification: const RemoteNotification(
    title: 'Sanksi Nadia 125 poin',
    body: 'Catatan privat lama',
  ),
);

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  late FakePushRuntime runtime;
  late FakePushRemote remote;
  late PushNotificationCoordinator coordinator;

  setUp(() {
    runtime = FakePushRuntime();
    remote = FakePushRemote();
    coordinator = PushNotificationCoordinator(runtime, remote);
  });
  tearDown(() async {
    await coordinator.dispose();
    await runtime.close();
  });
  Future<void> login(int id) async {
    remote.userId = id;
    await coordinator.authenticated(id);
  }

  test(
    'foreground di layar login tidak memeriksa/menampilkan pesan privat',
    () async {
      await coordinator.start();
      runtime.foreground.add(push('10'));
      await flushPush();
      expect(remote.inspected, isEmpty);
      expect(runtime.shown, 0);
      expect(runtime.destinations, isEmpty);
    },
  );

  test('foreground memverifikasi pemilik tanpa menandai baca; klik memakai rute server', () async {
    await login(1);
    runtime.foreground.add(push('10'));
    await flushPush();
    expect(remote.inspected, [10]);
    expect(remote.read, isEmpty);
    expect(runtime.shown, 1);
    expect(runtime.lastTarget!.title, 'Judul asli dari API');
    expect(runtime.lastTarget!.body, 'Detail terverifikasi dari API');
    expect(runtime.lastTarget!.body, isNot('Catatan privat lama'));
    await runtime.action!();
    expect(remote.read, [10]);
    expect(runtime.destinations, ['/nilai-saya']);
    expect(runtime.destinations, isNot(contains('/role-hak-akses')));
  });

  test('pesan akun lain ditolak pada foreground dan ketukan', () async {
    await login(2);
    runtime.foreground.add(push('10'));
    runtime.opened.add(push('10'));
    await flushPush();
    expect(runtime.shown, 0);
    expect(remote.read, isEmpty);
    expect(runtime.destinations, isEmpty);
  });

  test(
    'cold start akun yang sama membuka tujuan setelah autentikasi',
    () async {
      runtime.initial = push('10');
      await coordinator.start();
      expect(remote.inspected, isEmpty);
      await login(1);
      expect(remote.read, [10]);
      expect(runtime.destinations, ['/nilai-saya']);
    },
  );

  test('cold start pesan A tidak dibuka setelah login B', () async {
    runtime.initial = push('10');
    await coordinator.start();
    await login(2);
    expect(remote.inspected, [10]);
    expect(remote.read, isEmpty);
    expect(runtime.destinations, isEmpty);
  });

  test(
    'logout membersihkan pending, SnackBar, dan notifikasi sistem',
    () async {
      await coordinator.start();
      runtime.opened.add(push('10'));
      coordinator.loggedOut();
      await login(2);
      expect(remote.inspected, isEmpty);
      expect(runtime.hidden, 1);
      expect(runtime.cleared, 1);
      expect(runtime.destinations, isEmpty);
    },
  );

  test(
    'logout ketika verifikasi berjalan membuang hasil yang terlambat',
    () async {
      await login(1);
      remote.inspectGate = Completer<void>();
      runtime.foreground.add(push('10'));
      coordinator.loggedOut();
      remote.inspectGate!.complete();
      await flushPush();
      expect(runtime.shown, 0);
      expect(runtime.refreshes, 0);
    },
  );

  test(
    'pergantian A ke B ketika verifikasi berjalan tidak membuka hasil A',
    () async {
      await login(1);
      remote.inspectGate = Completer<void>();
      runtime.opened.add(push('10'));
      await login(2);
      remote.inspectGate!.complete();
      await flushPush();
      expect(remote.read, isEmpty);
      expect(runtime.destinations, isEmpty);
      expect(runtime.cleared, 1);
    },
  );

  test(
    'pergantian akun ketika penandaan baca berjalan tidak menavigasi sesi baru',
    () async {
      await login(1);
      remote.readGate = Completer<void>();
      runtime.opened.add(push('10'));
      await flushPush();
      await login(2);
      remote.readGate!.complete();
      await flushPush();
      expect(runtime.destinations, isEmpty);
      expect(runtime.refreshes, 0);
    },
  );

  test(
    'action lama yang tertahan tidak berlaku sesudah akun berganti',
    () async {
      await login(1);
      runtime.foreground.add(push('10'));
      await flushPush();
      final oldAction = runtime.action!;
      await login(2);
      await oldAction();
      expect(remote.inspected, [10]);
      expect(remote.read, isEmpty);
      expect(runtime.destinations, isEmpty);
    },
  );

  for (final status in [401, 403, 404, 428, 500]) {
    test(
      'verifikasi ditolak $status tidak menampilkan atau membuka payload',
      () async {
        await login(1);
        remote.inspectError = NetworkException('Ditolak', statusCode: status);
        runtime.foreground.add(push('10'));
        runtime.opened.add(push('10'));
        await flushPush();
        expect(runtime.shown, 0);
        expect(remote.read, isEmpty);
        expect(runtime.destinations, isEmpty);
      },
    );
  }

  for (final status in [401, 403, 428]) {
    test(
      'penandaan baca ditolak $status tidak diteruskan ke navigasi',
      () async {
        await login(1);
        remote.readError = NetworkException('Ditolak', statusCode: status);
        runtime.opened.add(push('10'));
        await flushPush();
        expect(runtime.destinations, isEmpty);
        expect(runtime.refreshes, 0);
      },
    );
  }

  test(
    'payload tanpa ID/ID rusak tidak memanggil API atau mengikuti tujuan',
    () async {
      await login(1);
      for (final id in ['', 'invalid', '-1', '0']) {
        runtime.opened.add(push(id));
        runtime.foreground.add(push(id));
      }
      await flushPush();
      expect(remote.inspected, isEmpty);
      expect(runtime.shown, 0);
      expect(runtime.destinations, isEmpty);
    },
  );

  test('respons API dengan ID berbeda ditolak', () async {
    await login(1);
    remote.responseId = 20;
    runtime.opened.add(push('10'));
    await flushPush();
    expect(remote.read, isEmpty);
    expect(runtime.destinations, isEmpty);
  });

  for (final destination in [
    null,
    'https://example.com',
    '//example.com',
    '/\\example.com',
  ]) {
    test('tujuan API tidak aman $destination kembali ke inbox', () async {
      await login(1);
      remote.destination = destination;
      runtime.opened.add(push('10'));
      await flushPush();
      expect(runtime.destinations, ['/beranda?tab=notifikasi']);
    });
  }

  test('ketukan ganda tidak menggandakan proses yang masih berjalan', () async {
    await login(1);
    remote.inspectGate = Completer<void>();
    runtime.opened.add(push('10'));
    runtime.opened.add(push('10'));
    remote.inspectGate!.complete();
    await flushPush();
    expect(remote.inspected, [10]);
    expect(remote.read, [10]);
    expect(runtime.destinations, ['/nilai-saya']);
  });

  test('dispose membatalkan hasil foreground yang belum selesai', () async {
    await login(1);
    remote.inspectGate = Completer<void>();
    runtime.foreground.add(push('10'));
    await coordinator.dispose();
    remote.inspectGate!.complete();
    await flushPush();
    expect(runtime.shown, 0);
    expect(runtime.refreshes, 0);
  });

  testWidgets(
    'runtime menampilkan teks umum dan membersihkan notifikasi Android',
    (tester) async {
      final container = ProviderContainer();
      addTearDown(container.dispose);
      await tester.pumpWidget(
        MaterialApp(
          scaffoldMessengerKey: nusaScaffoldMessengerKey,
          home: const Scaffold(body: Text('NUSA')),
        ),
      );
      final actualRuntime = container.read(pushNotificationRuntimeProvider);
      actualRuntime.showNotification(
        const PushNotificationTarget(id: 10),
        () async {},
      );
      await tester.pump();
      expect(
        find.text('Ada pembaruan di NUSA. Buka aplikasi untuk melihat detail.'),
        findsOneWidget,
      );
      expect(find.textContaining('Nadia'), findsNothing);
      const channel = MethodChannel(
        'id.sch.smpn2padangpanjang.nusa/push_notifications',
      );
      final calls = <String>[];
      tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(channel, (
        call,
      ) async {
        calls.add(call.method);
        return null;
      });
      addTearDown(
        () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
          channel,
          null,
        ),
      );
      await actualRuntime.clearDisplayedNotifications();
      expect(calls, ['clearNotifications']);
      actualRuntime.hideForegroundNotification();
      await tester.pumpAndSettle();
      expect(find.textContaining('Ada pembaruan'), findsNothing);
    },
  );

  testWidgets('password gate menghentikan push meskipun sesi masih tersedia', (
    tester,
  ) async {
    final router = GoRouter(
      routes: [GoRoute(path: '/', builder: (_, _) => const Scaffold())],
    );
    addTearDown(router.dispose);
    final container = ProviderContainer(
      overrides: [
        authControllerProvider.overrideWith(FakePushAuth.new),
        appRouterProvider.overrideWithValue(router),
        pushNotificationRuntimeProvider.overrideWithValue(runtime),
        pushNotificationRemoteDataSourceProvider.overrideWithValue(remote),
      ],
    );
    addTearDown(container.dispose);
    await tester.pumpWidget(
      UncontrolledProviderScope(container: container, child: const NusaApp()),
    );
    await tester.pump();
    expect(runtime.synchronized, 1);
    container.read(passwordChangeGateProvider.notifier).requireChange();
    runtime.foreground.add(push('10'));
    await tester.pump();
    expect(remote.inspected, isEmpty);
    expect(runtime.shown, 0);
    expect(
      container.read(authControllerProvider).value!.session!.pengguna.jenisAkun,
      'Siswa',
    );
    expect(
      container.read(authControllerProvider).value!.session!.pengguna.peran,
      ['siswa'],
    );
    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('runtime menampilkan detail API terverifikasi pada layar kecil', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 640));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final container = ProviderContainer();
    addTearDown(container.dispose);
    await tester.pumpWidget(
      MaterialApp(
        scaffoldMessengerKey: nusaScaffoldMessengerKey,
        home: const Scaffold(body: Text('Beranda')),
      ),
    );
    final actualRuntime = container.read(pushNotificationRuntimeProvider);
    actualRuntime.showNotification(
      const PushNotificationTarget(
        id: 10,
        title: 'Kehadiran anak Anda tercatat',
        body: 'Anak Anda, Nadia, tercatat hadir tepat waktu pukul 06.54 WIB.',
      ),
      () async {},
    );
    await tester.pumpAndSettle();
    expect(find.text('Kehadiran anak Anda tercatat'), findsOneWidget);
    expect(find.textContaining('06.54 WIB'), findsOneWidget);
    expect(find.textContaining('Catatan privat lama'), findsNothing);
    expect(find.text('Buka'), findsOneWidget);
    expect(tester.takeException(), isNull);
    actualRuntime.hideForegroundNotification();
    await tester.pumpAndSettle();
  });
}

final class FakePushRuntime implements PushNotificationRuntime {
  final foreground = StreamController<RemoteMessage>.broadcast(sync: true);
  final opened = StreamController<RemoteMessage>.broadcast(sync: true);
  final tokens = StreamController<String>.broadcast(sync: true);
  RemoteMessage? initial;
  int shown = 0, hidden = 0, cleared = 0, refreshes = 0, synchronized = 0;
  Future<void> Function()? action;
  PushNotificationTarget? lastTarget;
  final destinations = <String>[];
  @override
  bool get available => true;
  @override
  Stream<RemoteMessage> get foregroundMessages => foreground.stream;
  @override
  Stream<RemoteMessage> get openedMessages => opened.stream;
  @override
  Stream<String> get tokenRefreshes => tokens.stream;
  @override
  Future<RemoteMessage?> initialMessage() async => initial;
  @override
  Future<void> synchronizeDevice() async {
    synchronized++;
  }

  @override
  Future<void> synchronizeToken(String token) async {}
  @override
  Future<void> clearDisplayedNotifications() async {
    cleared++;
  }

  @override
  void hideForegroundNotification() {
    hidden++;
    action = null;
  }

  @override
  void showNotification(
    PushNotificationTarget target,
    Future<void> Function() onOpen,
  ) {
    shown++;
    lastTarget = target;
    action = onOpen;
  }

  @override
  void refreshInbox() {
    refreshes++;
  }

  @override
  void openDestination(String destination) {
    destinations.add(destination);
  }

  Future<void> close() async {
    await foreground.close();
    await opened.close();
    await tokens.close();
  }
}

final class FakePushRemote implements PushNotificationRemoteDataSource {
  int userId = 1;
  int? responseId;
  String? destination = '/nilai-saya';
  Object? inspectError, readError;
  Completer<void>? inspectGate, readGate;
  final inspected = <int>[];
  final read = <int>[];
  @override
  Future<PushNotificationTarget> inspectNotification(int id) async {
    inspected.add(id);
    final requestingUser = userId;
    await inspectGate?.future;
    if (inspectError != null) throw inspectError!;
    if ((id == 10 ? 1 : 2) != requestingUser) {
      throw const NetworkException('Bukan pemilik', statusCode: 403);
    }
    return PushNotificationTarget(
      id: responseId ?? id,
      destination: destination,
      title: 'Judul asli dari API',
      body: 'Detail terverifikasi dari API',
    );
  }

  @override
  Future<void> markNotificationRead(int id) async {
    if (readError != null) throw readError!;
    await readGate?.future;
    read.add(id);
  }

  @override
  Future<void> registerDevice({
    required String token,
    required String platform,
    required String deviceName,
  }) async {}
  @override
  Future<void> unregisterDevice(String token) async {}
}

class FakePushAuth extends AuthController {
  @override
  Future<AuthState> build() async => const AuthState(
    session: AuthSession(
      token: 'test-token',
      pengguna: Pengguna(
        id: 1,
        nama: 'Siswa',
        username: 'siswa',
        jenisAkun: 'Siswa',
        administrator: false,
        wajibGantiKataSandi: false,
        peran: ['siswa'],
        izin: [],
      ),
    ),
  );
}
