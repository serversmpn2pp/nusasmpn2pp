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
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_work_forms.dart';
import 'package:nusa/features/humas/presentation/humas_work_views.dart';
import 'package:nusa/features/humas/presentation/humas_work_relations.dart';
import 'package:nusa/features/menu/application/menu_controller.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';

final _fixtures = humasMap(
  jsonDecode(
    File('test/fixtures/humas_native_tahap_dua.json').readAsStringSync(),
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
    for (final kind in humasWorkKinds) {
      testWidgets('kontrak menu dan izin native $role/$kind', (t) async {
        final c = humasMap(_fixtures[role]),
            repo = _Repo(c),
            allowed = humasWorkModule(kind)
                .canOpen(Pengguna.fromJson(humasMap(c['user'])));
        await _mount(t, repo, c, '/humas/$kind');
        if (!allowed) {
          expect(repo.paths, isEmpty);
          expect(
            find.text('Menu ini tidak tersedia untuk akun Anda.'),
            findsOneWidget,
          );
          return;
        }
        final record = repo.record(kind);
        expect(find.byType(HumasWorkList), findsOneWidget);
        expect(
          find.byType(FloatingActionButton),
          humasMap(record['references'])['dapat_kelola'] == true
              ? findsOneWidget
              : findsNothing,
        );
        final data = humasMap(record['detail']);
        await _scroll(t, find.text('${data['nama'] ?? data['judul']}'));
        await t.tap(find.text('${data['nama'] ?? data['judul']}'));
        await t.pumpAndSettle();
        expect(find.byType(HumasWorkContent), findsOneWidget);
        if (kind == 'komite' && role == 'pimpinan') {
          expect(find.textContaining('081234567890'), findsNothing);
        }
        if (role == 'wakil_pimpinan_humas') {
          await _shot(t, '$kind-detail');
        }
        expect(t.takeException(), isNull);
      });
    }
  }
  for (final kind in [
    'program',
    'komite',
    'mitra',
    'akreditasi',
    'laporan',
    'program-komite',
    'mou',
    'butir',
  ]) {
    testWidgets('edit $kind mempertahankan versi dan isian ketika konflik', (
      t,
    ) async {
      final c = humasMap(_fixtures['wakil_pimpinan_humas']),
          repo = _Repo(c)..conflict = true;
      final r = repo.record(kind),
          d = humasMap(r['detail']),
          base = r['base']?.toString() ?? kind;
      await _mount(
        t,
        repo,
        c,
        '/humas/program',
        home: HumasWorkDetail(kind: kind, base: base, id: humasInt(d['id'])),
      );
      await _scroll(t, find.text('Edit ${humasWorkTitle(kind)}'));
      await t.tap(find.text('Edit ${humasWorkTitle(kind)}'));
      await t.pumpAndSettle();
      if ([
        'program',
        'komite',
        'akreditasi',
        'laporan',
        'program-komite',
      ].contains(kind)) {
        final field = find.byKey(
          Key(
            'humas-field-${kind == 'akreditasi' ? 'alasan' : 'catatan_perubahan'}',
          ),
        );
        await _scroll(t, field);
        await t.enterText(field, 'Koreksi data yang sudah diperiksa');
      }
      await _save(t);
      await _save(t);
      expect(repo.sent, hasLength(2));
      expect(repo.sent.map((v) => v['versi']), [d['versi'], d['versi']]);
      expect(repo.methods, ['PATCH', 'PATCH']);
      if (kind == 'komite') {
        expect(
          humasItems(repo.sent.first['pengurus']).single['id'],
          humasItems(d['pengurus']).single['id'],
        );
      }
      expect(t.takeException(), isNull);
    });
  }
  testWidgets('mitra baru memakai UUID tetap ketika dicoba ulang', (t) async {
    final c = humasMap(_fixtures['wakil_pimpinan_humas']),
        repo = _Repo(c)..failOnce = true;
    await _mount(t, repo, c, '/humas/mitra');
    await t.tap(find.byType(FloatingActionButton));
    await t.pumpAndSettle();
    await t.enterText(
      find.byKey(const Key('humas-field-nama')),
      'Mitra Baru Sekolah',
    );
    await _save(t);
    expect(repo.sent, hasLength(1));
    expect(find.text('Koneksi terputus.'), findsOneWidget);
    await _save(t);
    expect(repo.sent, hasLength(2));
    expect(
      repo.sent.first['token_pembuatan'],
      repo.sent.last['token_pembuatan'],
    );
    expect(repo.methods, ['POST', 'POST']);
  });
  for (final kind in humasWorkKinds) {
    testWidgets('paginasi $kind memakai halaman berikutnya', (t) async {
      final c = humasMap(_fixtures['wakil_pimpinan_humas']),
          repo = _Repo(c)..multiPage = true;
      await _mount(t, repo, c, '/humas/$kind');
      await _scroll(t, find.text('Muat berikutnya'));
      await t.tap(find.text('Muat berikutnya'));
      await t.pumpAndSettle();
      expect(repo.queries.map((q) => q['halaman']), [1, 2]);
      await _scroll(t, find.text('Data halaman kedua'));
      expect(find.text('Data halaman kedua'), findsOneWidget);
    });
    for (final size in [
      const Size(320, 640),
      const Size(390, 844),
      const Size(600, 900),
      const Size(1024, 768),
    ]) {
      for (final scale in [1.0, 2.0]) {
        testWidgets('layout $kind ${size.width}px teks $scale', (t) async {
          final c = humasMap(_fixtures['wakil_pimpinan_humas']),
              repo = _Repo(c);
          await _mount(t, repo, c, '/humas/$kind', size: size, scale: scale);
          await _scroll(
            t,
            find.text('${humasMap(repo.record(kind)['detail'])['nama']}'),
          );
          expect(t.takeException(), isNull);
          if (size.width == 390 && scale == 1) await _shot(t, '$kind-list');
          await t.tap(find.byType(FloatingActionButton));
          await t.pumpAndSettle();
          await _scroll(t, find.widgetWithText(FilledButton, 'Simpan'));
          expect(t.takeException(), isNull);
          if (size.width == 390 && scale == 1) await _shot(t, '$kind-form');
        });
      }
    }
  }
  testWidgets('pencabutan izin menghapus daftar lama dan tombol tambah', (
    t,
  ) async {
    final c = humasMap(_fixtures['wakil_pimpinan_humas']), repo = _Repo(c);
    await _mount(t, repo, c, '/humas/program');
    repo.denied = true;
    await t.tap(find.byTooltip('Muat ulang'));
    await t.pumpAndSettle();
    expect(find.text('Program Humas'), findsNothing);
    expect(find.byType(FloatingActionButton), findsNothing);
  });
  testWidgets('portofolio siap tidak menyediakan edit atau tambah butir', (
    t,
  ) async {
    final c = humasMap(_fixtures['wakil_pimpinan_humas']), repo = _Repo(c);
    final r = repo.record('akreditasi');
    repo.overrides['akreditasi'] = {
      ...humasMap(r['detail']),
      'status': 'siap',
      'hak_akses': {
        'dapat_kelola': false,
        'dapat_ubah_status': true,
        'dapat_ekspor': true,
      },
    };
    await _mount(
      t,
      repo,
      c,
      '/humas/akreditasi/${humasMap(r['detail'])['id']}',
    );
    await _scroll(t, find.text('Ubah status portofolio'));
    expect(find.text('Edit Portofolio Akreditasi'), findsNothing);
    expect(find.text('Ubah status portofolio'), findsOneWidget);
  });
  testWidgets('lembar cetak memakai endpoint asli bukan tampilan baru', (
    t,
  ) async {
    final c = humasMap(_fixtures['wakil_pimpinan_humas']),
        repo = _Repo(c),
        r = repo.record('program');
    await _mount(t, repo, c, '/humas/program/${humasMap(r['detail'])['id']}');
    await _scroll(t, find.text('Unduh lembar cetak (HTML)'));
    await t.tap(find.text('Unduh lembar cetak (HTML)'));
    await t.pumpAndSettle();
    expect(
      repo.downloads.single,
      'program/${humasMap(r['detail'])['id']}/cetak',
    );
  });
  for (final entry in const {
    'program': ('laporan', 'Laporan pelaksanaan'),
    'komite': ('program-komite', 'Program komite'),
    'mitra': ('mou', 'Daftar MoU'),
    'akreditasi': ('butir', 'Butir dan bukti'),
  }.entries) {
    testWidgets(
      'alur menu ${entry.key} membuka daftar dan detail ${entry.value.$1}',
      (t) async {
        final c = humasMap(_fixtures['wakil_pimpinan_humas']),
            repo = _Repo(c),
            parent = humasMap(repo.record(entry.key)['detail']),
            child = repo.record(entry.value.$1);
        await _mount(t, repo, c, '/humas/${entry.key}/${parent['id']}');
        await _scroll(t, find.text(entry.value.$2));
        await t.tap(find.text(entry.value.$2));
        await t.pumpAndSettle();
        expect(find.byType(HumasWorkList), findsOneWidget);
        final detail = humasMap(child['detail']);
        final target = find.descendant(
          of: find.byType(ListView).last,
          matching: find.text('${detail['nama'] ?? detail['judul']}'),
        );
        await _scroll(t, target);
        await t.tap(target);
        await t.pumpAndSettle();
        expect(find.byType(HumasWorkDetail), findsOneWidget);
        expect(repo.paths, contains('${child['base']}/${detail['id']}'));
        expect(t.takeException(), isNull);
      },
    );
  }
  for (final kind in ['akreditasi', 'laporan', 'butir']) {
    testWidgets('tindakan $kind memakai versi asli dan alasan pemeriksaan', (
      t,
    ) async {
      final c = humasMap(_fixtures['wakil_pimpinan_humas']),
          repo = _Repo(c)..conflict = true,
          r = repo.record(kind),
          d = humasMap(r['detail']),
          base = r['base']?.toString() ?? kind;
      final label = kind == 'akreditasi'
          ? 'Ubah status portofolio'
          : kind == 'laporan'
          ? 'Finalisasi laporan'
          : 'Periksa butir';
      final note = kind == 'akreditasi'
          ? 'alasan'
          : kind == 'laporan'
          ? 'catatan_perubahan'
          : 'catatan_pemeriksaan';
      await _mount(
        t,
        repo,
        c,
        '/humas/program',
        home: HumasWorkDetail(kind: kind, base: base, id: humasInt(d['id'])),
      );
      await _scroll(t, find.text(label));
      await t.tap(find.text(label));
      await t.pumpAndSettle();
      await _scroll(t, find.byKey(Key('humas-field-$note')));
      await t.enterText(
        find.byKey(Key('humas-field-$note')),
        'Pemeriksaan data dan bukti selesai',
      );
      await _save(t);
      await _save(t);
      expect(repo.sent.map((p) => p['versi']), [d['versi'], d['versi']]);
      expect(repo.sent.first[note], 'Pemeriksaan data dan bukti selesai');
      expect(repo.methods, ['POST', 'POST']);
    });
  }
  for (final supporting in [false, true]) {
    testWidgets(
      'hubungkan ${supporting ? 'dokumen pendukung' : 'agenda'} memakai versi daftar',
      (t) async {
        final c = humasMap(_fixtures['wakil_pimpinan_humas']),
            repo = _Repo(c),
            m = humasMap(repo.record('mitra')['detail']);
        final path = supporting
            ? 'kemitraan/77/pendukung'
            : 'mitra/${m['id']}/agenda';
        repo.responses[path] = {
          'items': [],
          'paginasi': {
            'halaman': 1,
            'total': 0,
            'ada_halaman_berikutnya': false,
          },
          'dapat_kelola': true,
          'versi': 4,
          'mou': {'versi': 4},
          'jenis': {'proposal': 'Proposal'},
        };
        repo.responses[supporting ? '$path/pilihan' : 'mitra/referensi'] = {
          supporting ? 'items' : 'agenda': [
            {'id': 9, 'id_versi': 11, 'judul': 'Dokumen / agenda pilihan'},
          ],
        };
        await _mount(
          t,
          repo,
          c,
          '/humas/program',
          home: HumasWorkRelations(
            path: path,
            base: 'mitra',
            recordId: humasInt(m['id']),
            repository: repo,
            supporting: supporting,
          ),
        );
        await t.tap(find.byType(FloatingActionButton));
        await t.pumpAndSettle();
        final note = supporting ? 'catatan' : 'catatan_perubahan';
        await _scroll(t, find.byKey(Key('humas-field-$note')));
        await t.enterText(
          find.byKey(Key('humas-field-$note')),
          'Tautkan sumber untuk pelaksanaan kegiatan',
        );
        await _save(t);
        expect(repo.sent.single['versi'], 4);
        expect(
          repo.sent.single[supporting ? 'id_versi' : 'agenda_humas_id'],
          supporting ? '11' : '9',
        );
        expect(repo.sendPaths.single, path);
        expect(t.takeException(), isNull);
      },
    );
  }
  testWidgets(
    'nomor pengurus pada respons lama tetap disembunyikan bagi pembaca',
    (t) async {
      final c = humasMap(_fixtures['pimpinan']),
          repo = _Repo(c),
          r = repo.record('komite'),
          d = humasMap(r['detail']);
      repo.overrides['komite'] = {
        ...d,
        'pengurus': [
          {
            'nama': 'Ketua Komite',
            'jabatan': 'ketua',
            'aktif': true,
            'nomor_telepon': 'RAHASIA NOMOR',
          },
        ],
      };
      await _mount(t, repo, c, '/humas/komite/${d['id']}');
      await t.drag(find.byType(ListView).first, const Offset(0, -1200));
      await t.pumpAndSettle();
      expect(find.textContaining('RAHASIA NOMOR'), findsNothing);
    },
  );
}

Future<void> _scroll(WidgetTester t, Finder target) async {
  await t.scrollUntilVisible(
    target,
    150,
    scrollable: find
        .descendant(
          of: find.byType(ListView).last,
          matching: find.byType(Scrollable),
        )
        .first,
    maxScrolls: 100,
    continuous: true,
  );
  await t.pumpAndSettle();
}

Future<void> _save(WidgetTester t) async {
  FocusManager.instance.primaryFocus?.unfocus();
  await t.pumpAndSettle();
  await _scroll(t, find.widgetWithText(FilledButton, 'Simpan'));
  await t.tap(find.widgetWithText(FilledButton, 'Simpan'));
  await t.pumpAndSettle();
}

Future<void> _shot(WidgetTester t, String name) async {
  if (Platform.environment['NUSA_CAPTURE_HUMAS_WIDGETS'] != '1') return;
  await t.runAsync(() async {
    final image =
        await (_capture.currentContext!.findRenderObject()!
                as RenderRepaintBoundary)
            .toImage();
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    image.dispose();
    final dir = Directory(
      '../storage/logs/humas-mobile-alignment/native-tahap-dua',
    );
    await dir.create(recursive: true);
    await File('${dir.path}/$name.png')
        .writeAsBytes(bytes!.buffer.asUint8List());
  });
}

Future<void> _mount(
  WidgetTester t,
  _Repo repo,
  HumasData c,
  String route, {
  Size size = const Size(390, 844),
  double scale = 1,
  Widget? home,
}) async {
  t.view.physicalSize = size;
  t.view.devicePixelRatio = 1;
  addTearDown(t.view.resetPhysicalSize);
  addTearDown(t.view.resetDevicePixelRatio);
  final container = ProviderContainer(
    overrides: [
      authControllerProvider.overrideWith(
        () => _Auth(Pengguna.fromJson(humasMap(c['user']))),
      ),
      menuControllerProvider.overrideWith(
        () => _Menu(MenuCatalog.fromJson(humasMap(c['menu']))),
      ),
      humasRepositoryProvider.overrideWithValue(repo),
      splashGateProvider.overrideWith((ref) async {}),
    ],
  );
  addTearDown(container.dispose);
  await container.read(authControllerProvider.future);
  await container.read(splashGateProvider.future);
  final router = container.read(appRouterProvider)..go(route);
  final theme = AppTheme.light.copyWith(
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
  );
  Widget builder(BuildContext context, Widget? child) => MediaQuery(
    data: MediaQuery.of(context).copyWith(textScaler: TextScaler.linear(scale)),
    child: child!,
  );
  await t.pumpWidget(
    UncontrolledProviderScope(
      container: container,
      child: RepaintBoundary(
        key: _capture,
        child: home == null
            ? MaterialApp.router(
                debugShowCheckedModeBanner: false,
                theme: theme,
                routerConfig: router,
                builder: builder,
              )
            : MaterialApp(
                debugShowCheckedModeBanner: false,
                theme: theme,
                home: home,
                builder: builder,
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

class _Repo extends HumasRepository {
  _Repo(this.c) : super(Dio());
  final HumasData c;
  final paths = <String>[], methods = <String>[], downloads = <String>[];
  final responses = <String, HumasData>{};
  final sendPaths = <String>[];
  final sent = <HumasData>[], queries = <HumasData>[];
  final overrides = <String, HumasData>{};
  bool conflict = false, failOnce = false, multiPage = false, denied = false;
  HumasData record(String kind) => humasMap(humasMap(c['records'])[kind]);
  String kindOf(String path) {
    final keys = humasMap(c['records']).keys.toList()
      ..sort(
        (a, b) => (record(b)['base'] ?? b).toString().length.compareTo(
          (record(a)['base'] ?? a).toString().length,
        ),
      );
    return keys.firstWhere(
      (k) =>
          path == (record(k)['base'] ?? k) ||
          path.startsWith('${record(k)['base'] ?? k}/'),
    );
  }

  @override
  Future<HumasData> get(String path, [HumasData query = const {}]) async {
    paths.add(path);
    if (denied) throw const NetworkException('Akses dicabut.', statusCode: 403);
    if (responses.containsKey(path)) return responses[path]!;
    final kind = kindOf(path), r = record(kind), base = r['base'] ?? kind;
    if (path.endsWith('/referensi')) return humasMap(r['references']);
    if (path == base) {
      queries.add(query);
      final data = humasMap(r['list']);
      return {
        ...data,
        if (multiPage)
          'items': [
            if (query['halaman'] == 2)
              {
                ...humasMap(r['detail']),
                'id': 999,
                'nama': 'Data halaman kedua',
                'judul': 'Data halaman kedua',
              }
            else
              humasMap(r['detail']),
          ],
        'paginasi': {
          ...humasMap(data['paginasi']),
          'halaman': query['halaman'] ?? 1,
          if (multiPage) 'total': 2,
          if (multiPage) 'ada_halaman_berikutnya': query['halaman'] != 2,
        },
      };
    }
    return overrides[kind] ?? humasMap(r['detail']);
  }

  @override
  Future<HumasData> send(
    String path,
    HumasData data, {
    String method = 'POST',
    Object? multipart,
  }) async {
    sent.add({...data});
    sendPaths.add(path);
    methods.add(method);
    if (failOnce) {
      failOnce = false;
      throw const NetworkException('Koneksi terputus.');
    }
    if (conflict) throw const ValidationException('Data berubah. Muat ulang.');
    return {'id': 1};
  }

  @override
  Future<bool> download(String path, String fileName, {HumasData? data}) async {
    downloads.add(path);
    return true;
  }
}
