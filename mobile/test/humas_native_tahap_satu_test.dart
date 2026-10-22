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
import 'package:nusa/features/humas/data/humas_photo_picker.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_detail_view.dart';
import 'package:nusa/features/humas/presentation/humas_list_view.dart';
import 'package:nusa/features/humas/presentation/humas_contact_publication_content.dart';
import 'package:nusa/features/menu/application/menu_controller.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';

final _fixtures = humasMap(
  jsonDecode(
    File('test/fixtures/humas_native_tahap_satu.json').readAsStringSync(),
  ),
);
final _capture = GlobalKey();
final _photo = Uint8List.fromList(
  base64Decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1X8AAAAASUVORK5CYII=',
  ),
);

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
  for (final role in ['humas', 'pimpinan', 'kontributor']) {
    for (final module in [HumasModule.contacts, HumasModule.publications]) {
      if (role == 'kontributor' && module == HumasModule.contacts) continue;
      testWidgets(
        'daftar dan detail native ${module.path} mengikuti kontrak $role',
        (t) async {
          final c = humasMap(_fixtures[role]), repo = _Repo(c);
          await _mount(t, repo, c, module.route);
          expect(find.byType(HumasListView), findsOneWidget);
          final canCreate = module == HumasModule.contacts
              ? humasMap(
                      humasMap(c['kontak_referensi'])['hak_akses'],
                    )['dapat_kelola'] ==
                    true
              : humasMap(c['publikasi_referensi'])['dapat_buat'] == true;
          expect(
            find.byType(FloatingActionButton),
            canCreate ? findsOneWidget : findsNothing,
          );
          final data = humasMap(c[module.path]);
          await _scroll(
            t,
            find.text(
              '${data[module == HumasModule.contacts ? 'nama' : 'judul']}',
            ),
          );
          await t.tap(
            find.text(
              '${data[module == HumasModule.contacts ? 'nama' : 'judul']}',
            ),
          );
          await t.pumpAndSettle();
          expect(find.byType(HumasDetailView), findsOneWidget);
          if (module == HumasModule.contacts) {
            expect(
              find.text('Kontak privat dibatasi'),
              role == 'pimpinan' ? findsOneWidget : findsNothing,
            );
          }
          await _shot(t, '${module.path}-$role');
          expect(t.takeException(), isNull);
        },
      );
    }
  }
  testWidgets(
    'kontak privat tetap tersembunyi meski respons lama memuat nilainya',
    (t) async {
      final c = humasMap(_fixtures['pimpinan']), repo = _Repo(c);
      repo.contact = {
        ...repo.contact,
        'nomor_kontak': 'RAHASIA NOMOR',
        'email': 'RAHASIA EMAIL',
        'catatan': 'RAHASIA CATATAN',
      };
      await _mount(t, repo, c, '/humas/kontak/${repo.contact['id']}');
      await t.drag(find.byType(ListView).first, const Offset(0, -2000));
      await t.pumpAndSettle();
      expect(find.textContaining('RAHASIA'), findsNothing);
      expect(find.text('Edit kontak'), findsNothing);
      expect(find.text('Riwayat perubahan'), findsNothing);
    },
  );
  testWidgets('izin dicabut menghilangkan isi lama dan tindakan kontak', (
    t,
  ) async {
    final c = humasMap(_fixtures['humas']), repo = _Repo(c);
    await _mount(t, repo, c, '/humas/kontak/${repo.contact['id']}');
    repo.denied = true;
    await t.tap(find.byTooltip('Muat ulang'));
    await t.pumpAndSettle();
    expect(find.text(repo.contact['nama']), findsNothing);
    expect(find.text('081234567890'), findsNothing);
    expect(find.text('Edit kontak'), findsNothing);
    expect(find.textContaining('Akses dicabut'), findsWidgets);
  });
  for (final role in ['Siswa', 'Orang tua']) {
    for (final module in [HumasModule.contacts, HumasModule.publications]) {
      testWidgets(
        'tautan langsung ${module.path} ditolak sebelum request untuk $role',
        (t) async {
          final c = humasMap(_fixtures['humas']), repo = _Repo(c);
          final user = {
            ...humasMap(c['user']),
            'jenis_akun': role,
            'administrator': true,
          };
          await _mount(t, repo, {...c, 'user': user}, '${module.route}/1');
          expect(repo.paths, isEmpty);
          expect(
            find.text('Menu ini tidak tersedia untuk akun Anda.'),
            findsOneWidget,
          );
        },
      );
    }
  }
  testWidgets('form kontak mempertahankan isi dan UUID ketika koneksi gagal', (
    t,
  ) async {
    final c = humasMap(_fixtures['humas']), repo = _Repo(c)..fail = true;
    await _mount(t, repo, c, '/humas/kontak');
    await t.tap(find.byType(FloatingActionButton));
    await t.pumpAndSettle();
    await t.enterText(
      find.byKey(const Key('humas-field-nama')),
      'Koordinator Sekolah',
    );
    await _scroll(t, find.byKey(const Key('humas-field-dasar_penggunaan')));
    await t.enterText(
      find.byKey(const Key('humas-field-dasar_penggunaan')),
      'Koordinasi kegiatan resmi sekolah.',
    );
    await _save(t);
    await _save(t);
    expect(repo.sent, hasLength(2));
    expect(
      repo.sent.first['token_pembuatan'],
      repo.sent.last['token_pembuatan'],
    );
    expect(repo.sent.last['nama'], 'Koordinator Sekolah');
    expect(repo.methods, ['POST', 'POST']);
    expect(t.takeException(), isNull);
  });
  testWidgets('edit kontak mempertahankan versi asli saat konflik', (t) async {
    final c = humasMap(_fixtures['humas']), repo = _Repo(c)..conflict = true;
    await _mount(t, repo, c, '/humas/kontak/${repo.contact['id']}');
    await _scroll(t, find.text('Edit kontak'));
    await t.tap(find.text('Edit kontak'));
    await t.pumpAndSettle();
    await _scroll(t, find.byKey(const Key('humas-field-alasan')));
    await t.enterText(
      find.byKey(const Key('humas-field-alasan')),
      'Koreksi jabatan narahubung.',
    );
    await _save(t);
    await _save(t);
    expect(repo.sent.map((x) => x['versi']), [0, 0]);
    expect(repo.methods, ['PATCH', 'PATCH']);
    expect(repo.sent.first.containsKey('token_pembuatan'), isFalse);
  });
  testWidgets(
    'kamera foto draf multipart dapat dicoba ulang tanpa mengganti token',
    (t) async {
      final c = humasMap(_fixtures['kontributor']),
          repo = _Repo(c)..fail = true,
          picker = _Picker();
      await _mount(t, repo, c, '/humas/publikasi', picker: picker);
      await t.tap(find.byType(FloatingActionButton));
      await t.pumpAndSettle();
      await t.enterText(
        find.byKey(const Key('humas-field-judul')),
        'Kegiatan Bersama Sekolah',
      );
      await _scroll(t, find.byKey(const Key('humas-field-isi')));
      await t.enterText(
        find.byKey(const Key('humas-field-isi')),
        'Naskah kegiatan pembelajaran bersama masyarakat.',
      );
      await _scroll(t, find.byKey(const Key('publikasi-foto-camera')));
      await t.tap(find.byKey(const Key('publikasi-foto-camera')));
      await t.pumpAndSettle();
      expect(picker.sources, [HumasPhotoSource.camera]);
      await _shot(t, 'publikasi-form-foto');
      await _save(t);
      await _save(t);
      expect(repo.sent, hasLength(2));
      expect(
        repo.sent.first['token_pembuatan'],
        repo.sent.last['token_pembuatan'],
      );
      expect(identical(repo.forms.first, repo.forms.last), isFalse);
      for (final form in repo.forms) {
        expect(form.files.single.key, 'foto[]');
        expect(form.files.single.value.filename, 'uji.png');
        expect(form.fields.any((f) => f.key == 'aset_dikirim'), isFalse);
      }
      expect(t.takeException(), isNull);
    },
  );
  testWidgets(
    'izin kamera gagal tidak menghapus naskah dan dapat mencoba galeri',
    (t) async {
      final c = humasMap(_fixtures['kontributor']),
          repo = _Repo(c),
          picker = _Picker()..fail = true;
      await _mount(t, repo, c, '/humas/publikasi', picker: picker);
      await t.tap(find.byType(FloatingActionButton));
      await t.pumpAndSettle();
      await t.enterText(
        find.byKey(const Key('humas-field-judul')),
        'Draf tetap tersimpan di formulir',
      );
      await _scroll(t, find.byKey(const Key('publikasi-foto-camera')));
      await t.tap(find.byKey(const Key('publikasi-foto-camera')));
      await t.pumpAndSettle();
      expect(find.textContaining('Izin kamera'), findsOneWidget);
      await t.tap(find.byKey(const Key('publikasi-foto-gallery')));
      await t.pumpAndSettle();
      expect(picker.sources, [
        HumasPhotoSource.camera,
        HumasPhotoSource.gallery,
      ]);
      await _scroll(t, find.byKey(const Key('humas-field-judul')), delta: -150);
      expect(find.text('Draf tetap tersimpan di formulir'), findsOneWidget);
    },
  );
  testWidgets('edit publikasi tidak mengubah aset tanpa pilihan eksplisit', (
    t,
  ) async {
    final c = humasMap(_fixtures['humas']), repo = _Repo(c);
    repo.publication = {
      ...repo.publication,
      'aset': [
        {'id': 9, 'nama': 'Logo sekolah', 'versi': 2},
      ],
      'lampiran': [
        {'id': 8, 'jenis': 'foto', 'nama': 'foto-lama.jpg'},
      ],
    };
    await _mount(t, repo, c, '/humas/publikasi/${repo.publication['id']}');
    await _scroll(t, find.text('Edit draf publikasi'));
    await t.tap(find.text('Edit draf publikasi'));
    await t.pumpAndSettle();
    await _scroll(t, find.text('foto-lama.jpg'));
    await t.tap(find.text('foto-lama.jpg'));
    await _save(t);
    final fields = repo.forms.single.fields;
    expect(Map.fromEntries(fields)['_method'], 'PATCH');
    expect(Map.fromEntries(fields)['versi'], '0');
    expect(Map.fromEntries(fields)['hapus_foto[]'], '8');
    expect(fields.any((f) => f.key.startsWith('aset')), isFalse);
  });
  for (final state in ['diajukan', 'disetujui', 'tayang']) {
    testWidgets('tindakan publikasi mengikuti hak server pada status $state', (
      t,
    ) async {
      final c = humasMap(_fixtures['humas']), repo = _Repo(c);
      repo.publication = {
        ...repo.publication,
        'status': state,
        'hak_akses': {
          'dapat_edit': false,
          'dapat_ajukan': false,
          'dapat_tarik': state == 'diajukan',
          'dapat_buka_revisi': state == 'disetujui',
          'dapat_tayang': state == 'disetujui',
        },
      };
      await _mount(t, repo, c, '/humas/publikasi/${repo.publication['id']}');
      await t.drag(find.byType(ListView).first, const Offset(0, -2000));
      await t.pumpAndSettle();
      expect(find.text('Edit draf publikasi'), findsNothing);
      expect(find.text('Ajukan ke pimpinan'), findsNothing);
      expect(
        find.text('Catat bukti tayang'),
        state == 'disetujui' ? findsOneWidget : findsNothing,
      );
      expect(
        find.text('Tarik pengajuan'),
        state == 'diajukan' ? findsOneWidget : findsNothing,
      );
    });
  }
  for (final module in [HumasModule.contacts, HumasModule.publications]) {
    testWidgets(
      'paginasi native ${module.path} tidak mengulang halaman pertama',
      (t) async {
        final c = humasMap(_fixtures['humas']),
            repo = _Repo(c)..multiPage = true;
        await _mount(t, repo, c, module.route);
        await _scroll(t, find.text('Muat berikutnya'));
        await t.tap(find.text('Muat berikutnya'));
        await t.pumpAndSettle();
        expect(repo.queries.map((q) => q['halaman']), [1, 2]);
        await _scroll(t, find.text('Data halaman kedua'));
        expect(find.text('Data halaman kedua'), findsOneWidget);
      },
    );
  }
  testWidgets(
    'aset hanya disinkronkan dan diperbarui setelah pilihan eksplisit',
    (t) async {
      final c = humasMap(_fixtures['humas']), repo = _Repo(c);
      repo.publication = {
        ...repo.publication,
        'aset': [
          {'id': 9, 'nama': 'Logo sekolah', 'versi': 2},
        ],
      };
      await _mount(t, repo, c, '/humas/publikasi/${repo.publication['id']}');
      await _scroll(t, find.text('Edit draf publikasi'));
      await t.tap(find.text('Edit draf publikasi'));
      await t.pumpAndSettle();
      await _scroll(t, find.text('Ubah aset terpakai'));
      await t.tap(find.text('Ubah aset terpakai'));
      await t.pumpAndSettle();
      await _scroll(t, find.text('Gunakan versi aset terbaru'));
      await t.tap(find.text('Gunakan versi aset terbaru'));
      await t.pumpAndSettle();
      await _save(t);
      final fields = Map.fromEntries(repo.forms.single.fields);
      expect(fields['aset_dikirim'], '1');
      expect(fields['aset_ids[]'], '9');
      expect(fields['perbarui_aset[]'], '9');
    },
  );
  testWidgets('foto privat dirender melalui endpoint lokal bukan URL respons', (
    t,
  ) async {
    final c = humasMap(_fixtures['humas']), repo = _Repo(c);
    repo.photoBytes = File('../public/images/login-sekolah.jpg')
        .readAsBytesSync();
    repo.publication = {
      ...repo.publication,
      'lampiran': [
        {
          'id': 8,
          'jenis': 'foto',
          'nama': 'kegiatan.jpg',
          'tipe_file': 'image/jpeg',
          'url': 'https://luar.test/rahasia',
        },
      ],
    };
    await _mount(t, repo, c, '/humas/publikasi/${repo.publication['id']}');
    await _scroll(t, find.byType(HumasPublicationPhoto));
    await t.runAsync(
      () => Future<void>.delayed(const Duration(milliseconds: 100)),
    );
    await t.pumpAndSettle();
    expect(repo.bytePaths, ['publikasi/${repo.publication['id']}/lampiran/8']);
    expect(
      find.descendant(
        of: find.byType(HumasPublicationPhoto),
        matching: find.byType(Image),
      ),
      findsOneWidget,
    );
    await _shot(t, 'publikasi-foto-privat');
    expect(t.takeException(), isNull);
  });
  testWidgets(
    'pemeriksa harus konfirmasi dan versi tidak diganti saat konflik',
    (t) async {
      final c = humasMap(_fixtures['pimpinan']),
          repo = _Repo(c)..conflict = true;
      repo.publication = {
        ...repo.publication,
        'versi': 5,
        'hak_akses': {'dapat_periksa': true},
      };
      await _mount(t, repo, c, '/humas/publikasi/${repo.publication['id']}');
      await _scroll(t, find.text('Setujui konten'));
      await t.tap(find.text('Setujui konten'));
      await t.pumpAndSettle();
      await _save(t);
      expect(repo.sent, isEmpty);
      await t.tap(find.byType(SwitchListTile));
      await t.pumpAndSettle();
      await _save(t);
      await _save(t);
      expect(repo.sent.map((s) => s['versi']), [5, 5]);
      expect(repo.sent.first.containsKey('konfirmasi'), isFalse);
      expect(repo.sendPaths, everyElement(endsWith('/tindakan/setujui')));
    },
  );
  for (final size in [
    const Size(320, 640),
    const Size(390, 844),
    const Size(600, 900),
    const Size(1024, 768),
  ]) {
    for (final scale in [1.0, 2.0]) {
      for (final module in [HumasModule.contacts, HumasModule.publications]) {
        testWidgets('layout ${module.path} ${size.width} skala $scale', (
          t,
        ) async {
          final c = humasMap(_fixtures['humas']), repo = _Repo(c);
          await _mount(t, repo, c, module.route, size: size, scale: scale);
          await t.drag(find.byType(ListView).first, const Offset(0, -1800));
          await t.pumpAndSettle();
          expect(t.takeException(), isNull);
          await t.tap(find.byType(FloatingActionButton));
          await t.pumpAndSettle();
          await _scroll(t, find.widgetWithText(FilledButton, 'Simpan'));
          expect(t.takeException(), isNull);
          if (size.width == 390 && scale == 1) {
            await _shot(t, '${module.path}-form');
          }
        });
      }
    }
  }
}

Future<void> _scroll(
  WidgetTester t,
  Finder target, {
  double delta = 150,
}) async {
  await t.scrollUntilVisible(
    target,
    delta,
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
      '../storage/logs/humas-mobile-alignment/native-tahap-satu',
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
  _Picker? picker,
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
      humasPhotoPickerProvider.overrideWithValue(picker ?? _Picker()),
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

class _Picker implements HumasPhotoPicker {
  bool fail = false;
  final sources = <HumasPhotoSource>[];
  @override
  Future<List<HumasPickedFile>> pick(HumasPhotoSource source) async {
    sources.add(source);
    if (fail) {
      fail = false;
      throw const NetworkException('Izin kamera belum diberikan.');
    }
    return [HumasPickedFile('uji.png', _photo)];
  }
}

class _Repo extends HumasRepository {
  _Repo(HumasData c)
    : contact = humasMap(c['kontak']),
      publication = humasMap(c['publikasi']),
      contract = c,
      super(Dio());
  final HumasData contract;
  HumasData contact, publication;
  bool denied = false, fail = false, conflict = false, multiPage = false;
  Uint8List? photoBytes;
  final bytePaths = <String>[];
  final paths = <String>[], methods = <String>[], sendPaths = <String>[];
  final sent = <HumasData>[], queries = <HumasData>[];
  final forms = <FormData>[];
  @override
  Future<HumasData> get(String path, [HumasData query = const {}]) async {
    paths.add(path);
    if (denied) throw const NetworkException('Akses dicabut.', statusCode: 403);
    final contacts = path.startsWith('kontak'),
        data = contacts ? contact : publication;
    if (path.endsWith('/referensi')) {
      return humasMap(
        contract['${contacts ? 'kontak' : 'publikasi'}_referensi'],
      );
    }
    if (!path.contains('/')) {
      queries.add(query);
      return {
        'items': [
          query['halaman'] == 2
              ? {
                  ...data,
                  'id': 999,
                  contacts ? 'nama' : 'judul': 'Data halaman kedua',
                }
              : data,
        ],
        'paginasi': {
          'halaman': query['halaman'] ?? 1,
          'total': multiPage ? 2 : 1,
          'ada_halaman_berikutnya': multiPage && query['halaman'] != 2,
        },
      };
    }
    return data;
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
    sendPaths.add(path);
    if (multipart is FormData) forms.add(multipart);
    if (fail) {
      fail = false;
      throw const NetworkException('Koneksi terputus. Coba lagi.');
    }
    if (conflict) throw const ValidationException('Versi berubah. Muat ulang.');
    return {'id': 1};
  }

  @override
  Future<Uint8List> bytes(String path) async {
    bytePaths.add(path);
    return photoBytes ?? _photo;
  }
}
