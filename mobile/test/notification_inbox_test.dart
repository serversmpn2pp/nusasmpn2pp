import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/home/application/notification_inbox_controller.dart';
import 'package:nusa/features/home/data/notification_inbox_remote_data_source.dart';
import 'package:nusa/features/home/domain/home_dashboard.dart';
import 'package:nusa/features/home/domain/notification_inbox.dart';
import 'package:nusa/features/home/presentation/home_tabs.dart';
import 'package:nusa/shared/widgets/nusa_bottom_navigation.dart';

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
  late FakeInboxRemote remote;
  late ProviderContainer container;
  setUp(() {
    remote = FakeInboxRemote();
    container = ProviderContainer(
      overrides: [
        authControllerProvider.overrideWith(InboxTestAuth.new),
        notificationInboxRemoteDataSourceProvider.overrideWithValue(remote),
      ],
    );
    container.listen(notificationInboxControllerProvider, (_, _) {});
  });
  tearDown(() => container.dispose());

  test('memuat 20 pertama lalu seluruh riwayat tanpa duplikasi', () async {
    final first = await container.read(
      notificationInboxControllerProvider.future,
    );
    final controller = container.read(
      notificationInboxControllerProvider.notifier,
    );
    expect(first.items.length, 20);
    expect(first.nextBeforeId, 26);
    await controller.loadMore();
    expect(
      container.read(notificationInboxControllerProvider).value!.items.length,
      40,
    );
    await controller.loadMore();
    final all = container.read(notificationInboxControllerProvider).value!;
    expect(all.items.map((item) => item.id).toSet().length, 45);
    expect(all.nextBeforeId, isNull);
    expect(remote.requests, [('semua', null), ('semua', 26), ('semua', 6)]);
  });

  test(
    'gagal muat halaman lama mempertahankan riwayat dan bisa dicoba ulang',
    () async {
      await container.read(notificationInboxControllerProvider.future);
      final controller = container.read(
        notificationInboxControllerProvider.notifier,
      );
      remote.failNext = true;
      await expectLater(
        controller.loadMore(),
        throwsA(isA<NetworkException>()),
      );
      final current = container
          .read(notificationInboxControllerProvider)
          .value!;
      expect(current.items.length, 20);
      expect(current.isLoadingMore, false);
      await controller.loadMore();
      expect(
        container.read(notificationInboxControllerProvider).value!.items.length,
        40,
      );
    },
  );

  test(
    'permintaan ganda dan hasil filter lama tidak menimpa filter baru',
    () async {
      await container.read(notificationInboxControllerProvider.future);
      final controller = container.read(
        notificationInboxControllerProvider.notifier,
      );
      final pending = Completer<NotificationInbox>();
      remote.nextGate = pending;
      final loading = controller.loadMore();
      await controller.loadMore();
      expect(remote.requests.length, 2);
      await controller.filter('belum_dibaca');
      pending.complete(remote.page('semua', 26));
      await loading;
      final current = container
          .read(notificationInboxControllerProvider)
          .value!;
      expect(controller.status, 'belum_dibaca');
      expect(current.items.every((item) => item.unread), true);
      expect(current.items.length, 20);
      expect(current.isLoadingMore, false);
    },
  );

  test(
    'menandai riwayat lama dibaca tidak mereset halaman yang sudah dimuat',
    () async {
      await container.read(notificationInboxControllerProvider.future);
      final controller = container.read(
        notificationInboxControllerProvider.notifier,
      );
      await controller.loadMore();
      await controller.markRead(7);
      final current = container
          .read(notificationInboxControllerProvider)
          .value!;
      expect(current.items.length, 40);
      expect(current.items.firstWhere((item) => item.id == 7).unread, false);
      expect(current.nextBeforeId, 6);
      expect(current.unreadCount, 29);
    },
  );

  test(
    'baca semua mengosongkan filter belum dibaca termasuk halaman lama',
    () async {
      await container.read(notificationInboxControllerProvider.future);
      final controller = container.read(
        notificationInboxControllerProvider.notifier,
      );
      await controller.filter('belum_dibaca');
      await controller.markRead(44);
      expect(
        container
            .read(notificationInboxControllerProvider)
            .value!
            .items
            .any((item) => item.id == 44),
        false,
      );
      await controller.markAllRead();
      final current = container
          .read(notificationInboxControllerProvider)
          .value!;
      expect(current.items, isEmpty);
      expect(current.nextBeforeId, isNull);
      expect(remote.records.every((item) => !item.unread), true);
      await controller.filter('semua');
      expect(
        container.read(notificationInboxControllerProvider).value!.items.length,
        20,
      );
    },
  );

  test('remote memakai filter dan penanda halaman serta membaca jumlah dari server', () async {
    final dio = Dio(BaseOptions(baseUrl: 'https://nusa.test/api/v1/'));
    final calls = <RequestOptions>[];
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          calls.add(options);
          handler.resolve(
            Response(
              requestOptions: options,
              statusCode: 200,
              data: {
                'data': options.method == 'GET'
                    ? {
                        'items': <dynamic>[],
                        'jumlah_belum_dibaca': 31,
                        'paginasi': {
                          'ada_lagi': false,
                          'sebelum_id_berikutnya': null,
                        },
                      }
                    : {'jumlah_belum_dibaca': 30},
              },
            ),
          );
        },
      ),
    );
    final source = DioNotificationInboxRemoteDataSource(dio);
    expect(
      (await source.fetch(status: 'belum_dibaca', beforeId: 26)).unreadCount,
      31,
    );
    expect(calls.single.queryParameters, {
      'status': 'belum_dibaca',
      'sebelum_id': 26,
    });
    expect(await source.markRead(7), 30);
    expect(calls.last.path, 'notifikasi/7/baca');
    expect(calls.last.method, 'PATCH');
    dio.close();
  });

  testWidgets('halaman sempit memuat riwayat, filter, dan baca semua', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 720));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(
          theme: AppTheme.light,
          home: const Scaffold(
            body: NusaPageFrame(
              title: 'Notifikasi',
              child: NotificationsPage(),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Semua'), findsOneWidget);
    expect(
      container.read(notificationInboxControllerProvider).value!.items.length,
      20,
    );
    await tester.scrollUntilVisible(
      find.byKey(const Key('load-older-notifications')),
      350,
    );
    await tester.tap(find.byKey(const Key('load-older-notifications')));
    await tester.pumpAndSettle();
    expect(
      container.read(notificationInboxControllerProvider).value!.items.length,
      40,
    );
    await tester.tap(find.text('Belum dibaca'));
    await tester.pumpAndSettle();
    expect(remote.requests.last, ('belum_dibaca', null));
    await tester.tap(find.byKey(const Key('mark-all-notifications-read')));
    await tester.pumpAndSettle();
    expect(find.text('Semua notifikasi sudah dibaca.'), findsOneWidget);
    expect(find.text('0 belum dibaca'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox.shrink());
  });

  for (final width in [390.0, 800.0]) {
    testWidgets('tampilan notifikasi pada lebar $width tanpa overflow', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(
        Size(width, width < 600 ? 844 : 1100),
      );
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final capture = GlobalKey();
      await tester.pumpWidget(
        UncontrolledProviderScope(
          container: container,
          child: MaterialApp(
            theme: AppTheme.light,
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(
                context,
              ).copyWith(textScaler: TextScaler.linear(width < 600 ? 1 : 1.5)),
              child: child!,
            ),
            home: RepaintBoundary(
              key: capture,
              child: Scaffold(
                body: const NusaPageFrame(
                  title: 'Notifikasi',
                  child: NotificationsPage(),
                ),
                bottomNavigationBar: NusaBottomNavigation(
                  selectedIndex: 3,
                  unreadCount: 30,
                  onSelected: (_) {},
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await _capture(tester, capture, 'notifikasi-${width.toInt()}');
      await tester.scrollUntilVisible(
        find.byKey(const Key('load-older-notifications')),
        350,
      );
      expect(tester.takeException(), isNull);
      await _capture(tester, capture, 'notifikasi-riwayat-${width.toInt()}');
      await tester.pumpWidget(const SizedBox.shrink());
    });
  }
}

Future<void> _capture(WidgetTester tester, GlobalKey key, String name) async {
  if (Platform.environment['NUSA_NOTIFICATIONS_PREVIEW'] != '1') return;
  final boundary =
      key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
  await tester.runAsync(() async {
    final image = await boundary.toImage(pixelRatio: 1.5);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    image.dispose();
    await Directory('build/ui-preview').create(recursive: true);
    await File('build/ui-preview/$name.png')
        .writeAsBytes(bytes!.buffer.asUint8List());
  });
}

class InboxTestAuth extends AuthController {
  @override
  Future<AuthState> build() async => const AuthState();
}

class FakeInboxRemote implements NotificationInboxRemoteDataSource {
  List<AppNotification> records = [
    for (var id = 45; id > 0; id--)
      AppNotification(
        id: id,
        type: 'informasi',
        typeLabel: 'Informasi',
        title: 'Riwayat notifikasi $id',
        message: 'Informasi kegiatan sekolah untuk akun Anda.',
        unread: id % 3 != 0,
        createdAt: DateTime(2026, 11, 10),
        relativeTime: '1 hari yang lalu',
      ),
  ];
  final requests = <(String, int?)>[];
  bool failNext = false;
  Completer<NotificationInbox>? nextGate;
  NotificationInbox page(String status, int? beforeId) {
    final filtered = records
        .where(
          (item) =>
              (status != 'belum_dibaca' || item.unread) &&
              (beforeId == null || item.id < beforeId),
        )
        .toList();
    final items = filtered.take(20).toList();
    return NotificationInbox(
      items: items,
      unreadCount: records.where((item) => item.unread).length,
      nextBeforeId: filtered.length > 20 ? items.last.id : null,
    );
  }

  @override
  Future<NotificationInbox> fetch({
    required String status,
    int? beforeId,
  }) async {
    requests.add((status, beforeId));
    if (failNext) {
      failNext = false;
      throw const NetworkException('Koneksi terputus. Coba lagi.');
    }
    if (nextGate case final gate?) {
      nextGate = null;
      return gate.future;
    }
    return page(status, beforeId);
  }

  @override
  Future<int> markRead(int id) async => _read(id);
  @override
  Future<int> markAllRead() async => _read(null);
  int _read(int? id) {
    records = NotificationInbox(
      items: records,
      unreadCount: 0,
    ).markRead(id, 0, unreadOnly: false).items;
    return records.where((item) => item.unread).length;
  }
}
