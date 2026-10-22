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
import 'package:nusa/features/humas/presentation/humas_detail_view.dart';
import 'package:nusa/features/humas/presentation/humas_list_view.dart';
import 'package:nusa/features/humas/presentation/humas_menu_sections.dart';
import 'package:nusa/features/menu/application/menu_controller.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';
import 'package:nusa/features/menu/presentation/widgets/menu_group_content.dart';

final _contracts = humasMap(
  jsonDecode(File('test/fixtures/humas_api_contract.json').readAsStringSync()),
);
final _capture = GlobalKey();

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    await (FontLoader('Roboto')
          ..addFont(rootBundle.load('assets/fonts/roboto-regular.ttf'))
          ..addFont(rootBundle.load('assets/fonts/roboto-bold.ttf')))
        .load();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
  });
  test(
    'menu backend asli cocok dengan izin native untuk sebelas jenis akun',
    () {
      for (final entry in _contracts.entries) {
        final c = humasMap(entry.value),
            user = Pengguna.fromJson(humasMap(c['user']));
        final menu = MenuCatalog.fromJson(humasMap(c['menu']));
        for (final module in HumasModule.values) {
          final advertised = menu.entryByCode(module.menuCode);
          expect(
            advertised != null,
            module.canOpen(user),
            reason: '${entry.key}/${module.path}',
          );
          if (advertised != null) expect(advertised.route, module.route);
        }
        expect(
          HumasModule.faq.canManage(user),
          humasMap(c['faq'])['dapat_kelola'],
          reason: entry.key,
        );
        if (c['kinerja'] != null) {
          expect(
            HumasModule.performance.canManage(user),
            humasMap(c['kinerja'])['dapat_kelola'],
          );
        }
      }
    },
  );
  for (final role in _contracts.keys) {
    test('bagian menu mempertahankan menu native tanpa duplikasi $role', () {
      final c = humasMap(_contracts[role]);
      final catalog = MenuCatalog.fromJson(humasMap(c['menu']));
      final user = Pengguna.fromJson(humasMap(c['user']));
      final sections = humasMenuSections(
        group: catalog.groupByCode('humas'),
        user: user,
        workspaces: humasItems(humasMap(c['dashboard'])['ruang_kerja']),
      );
      final entries = sections.expand((s) => s.items).toList();
      final codes = entries.map((link) => link.entry.code).toList();
      expect(codes, contains('faq-humas'), reason: role);
      expect(codes.toSet().length, codes.length, reason: role);
      for (final module in HumasModule.values) {
        if (module.canOpen(user)) {
          expect(
            codes.where((code) => code == module.menuCode),
            hasLength(1),
            reason: '$role/${module.menuCode}: $codes',
          );
        }
      }
    });
    testWidgets('hub dan FAQ sesuai kontrak backend $role', (tester) async {
      final c = humasMap(_contracts[role]),
          repo = _ContractRepo(c),
          user = Pengguna.fromJson(humasMap(c['user']));
      await _mount(tester, repo, c, route: '/humas');
      if (role == 'wakil_pimpinan_humas') {
        await _screenshot(tester, 'hub-humas-390');
      }
      expect(find.byType(NusaMenuGroupHeader), findsOneWidget);
      expect(find.byType(ExpansionTile), findsNothing);
      await _scroll(tester, find.byKey(const Key('menu-item-faq-humas')));
      expect(find.byKey(const Key('menu-item-faq-humas')), findsOneWidget);
      if (!HumasModule.performance.canOpen(user)) {
        expect(find.byKey(const Key('menu-item-kinerja-humas')), findsNothing);
      }
      if (humasParentUser(user) || humasStudentUser(user)) {
        expect(find.text('Ringkasan Humas'), findsNothing);
        expect(repo.paths, isNot(contains('dashboard')));
      }
      await tester.ensureVisible(find.byKey(const Key('menu-item-faq-humas')));
      await tester.tap(find.byKey(const Key('menu-item-faq-humas')));
      await tester.pumpAndSettle();
      expect(find.byType(HumasListView), findsOneWidget);
      expect(
        find.byType(FloatingActionButton),
        HumasModule.faq.canManage(user) ? findsOneWidget : findsNothing,
      );
      await tester.ensureVisible(find.text('FAQ semua'));
      await tester.tap(find.text('FAQ semua'));
      await tester.pumpAndSettle();
      expect(find.byType(HumasDetailView), findsOneWidget);
      expect(
        find.text('Hubungi operator sekolah untuk informasi lebih lanjut.'),
        findsOneWidget,
      );
      expect(
        find.widgetWithText(OutlinedButton, 'Edit FAQ'),
        HumasModule.faq.canManage(user) ? findsOneWidget : findsNothing,
      );
      expect(tester.takeException(), isNull);
      if (role == 'orang_tua_a' || role == 'wakil_pimpinan_humas') {
        await _screenshot(tester, 'faq-$role');
      }
    });
  }
  for (final role in ['administrator', 'wakil_pimpinan_humas', 'pimpinan']) {
    testWidgets(
      'laporan native mengikuti akses $role dan unduhan memakai endpoint cetak yang sama',
      (tester) async {
        final c = humasMap(_contracts[role]),
            repo = _ContractRepo(c),
            data = humasMap(c['kinerja']);
        await _mount(tester, repo, c, route: '/humas/kinerja/${data['id']}');
        expect(find.text(data['judul']), findsOneWidget);
        await _scroll(tester, find.text('Unduh lembar cetak (HTML)'));
        await tester.tap(find.text('Unduh lembar cetak (HTML)'));
        await tester.pumpAndSettle();
        expect(repo.downloads.single, 'kinerja/${data['id']}/cetak');
        expect(
          find.text('Edit draf laporan'),
          role == 'pimpinan' ? findsNothing : findsOneWidget,
        );
        expect(tester.takeException(), isNull);
      },
    );
  }
  testWidgets(
    'perubahan izin sumber menyembunyikan semua tindakan pengelolaan laporan',
    (tester) async {
      final c = humasMap(_contracts['wakil_pimpinan_humas']),
          repo = _ContractRepo(c);
      repo.reportOverride = {
        ...humasMap(c['kinerja']),
        'dapat_kelola': false,
        'snapshot': {
          ...humasMap(humasMap(c['kinerja'])['snapshot']),
          'akses_dibatasi': true,
        },
      };
      await _mount(
        tester,
        repo,
        c,
        route: '/humas/kinerja/${humasMap(c['kinerja'])['id']}',
      );
      await _scroll(tester, find.text('Akses sumber terbatas'));
      await tester.drag(find.byType(ListView).first, const Offset(0, -5000));
      await tester.pumpAndSettle();
      expect(find.text('Finalisasi laporan'), findsNothing);
      expect(find.text('Edit draf laporan'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'final terkunci hanya memberi pilihan buka revisi bagi pengelola',
    (tester) async {
      final c = humasMap(_contracts['wakil_pimpinan_humas']),
          repo = _ContractRepo(c);
      repo.reportOverride = {
        ...humasMap(c['kinerja']),
        'status': 'final',
        'status_label': 'Final',
      };
      await _mount(
        tester,
        repo,
        c,
        route: '/humas/kinerja/${humasMap(c['kinerja'])['id']}',
      );
      await _scroll(tester, find.text('Buka revisi'));
      expect(find.text('Finalisasi laporan'), findsNothing);
      expect(find.text('Edit draf laporan'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'konfirmasi final sementara dan versi asli dipertahankan setelah penolakan',
    (tester) async {
      final c = humasMap(_contracts['wakil_pimpinan_humas']),
          repo = _ContractRepo(c)..requireTemporaryConfirmation = true;
      await _mount(
        tester,
        repo,
        c,
        route: '/humas/kinerja/${humasMap(c['kinerja'])['id']}',
      );
      await _scroll(tester, find.text('Finalisasi laporan'));
      await tester.tap(find.text('Finalisasi laporan'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const Key('humas-field-alasan')),
        'Laporan semester sudah diperiksa',
      );
      await tester.tap(find.widgetWithText(FilledButton, 'Simpan'));
      await tester.pumpAndSettle();
      expect(find.text('Konfirmasikan laporan sementara.'), findsOneWidget);
      await tester.tap(find.byType(SwitchListTile));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Simpan'));
      await tester.pumpAndSettle();
      expect(repo.sent.length, 2);
      expect(repo.sent.last['versi'], repo.sent.first['versi']);
      expect(repo.sent.first['konfirmasi_sementara'], false);
      expect(repo.sent.last['konfirmasi_sementara'], true);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('FAQ create mempertahankan UUID ketika jaringan terputus', (
    tester,
  ) async {
    final c = humasMap(_contracts['wakil_pimpinan_humas']),
        repo = _ContractRepo(c)..failOnce = true;
    await _mount(tester, repo, c, route: '/humas/faq');
    await tester.tap(find.byType(FloatingActionButton));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('humas-field-pertanyaan')),
      'Bagaimana menghubungi sekolah?',
    );
    await tester.enterText(
      find.byKey(const Key('humas-field-jawaban')),
      'Hubungi operator sekolah pada jam layanan.',
    );
    await _scroll(tester, find.widgetWithText(FilledButton, 'Simpan'));
    await tester.tap(find.widgetWithText(FilledButton, 'Simpan'));
    await tester.pumpAndSettle();
    await _scroll(tester, find.widgetWithText(FilledButton, 'Simpan'));
    await tester.tap(find.widgetWithText(FilledButton, 'Simpan'));
    await tester.pumpAndSettle();
    expect(repo.sent.length, 2);
    expect(
      repo.sent.first['token_pembuatan'],
      repo.sent.last['token_pembuatan'],
    );
    expect(repo.methods, ['POST', 'POST']);
    expect(tester.takeException(), isNull);
  });
  testWidgets(
    'FAQ pagination menambah halaman dua dan gagal izin menghapus data lama',
    (tester) async {
      final c = humasMap(_contracts['orang_tua_a']),
          repo = _ContractRepo(c)..multiPage = true;
      await _mount(tester, repo, c, route: '/humas/faq');
      await _scroll(tester, find.text('Muat berikutnya'));
      await tester.tap(find.text('Muat berikutnya'));
      await tester.pumpAndSettle();
      expect(repo.queries.last['halaman'], 2);
      expect(find.text('FAQ halaman kedua'), findsOneWidget);
      repo.deny = true;
      await tester.tap(find.byIcon(Icons.refresh_rounded));
      await tester.pumpAndSettle();
      expect(find.text('FAQ halaman kedua'), findsNothing);
      expect(find.text('FAQ semua'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('filter urusan dari tujuh bidang diteruskan ke dokumen native', (
    tester,
  ) async {
    final c = humasMap(_contracts['wakil_pimpinan_humas']),
        repo = _ContractRepo(c);
    await _mount(tester, repo, c, route: '/humas');
    await _scroll(tester, find.text('Arsip Komite'));
    await tester.tap(find.text('Arsip Komite'));
    await tester.pumpAndSettle();
    expect(repo.paths, contains('dokumen'));
    expect(repo.queries.last['urusan'], 'komite');
    expect(tester.takeException(), isNull);
  });
  test('menu dashboard lama tidak menghidupkan izin yang sudah dicabut', () {
    final c = humasMap(_contracts['wakil_pimpinan_humas']);
    final catalog = MenuCatalog.fromJson(humasMap(c['menu']));
    final userData = humasMap(c['user']);
    final user = Pengguna.fromJson({
      ...userData,
      'izin': (userData['izin'] as List)
          .where((p) => !p.toString().startsWith('dokumen_humas.'))
          .toList(),
    });
    final sections = humasMenuSections(
      group: catalog.groupByCode('humas'),
      user: user,
      workspaces: humasItems(humasMap(c['dashboard'])['ruang_kerja']),
    );
    expect(
      sections
          .expand((s) => s.items)
          .where((link) => link.native?.code == 'dokumen-humas'),
      isEmpty,
    );
  });
  testWidgets('fitur web berikon tidak membuka rute native atau API palsu', (
    tester,
  ) async {
    final c = humasMap(_contracts['wakil_pimpinan_humas']),
        repo = _ContractRepo(c);
    final container = await _mount(tester, repo, c, route: '/humas');
    await _scroll(
      tester,
      find.byKey(const Key('menu-item-aset-promosi-humas')),
    );
    final before = repo.paths.length;
    await tester.tap(find.byKey(const Key('menu-item-aset-promosi-humas')));
    await tester.pumpAndSettle();
    expect(
      find.text('Bank Aset Promosi tersedia melalui NUSA web.'),
      findsOneWidget,
    );
    expect(
      container.read(appRouterProvider).routeInformationProvider.value.uri.path,
      '/humas',
    );
    expect(repo.paths.length, before);
    expect(tester.takeException(), isNull);
  });
  testWidgets('header Data Sekolah tetap memakai komponen yang sama', (
    tester,
  ) async {
    final c = {...humasMap(_contracts['administrator'])};
    final menu = humasMap(c['menu']);
    c['menu'] = {
      ...menu,
      'kelompok': [
        ...humasItems(menu['kelompok']),
        {
          'kode': 'data-sekolah',
          'label': 'Data Sekolah',
          'deskripsi':
              'Data induk sekolah, pegawai, siswa, kelas, dan identitas.',
          'ikon': 'school',
          'items': [
            for (final item in const {
              'tahun-pelajaran': ('Tahun Pelajaran', 'Periode Akademik'),
              'pegawai': ('Pegawai', 'Pegawai'),
              'siswa': ('Siswa', 'Siswa dan Kelas'),
              'kelas': ('Kelas', 'Siswa dan Kelas'),
              'penempatan-siswa': ('Penempatan Siswa', 'Siswa dan Kelas'),
              'kenaikan-kelas': ('Kenaikan Kelas', 'Siswa dan Kelas'),
              'foto-identitas': ('Foto Identitas', 'Identitas dan Kartu'),
              'kartu-pegawai': ('Kartu Pegawai', 'Identitas dan Kartu'),
              'kartu-pelajar': ('Kartu Pelajar', 'Identitas dan Kartu'),
            }.entries)
              {
                'kode': item.key,
                'label': item.value.$1,
                'subkelompok': item.value.$2,
                'status': 'tersedia',
                'rute': '/${item.key}',
              },
          ],
        },
      ],
    };
    await _mount(tester, _ContractRepo(c), c, route: '/menu/data-sekolah');
    expect(find.byType(NusaMenuGroupHeader), findsOneWidget);
    expect(find.text('Menu Data Sekolah'), findsOneWidget);
    expect(find.byType(ExpansionTile), findsNothing);
    expect(tester.takeException(), isNull);
    await _screenshot(tester, 'menu-data-sekolah-390');
  });
  for (final size in [
    const Size(320, 640),
    const Size(390, 844),
    const Size(600, 900),
    const Size(1024, 768),
  ]) {
    for (final scale in [1.0, 2.0]) {
      testWidgets(
        'grid tujuh bidang tanpa overflow ${size.width}px teks $scale',
        (tester) async {
          final c = humasMap(_contracts['wakil_pimpinan_humas']);
          final repo = _ContractRepo(c);
          await _mount(
            tester,
            repo,
            c,
            route: '/humas',
            size: size,
            scale: scale,
          );
          final header = tester.widget<NusaMenuGroupHeader>(
            find.byType(NusaMenuGroupHeader),
          );
          expect(
            header.group.items.map((item) => item.code).toSet().length,
            header.group.items.length,
          );
          expect(
            header.group.items
                .where((item) => !item.isAvailable)
                .every((item) => item.status == 'web'),
            isTrue,
          );
          await _screenshot(tester, 'hub-grid-${size.width.toInt()}-$scale');
          for (final workspace in humasItems(
            humasMap(c['dashboard'])['ruang_kerja'],
          )) {
            await _scroll(
              tester,
              find.byKey(Key('humas-workspace-${workspace['kode']}')),
            );
            expect(find.byType(ExpansionTile), findsNothing);
            expect(tester.takeException(), isNull);
          }
          await _scroll(tester, find.byKey(const Key('menu-item-faq-humas')));
          expect(tester.takeException(), isNull);
        },
      );
    }
  }
  testWidgets(
    'riwayat privat tidak dipertahankan pada layar setelah akses dicabut',
    (tester) async {
      final c = humasMap(_contracts['wakil_pimpinan_humas']),
          repo = _ContractRepo(c);
      await _mount(
        tester,
        repo,
        c,
        route: '/humas/faq/${humasMap(c['faq_detail'])['id']}',
      );
      await _scroll(tester, find.text('Riwayat perubahan'));
      await tester.tap(find.text('Riwayat perubahan'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Catatan riwayat privat'), findsOneWidget);
      repo.deny = true;
      await tester.tap(find.byIcon(Icons.refresh));
      await tester.pumpAndSettle();
      expect(find.textContaining('Catatan riwayat privat'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('tombol himpun mengikuti pencabutan izin sumber dari server', (
    tester,
  ) async {
    final c = humasMap(_contracts['wakil_pimpinan_humas']),
        repo = _ContractRepo(c);
    repo.reportReferencesOverride = {
      ...humasMap(c['kinerja_referensi']),
      'dapat_himpun': false,
    };
    await _mount(tester, repo, c, route: '/humas/kinerja');
    expect(find.byType(FloatingActionButton), findsNothing);
    expect(tester.takeException(), isNull);
  });
  for (final confirm in [false, true]) {
    testWidgets('arsip lama tidak dikonfirmasi otomatis: konfirmasi $confirm', (
      tester,
    ) async {
      final c = humasMap(_contracts['wakil_pimpinan_humas']),
          repo = _ContractRepo(c);
      await _mount(tester, repo, c, route: '/humas/dokumen/4');
      await _scroll(tester, find.text('Ubah informasi dokumen'));
      await tester.tap(find.text('Ubah informasi dokumen'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const Key('humas-field-judul')),
        'Judul arsip diperbaiki',
      );
      tester.binding.focusManager.primaryFocus?.unfocus();
      await tester.pumpAndSettle();
      if (confirm) {
        await _scroll(tester, find.byType(SwitchListTile));
        await tester.tap(find.byType(SwitchListTile));
        await tester.pumpAndSettle();
      }
      await _scroll(tester, find.widgetWithText(FilledButton, 'Simpan'));
      await tester.tap(find.widgetWithText(FilledButton, 'Simpan'));
      await tester.pumpAndSettle();
      expect(repo.sent.length, 1);
      expect(repo.sent.last.containsKey('urusan'), confirm);
      expect(repo.sent.last.containsKey('jenis_dokumen'), confirm);
      expect(repo.sent.last.containsKey('konfirmasi_klasifikasi'), false);
      expect(tester.takeException(), isNull);
    });
  }
  for (final size in [
    const Size(320, 640),
    const Size(390, 844),
    const Size(1024, 768),
  ]) {
    for (final scale in [1.0, 2.0]) {
      for (final module in [HumasModule.faq, HumasModule.performance]) {
        testWidgets(
          '${module.path} tanpa overflow ${size.width}px teks $scale',
          (tester) async {
            final c = humasMap(_contracts['wakil_pimpinan_humas']),
                repo = _ContractRepo(c),
                d = humasMap(
                  c[module == HumasModule.faq ? 'faq_detail' : 'kinerja'],
                );
            await _mount(
              tester,
              repo,
              c,
              route: '${module.route}/${d['id']}',
              size: size,
              scale: scale,
            );
            await tester.drag(
              find.byType(ListView).first,
              const Offset(0, -5000),
            );
            await tester.pumpAndSettle();
            await tester.drag(
              find.byType(ListView).first,
              const Offset(0, 5000),
            );
            await tester.pumpAndSettle();
            expect(tester.takeException(), isNull);
            if (scale == 1 && size.width == 390) {
              await _screenshot(tester, '${module.path}-390');
            }
          },
        );
      }
    }
  }
}

Future<void> _scroll(WidgetTester tester, Finder target) async {
  await tester.scrollUntilVisible(
    target,
    150,
    scrollable: find.byType(Scrollable).first,
    maxScrolls: 100,
    continuous: true,
  );
  await tester.pumpAndSettle();
}

Future<void> _screenshot(WidgetTester tester, String name) async {
  if (Platform.environment['NUSA_CAPTURE_HUMAS_WIDGETS'] != '1') return;
  await tester.pumpAndSettle();
  await tester.runAsync(() async {
    final image =
        await (_capture.currentContext!.findRenderObject()!
                as RenderRepaintBoundary)
            .toImage();
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    image.dispose();
    final directory = Directory(
      '../storage/logs/humas-mobile-alignment/native',
    );
    await directory.create(recursive: true);
    await File('${directory.path}/$name.png')
        .writeAsBytes(bytes!.buffer.asUint8List());
  });
}

Future<ProviderContainer> _mount(
  WidgetTester tester,
  _ContractRepo repo,
  HumasData c, {
  required String route,
  Size size = const Size(390, 844),
  double scale = 1,
}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
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
  final router = container.read(appRouterProvider);
  router.go(route);
  await tester.pumpWidget(
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
  await tester.pumpAndSettle();
  return container;
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

class _ContractRepo extends HumasRepository {
  _ContractRepo(this.contract) : super(Dio());
  final HumasData contract;
  final paths = <String>[], downloads = <String>[], methods = <String>[];
  final queries = <HumasData>[], sent = <HumasData>[];
  bool failOnce = false,
      requireTemporaryConfirmation = false,
      multiPage = false,
      deny = false;
  HumasData? reportOverride;
  HumasData? reportReferencesOverride;
  @override
  Future<HumasData> get(String path, [HumasData query = const {}]) async {
    paths.add(path);
    if (deny) throw const NetworkException('Akses dicabut.', statusCode: 403);
    if (path == 'dashboard') return humasMap(contract['dashboard']);
    if (path == 'dashboard/referensi') {
      return {
        'tahun': [],
        'periode': {'tahunan': 'Satu tahun'},
      };
    }
    if (path == 'faq/referensi') return humasMap(contract['faq_referensi']);
    if (path == 'kinerja/referensi') {
      return reportReferencesOverride ??
          humasMap(contract['kinerja_referensi']);
    }
    if (path == 'dokumen/referensi') {
      return {
        'kategori': {'sop': 'SOP Humas'},
        'format_berkas': ['pdf'],
        'urusan': {
          'komite': 'Dokumen Komite Sekolah',
          'kedinasan': 'Dokumen Kedinasan Humas',
        },
        'jenis_dokumen': {'notulen': 'Notulen', 'sop': 'SOP'},
      };
    }
    if (path == 'faq') {
      queries.add({...query});
      if (multiPage) {
        return {
          'items': [
            if (query['halaman'] == 2)
              {
                ...humasMap(contract['faq_detail']),
                'id': 999,
                'pertanyaan': 'FAQ halaman kedua',
              }
            else
              humasMap(contract['faq_detail']),
          ],
          'paginasi': {
            'halaman': query['halaman'],
            'total': 2,
            'ada_halaman_berikutnya': query['halaman'] == 1,
          },
        };
      }
      return humasMap(contract['faq']);
    }
    if (path == 'kinerja') {
      queries.add({...query});
      return {
        'items': [humasMap(contract['kinerja'])],
        'paginasi': {'halaman': 1, 'total': 1, 'ada_halaman_berikutnya': false},
      };
    }
    if (path == 'dokumen') {
      queries.add({...query});
      return {
        'items': [],
        'paginasi': {'halaman': 1, 'total': 0, 'ada_halaman_berikutnya': false},
      };
    }
    if (path == 'dokumen/4') {
      return {
        'id': 4,
        'judul': 'Arsip SOP sekolah',
        'kategori': 'sop',
        'kategori_label': 'SOP Humas',
        'urusan': 'kedinasan',
        'jenis_dokumen': 'sop',
        'sidik': 'checksum',
        'klasifikasi': {'perlu_tinjau': true},
        'hak_akses': {'dapat_kelola': true},
      };
    }
    if (path.endsWith('/riwayat')) {
      return {
        'items': [
          {
            'aksi': 'draf_diperbarui',
            'versi': 1,
            'alasan': 'Catatan riwayat privat',
            'created_at': '2026-10-22T10:00:00+07:00',
          },
        ],
        'paginasi': {'halaman': 1, 'total': 1, 'ada_halaman_berikutnya': false},
      };
    }
    if (path.startsWith('faq/')) return humasMap(contract['faq_detail']);
    if (path.startsWith('kinerja/')) {
      return reportOverride ?? humasMap(contract['kinerja']);
    }
    return {};
  }

  @override
  Future<HumasData> send(
    String path,
    HumasData data, {
    String method = 'POST',
    Object? multipart,
  }) async {
    sent.add({...data});
    methods.add(method);
    if (failOnce) {
      failOnce = false;
      throw const NetworkException('Koneksi terputus.');
    }
    if (requireTemporaryConfirmation && data['konfirmasi_sementara'] != true) {
      throw const ValidationException(
        'Konfirmasikan laporan sementara.',
        errors: {
          'konfirmasi_sementara': ['Konfirmasikan laporan sementara.'],
        },
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
