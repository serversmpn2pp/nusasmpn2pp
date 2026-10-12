import 'dart:async';
import 'dart:convert';
import 'dart:io';
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
import 'package:nusa/features/persuratan/data/persuratan_repository.dart';
import 'package:nusa/features/persuratan/data/surat_file_picker.dart';
import 'package:nusa/features/persuratan/domain/persuratan.dart';
import 'package:nusa/features/persuratan/presentation/persuratan_detail_view.dart';
import 'package:nusa/features/persuratan/presentation/persuratan_list_view.dart';
import 'package:nusa/features/persuratan/presentation/surat_editors.dart';
import 'package:nusa/features/persuratan/presentation/widgets/surat_widgets.dart';

const _tu = Pengguna(
  id: 10,
  nama: 'Petugas Tata Usaha',
  username: 'tu',
  jenisAkun: 'Pegawai',
  administrator: false,
  wajibGantiKataSandi: false,
  peran: ['tata_usaha'],
  izin: ['surat.lihat', 'surat.kelola'],
);
const _head = Pengguna(
  id: 11,
  nama: 'Kepala Sekolah',
  username: 'kepala',
  jenisAkun: 'Pegawai',
  administrator: false,
  wajibGantiKataSandi: false,
  peran: ['pimpinan'],
  izin: ['surat.lihat', 'surat.sahkan', 'surat.disposisi'],
);
const _teacher = Pengguna(
  id: 12,
  nama: 'Guru Penerima',
  username: 'guru',
  jenisAkun: 'Pegawai',
  administrator: false,
  wajibGantiKataSandi: false,
  peran: ['guru_mapel'],
  izin: [],
);
const _student = Pengguna(
  id: 13,
  nama: 'Siswa',
  username: 'siswa',
  jenisAkun: 'Siswa',
  administrator: true,
  wajibGantiKataSandi: false,
  peran: ['administrator', 'tata_usaha'],
  izin: ['surat.kelola', 'surat.sahkan'],
);
final _png = base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
);
SuratUpload _photo() =>
    SuratUpload(name: 'foto-surat.png', bytes: _png, image: true);
final _capture = GlobalKey();

void main() {
  setUpAll(() async {
    final path = Platform.environment['NUSA_QA_FONT'];
    if (path != null) {
      final font = FontLoader('Roboto')
        ..addFont(
          Future.value(ByteData.sublistView(await File(path).readAsBytes())),
        );
      await font.load();
      final icons = File('${File(path).parent.path}/materialicons-regular.otf');
      if (await icons.exists()) {
        await (FontLoader('MaterialIcons')..addFont(
              Future.value(ByteData.sublistView(await icons.readAsBytes())),
            ))
            .load();
      }
    }
  });

  test(
    'identity guards block privileged student, separate TU, head and teacher',
    () {
      expect(SuratModule.values.any((m) => m.canOpen(_student)), isFalse);
      expect(SuratModule.approval.canOpen(_tu), isFalse);
      expect(SuratModule.approval.canOpen(_head), isTrue);
      expect(SuratModule.archive.canOpen(_teacher), isFalse);
      expect(SuratModule.myLetters.canOpen(_teacher), isTrue);
      expect(SuratModule.myTasks.canOpen(_teacher), isTrue);
      expect(suratText(0), '0');
      final p = SuratPage(_list(2));
      expect(p.page, 2);
      expect(p.hasNext, isFalse);
    },
  );
  test(
    'multipart uses PHP array fields, fresh streams and boolean confirmations',
    () async {
      FormData form() => suratMultipart(
        {
          '_method': 'PATCH',
          'versi': 7,
          'pegawai_ids': [9, 10],
          'hapus_lampiran': [8],
          'konfirmasi_berkas': true,
        },
        primary: _photo(),
        attachments: [_photo()],
        evidence: [_photo()],
      );
      final first = form();
      expect(
        first.fields.where((e) => e.key == 'pegawai_ids[]').map((e) => e.value),
        ['9', '10'],
      );
      expect(Map.fromEntries(first.fields)['_method'], 'PATCH');
      expect(Map.fromEntries(first.fields)['konfirmasi_berkas'], '1');
      expect(first.files.map((e) => e.key), [
        'berkas_utama',
        'lampiran[]',
        'bukti[]',
      ]);
      await first.finalize().drain<void>();
      expect(form().isFinalized, isFalse);
      expect(
        () => suratMultipart(
          {},
          primary: SuratUpload(name: 'empty.pdf', bytes: Uint8List(0)),
        ),
        throwsA(isA<ValidationException>()),
      );
      expect(
        () => validateSuratUpload(
          SuratUpload(
            name: 'large.png',
            bytes: Uint8List(SuratUpload.maxBytes + 1),
          ),
        ),
        throwsA(isA<ValidationException>()),
      );
      expect(safeSuratFileName('../surat:1.pdf'), '.._surat_1.pdf');
    },
  );
  test(
    'authenticated downloads reject untrusted URLs and parse error bytes',
    () async {
      final dio = Dio(
        BaseOptions(
          baseUrl: 'https://nusa.test/api/v1/',
          headers: {'Authorization': 'Bearer test-only'},
        ),
      );
      var calls = 0;
      dio.interceptors.add(
        InterceptorsWrapper(
          onRequest: (o, h) {
            calls++;
            expect(
              o.uri.toString(),
              'https://nusa.test/api/v1/surat-saya/7/berkas/8',
            );
            expect(o.headers['Authorization'], 'Bearer test-only');
            h.reject(
              DioException(
                requestOptions: o,
                type: DioExceptionType.badResponse,
                response: Response(
                  requestOptions: o,
                  statusCode: 422,
                  data: utf8.encode(
                    jsonEncode({
                      'message': 'Data berubah',
                      'errors': {
                        'versi': ['Muat ulang surat.'],
                      },
                    }),
                  ),
                ),
              ),
            );
          },
        ),
      );
      final repo = PersuratanRepository(dio);
      for (final path in [
        'https://luar.test/file',
        '/persuratan/7',
        'persuratan/../foto',
        'surat-saya/7?url=x',
      ]) {
        await expectLater(
          repo.download(path, 'surat.pdf'),
          throwsA(isA<ValidationException>()),
        );
      }
      expect(calls, 0);
      await expectLater(
        repo.download('surat-saya/7/berkas/8', 'surat.pdf'),
        throwsA(
          isA<ValidationException>().having(
            (e) => e.errors['versi']?.single,
            'version conflict',
            'Muat ulang surat.',
          ),
        ),
      );
      expect(calls, 1);
    },
  );

  for (final module in SuratModule.values) {
    testWidgets(
      'native route ${module.route} opens correct list without mutations',
      (t) async {
        final repo = _Repo();
        await _mount(t, const SizedBox(), repo, _head, route: module.route);
        expect(find.text(module.title), findsOneWidget);
        expect(repo.reads.map((r) => r.$1), contains(module.path));
        expect(repo.sent, isEmpty);
        expect(find.byType(PersuratanDetailView), findsNothing);
        expect(t.takeException(), isNull);
      },
    );
  }
  for (final path in [
    '/persuratan/7',
    '/surat-saya/7',
    '/tindak-lanjut-surat/7',
  ]) {
    testWidgets(
      'notification route $path opens native detail without auto-read',
      (t) async {
        final repo = _Repo();
        await _mount(t, const SizedBox(), repo, _head, route: path);
        expect(find.byType(PersuratanDetailView), findsOneWidget);
        expect(repo.reads.map((r) => r.$1), contains(path.substring(1)));
        expect(repo.sent, isEmpty);
        expect(t.takeException(), isNull);
      },
    );
  }
  testWidgets('unauthorized deep link never requests protected data', (
    t,
  ) async {
    final repo = _Repo();
    await _mount(t, const PersuratanDetailView(id: 7), repo, _student);
    expect(
      find.text('Menu ini tidak tersedia untuk akun Anda.'),
      findsOneWidget,
    );
    expect(repo.reads, isEmpty);
  });
  testWidgets(
    'changing detail ID reloads instead of retaining the previous record',
    (t) async {
      final repo = _Repo();
      final c = await _mount(
        t,
        const SizedBox(),
        repo,
        _head,
        route: '/persuratan/7',
      );
      c.read(appRouterProvider).go('/persuratan/8');
      await t.pumpAndSettle();
      expect(repo.reads.map((r) => r.$1), contains('persuratan/8'));
      expect(repo.sent, isEmpty);
    },
  );
  testWidgets('filters and pagination reach API and preserve prior page', (
    t,
  ) async {
    final repo = _Repo();
    await _mount(
      t,
      const PersuratanListView(module: SuratModule.archive),
      repo,
      _tu,
    );
    await _tap(t, find.text('Muat berikutnya'));
    expect(repo.reads.last.$2['halaman'], 2);
    expect(find.text('Surat halaman 2'), findsOneWidget);
    await t.drag(find.byType(ListView).first, const Offset(0, 2000));
    await t.pumpAndSettle();
    await _tap(t, find.byTooltip('Filter surat'));
    await _tap(t, find.byKey(const Key('filter-jenis')));
    await _tap(t, find.text('Surat masuk').last);
    expect(repo.reads.last.$2['jenis'], 'masuk');
    expect(repo.reads.last.$2['halaman'], 1);
    await t.drag(find.byType(ListView).first, const Offset(0, 2000));
    await t.pumpAndSettle();
    await _tap(t, find.byTooltip('Reset filter'));
    expect(repo.reads.last.$2.containsKey('jenis'), isFalse);
    expect(t.takeException(), isNull);
  });
  testWidgets('out-of-order search result cannot replace newest query', (
    t,
  ) async {
    final repo = _Repo();
    await _mount(
      t,
      const PersuratanListView(module: SuratModule.archive),
      repo,
      _tu,
    );
    repo.pendingSearch = true;
    await t.enterText(find.byKey(const Key('surat-search')), 'lama');
    await t.pump(const Duration(milliseconds: 500));
    await t.enterText(find.byKey(const Key('surat-search')), 'baru');
    await t.pump(const Duration(milliseconds: 500));
    repo.pending['baru']!.complete(_list(1, title: 'Hasil terbaru'));
    await t.pumpAndSettle();
    repo.pending['lama']!.complete(_list(1, title: 'Hasil usang'));
    await t.pumpAndSettle();
    expect(find.text('Hasil terbaru'), findsOneWidget);
    expect(find.text('Hasil usang'), findsNothing);
  });
  testWidgets(
    'camera preview requires consent and cancellation retains old file',
    (t) async {
      final repo = _Repo();
      final picker = _Picker();
      var changes = 0;
      await _mount(
        t,
        Scaffold(
          body: SuratUploadField(
            title: 'Berkas utama',
            primary: true,
            maxFiles: 1,
            onChanged: (_) => changes++,
          ),
        ),
        repo,
        _tu,
        picker: picker,
      );
      await _camera(t, 'Berkas utama', confirm: false);
      expect(picker.sources, [SuratFileSource.camera]);
      expect(changes, 0);
      await _camera(t, 'Berkas utama');
      expect(changes, 1);
      expect(find.text('foto-surat.png'), findsOneWidget);
      await _camera(t, 'Berkas utama', confirm: false);
      expect(changes, 1);
      expect(find.text('foto-surat.png'), findsOneWidget);
      expect(repo.sent, isEmpty);
      expect(t.takeException(), isNull);
    },
  );
  testWidgets(
    'gallery, file, permission failure and oversized photo are handled',
    (t) async {
      final picker = _Picker();
      var changes = 0;
      await _mount(
        t,
        Scaffold(
          body: SuratUploadField(
            title: 'Lampiran',
            onChanged: (_) => changes++,
          ),
        ),
        _Repo(),
        _tu,
        picker: picker,
      );
      await _tap(t, find.byKey(const Key('upload-Lampiran')));
      await _tap(t, find.text('Pilih foto dari galeri'));
      await _tap(t, find.text('Gunakan foto'));
      picker.value = SuratUpload(
        name: 'surat.pdf',
        bytes: Uint8List.fromList(utf8.encode('%PDF-1.7')),
      );
      await _tap(t, find.byKey(const Key('upload-Lampiran')));
      await _tap(t, find.text('Pilih berkas'));
      expect(picker.sources, [SuratFileSource.gallery, SuratFileSource.file]);
      picker.error = const ValidationException('Izin kamera belum diberikan.');
      await _tap(t, find.byKey(const Key('upload-Lampiran')));
      await _tap(t, find.text('Ambil foto surat'));
      expect(find.text('Izin kamera belum diberikan.'), findsOneWidget);
      picker.error = null;
      picker.value = SuratUpload(
        name: 'besar.png',
        bytes: Uint8List(SuratUpload.maxBytes + 1),
        image: true,
      );
      await _tap(t, find.byKey(const Key('upload-Lampiran')));
      await _tap(t, find.text('Ambil foto surat'));
      expect(find.text('Berkas maksimal 10 MB.'), findsOneWidget);
      expect(changes, 2);
      expect(t.takeException(), isNull);
    },
  );
  testWidgets(
    'photo in draft edit posts method override and preserves data/version on conflict',
    (t) async {
      final repo = _Repo()..failNext = true;
      await _mount(
        t,
        _Editor((c) => editSurat(c, repo, _tu.id, record: _letter())),
        repo,
        _tu,
      );
      await _tap(t, find.text('Buka formulir'));
      await _enter(t, 'catatan_revisi', 'Memperbarui foto yang lebih jelas');
      await _camera(t, 'Berkas utama');
      await _tap(t, find.text('lampiran-lama.pdf'));
      await _tap(t, find.byKey(const Key('surat-save')));
      expect(repo.sent.single.$1, 'persuratan/7');
      expect(repo.sent.single.$2['_method'], 'PATCH');
      expect(repo.sent.single.$2['versi'], 7);
      expect(repo.sent.single.$2['hapus_lampiran'], [81]);
      expect(repo.forms.single.files.single.key, 'berkas_utama');
      expect(
        repo.sent.single.$2['catatan_revisi'],
        'Memperbarui foto yang lebih jelas',
      );
      await _tap(t, find.byKey(const Key('surat-save')));
      expect(repo.sent.map((s) => s.$2['versi']), [7, 7]);
      expect(repo.forms[0].isFinalized, isFalse);
      expect(identical(repo.forms[0], repo.forms[1]), isFalse);
      expect(t.takeException(), isNull);
    },
  );
  testWidgets('incoming letter camera upload sends matching metadata', (
    t,
  ) async {
    final repo = _Repo();
    await _mount(t, _Editor((c) => editSurat(c, repo, _tu.id)), repo, _tu);
    await _tap(t, find.text('Buka formulir'));
    await _tap(t, find.text('Surat masuk'));
    await _enter(t, 'nomor_surat', '421/PHOTO/2026');
    await _enter(t, 'nomor_agenda', 'AG-099');
    await _enter(t, 'perihal', 'Undangan rapat komite');
    await _enter(t, 'pihak', 'Dinas Pendidikan');
    await _camera(t, 'Berkas utama');
    await _tap(t, find.byKey(const Key('surat-save')));
    final data = repo.sent.single.$2;
    expect(data['jenis'], 'masuk');
    expect(data['nomor_agenda'], 'AG-099');
    expect(data['tanggal_diterima'], isNotEmpty);
    expect(data.containsKey('pengesah_pengguna_id'), isFalse);
    expect(repo.forms.single.files.single.value.filename, 'foto-surat.png');
    expect(t.takeException(), isNull);
  });
  testWidgets(
    'task report retains latest old evidence and uploads new camera proof',
    (t) async {
      final repo = _Repo();
      await _mount(
        t,
        _Editor((c) => reportSurat(c, repo, _teacher.id, _task())),
        repo,
        _teacher,
      );
      await _tap(t, find.text('Buka formulir'));
      await _enter(t, 'hasil', 'Pelatihan telah dilaksanakan');
      await _camera(t, 'Bukti baru');
      await _tap(t, find.byKey(const Key('surat-aksi')));
      await _tap(t, find.text('Ajukan untuk diperiksa'));
      await _tap(t, find.byKey(const Key('surat-save')));
      expect(repo.sent.single.$1, 'tindak-lanjut-surat/7/laporan');
      expect(repo.sent.single.$2['aksi'], 'ajukan');
      expect(repo.sent.single.$2['versi'], 4);
      expect(repo.sent.single.$2['bukti_lama'], [91]);
      expect(repo.forms.single.files.single.key, 'bukti[]');
      expect(t.takeException(), isNull);
    },
  );
  testWidgets('pengesahan needs confirmation and uses versioned action', (
    t,
  ) async {
    final repo = _Repo();
    await _mount(
      t,
      _Editor(
        (c) => suratDecision(
          c,
          repo,
          _head.id,
          _letter(),
          'persuratan',
          'sahkan',
          'Sahkan surat',
        ),
      ),
      repo,
      _head,
    );
    await _tap(t, find.text('Buka formulir'));
    await _tap(t, find.byKey(const Key('surat-save')));
    expect(repo.sent, isEmpty);
    await _tap(t, find.text('Saya telah memeriksa berkas final surat'));
    await _tap(t, find.byKey(const Key('surat-save')));
    expect(repo.sent.single.$2['konfirmasi_berkas'], isTrue);
    expect(repo.sent.single.$2['versi'], 7);
  });
  testWidgets(
    'disposition searchable recipients and version are sent without local mutation',
    (t) async {
      final repo = _Repo();
      await _mount(
        t,
        _Editor((c) => disposeSurat(c, repo, _head.id, _letter())),
        repo,
        _head,
      );
      await _tap(t, find.text('Buka formulir'));
      await _enter(t, 'instruksi', 'Siapkan agenda rapat');
      await _tap(t, find.byKey(const Key('surat-batas_tanggal')));
      await _tap(t, find.text('OK'));
      await _tap(t, find.byKey(const Key('surat-recipients')));
      await _tap(t, find.text('Guru Penerima'));
      await _tap(t, find.text('Pilih 1 penerima'));
      expect(find.text('Penerima (1)'), findsOneWidget);
      await _tap(t, find.byKey(const Key('surat-save')));
      expect(repo.sent.single.$1, 'persuratan/7/disposisi');
      expect(repo.sent.single.$2['pegawai_ids'], [12]);
      expect(repo.sent.single.$2['versi'], 7);
    },
  );
  testWidgets('own letter read is explicit and does not post a version', (
    t,
  ) async {
    final repo = _Repo()
      ..letter = _letter(rights: {'dapat_konfirmasi_baca': true});
    await _mount(
      t,
      const PersuratanDetailView(id: 7, personal: true),
      repo,
      _teacher,
    );
    expect(repo.sent, isEmpty);
    await _tap(t, find.text('Sudah dibaca'));
    expect(repo.sent, isEmpty);
    await _tap(t, find.text('Lanjutkan'));
    expect(repo.sent.single.$1, 'surat-saya/7/baca');
    expect(repo.sent.single.$2, isEmpty);
  });
  for (final size in [
    const Size(320, 760),
    const Size(390, 844),
    const Size(768, 1024),
  ]) {
    testWidgets('list and detail fit $size with large text', (t) async {
      final repo = _Repo();
      await _mount(
        t,
        const PersuratanListView(module: SuratModule.archive),
        repo,
        _tu,
        size: size,
        textScale: 1.35,
      );
      await _screenshot(t, 'list-${size.width.toInt()}');
      expect(t.takeException(), isNull);
      await _mount(
        t,
        const PersuratanDetailView(id: 7),
        repo,
        _head,
        size: size,
        textScale: 1.35,
      );
      await _screenshot(t, 'detail-${size.width.toInt()}');
      await _tap(t, find.text('Sahkan surat'));
      await _screenshot(t, 'approval-${size.width.toInt()}');
      expect(t.takeException(), isNull);
    });
  }
  testWidgets(
    'account switch hides old data and prevents pending photo assignment',
    (t) async {
      final picker = _Picker();
      var changes = 0;
      final container = await _mount(
        t,
        Scaffold(
          body: SuratUploadField(
            title: 'Berkas utama',
            onChanged: (_) => changes++,
          ),
        ),
        _Repo(),
        _tu,
        picker: picker,
      );
      await _tap(t, find.byKey(const Key('upload-Berkas utama')));
      await _tap(t, find.text('Ambil foto surat'));
      (container.read(authControllerProvider.notifier) as _Auth).change(
        _teacher,
      );
      await t.pumpAndSettle();
      await _tap(t, find.text('Gunakan foto'));
      expect(changes, 0);
    },
  );
}

Future<void> _tap(WidgetTester t, Finder f) async {
  if (f.evaluate().isEmpty) {
    await t.scrollUntilVisible(
      f,
      240,
      scrollable: find.byType(Scrollable).last,
    );
  }
  await t.ensureVisible(f);
  await t.pumpAndSettle();
  await t.tap(f);
  await t.pumpAndSettle();
}

Future<void> _enter(WidgetTester t, String field, String value) async {
  final f = find.byKey(Key('surat-$field'));
  if (f.evaluate().isEmpty) {
    await t.scrollUntilVisible(
      f,
      240,
      scrollable: find.byType(Scrollable).last,
    );
  }
  await t.ensureVisible(f);
  await t.enterText(f, value);
  await t.pumpAndSettle();
}

Future<void> _camera(
  WidgetTester t,
  String title, {
  bool confirm = true,
}) async {
  await _tap(t, find.byKey(Key('upload-$title')));
  await _tap(t, find.text('Ambil foto surat'));
  expect(find.text('Pratinjau foto surat'), findsOneWidget);
  await _screenshot(t, 'camera-preview');
  await _tap(t, find.text(confirm ? 'Gunakan foto' : 'Batal').last);
}

Future<void> _screenshot(WidgetTester t, String name) async {
  if (Platform.environment['NUSA_CAPTURE_PERSURATAN_UI'] != '1') return;
  await t.pumpAndSettle();
  final boundary =
      _capture.currentContext!.findRenderObject()! as RenderRepaintBoundary;
  await t.runAsync(() async {
    final image = await boundary.toImage();
    final data = await image.toByteData(format: ui.ImageByteFormat.png);
    image.dispose();
    final dir = Directory('build/persuratan-qa');
    await dir.create(recursive: true);
    await File('${dir.path}/$name.png')
        .writeAsBytes(data!.buffer.asUint8List());
  });
}

Future<ProviderContainer> _mount(
  WidgetTester t,
  Widget child,
  _Repo repo,
  Pengguna user, {
  String? route,
  _Picker? picker,
  Size size = const Size(390, 844),
  double textScale = 1,
}) async {
  t.view.physicalSize = size;
  t.view.devicePixelRatio = 1;
  t.platformDispatcher.textScaleFactorTestValue = textScale;
  addTearDown(t.view.resetPhysicalSize);
  addTearDown(t.view.resetDevicePixelRatio);
  addTearDown(t.platformDispatcher.clearTextScaleFactorTestValue);
  final c = ProviderContainer(
    overrides: [
      authControllerProvider.overrideWith(() => _Auth(user)),
      persuratanRepositoryProvider.overrideWithValue(repo),
      suratFilePickerProvider.overrideWithValue(picker ?? _Picker()),
      splashGateProvider.overrideWith((ref) async {}),
    ],
  );
  await c.read(authControllerProvider.future);
  await c.read(splashGateProvider.future);
  addTearDown(c.dispose);
  final router = route == null ? null : c.read(appRouterProvider);
  if (route != null) router!.go(route);
  var theme = AppTheme.light;
  if (Platform.environment['NUSA_QA_FONT'] != null) {
    const textStyle = WidgetStatePropertyAll(
      TextStyle(fontFamily: 'Roboto', fontWeight: FontWeight.w600),
    );
    theme = theme.copyWith(
      filledButtonTheme: FilledButtonThemeData(
        style: theme.filledButtonTheme.style?.copyWith(textStyle: textStyle),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: theme.outlinedButtonTheme.style?.copyWith(textStyle: textStyle),
      ),
      textButtonTheme: TextButtonThemeData(
        style: theme.textButtonTheme.style?.copyWith(textStyle: textStyle),
      ),
    );
  }
  await t.pumpWidget(
    UncontrolledProviderScope(
      container: c,
      child: RepaintBoundary(
        key: _capture,
        child: route == null
            ? MaterialApp(theme: theme, home: child)
            : MaterialApp.router(theme: theme, routerConfig: router),
      ),
    ),
  );
  await t.pumpAndSettle();
  return c;
}

class _Auth extends AuthController {
  _Auth(this.user);
  final Pengguna user;
  @override
  Future<AuthState> build() async => AuthState(
    session: AuthSession(token: 'test-only', pengguna: user),
  );
  void change(Pengguna user) => state = AsyncData(
    AuthState(
      session: AuthSession(token: 'test-only', pengguna: user),
    ),
  );
}

class _Picker implements SuratFilePicker {
  final sources = <SuratFileSource>[];
  SuratUpload? value = _photo();
  Object? error;
  @override
  Future<SuratUpload?> pick(
    SuratFileSource source, {
    required bool primary,
  }) async {
    sources.add(source);
    if (error != null) {
      throw error!;
    }
    return value;
  }
}

class _Editor extends StatelessWidget {
  const _Editor(this.open);
  final Future<bool> Function(BuildContext) open;
  @override
  Widget build(BuildContext context) => Scaffold(
    body: Center(
      child: FilledButton(
        onPressed: () => open(context),
        child: const Text('Buka formulir'),
      ),
    ),
  );
}

class _Repo extends PersuratanRepository {
  _Repo() : super(Dio());
  final reads = <(String, SuratData)>[];
  final sent = <(String, SuratData)>[];
  final forms = <FormData>[];
  bool failNext = false;
  bool pendingSearch = false;
  final pending = <String, Completer<SuratData>>{};
  SuratData letter = _letter();
  @override
  Future<SuratData> get(String path, [SuratData query = const {}]) async {
    reads.add((path, Map.of(query)));
    if (path == 'persuratan/referensi') {
      return {
        'jenis': {
          'masuk': 'Surat masuk',
          'keluar': 'Surat keluar',
          'tugas': 'Surat tugas',
        },
        'sifat': {'biasa': 'Biasa', 'penting': 'Penting'},
        'pegawai': _recipients,
        'pengesah': [
          {'id': 11, 'nama': 'Kepala Sekolah', 'dapat_surat_rahasia': true},
        ],
        'hak_akses': {'dapat_kelola': true},
      };
    }
    if (RegExp(r'/\d+$').hasMatch(path)) {
      return path.startsWith('tindak-lanjut-surat') ? _task() : letter;
    }
    if (pendingSearch && query.containsKey('kata_kunci')) {
      return (pending[query['kata_kunci']] = Completer<SuratData>()).future;
    }
    return _list(
      suratInt(query['halaman']) == 0 ? 1 : suratInt(query['halaman']),
      task: path.contains('tindak-lanjut'),
    );
  }

  @override
  Future<SuratData> send(
    String path,
    SuratData data, {
    FormData? multipart,
  }) async {
    sent.add((path, Map.of(data)));
    if (multipart != null) forms.add(multipart);
    if (failNext) {
      failNext = false;
      throw const ValidationException(
        'Data telah berubah. Muat ulang sebelum melanjutkan.',
      );
    }
    return {};
  }
}

const _recipients = <SuratData>[
  {'id': 12, 'nama_lengkap': 'Guru Penerima', 'jabatan_utama': 'Guru mapel'},
];
SuratData _letter({SuratData? rights}) => {
  'id': 7,
  'jenis': 'keluar',
  'jenis_label': 'Surat keluar',
  'nomor_surat': '421/099/SMP/2026',
  'perihal': 'Undangan Rapat Koordinasi Sekolah',
  'pihak': 'Dinas Pendidikan Kota Padang Panjang',
  'sifat': 'biasa',
  'sifat_label': 'Biasa',
  'status': 'draf',
  'status_label': 'Draf',
  'versi': 7,
  'tanggal_surat': '2026-10-13',
  'pengesah_pengguna_id': 11,
  'pengesah': {'id': 11, 'nama': 'Kepala Sekolah'},
  'berkas': [
    {
      'id': 80,
      'nama': 'surat-rapat.pdf',
      'jenis': 'utama',
      'aktif': true,
      'ukuran_byte': 180000,
    },
    {
      'id': 81,
      'nama': 'lampiran-lama.pdf',
      'jenis': 'lampiran',
      'aktif': true,
      'ukuran_byte': 45000,
    },
  ],
  'penerima_disposisi': _recipients,
  'penugasan': [],
  'hak_akses':
      rights ??
      {
        'dapat_edit': true,
        'dapat_ajukan': true,
        'dapat_sahkan': true,
        'dapat_kembalikan': true,
      },
};
SuratData _task() => {
  'id': 7,
  'jenis': 'disposisi',
  'versi': 4,
  'status': 'diproses',
  'status_label': 'Diproses',
  'nama_pegawai': 'Guru Penerima',
  'pegawai_id': 12,
  'batas_tanggal': '2026-10-25',
  'pemeriksa': {'id': 11, 'nama': 'Kepala Sekolah'},
  'surat': _letter(),
  'instruksi': 'Siapkan agenda dan dokumentasi rapat',
  'berkas_surat': [],
  'hak_akses': {'dapat_laporkan': true, 'dapat_baca': true},
  'laporan': [
    {
      'id': 90,
      'nomor': 2,
      'hasil': 'Agenda telah disiapkan',
      'disimpan_pada': '2026-10-13T07:00:00+07:00',
      'bukti': [
        {'id': 91, 'nama': 'agenda.pdf', 'ukuran_byte': 20000},
      ],
    },
  ],
};
SuratData _list(int page, {String? title, bool task = false}) => {
  'items': [
    {
      ...(task ? _task() : _letter()),
      'id': page,
      if (title != null || page > 1) 'perihal': title ?? 'Surat halaman $page',
    },
  ],
  'paginasi': {'halaman': page, 'total': 2, 'ada_halaman_berikutnya': page < 2},
  'statistik': {'draf': 2},
  'referensi': {
    'status': {'draf': 'Draf', 'disahkan': 'Disahkan'},
  },
};
