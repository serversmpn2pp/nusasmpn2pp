import 'dart:io';
import 'dart:async';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/app/router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/auth/domain/auth_session.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';
import 'package:nusa/features/sts_publication/data/sts_publication_repository.dart';
import 'package:nusa/features/sts_publication/presentation/sts_publication_view.dart';
import 'package:nusa/features/sts_publication/presentation/published_sts_view.dart';
import 'package:nusa/features/sts_publication/application/published_sts_document_service.dart';
import 'package:nusa/features/menu/data/menu_remote_data_source.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';

const _admin = Pengguna(
  id: 1,
  nama: 'Wali STS',
  username: 'wali',
  jenisAkun: 'Pegawai',
  administrator: false,
  wajibGantiKataSandi: false,
  peran: ['wali_kelas'],
  izin: ['nilai.rekap'],
);
const _parent = Pengguna(
  id: 2,
  nama: 'Orang tua',
  username: 'parent',
  jenisAkun: 'Orang tua',
  administrator: false,
  wajibGantiKataSandi: false,
  peran: ['orang_tua'],
  izin: [],
);
const _student = Pengguna(
  id: 3,
  nama: 'Siswa',
  username: 'student',
  jenisAkun: 'Siswa',
  administrator: true,
  wajibGantiKataSandi: false,
  peran: ['administrator'],
  izin: ['nilai.rekap'],
);
final _capture = GlobalKey();

void main() {
  setUpAll(() async {
    final font = Platform.environment['NUSA_QA_FONT'];
    if (font != null) {
      await (FontLoader('Roboto')..addFont(
            Future.value(ByteData.sublistView(await File(font).readAsBytes())),
          ))
          .load();
      await (FontLoader('MaterialIcons')..addFont(
            Future.value(
              ByteData.sublistView(
                await File(
                  '${File(font).parent.path}/materialicons-regular.otf',
                ).readAsBytes(),
              ),
            ),
          ))
          .load();
    }
  });
  test('roles separate publishing and private student / parent viewing', () {
    expect(stsPublisher(_admin), isTrue);
    expect(stsPublisher(_student), isFalse);
    expect(stsPublisher(_parent), isFalse);
    expect(stsRecipient(_parent), isTrue);
    expect(stsRecipient(_student), isTrue);
    expect(stsRecipient(_admin), isFalse);
  });
  test(
    'repository uses authenticated API routes and explicit confirmation',
    () async {
      final calls = <RequestOptions>[];
      final dio = Dio(BaseOptions(baseUrl: 'https://school.test/api/v1/'));
      dio.interceptors.add(
        InterceptorsWrapper(
          onRequest: (o, h) {
            calls.add(o);
            h.resolve(
              Response(requestOptions: o, data: {'data': <String, dynamic>{}}),
            );
          },
        ),
      );
      final repo = StsPublicationRepository(dio);
      await repo.list();
      await repo.detail(19);
      await repo.publication(5, 6);
      await repo.publish(5, 6, {
        'penerima': 'orang_tua',
        'sertakan_perilaku': false,
        'siswa': [],
      });
      await repo.cancel(5, 6, [
        {'anggota_id': 8, 'versi_publikasi': 1},
      ], 'Koreksi nilai ulang.');
      await repo.workspace(activityId: 5);
      expect(calls.map((r) => r.path), [
        'rapor-sts-saya',
        'rapor-sts-saya/19',
        'rapor-sts/5/6/publikasi',
        'rapor-sts/5/6/publikasi',
        'rapor-sts/5/6/publikasi',
        'publikasi-rapor-sts',
      ]);
      expect(calls[3].method, 'POST');
      expect(calls[3].data['konfirmasi'], isTrue);
      expect(calls[4].method, 'DELETE');
      expect(calls[4].data['konfirmasi'], isTrue);
      expect(calls[5].queryParameters, {'kegiatan_id': 5});
      expect(() => repo.detail(0), throwsArgumentError);
    },
  );
  testWidgets(
    'publication defaults to parent only and requires chosen pupils + confirmation',
    (t) async {
      final repo = _Repo();
      await _mount(
        t,
        const StsPublicationView(activityId: 5, classId: 6),
        repo,
      );
      expect(find.text('Orang tua saja'), findsOneWidget);
      expect(find.text('Siswa saja'), findsNothing);
      await _visible(t, find.text('Pilih yang siap'));
      await t.tap(find.text('Pilih yang siap'));
      await t.pumpAndSettle();
      expect(find.text('2 siswa dipilih'), findsOneWidget);
      final button = find.byKey(const Key('sts-publish-submit'));
      await _visible(t, button);
      await t.tap(button);
      await t.pumpAndSettle();
      expect(repo.publishes, isEmpty);
      await _visible(t, find.byKey(const Key('sts-publish-confirm')));
      await t.tap(find.byKey(const Key('sts-publish-confirm')));
      await t.pumpAndSettle();
      await _visible(t, button);
      await t.tap(button);
      await t.pumpAndSettle();
      expect(repo.publishes.single['penerima'], 'orang_tua');
      expect(repo.publishes.single['sertakan_perilaku'], isFalse);
      expect(
        stsItems(repo.publishes.single['siswa']).map((s) => s['anggota_id']),
        [8, 9],
      );
      expect(
        stsItems(repo.publishes.single['siswa']).first['sidik_sumber'],
        'report-8',
      );
    },
  );
  testWidgets(
    'behavior selection only selects reviewed pupil and sends combined hash',
    (t) async {
      final repo = _Repo();
      await _mount(
        t,
        const StsPublicationView(activityId: 5, classId: 6),
        repo,
      );
      await _visible(t, find.text('Sertakan lampiran perilaku'));
      await t.tap(find.text('Sertakan lampiran perilaku'));
      await t.pumpAndSettle();
      await _visible(t, find.text('Pilih yang siap'));
      await t.tap(find.text('Pilih yang siap'));
      await t.pumpAndSettle();
      expect(find.text('1 siswa dipilih'), findsOneWidget);
      await _visible(t, find.byKey(const Key('sts-publish-confirm')));
      await t.tap(find.byKey(const Key('sts-publish-confirm')));
      await t.pumpAndSettle();
      await _visible(t, find.byKey(const Key('sts-publish-submit')));
      await t.tap(find.byKey(const Key('sts-publish-submit')));
      await t.pumpAndSettle();
      expect(repo.publishes.single['sertakan_perilaku'], isTrue);
      expect(
        stsItems(repo.publishes.single['siswa']).single['sidik_sumber'],
        'combined-8',
      );
    },
  );
  testWidgets(
    'stale publication rejects without auto retry and clears confirmation',
    (t) async {
      final repo = _Repo()
        ..publishError = const ValidationException(
          'Data berubah',
          errors: {
            'publikasi': ['Muat ulang dan periksa kembali.'],
          },
        );
      await _mount(
        t,
        const StsPublicationView(activityId: 5, classId: 6),
        repo,
      );
      await _visible(t, find.text('Pilih yang siap'));
      await t.tap(find.text('Pilih yang siap'));
      await t.pumpAndSettle();
      await _visible(t, find.byKey(const Key('sts-publish-confirm')));
      await t.tap(find.byKey(const Key('sts-publish-confirm')));
      await t.pumpAndSettle();
      await _visible(t, find.byKey(const Key('sts-publish-submit')));
      await t.tap(find.byKey(const Key('sts-publish-submit')));
      await t.pumpAndSettle();
      expect(repo.publishes.length, 1);
      expect(find.text('Muat ulang dan periksa kembali.'), findsOneWidget);
      expect(
        t
            .widget<CheckboxListTile>(
              find.byKey(const Key('sts-publish-confirm')),
            )
            .value,
        isFalse,
      );
      await _visible(t, find.text('2 siswa dipilih'));
      expect(find.text('2 siswa dipilih'), findsOneWidget);
    },
  );
  testWidgets(
    'cancellation requires reason and uses selected current versions',
    (t) async {
      final repo = _Repo();
      await _mount(
        t,
        const StsPublicationView(activityId: 5, classId: 6),
        repo,
      );
      await _visible(t, find.text('Pilih terpublikasi'));
      await t.tap(find.text('Pilih terpublikasi'));
      await t.pumpAndSettle();
      await _visible(t, find.byKey(const Key('sts-publish-confirm')));
      await t.tap(find.byKey(const Key('sts-publish-confirm')));
      await t.pumpAndSettle();
      await _visible(t, find.text('Batalkan publikasi'));
      await t.tap(find.text('Batalkan publikasi'));
      await t.pumpAndSettle();
      await _visible(t, find.byKey(const Key('sts-publish-cancel')));
      await t.tap(find.byKey(const Key('sts-publish-cancel')));
      await t.pumpAndSettle();
      expect(repo.cancellations, isEmpty);
      await _visible(t, find.byKey(const Key('sts-cancellation-reason')));
      await t.enterText(
        find.byKey(const Key('sts-cancellation-reason')),
        'Koreksi nilai sebelum pembagian.',
      );
      await t.pumpAndSettle();
      await _visible(t, find.byKey(const Key('sts-publish-cancel')));
      await t.tap(find.byKey(const Key('sts-publish-cancel')));
      await t.pumpAndSettle();
      expect(repo.cancellations.single.$1.single['versi_publikasi'], 1);
    },
  );
  testWidgets(
    'private report list supports multiple children and real native route',
    (t) async {
      final repo = _Repo();
      await _mount(
        t,
        const PublishedStsView(),
        repo,
        user: _parent,
        route: '/rapor-sts-saya',
      );
      expect(find.text('Semua anak'), findsOneWidget);
      expect(find.text('Alya STS'), findsOneWidget);
      await _visible(t, find.text('Alya STS'));
      await t.tap(find.text('Alya STS'));
      await t.pumpAndSettle();
      expect(repo.details, [19]);
      expect(find.text('88,50'), findsOneWidget);
      expect(find.text('Belum tersedia'), findsOneWidget);
      expect(find.text('Ketidakhadiran (hari)'), findsNothing);
      expect(t.takeException(), isNull);
    },
  );
  testWidgets(
    'personal data and pending responses are discarded on account change',
    (t) async {
      final repo = _Repo();
      final c = await _mount(
        t,
        const PublishedStsView(id: 19),
        repo,
        user: _parent,
      );
      expect(find.text('Alya STS'), findsOneWidget);
      (c.read(authControllerProvider.notifier) as _Auth).change(_admin);
      await t.pumpAndSettle();
      expect(find.text('Alya STS'), findsNothing);
      expect(find.text('88,50'), findsNothing);
    },
  );
  testWidgets('wrong identity cannot open management or fetch its data', (
    t,
  ) async {
    final repo = _Repo();
    await _mount(
      t,
      const StsPublicationView(activityId: 5, classId: 6),
      repo,
      user: _student,
    );
    expect(repo.managementReads, 0);
    expect(find.text('Akses publikasi rapor tidak tersedia.'), findsOneWidget);
  });
  test('PDF is generated with optional reviewed behavior', () async {
    final service = PublishedStsDocumentService();
    final bytes = await service.build(_detail());
    expect(String.fromCharCodes(bytes.take(8)), startsWith('%PDF-'));
    expect(bytes.length, greaterThan(5000));
    if (Platform.environment['NUSA_CAPTURE_PUBLICATION_STS'] == '1') {
      final dir = Directory('build/sts-publication-qa');
      await dir.create(recursive: true);
      await File('${dir.path}/published.pdf').writeAsBytes(bytes);
    }
  });
  for (final user in [_admin, _parent, _student]) {
    testWidgets(
      'real Akademik menu opens STS destination for ${user.jenisAkun}',
      (t) async {
        final repo = _Repo();
        await _mount(
          t,
          const SizedBox(),
          repo,
          user: user,
          route: '/menu/akademik',
        );
        final code = user == _admin
            ? 'publikasi-rapor-sts'
            : user == _parent
            ? 'rapor-sts-anak'
            : 'rapor-sts-saya';
        await t.tap(find.byKey(Key('menu-item-$code')));
        await t.pumpAndSettle();
        if (user == _admin) {
          expect(find.byType(StsPublicationView), findsOneWidget);
          expect(repo.workspaces.single, (null, null));
          expect(find.text('Kegiatan STS'), findsOneWidget);
        } else {
          expect(find.byType(PublishedStsView), findsOneWidget);
          expect(repo.managementReads, 0);
        }
        expect(t.takeException(), isNull);
      },
    );
  }
  testWidgets('changing activity discards class, selections and confirmation', (
    t,
  ) async {
    final repo = _Repo();
    await _mount(t, const StsPublicationView(), repo);
    await _visible(t, find.text('Pilih yang siap'));
    await t.tap(find.text('Pilih yang siap'));
    await t.pumpAndSettle();
    await _visible(t, find.byKey(const Key('sts-publish-confirm')));
    await t.tap(find.byKey(const Key('sts-publish-confirm')));
    await t.pumpAndSettle();
    final activity = find.byWidgetPredicate(
      (w) =>
          w is DropdownButtonFormField<int> &&
          w.decoration.labelText == 'Kegiatan STS',
    );
    await _visible(t, activity);
    await t.tap(activity);
    await t.pumpAndSettle();
    await t.tap(find.text('STS Genap').last);
    await t.pumpAndSettle();
    expect(repo.workspaces.last, (11, null));
    await _visible(t, find.text('0 siswa dipilih'));
    expect(find.text('0 siswa dipilih'), findsOneWidget);
    await _visible(t, find.byKey(const Key('sts-publish-confirm')));
    expect(
      t
          .widget<CheckboxListTile>(
            find.byKey(const Key('sts-publish-confirm')),
          )
          .value,
      isFalse,
    );
    expect(repo.publishes, isEmpty);
  });
  for (final user in [_parent, _student]) {
    testWidgets('reviewed behavior has a separate tab for ${user.jenisAkun}', (
      t,
    ) async {
      await _mount(t, const PublishedStsView(id: 19), _Repo(), user: user);
      expect(find.text('88,50'), findsOneWidget);
      expect(find.text('Pembinaan disiplin waktu'), findsNothing);
      await t.tap(find.byKey(const Key('published-sts-behavior-tab')));
      await t.pumpAndSettle();
      await _visible(t, find.text('Pembinaan disiplin waktu'));
      expect(find.text('Pembinaan disiplin waktu'), findsOneWidget);
      expect(find.text('88,50'), findsNothing);
      await _visible(t, find.byKey(const Key('published-sts-report-tab')));
      await t.tap(find.byKey(const Key('published-sts-report-tab')));
      await t.pumpAndSettle();
      expect(find.text('88,50'), findsOneWidget);
    });
  }
  testWidgets(
    'attachment not included does not suggest there were no incidents',
    (t) async {
      final repo = _Repo()..withBehavior = false;
      await _mount(t, const PublishedStsView(id: 19), repo, user: _parent);
      expect(find.byKey(const Key('published-sts-behavior-tab')), findsNothing);
      await _visible(
        t,
        find.textContaining('Lampiran perilaku tidak disertakan'),
      );
      expect(
        find.textContaining('Ini bukan keterangan bahwa tidak ada kejadian'),
        findsOneWidget,
      );
    },
  );
  testWidgets('revoked export rechecks permission and removes cached report', (
    t,
  ) async {
    final repo = _Repo();
    await _mount(t, const PublishedStsView(id: 19), repo, user: _parent);
    repo.detailError = const NetworkException(
      'Rapor tidak tersedia.',
      statusCode: 404,
    );
    await t.tap(find.byKey(const Key('published-sts-print')));
    await t.pumpAndSettle();
    expect(repo.details, [19, 19]);
    expect(find.text('Alya STS'), findsNothing);
    expect(find.text('Rapor tidak tersedia.'), findsOneWidget);
  });
  testWidgets('late response cannot expose a previous account report', (
    t,
  ) async {
    final repo = _Repo()..pendingDetail = Completer<StsData>();
    final c = await _mount(
      t,
      const PublishedStsView(id: 19),
      repo,
      user: _parent,
      settle: false,
    );
    (c.read(authControllerProvider.notifier) as _Auth).change(_admin);
    await t.pump();
    repo.pendingDetail!.complete(_detail());
    await t.pumpAndSettle();
    expect(find.text('Alya STS'), findsNothing);
    expect(find.text('88,50'), findsNothing);
  });
  testWidgets('empty workspace gives safe empty state without publishing', (
    t,
  ) async {
    final repo = _Repo()..emptyWorkspace = true;
    await _mount(t, const StsPublicationView(), repo);
    expect(
      find.textContaining('Belum ada kegiatan STS atau kelas'),
      findsOneWidget,
    );
    expect(find.byKey(const Key('sts-publish-submit')), findsNothing);
    expect(repo.publishes, isEmpty);
    expect(t.takeException(), isNull);
  });
  testWidgets('unreviewed selected attachment keeps publish disabled', (
    t,
  ) async {
    final repo = _Repo();
    await _mount(t, const StsPublicationView(), repo);
    await _visible(t, find.text('Pilih yang siap'));
    await t.tap(find.text('Pilih yang siap'));
    await t.pumpAndSettle();
    await _visible(t, find.text('Sertakan lampiran perilaku'));
    await t.tap(find.text('Sertakan lampiran perilaku'));
    await t.pumpAndSettle();
    await _visible(t, find.byKey(const Key('sts-publish-confirm')));
    await t.tap(find.byKey(const Key('sts-publish-confirm')));
    await t.pumpAndSettle();
    await _visible(t, find.byKey(const Key('sts-publish-submit')));
    expect(
      t
          .widget<FilledButton>(find.byKey(const Key('sts-publish-submit')))
          .onPressed,
      isNull,
    );
    expect(repo.publishes, isEmpty);
  });
  testWidgets('320px at 2x text fits publication and both recipient panels', (
    t,
  ) async {
    await _mount(
      t,
      const StsPublicationView(),
      _Repo(),
      size: const Size(320, 844),
      scale: 2,
    );
    await _visible(t, find.text('Batalkan publikasi'));
    expect(t.takeException(), isNull);
    await _mount(
      t,
      const PublishedStsView(id: 19),
      _Repo(),
      user: _student,
      size: const Size(320, 844),
      scale: 2,
    );
    await _visible(t, find.text('Wali kelas'));
    expect(t.takeException(), isNull);
    await _visible(t, find.byKey(const Key('published-sts-behavior-tab')));
    await t.tap(find.byKey(const Key('published-sts-behavior-tab')));
    await t.pumpAndSettle();
    await _visible(t, find.text('Pembinaan telah dilakukan.'));
    expect(t.takeException(), isNull);
  });
  if (Platform.environment['NUSA_CAPTURE_PUBLICATION_STS'] == '1') {
    testWidgets('capture native publication and recipient screens', (t) async {
      final oldShadows = debugDisableShadows;
      debugDisableShadows = false;
      try {
        await _mount(t, const StsPublicationView(), _Repo());
        await _screenshot(t, 'publication-mobile');
        await _mount(t, const PublishedStsView(), _Repo(), user: _parent);
        await _screenshot(t, 'parent-list-mobile');
        await _mount(t, const PublishedStsView(id: 19), _Repo(), user: _parent);
        await _screenshot(t, 'report-mobile');
        await t.tap(find.byKey(const Key('published-sts-behavior-tab')));
        await t.pumpAndSettle();
        await _screenshot(t, 'behavior-mobile');
      } finally {
        debugDisableShadows = oldShadows;
      }
    });
  }
  for (final width in [320.0, 390.0, 768.0]) {
    testWidgets('publication and recipient UI fits $width with larger text', (
      t,
    ) async {
      await _mount(
        t,
        const StsPublicationView(activityId: 5, classId: 6),
        _Repo(),
        size: Size(width, 844),
        scale: 1.35,
      );
      expect(t.takeException(), isNull);
      await _screenshot(t, 'management-${width.toInt()}');
      await _mount(
        t,
        const PublishedStsView(id: 19),
        _Repo(),
        user: _parent,
        size: Size(width, 844),
        scale: 1.35,
      );
      expect(t.takeException(), isNull);
      await _screenshot(t, 'report-${width.toInt()}');
      await _visible(t, find.byKey(const Key('published-sts-behavior-tab')));
      await t.tap(find.byKey(const Key('published-sts-behavior-tab')));
      await t.pumpAndSettle();
      await _screenshot(t, 'behavior-${width.toInt()}');
      await t.drag(find.byType(ListView), const Offset(0, -900));
      await t.pumpAndSettle();
      expect(t.takeException(), isNull);
    });
  }
}

Future<void> _visible(WidgetTester t, Finder finder) async {
  if (finder.evaluate().isEmpty) {
    final scrollable = find
        .descendant(
          of: find.byType(ListView).first,
          matching: find.byType(Scrollable),
        )
        .first;
    t.state<ScrollableState>(scrollable).position.jumpTo(0);
    await t.pumpAndSettle();
    if (finder.evaluate().isNotEmpty) {
      await t.ensureVisible(finder);
      await t.pumpAndSettle();
      return;
    }
    await t.scrollUntilVisible(finder, 250, scrollable: scrollable);
  } else {
    await t.ensureVisible(finder);
  }
  await t.pumpAndSettle();
}

Future<void> _screenshot(WidgetTester t, String name) async {
  if (Platform.environment['NUSA_CAPTURE_PUBLICATION_STS'] != '1') return;
  final boundary =
      _capture.currentContext!.findRenderObject()! as RenderRepaintBoundary;
  await t.runAsync(() async {
    final img = await boundary.toImage();
    final data = await img.toByteData(format: ui.ImageByteFormat.png);
    img.dispose();
    final dir = Directory('build/sts-publication-qa');
    await dir.create(recursive: true);
    await File('${dir.path}/$name.png')
        .writeAsBytes(data!.buffer.asUint8List());
  });
}

Future<ProviderContainer> _mount(
  WidgetTester t,
  Widget view,
  _Repo repo, {
  Pengguna user = _admin,
  String? route,
  Size size = const Size(390, 844),
  double scale = 1,
  bool settle = true,
}) async {
  await t.pumpWidget(const SizedBox());
  t.view.physicalSize = size;
  t.view.devicePixelRatio = 1;
  t.platformDispatcher.textScaleFactorTestValue = scale;
  addTearDown(t.view.resetPhysicalSize);
  addTearDown(t.view.resetDevicePixelRatio);
  addTearDown(t.platformDispatcher.clearTextScaleFactorTestValue);
  final c = ProviderContainer(
    overrides: [
      authControllerProvider.overrideWith(() => _Auth(user)),
      stsPublicationRepositoryProvider.overrideWithValue(repo),
      splashGateProvider.overrideWith((ref) async {}),
      menuRemoteDataSourceProvider.overrideWithValue(_Menu(user)),
    ],
  );
  await c.read(authControllerProvider.future);
  await c.read(splashGateProvider.future);
  addTearDown(c.dispose);
  final router = route == null ? null : c.read(appRouterProvider);
  if (route != null) router!.go(route);
  await t.pumpWidget(
    UncontrolledProviderScope(
      container: c,
      child: RepaintBoundary(
        key: _capture,
        child: route == null
            ? MaterialApp(
                theme: AppTheme.light,
                debugShowCheckedModeBanner: false,
                home: view,
              )
            : MaterialApp.router(
                theme: AppTheme.light,
                debugShowCheckedModeBanner: false,
                routerConfig: router,
              ),
      ),
    ),
  );
  if (settle) {
    await t.pumpAndSettle();
  } else {
    await t.pump();
    await t.pump();
  }
  return c;
}

class _Auth extends AuthController {
  _Auth(this.user);
  final Pengguna user;
  @override
  Future<AuthState> build() async => AuthState(
    session: AuthSession(token: 'test-only', pengguna: user),
  );
  void change(Pengguna u) => state = AsyncData(
    AuthState(
      session: AuthSession(token: 'test-only', pengguna: u),
    ),
  );
}

class _Repo extends StsPublicationRepository {
  _Repo() : super(Dio());
  int managementReads = 0;
  final publishes = <StsData>[], details = <int>[];
  final cancellations = <(List<StsData>, String)>[];
  Object? publishError;
  Object? detailError;
  bool withBehavior = true;
  bool emptyWorkspace = false;
  Completer<StsData>? pendingDetail;
  final workspaces = <(int?, int?)>[];
  @override
  Future<StsData> workspace({int? activityId, int? classId}) async {
    workspaces.add((activityId, classId));
    if (emptyWorkspace) {
      return {
        'filter': {},
        'opsi': {'kegiatan': [], 'kelas': []},
        'konteks': null,
      };
    }
    return {
      'filter': {'kegiatan_id': activityId ?? 5, 'kelas_id': classId ?? 6},
      'opsi': {
        'kegiatan': [
          {'id': 5, 'label': 'STS Ganjil'},
          {'id': 11, 'label': 'STS Genap'},
        ],
        'kelas': [
          {'id': 6, 'label': 'VII.A'},
        ],
      },
      'konteks': await publication(activityId ?? 5, classId ?? 6),
    };
  }

  @override
  Future<StsData> publication(int a, int c) async {
    managementReads++;
    return {
      'kegiatan': 'Sumatif Tengah Semester Ganjil 2026/2027',
      'kelas': 'VII.A',
      'siswa': [
        for (final id in [8, 9, 10])
          {
            'anggota_id': id,
            'nama': ['Alya STS', 'Bima STS', 'Citra STS'][id - 8],
            'siap': id != 10,
            'perilaku_siap': id == 8,
            'versi_publikasi': id == 8 ? 1 : 0,
            'aktif': id == 8,
            'sidik_rapor': 'report-$id',
            'sidik_gabungan': 'combined-$id',
            'riwayat': [],
          },
      ],
    };
  }

  @override
  Future<StsData> publish(int a, int c, StsData d) async {
    publishes.add(d);
    if (publishError != null) throw publishError!;
    return {'pesan': 'Rapor diterbitkan.'};
  }

  @override
  Future<StsData> cancel(int a, int c, List<StsData> s, String r) async {
    cancellations.add((s, r));
    return {'pesan': 'Publikasi dibatalkan.'};
  }

  @override
  Future<StsData> list() async => {
    'mode': 'orang_tua',
    'items': [
      for (final (id, name) in [(19, 'Alya STS'), (20, 'Bima STS')])
        {
          'id': id,
          'versi': 1,
          'sertakan_perilaku': id == 19,
          'siswa': {'id': id, 'nama': name},
          'kelas': {'nama': 'VII.A'},
          'kegiatan': {'nama': 'STS Ganjil', 'tahun_pelajaran': '2026/2027'},
          'dipublikasikan_pada': '2026-10-13T10:00:00+07:00',
        },
    ],
  };
  @override
  Future<StsData> detail(int id) async {
    details.add(id);
    if (detailError != null) throw detailError!;
    if (pendingDetail != null) return pendingDetail!.future;
    final data = _detail();
    if (!withBehavior) (data['rapor'] as StsData)['perilaku'] = null;
    return data;
  }
}

class _Menu implements MenuRemoteDataSource {
  _Menu(this.user);
  final Pengguna user;
  @override
  Future<MenuCatalog> fetchCatalog() async => MenuCatalog.fromJson({
    'dihasilkan_pada': '2026-10-13T12:00:00+07:00',
    'kelompok': [
      {
        'kode': 'akademik',
        'label': 'Akademik',
        'ikon': 'academic',
        'items': [
          {
            'kode': user == _admin
                ? 'publikasi-rapor-sts'
                : user == _parent
                ? 'rapor-sts-anak'
                : 'rapor-sts-saya',
            'label': user == _admin
                ? 'Publikasi Rapor STS'
                : user == _parent
                ? 'Rapor STS Anak Saya'
                : 'Rapor STS Saya',
            'inisial': 'RS',
            'subkelompok': 'Penilaian',
            'status': 'tersedia',
            'rute': user == _admin
                ? AppRoutes.stsPublication
                : AppRoutes.publishedSts,
          },
        ],
      },
    ],
  });
  @override
  Future<MenuCatalog> resetQuickAccess() => throw UnimplementedError();
  @override
  Future<MenuCatalog> saveQuickAccess(List<String> codes) =>
      throw UnimplementedError();
}

StsData _detail() => {
  'id': 19,
  'versi': 1,
  'dipublikasikan_pada': '2026-10-13T10:00:00+07:00',
  'rapor': {
    'siswa': {'id': 19, 'nama': 'Alya STS'},
    'kelas': {'nama': 'VII.A', 'tingkat': 7},
    'kegiatan': {
      'nama': 'STS Ganjil',
      'tahun_pelajaran': '2026/2027',
      'semester': 'ganjil',
    },
    'periode': {
      'awal': '2026-08-01',
      'akhir': '2026-09-20',
      'tanggal_rapor': '2026-10-02',
    },
    'wali_kelas': {'nama_lengkap': 'Wali Kelas STS', 'nip': '123456789'},
    'nilai': [
      {
        'mapel': 'Pendidikan Jasmani, Olahraga, dan Kesehatan',
        'nilai': 88.5,
        'keterangan': 'Baik',
      },
      {
        'mapel': 'Keminangkabauan',
        'nilai': null,
        'keterangan': 'Belum tersedia',
      },
    ],
    'nilai_tuntas': false,
    'jumlah': null,
    'rata': null,
    'kehadiran': {'sakit': 2, 'izin': 1, 'alfa': 3},
    'keterlambatan': {'jumlah': 2, 'total_menit': 30},
    'perilaku': {
      'label_isi': 'Hanya pelanggaran berpoin final',
      'pesan_kosong': 'Tidak ada pelanggaran berpoin final pada periode ini.',
      'baris': [
        {
          'tanggal': '2026-09-05',
          'kejadian': 'Terlambat datang ke sekolah',
          'tindakan': 'Pembinaan disiplin waktu',
          'poin': 15,
        },
      ],
      'ringkasan': {
        'jumlah_kejadian': 1,
        'poin_masuk': 15,
        'poin_dikurangi': 0,
        'saldo': 15,
      },
      'catatan': 'Pembinaan telah dilakukan.',
      'guru_bk': {'nama_lengkap': 'Guru BK Tingkat 7'},
      'wakil_kesiswaan': {'nama_lengkap': 'Wakil Kesiswaan'},
    },
  },
};
