import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart' hide MenuController;
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
import 'package:nusa/features/humas/presentation/humas_feedback_content.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/humas_hub_view.dart';
import 'package:nusa/features/humas/presentation/humas_list_view.dart';
import 'package:nusa/features/humas/presentation/humas_scan_view.dart';
import 'package:nusa/features/home/presentation/home_view.dart';
import 'package:nusa/features/menu/application/menu_controller.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';

const _parent = Pengguna(
  id: 21,
  nama: 'Orang Tua Mobile',
  username: 'ORT-01',
  jenisAkun: 'Orang tua',
  administrator: false,
  wajibGantiKataSandi: false,
  peran: [],
  izin: [],
);
const _staff = Pengguna(
  id: 10,
  nama: 'Petugas Humas',
  username: 'humas',
  jenisAkun: 'Pegawai',
  administrator: false,
  wajibGantiKataSandi: false,
  peran: ['wakil_pimpinan_humas'],
  izin: [
    'dashboard_humas.lihat',
    'agenda_humas.kelola',
    'pengaduan_humas.kelola',
    'dokumen_humas.kelola',
    'umpan_balik_humas.kelola',
  ],
);
const _questions = <HumasData>[
  {
    'id': 91,
    'urutan': 1,
    'jenis': 'skala',
    'teks': 'Kejelasan informasi sekolah',
    'wajib': true,
  },
  {
    'id': 92,
    'urutan': 2,
    'jenis': 'teks',
    'teks': 'Saran untuk sekolah',
    'wajib': false,
  },
];

void main() {
  test('unduhan menjaga jalur autentikasi dan membaca konflik JSON walau respons berupa bytes', () async {
    final dio = Dio(
      BaseOptions(
        baseUrl: 'https://nusa.sekolah.test/api/v1/',
        headers: {'Authorization': 'Bearer test-only'},
      ),
    );
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          expect(
            options.uri.toString(),
            'https://nusa.sekolah.test/api/v1/humas/dokumen/4/unduh',
          );
          expect(options.headers['Authorization'], 'Bearer test-only');
          handler.reject(
            DioException(
              requestOptions: options,
              type: DioExceptionType.badResponse,
              response: Response(
                requestOptions: options,
                statusCode: 422,
                data: utf8.encode(
                  jsonEncode({
                    'message': 'Data telah berubah.',
                    'errors': {
                      'sidik': ['Dokumen diperbarui petugas lain. Muat ulang.'],
                    },
                  }),
                ),
              ),
            ),
          );
        },
      ),
    );
    await expectLater(
      HumasRepository(dio).download('dokumen/4/unduh', 'dokumen.pdf'),
      throwsA(
        isA<ValidationException>().having(
          (e) => e.errors['sidik']?.single,
          'konflik',
          'Dokumen diperbarui petugas lain. Muat ulang.',
        ),
      ),
    );
  });
  testWidgets(
    'laporan orang tua mengirim boolean multipart benar dan mempertahankan UUID ketika mencoba ulang',
    (tester) async {
      final repo = _FakeRepo()..failOnceSend = true;
      await _mount(
        tester,
        const HumasListView(module: HumasModule.myComplaints),
        repo,
        _parent,
      );
      await tester.tap(find.byType(FloatingActionButton));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const Key('humas-field-judul')),
        'Perbaikan ruang layanan',
      );
      await tester.scrollUntilVisible(
        find.byKey(const Key('humas-field-isi')),
        240,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.enterText(
        find.byKey(const Key('humas-field-isi')),
        'Mohon perbaikan fasilitas di ruang layanan sekolah.',
      );
      await tester.scrollUntilVisible(
        find.widgetWithText(FilledButton, 'Simpan'),
        240,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Simpan'));
      await tester.pumpAndSettle();
      expect(repo.sent.length, 1);
      await tester.scrollUntilVisible(
        find.widgetWithText(FilledButton, 'Simpan'),
        240,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Simpan'));
      await tester.pumpAndSettle();
      expect(repo.sent.length, 2);
      expect(repo.sent[0]['token_pembuatan'], repo.sent[1]['token_pembuatan']);
      expect(
        Map.fromEntries(repo.sentForms[0].fields)['rahasiakan_identitas'],
        '0',
      );
      expect(
        Map.fromEntries(repo.sentForms[1].fields)['judul'],
        'Perbaikan ruang layanan',
      );
      expect(tester.takeException(), isNull);
    },
  );
  test('izin memakai identitas akun, bukan nama role', () {
    expect(HumasModule.invitations.canOpen(_parent), isTrue);
    expect(HumasModule.agenda.canOpen(_parent), isFalse);
    expect(HumasModule.agenda.canManage(_staff), isTrue);
    expect(HumasModule.invitations.canOpen(_staff), isFalse);
    final roleOnly = Pengguna(
      id: 5,
      nama: 'Testing',
      username: 'test',
      jenisAkun: 'Pegawai',
      administrator: false,
      wajibGantiKataSandi: false,
      peran: const ['orang_tua'],
      izin: const [],
    );
    expect(HumasModule.myComplaints.canOpen(roleOnly), isFalse);
    final student = Pengguna(
      id: 6,
      nama: 'Siswa',
      username: 'siswa',
      jenisAkun: 'Siswa',
      administrator: true,
      wajibGantiKataSandi: false,
      peran: const ['administrator'],
      izin: _staff.izin,
    );
    expect(HumasModule.values.any((m) => m.canOpen(student)), isFalse);
  });
  test('paginasi membaca semua halaman, nilai nol bukan data kosong', () {
    final page = HumasPage.fromJson({
      'items': [
        {'id': 0},
      ],
      'paginasi': {'halaman': 2, 'total': 40, 'ada_halaman_berikutnya': true},
    });
    expect(page.page, 2);
    expect(page.hasNext, isTrue);
    expect(page.total, 40);
    expect(humasText(0), '0');
    expect(humasFeedbackAnswers(_questions, {'91': '0', '92': null}), {
      '91': 0,
    });
  });
  test('QR hanya menerima alamat pertemuan NUSA, tidak tautan sembarang', () {
    final base = Uri.parse('https://nusa.sekolah.test/api/v1/');
    final token = List.filled(64, 'A').join();
    expect(
      humasMeetingToken(
        'https://nusa.sekolah.test/presensi-pertemuan/$token',
        base,
      ),
      token,
    );
    for (final qr in [
      'https://lain.test/presensi-pertemuan/$token',
      'https://user@nusa.sekolah.test/presensi-pertemuan/$token',
      'https://nusa.sekolah.test/presensi-pertemuan/$token?next=lain',
      'https://nusa.sekolah.test/pengaduan/$token',
      'kode siswa',
    ]) {
      expect(humasMeetingToken(qr, base), isNull);
    }
  });
  test('UUID pengiriman valid, unik antar operasi', () {
    final a = humasUuid(), b = humasUuid();
    expect(
      a,
      matches(
        RegExp(
          r'^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$',
        ),
      ),
    );
    expect(a, isNot(b));
  });
  test('kategori Humas langsung membuka ringkasan', () {
    expect(nusaMenuGroupDestination(_catalog.groups.single), '/humas');
    for (final module in HumasModule.values) {
      expect(HumasModule.fromMenuCode(module.menuCode), module);
    }
    expect(HumasModule.fromMenuCode('menu-tidak-dikenal'), isNull);
    expect(humasCanViewDashboard(_staff), isTrue);
    expect(humasCanViewDashboard(_parent), isFalse);
  });
  testWidgets('tautan menu Humas lama diarahkan ke dashboard native', (
    tester,
  ) async {
    final container = await _mount(
      tester,
      const SizedBox.shrink(),
      _FakeRepo(),
      _staff,
      route: '/menu/humas',
    );
    expect(
      container.read(appRouterProvider).routeInformationProvider.value.uri.path,
      '/humas',
    );
    expect(find.byType(HumasHubView), findsOneWidget);
    expect(find.text('Ringkasan Humas'), findsOneWidget);
    expect(find.text('5 sub-menu · 5 tersedia'), findsNothing);
    expect(tester.takeException(), isNull);
  });
  for (final module in HumasModule.values) {
    testWidgets('kartu ${module.menuCode} membuka daftar dan detail native', (
      tester,
    ) async {
      final repo = _FakeRepo();
      await _mount(
        tester,
        const SizedBox.shrink(),
        repo,
        module.parentOnly ? _parent : _staff,
        route: '/humas',
      );
      final card = find.byKey(Key('menu-item-${module.menuCode}'));
      await tester.ensureVisible(card);
      await tester.tap(card);
      await tester.pumpAndSettle();
      expect(find.byType(HumasListView), findsOneWidget);
      expect(repo.paths, contains(module.path));
      expect(
        tester.widget<HumasListView>(find.byType(HumasListView)).module,
        module,
      );
      await tester.ensureVisible(find.text('Pertemuan orang tua'));
      await tester.tap(find.text('Pertemuan orang tua'));
      await tester.pumpAndSettle();
      expect(find.byType(HumasDetailView), findsOneWidget);
      expect(repo.paths, contains('${module.path}/4'));
      expect(tester.takeException(), isNull);
    });
  }
  testWidgets('ringkasan diperbarui setelah kembali dari submenu', (
    tester,
  ) async {
    final repo = _FakeRepo();
    final container = await _mount(
      tester,
      const SizedBox.shrink(),
      repo,
      _staff,
      route: '/humas',
    );
    await tester.tap(find.byKey(const Key('menu-item-agenda-humas')));
    await tester.pumpAndSettle();
    repo.dashboardValue = 8;
    container.read(appRouterProvider).pop();
    await tester.pumpAndSettle();
    expect(repo.paths.where((path) => path == 'dashboard'), hasLength(2));
    await tester.scrollUntilVisible(
      find.text('Pertemuan selesai'),
      250,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('8'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets('tarik muat ulang memperbarui katalog dan ringkasan', (
    tester,
  ) async {
    final repo = _FakeRepo();
    final menu = _FakeMenu();
    await _mount(tester, const HumasHubView(), repo, _staff, menu: menu);
    final indicator = tester.widget<RefreshIndicator>(
      find.byType(RefreshIndicator),
    );
    await indicator.onRefresh();
    await tester.pumpAndSettle();
    expect(menu.refreshCount, 1);
    expect(repo.paths.where((path) => path == 'dashboard'), hasLength(2));
    expect(tester.takeException(), isNull);
  });
  testWidgets('menu orang tua tidak menampilkan kartu atau ringkasan petugas', (
    tester,
  ) async {
    final repo = _FakeRepo();
    await _mount(tester, const HumasHubView(), repo, _parent);
    expect(find.byKey(const Key('menu-item-pertemuan-saya')), findsOneWidget);
    expect(find.byKey(const Key('menu-item-pengaduan-saya')), findsOneWidget);
    expect(find.byKey(const Key('menu-item-umpan-balik-saya')), findsOneWidget);
    expect(find.byKey(const Key('menu-item-agenda-humas')), findsNothing);
    expect(find.text('Ringkasan Humas'), findsNothing);
    expect(repo.paths, isEmpty);
  });
  testWidgets('kartu tanpa rute tidak menimbulkan error ketika ditekan', (
    tester,
  ) async {
    const unavailable = MenuEntry(
      code: 'dokumen-humas',
      label: 'Pusat Dokumen Humas',
      description: '',
      initials: 'DH',
      subgroup: null,
      icon: null,
      status: 'segera_hadir',
      route: null,
    );
    final menu = _FakeMenu(
      MenuCatalog(
        generatedAt: _catalog.generatedAt,
        itemCount: 1,
        groups: [
          _catalog.groups.single.copyWithItems([unavailable]),
        ],
      ),
    );
    await _mount(tester, const HumasHubView(), _FakeRepo(), _staff, menu: menu);
    await tester.tap(find.byKey(const Key('menu-item-dokumen-humas')));
    await tester.pumpAndSettle();
    expect(
      find.textContaining('Menu ini belum tersedia untuk akun Anda.'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });
  for (final textScale in [1.0, 2.0]) {
    testWidgets('dashboard 320px dengan skala teks $textScale tanpa overflow', (
      tester,
    ) async {
      await _mount(
        tester,
        const HumasHubView(),
        _FakeRepo(),
        _staff,
        size: const Size(320, 640),
        textScale: textScale,
      );
      await tester.scrollUntilVisible(
        find.text('Agenda & Pertemuan'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Agenda & Pertemuan'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Pertemuan selesai'),
        250,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('3'), findsOneWidget);
      await tester.drag(find.byType(ListView).first, const Offset(0, -2000));
      await tester.pumpAndSettle();
      await tester.drag(find.byType(ListView).first, const Offset(0, 2000));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    });
  }
  testWidgets('pencarian menunggu 550ms dan tidak mengirim setiap huruf', (
    tester,
  ) async {
    final repo = _FakeRepo();
    await _mount(
      tester,
      const HumasListView(module: HumasModule.agenda),
      repo,
      _staff,
    );
    expect(repo.listQueries.length, 1);
    await tester.enterText(find.byType(TextField), 'ra');
    await tester.pump(const Duration(milliseconds: 300));
    await tester.enterText(find.byType(TextField), 'rapat');
    await tester.pump(const Duration(milliseconds: 549));
    expect(repo.listQueries.length, 1);
    await tester.pump(const Duration(milliseconds: 1));
    await tester.pumpAndSettle();
    expect(repo.listQueries.length, 2);
    expect(repo.listQueries.last['kata_kunci'], 'rapat');
    expect(tester.takeException(), isNull);
  });
  testWidgets('respons pencarian lama tidak mengganti hasil baru', (
    tester,
  ) async {
    final repo = _FakeRepo();
    await _mount(
      tester,
      const HumasListView(module: HumasModule.agenda),
      repo,
      _staff,
    );
    repo.pendingSearch = true;
    await tester.enterText(find.byType(TextField), 'lama');
    await tester.pump(const Duration(milliseconds: 550));
    await tester.enterText(find.byType(TextField), 'baru');
    await tester.pump(const Duration(milliseconds: 550));
    repo.pending['baru']!.complete(_list('Agenda baru'));
    await tester.pumpAndSettle();
    repo.pending['lama']!.complete(_list('Agenda lama'));
    await tester.pumpAndSettle();
    expect(find.text('Agenda baru'), findsOneWidget);
    expect(find.text('Agenda lama'), findsNothing);
  });
  testWidgets(
    'orang tua tidak memanggil API dokumen petugas meski URL dibuka langsung',
    (tester) async {
      final repo = _FakeRepo();
      await _mount(
        tester,
        const HumasDetailView(module: HumasModule.documents, id: 4),
        repo,
        _parent,
      );
      expect(repo.paths, isEmpty);
      expect(
        find.text('Menu ini tidak tersedia untuk akun Anda.'),
        findsOneWidget,
      );
    },
  );
  for (final module in [
    HumasModule.agenda,
    HumasModule.documents,
    HumasModule.complaints,
    HumasModule.feedback,
    HumasModule.invitations,
    HumasModule.myComplaints,
    HumasModule.myFeedback,
  ]) {
    testWidgets(
      '${module.path} detail dapat digulir tanpa overflow pada 320px',
      (tester) async {
        await _mount(
          tester,
          HumasDetailView(module: module, id: 4),
          _FakeRepo(),
          module.parentOnly ? _parent : _staff,
          size: const Size(320, 640),
        );
        await tester.drag(find.byType(ListView).first, const Offset(0, -2000));
        await tester.pumpAndSettle();
        await tester.drag(find.byType(ListView).first, const Offset(0, 2000));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      },
    );
  }
  testWidgets('umpan balik wajib memilih jawaban dan nol dapat dikirim', (
    tester,
  ) async {
    HumasData? submitted;
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: HumasFormView(
          title: 'Isi Umpan Balik',
          fields: humasFeedbackFields(_questions),
          onSave: (v) async {
            submitted = humasFeedbackAnswers(_questions, v);
          },
        ),
      ),
    );
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();
    expect(submitted, isNull);
    await tester.tap(find.byKey(const Key('humas-field-91')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tidak menilai').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();
    expect(submitted, {'91': 0});
    expect(tester.takeException(), isNull);
  });
  testWidgets(
    'form mempertahankan draft dan versi ketika server menolak data basi',
    (tester) async {
      final payloads = <HumasData>[];
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.light,
          home: HumasFormView(
            title: 'Simpan',
            fields: const [HumasField('catatan', 'Catatan')],
            onSave: (v) async {
              payloads.add({...v, 'versi': 7});
              throw const ValidationException(
                'Data telah berubah. Muat ulang sebelum mencoba kembali.',
              );
            },
          ),
        ),
      );
      await tester.enterText(find.byType(TextFormField), 'Masukan penting');
      await tester.tap(find.widgetWithText(FilledButton, 'Simpan'));
      await tester.pumpAndSettle();
      expect(find.text('Masukan penting'), findsOneWidget);
      expect(find.textContaining('Data telah berubah'), findsOneWidget);
      await tester.tap(find.widgetWithText(FilledButton, 'Simpan'));
      await tester.pumpAndSettle();
      expect(payloads.map((p) => p['versi']), [7, 7]);
      expect(tester.takeException(), isNull);
    },
  );
}

Future<ProviderContainer> _mount(
  WidgetTester tester,
  Widget child,
  _FakeRepo repo,
  Pengguna user, {
  Size size = const Size(390, 780),
  String? route,
  _FakeMenu? menu,
  double textScale = 1,
}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  tester.platformDispatcher.textScaleFactorTestValue = textScale;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
  final container = ProviderContainer(
    overrides: [
      authControllerProvider.overrideWith(() => _FakeAuth(user)),
      humasRepositoryProvider.overrideWithValue(repo),
      menuControllerProvider.overrideWith(() => menu ?? _FakeMenu()),
      splashGateProvider.overrideWith((ref) async {}),
    ],
  );
  await container.read(authControllerProvider.future);
  await container.read(splashGateProvider.future);
  addTearDown(container.dispose);
  final router = route == null ? null : container.read(appRouterProvider);
  if (route != null) router!.go(route);
  final app = route == null
      ? MaterialApp(theme: AppTheme.light, home: child)
      : MaterialApp.router(theme: AppTheme.light, routerConfig: router);
  await tester.pumpWidget(
    UncontrolledProviderScope(container: container, child: app),
  );
  await tester.pumpAndSettle();
  return container;
}

class _FakeAuth extends AuthController {
  _FakeAuth(this.user);
  final Pengguna user;
  @override
  Future<AuthState> build() async => AuthState(
    session: AuthSession(token: 'test-token', pengguna: user),
  );
}

class _FakeMenu extends MenuController {
  _FakeMenu([MenuCatalog? catalog]) : catalog = catalog ?? _catalog;
  final MenuCatalog catalog;
  int refreshCount = 0;
  @override
  Future<MenuCatalog> build() async => catalog;
  @override
  Future<void> refresh() async {
    refreshCount++;
    state = AsyncData(catalog);
  }
}

final _catalog = MenuCatalog(
  generatedAt: DateTime(2026, 10, 5),
  itemCount: 8,
  groups: [
    MenuGroup(
      code: 'humas',
      label: 'Humas',
      description: '',
      icon: 'humas',
      items: [
        const MenuEntry(
          code: 'dashboard-humas',
          label: 'Dashboard Humas',
          description: '',
          initials: 'DH',
          subgroup: null,
          icon: null,
          status: 'tersedia',
          route: '/humas',
        ),
        for (final module in HumasModule.values)
          MenuEntry(
            code: module.menuCode,
            label: module.title,
            description: '',
            initials: '',
            subgroup: null,
            icon: null,
            status: 'tersedia',
            route: module.route,
          ),
      ],
    ),
  ],
);
HumasData _list(String title) => {
  'items': [
    {'id': 4, 'judul': title, 'status_label': 'Terjadwal'},
  ],
  'paginasi': {'halaman': 1, 'total': 1, 'ada_halaman_berikutnya': false},
};

class _FakeRepo extends HumasRepository {
  _FakeRepo() : super(Dio());
  final paths = <String>[];
  final listQueries = <HumasData>[];
  bool pendingSearch = false;
  final pending = <String, Completer<HumasData>>{};
  bool failOnceSend = false;
  final sent = <HumasData>[];
  final sentForms = <FormData>[];
  int dashboardValue = 3;
  @override
  Future<HumasData> send(
    String path,
    HumasData data, {
    String method = 'POST',
    Object? multipart,
  }) async {
    sent.add({...data});
    if (multipart is FormData) sentForms.add(multipart);
    if (failOnceSend) {
      failOnceSend = false;
      throw const NetworkException('Koneksi terputus. Silakan coba lagi.');
    }
    return {'id': 4};
  }

  @override
  Future<HumasData> get(String path, [HumasData query = const {}]) async {
    paths.add(path);
    if (path == 'pengaduan-saya/referensi') {
      return {
        'jenis': {'pengaduan': 'Pengaduan'},
        'kategori': {'layanan': 'Layanan'},
        'token_pembuatan': humasUuid(),
      };
    }
    if (path == 'dashboard/referensi') {
      return {
        'tahun': [],
        'periode': {'tahunan': 'Tahunan', 'ganjil': 'Ganjil'},
      };
    }
    if (path == 'dashboard') {
      return {
        'label_periode': 'Tahun 2026/2027',
        'metrik': {
          'agenda': {
            'label': 'Pertemuan selesai',
            'jumlah': dashboardValue,
            'dasar': 'Periode aktif',
          },
        },
        'agenda': [],
      };
    }
    if (!path.contains('/')) {
      listQueries.add({...query});
      if (pendingSearch) {
        final key = '${query['kata_kunci']}';
        pending[key] = Completer<HumasData>();
        return pending[key]!.future;
      }
      final result = _list('Pertemuan orang tua');
      if (path == 'pertemuan-saya') {
        result['items'] = [
          {'agenda': humasItems(result['items']).single, 'kehadiran': {}},
        ];
      }
      return result;
    }
    final record = <String, dynamic>{
      'id': 4,
      'judul': 'Pertemuan dan layanan sekolah',
      'tempat': 'Aula sekolah',
      'status_label': 'Aktif',
      'status': 'aktif',
      'waktu_mulai': '2026-10-05T08:00:00+07:00',
      'waktu_selesai': '2026-10-05T12:00:00+07:00',
      'topik': 'Topik pertemuan',
      'rekap_presensi': {'total': 4, 'hadir': 2, 'belum_dicatat': 2},
      'versi': 7,
      'sidik': 'checksum',
      'berkas': {'nama': 'berkas.pdf'},
      'isi': 'Laporan untuk memperbaiki fasilitas layanan sekolah.',
      'kanal': 'akun_orang_tua',
      'kategori': 'layanan',
      'nomor': 'HMS-0004',
      'rahasiakan_identitas': true,
      'pengantar': 'Masukan untuk sekolah',
      'pertanyaan': _questions,
      'mulai_pada': '2026-10-05T08:00:00+07:00',
      'selesai_pada': '2026-10-12T12:00:00+07:00',
      'dapat_mengirim': true,
      'token_pengiriman': humasUuid(),
      'rekap': {
        'sasaran': 10,
        'respons': 5,
        'persen': 50,
        'pertanyaan': {
          '91': {'menilai': 4, 'rata': 3.5, 'puas': 75},
          '92': {'teks': 3},
        },
      },
    };
    if (path.startsWith('pertemuan-saya/')) {
      return {
        'agenda': record,
        'kehadiran': {'status_label': 'Belum dicatat'},
      };
    }
    return record;
  }
}
