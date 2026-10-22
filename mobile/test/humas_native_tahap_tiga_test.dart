import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart' hide MenuController;
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
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/data/humas_photo_picker.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_catalog_content.dart';
import 'package:nusa/features/humas/presentation/humas_catalog_forms.dart';
import 'package:nusa/features/humas/presentation/humas_work_views.dart';
import 'package:nusa/features/menu/application/menu_controller.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';

final _fixtures = humasMap(
  jsonDecode(
    File('test/fixtures/humas_native_tahap_tiga.json').readAsStringSync(),
  ),
);
final _capture = GlobalKey();

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
  for (final role in _fixtures.keys) {
    for (final kind in humasCatalogKinds) {
      testWidgets('menu dan hak akses backend $role/$kind', (t) async {
        final c = humasMap(_fixtures[role]), repo = _Repo(c);
        final module = humasCatalogModule(kind),
            user = Pengguna.fromJson(humasMap(c['pengguna']));
        final menu = MenuCatalog.fromJson(humasMap(c['menu']));
        expect(menu.entryByCode(module.menuCode) != null, module.canOpen(user));
        await _mount(t, c, repo, '/humas/$kind');
        if (!module.canOpen(user)) {
          expect(repo.paths, isEmpty);
          expect(
            find.text('Menu ini tidak tersedia untuk akun Anda.'),
            findsOneWidget,
          );
          return;
        }
        expect(find.byType(HumasWorkList), findsOneWidget);
        final record = repo.record(kind), detail = humasMap(record['detail']);
        expect(
          find.byType(FloatingActionButton),
          module.canManage(user) ? findsOneWidget : findsNothing,
        );
        await _scroll(t, find.text('${detail['judul']}'));
        await t.tap(find.text('${detail['judul']}'));
        await t.pumpAndSettle();
        expect(find.byType(HumasCatalogContent), findsOneWidget);
        if (kind == 'alumni' && !module.canManage(user)) {
          expect(find.textContaining('081234567890'), findsNothing);
        }
        expect(t.takeException(), isNull);
      });
    }
  }
  for (final kind in humasCatalogKinds) {
    testWidgets('edit $kind mempertahankan versi dan isian saat konflik', (
      t,
    ) async {
      final c = humasMap(_fixtures['wakil_pimpinan_humas']),
          repo = _Repo(c)..conflict = true;
      final d = humasMap(repo.record(kind)['detail']);
      await _mount(t, c, repo, '/humas/$kind/${d['id']}');
      await _scroll(t, find.text('Edit data'));
      await t.tap(find.text('Edit data'));
      await t.pumpAndSettle();
      final reason = find.byKey(
        Key(
          'humas-field-${kind == 'aset' ? 'catatan_revisi' : 'catatan_perubahan'}',
        ),
      );
      if (kind == 'alumni') {
        await _scroll(t, find.text('Kelulusan sudah dikonfirmasi'));
        await t.tap(find.text('Kelulusan sudah dikonfirmasi'));
      }
      await _scroll(t, reason);
      await t.enterText(reason, 'Koreksi data yang telah diperiksa');
      await _save(t);
      await _save(t);
      expect(repo.sent, hasLength(2));
      expect(repo.sent.map((v) => v['versi']), [d['versi'], d['versi']]);
      expect(repo.methods, ['PATCH', 'PATCH']);
      expect(t.takeException(), isNull);
    });
    for (final size in [
      const Size(320, 640),
      const Size(390, 844),
      const Size(600, 800),
      const Size(1024, 768),
    ]) {
      for (final scale in [1.0, 2.0]) {
        testWidgets('$kind tanpa overflow ${size.width}px teks $scale', (
          t,
        ) async {
          final c = humasMap(_fixtures['wakil_pimpinan_humas']),
              repo = _Repo(c);
          await _mount(t, c, repo, '/humas/$kind', size: size, scale: scale);
          await _scroll(
            t,
            find.text('${humasMap(repo.record(kind)['detail'])['judul']}'),
          );
          if (scale == 1 && size.width == 390) await _shot(t, '$kind-list');
          await t.tap(
            find.text('${humasMap(repo.record(kind)['detail'])['judul']}'),
          );
          await t.pumpAndSettle();
          if (scale == 1 && size.width == 390) await _shot(t, '$kind-detail');
          await _scroll(t, find.text('Edit data'));
          await t.tap(find.text('Edit data'));
          await t.pumpAndSettle();
          if (scale == 1 && size.width == 390) await _shot(t, '$kind-form');
          await _scroll(t, find.text('Simpan'));
          expect(t.takeException(), isNull);
        });
      }
    }
  }
  for (final kind in ['prestasi', 'alumni']) {
    testWidgets('ekspor dan cetak $kind mempertahankan filter pencarian', (
      t,
    ) async {
      final c = humasMap(_fixtures['wakil_pimpinan_humas']), repo = _Repo(c);
      await _mount(t, c, repo, '/humas/$kind');
      await t.enterText(find.byType(TextField).first, 'Nama uji');
      await t.pump(const Duration(milliseconds: 600));
      await t.pumpAndSettle();
      await _scroll(t, find.text('Ekspor Excel'));
      await t.tap(find.text('Ekspor Excel'));
      await t.pumpAndSettle();
      expect(repo.downloads.single, contains('$kind/ekspor?'));
      expect(
        Uri.parse(repo.downloads.single).queryParameters['kata_kunci'],
        'Nama uji',
      );
      await t.tap(find.text('Simpan lembar cetak'));
      await t.pumpAndSettle();
      expect(repo.downloads.last, contains('$kind/cetak?'));
    });
  }
  testWidgets('form media baru menggunakan UUID tetap saat koneksi gagal', (
    t,
  ) async {
    final c = humasMap(_fixtures['wakil_pimpinan_humas']),
        repo = _Repo(c)..conflict = true;
    await _mount(t, c, repo, '/humas/media');
    await t.tap(find.byType(FloatingActionButton));
    await t.pumpAndSettle();
    for (final e in {
      'nama': 'Instagram Sekolah',
      'penanggung_jawab': 'Tim Humas',
      'tautan': 'https://example.org/media',
    }.entries) {
      final f = find.byKey(Key('humas-field-${e.key}'));
      await _scroll(t, f);
      await t.enterText(f, e.value);
    }
    await _save(t);
    await _save(t);
    expect(repo.sent, hasLength(2));
    expect(
      repo.sent.first['token_pembuatan'],
      repo.sent.last['token_pembuatan'],
    );
    expect(
      repo.sent.first['token_pembuatan'],
      matches(RegExp(r'^[0-9a-f-]{36}$')),
    );
  });
  testWidgets('akses alumni dicabut menghapus data dan tombol pengelola', (
    t,
  ) async {
    final c = humasMap(_fixtures['wakil_pimpinan_humas']), repo = _Repo(c);
    final d = humasMap(repo.record('alumni')['detail']);
    await _mount(t, c, repo, '/humas/alumni/${d['id']}');
    expect(find.byType(HumasCatalogContent), findsOneWidget);
    repo.denied = true;
    await t.tap(find.byTooltip('Muat ulang'));
    await t.pumpAndSettle();
    expect(find.byType(HumasCatalogContent), findsNothing);
    expect(find.text('Edit data'), findsNothing);
    expect(find.textContaining('081234567890'), findsNothing);
  });
  for (final kind in ['aset', 'kliping', 'prestasi']) {
    testWidgets('unggah foto kamera $kind memakai multipart dan versi asli', (
      t,
    ) async {
      final c = humasMap(_fixtures['wakil_pimpinan_humas']), repo = _Repo(c);
      final d = humasMap(repo.record(kind)['detail']);
      await _mount(t, c, repo, '/humas/$kind/${d['id']}');
      await _scroll(t, find.text('Edit data'));
      await t.tap(find.text('Edit data'));
      await t.pumpAndSettle();
      final reason = find.byKey(
        Key(
          'humas-field-${kind == 'aset' ? 'catatan_revisi' : 'catatan_perubahan'}',
        ),
      );
      await _scroll(t, reason);
      await t.enterText(reason, 'Menambahkan foto bukti kegiatan');
      await _scroll(t, find.byKey(const Key('catalog-source')));
      await t.tap(find.byKey(const Key('catalog-source')));
      await t.pumpAndSettle();
      await t.tap(find.text('Unggah berkas').last);
      await t.pumpAndSettle();
      await _scroll(t, find.text('Kamera'));
      await t.tap(find.text('Kamera'));
      await t.pumpAndSettle();
      expect(find.text('foto-uji.png'), findsOneWidget);
      await _save(t);
      expect(repo.sent.single['_method'], 'PATCH');
      expect(repo.sent.single['versi'], '${d['versi']}');
      expect(repo.sent.single['metode'], 'unggah');
      expect(repo.fileNames, ['foto-uji.png']);
      expect(t.takeException(), isNull);
    });
  }
  testWidgets('penerima prestasi dapat dipilih dari data siswa', (t) async {
    final c = humasMap(_fixtures['wakil_pimpinan_humas']),
        repo = _Repo(c)..conflict = true;
    final d = humasMap(repo.record('prestasi')['detail']);
    await _mount(t, c, repo, '/humas/prestasi/${d['id']}');
    await _scroll(t, find.text('Edit data'));
    await t.tap(find.text('Edit data'));
    await t.pumpAndSettle();
    final reason = find.byKey(const Key('humas-field-catatan_perubahan'));
    await _scroll(t, reason);
    await t.enterText(reason, 'Menambahkan penerima prestasi');
    await _scroll(t, find.text('Siswa').last);
    await t.tap(find.text('Siswa').last);
    await t.pumpAndSettle();
    await t.tap(find.text('Siswa Uji NUSA'));
    await t.pumpAndSettle();
    await _save(t);
    expect(humasItems(repo.sent.single['peserta']).single['siswa_id'], 991);
    expect(t.takeException(), isNull);
  });
}

Future<void> _scroll(WidgetTester t, Finder f) async {
  FocusManager.instance.primaryFocus?.unfocus();
  await t.pumpAndSettle();
  await t.scrollUntilVisible(
    f,
    250,
    scrollable: find
        .descendant(
          of: find.byType(ListView).last,
          matching: find.byType(Scrollable),
        )
        .first,
    maxScrolls: 90,
  );
  await t.ensureVisible(f);
  await t.pumpAndSettle();
}

Future<void> _save(WidgetTester t) async {
  await _scroll(t, find.text('Simpan'));
  await t.tap(find.text('Simpan'));
  await t.pumpAndSettle();
}

Future<void> _shot(WidgetTester t, String name) async {
  if (Platform.environment['NUSA_CAPTURE_HUMAS_WIDGETS'] != '1') return;
  await t.runAsync(() async {
    final boundary =
        _capture.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 1);
    final data = await image.toByteData(format: ui.ImageByteFormat.png);
    final file = File(
      '../storage/logs/humas-mobile-alignment/native-tahap-tiga/$name.png',
    );
    await file.parent.create(recursive: true);
    await file.writeAsBytes(data!.buffer.asUint8List());
    image.dispose();
  });
}

Future<void> _mount(
  WidgetTester t,
  HumasData c,
  _Repo repo,
  String route, {
  Size size = const Size(390, 844),
  double scale = 1,
}) async {
  t.view.physicalSize = size;
  t.view.devicePixelRatio = 1;
  addTearDown(t.view.resetPhysicalSize);
  addTearDown(t.view.resetDevicePixelRatio);
  final container = ProviderContainer(
    overrides: [
      authControllerProvider.overrideWith(
        () => _Auth(Pengguna.fromJson(humasMap(c['pengguna']))),
      ),
      menuControllerProvider.overrideWith(
        () => _Menu(MenuCatalog.fromJson(humasMap(c['menu']))),
      ),
      humasRepositoryProvider.overrideWithValue(repo),
      humasPhotoPickerProvider.overrideWithValue(_Photos()),
      splashGateProvider.overrideWith((ref) async {}),
    ],
  );
  addTearDown(container.dispose);
  await container.read(authControllerProvider.future);
  await container.read(splashGateProvider.future);
  final router = container.read(appRouterProvider)..go(route);
  await t.pumpWidget(
    UncontrolledProviderScope(
      container: container,
      child: RepaintBoundary(
        key: _capture,
        child: MaterialApp.router(
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light.copyWith(
            textTheme: AppTheme.light.textTheme.apply(fontFamily: 'Roboto'),
            primaryTextTheme: AppTheme.light.primaryTextTheme.apply(
              fontFamily: 'Roboto',
            ),
            filledButtonTheme: FilledButtonThemeData(
              style: AppTheme.light.filledButtonTheme.style!.copyWith(
                textStyle: const WidgetStatePropertyAll(
                  TextStyle(
                    fontFamily: 'Roboto',
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
          routerConfig: router,
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: TextScaler.linear(scale)),
            child: child!,
          ),
        ),
      ),
    ),
  );
  await t.pumpAndSettle();
}

class _Auth extends AuthController {
  _Auth(this.user);
  final Pengguna user;
  @override
  Future<AuthState> build() async => AuthState(
    session: AuthSession(token: 'test-only', pengguna: user),
  );
}

class _Menu extends MenuController {
  _Menu(this.catalog);
  final MenuCatalog catalog;
  @override
  Future<MenuCatalog> build() async => catalog;
}

class _Photos implements HumasPhotoPicker {
  @override
  Future<List<HumasPickedFile>> pick(HumasPhotoSource source) async => [
    HumasPickedFile(
      'foto-uji.png',
      Uint8List.fromList([0x89, 0x50, 0x4e, 0x47]),
    ),
  ];
}

class _Repo extends HumasRepository {
  _Repo(this.c) : super(Dio());
  final HumasData c;
  final paths = <String>[], methods = <String>[], downloads = <String>[];
  final sent = <HumasData>[];
  final fileNames = <String>[];
  bool conflict = false, denied = false;
  HumasData record(String kind) => humasMap(humasMap(c['records'])[kind]);
  @override
  Future<HumasData> get(String path, [HumasData query = const {}]) async {
    paths.add(path);
    if (denied) throw const NetworkException('Akses dicabut', statusCode: 403);
    if (path.endsWith('/pilihan')) {
      return {
        'items': [
          {'id': 991, 'nama_lengkap': 'Siswa Uji NUSA', 'kelas': <HumasData>[]},
        ],
        'next_page': null,
      };
    }
    final r = record(path.split('/').first);
    if (path.endsWith('/referensi')) return humasMap(r['references']);
    if (!path.contains('/')) return humasMap(r['list']);
    return humasMap(r['detail']);
  }

  @override
  Future<HumasData> send(
    String path,
    HumasData data, {
    String method = 'POST',
    Object? multipart,
  }) async {
    sent.add(
      multipart is FormData
          ? Map<String, dynamic>.fromEntries(multipart.fields)
          : {...data},
    );
    methods.add(method);
    if (multipart is FormData) {
      fileNames.addAll(multipart.files.map((f) => f.value.filename!));
    }
    if (conflict) {
      throw const ValidationException(
        'Data berubah. Muat ulang sebelum menyimpan.',
      );
    }
    return {'id': 1};
  }

  @override
  Future<bool> download(String path, String fileName, {HumasData? data}) async {
    downloads.add(path);
    return true;
  }
}
