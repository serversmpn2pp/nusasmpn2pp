import 'dart:async';
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
import 'package:nusa/features/attendance_exceptions/data/attendance_exceptions_repository.dart';
import 'package:nusa/features/attendance_exceptions/domain/attendance_exception.dart';
import 'package:nusa/features/attendance_exceptions/presentation/attendance_exception_form_view.dart';
import 'package:nusa/features/attendance_exceptions/presentation/attendance_exceptions_view.dart';
import 'package:nusa/features/student_attendance_settings/domain/student_attendance_settings.dart';

const _admin = Pengguna(
  id: 10,
  nama: 'Pengelola Presensi',
  username: 'petugas',
  jenisAkun: 'Pegawai',
  administrator: true,
  wajibGantiKataSandi: false,
  peran: ['administrator'],
  izin: ['absensi.pengaturan_kelola'],
);
const _student = Pengguna(
  id: 11,
  nama: 'Siswa',
  username: 'siswa',
  jenisAkun: 'Siswa',
  administrator: true,
  wajibGantiKataSandi: false,
  peran: ['administrator'],
  izin: ['absensi.pengaturan_kelola'],
);
const _wali = Pengguna(
  id: 12,
  nama: 'Wali Kelas',
  username: 'wali',
  jenisAkun: 'Pegawai',
  administrator: false,
  wajibGantiKataSandi: false,
  peran: ['wali_kelas'],
  izin: ['absensi.pengaturan_kelola'],
);
final _capture = GlobalKey();

void main() {
  setUpAll(() async {
    final path = Platform.environment['NUSA_QA_FONT'];
    if (path != null) {
      await (FontLoader('Roboto')..addFont(
            Future.value(ByteData.sublistView(await File(path).readAsBytes())),
          ))
          .load();
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
    'access separates staff administrator from student and limited wali',
    () {
      expect(canManageAttendanceExceptions(_admin), isTrue);
      expect(canManageAttendanceExceptions(_student), isFalse);
      expect(canManageAttendanceExceptions(_wali), isFalse);
      expect(exceptionText(0), '0');
      expect(
        StudentAttendanceSettingsCatalog.fromJson({
          'hak_akses': {'dapat_kelola_pengecualian': true},
        }).canManageExceptions,
        isTrue,
      );
      expect(
        StudentAttendanceSettingsCatalog.fromJson({}).canManageExceptions,
        isFalse,
      );
    },
  );
  test(
    'repository uses preview, confirmation and cancellation contract',
    () async {
      final captured = <RequestOptions>[];
      final dio = Dio(BaseOptions(baseUrl: 'https://nusa.test/api/v1/'));
      dio.interceptors.add(
        InterceptorsWrapper(
          onRequest: (o, h) {
            captured.add(o);
            h.resolve(
              Response(
                requestOptions: o,
                statusCode: 200,
                data: {
                  'data': {'id': 7},
                },
              ),
            );
          },
        ),
      );
      final r = AttendanceExceptionsRepository(dio);
      await r.preview({'jenis': 'pjj', 'kelas_id': null});
      await r.apply('opaque-token');
      await r.cancel(7, 'Periode keliru berdasarkan keputusan sekolah.');
      expect(captured.map((o) => o.path), [
        'pengecualian-presensi/pratinjau',
        'pengecualian-presensi',
        'pengecualian-presensi/7/batalkan',
      ]);
      expect(captured[1].data, {'token': 'opaque-token', 'konfirmasi': true});
      expect(captured[2].data['konfirmasi'], isTrue);
      expect(captured[2].data.containsKey('aktif'), isFalse);
    },
  );
  testWidgets('native route loads history and pagination without mutation', (
    t,
  ) async {
    final repo = _Repo();
    await _mount(
      t,
      const SizedBox(),
      repo,
      route: AppRoutes.attendanceExceptions,
    );
    expect(find.byType(AttendanceExceptionsView), findsOneWidget);
    expect(repo.applied, isEmpty);
    await _tap(t, find.text('Muat berikutnya'));
    expect(repo.filters.last['halaman'], 2);
    expect(find.text('PJJ akibat bencana asap - kedua.'), findsOneWidget);
    await t.drag(find.byType(ListView).first, const Offset(0, 2000));
    await t.pumpAndSettle();
    await _tap(t, find.byTooltip('Filter pengecualian'));
    await _tap(t, find.byKey(const Key('exception-filter-status')));
    await _tap(t, find.text('Dibatalkan').last);
    expect(repo.filters.last['status'], 'dibatalkan');
    expect(repo.filters.last['halaman'], 1);
  });
  testWidgets('blocked identity cannot load reference, history or detail', (
    t,
  ) async {
    final repo = _Repo();
    await _mount(t, const AttendanceExceptionsView(), repo, user: _student);
    expect(
      find.text('Menu ini tidak tersedia untuk akun Anda.'),
      findsOneWidget,
    );
    expect(repo.reads, isEmpty);
    await _mount(
      t,
      const AttendanceExceptionDetailView(id: 7),
      repo,
      user: _wali,
    );
    expect(repo.reads, isEmpty);
  });
  testWidgets(
    'full form opens preview, requires confirmation and applies same token',
    (t) async {
      final repo = _Repo();
      await _mount(t, const AttendanceExceptionsView(), repo);
      await _tap(t, find.byTooltip('Tambah pengecualian'));
      await _enterReason(
        t,
        'PJJ akibat bencana asap sesuai keputusan sekolah.',
      );
      await _tap(t, find.byKey(const Key('exception-preview')));
      expect(repo.previews.single['jenis'], 'pjj');
      expect(repo.previews.single['kelas_id'], isNull);
      expect(find.byType(AttendanceExceptionPreviewView), findsOneWidget);
      expect(repo.applied, isEmpty);
      expect(
        t
            .widget<FilledButton>(find.byKey(const Key('exception-apply')))
            .onPressed,
        isNull,
      );
      await _tap(t, find.byKey(const Key('exception-confirm')));
      await _tap(t, find.byKey(const Key('exception-apply')));
      expect(repo.applied, ['opaque-token']);
      expect(find.byType(AttendanceExceptionFormView), findsNothing);
      expect(find.byType(AttendanceExceptionsView), findsOneWidget);
      expect(t.takeException(), isNull);
    },
  );
  testWidgets(
    'switching year resets class to school and clamps dates to new year',
    (t) async {
      final repo = _Repo();
      await _mount(
        t,
        const AttendanceExceptionFormView(
          reference: _references,
          accountId: 10,
        ),
        repo,
      );
      await _tap(t, find.byKey(const Key('exception-class')));
      await _tap(t, find.text('VIII.A').last);
      await _tap(t, find.byKey(const Key('exception-year')));
      await _tap(t, find.text('2027/2028').last);
      expect(find.text('Seluruh sekolah'), findsOneWidget);
      await _enterReason(t, 'Gangguan scanner pada tahun pelajaran baru.');
      await _tap(t, find.byKey(const Key('exception-preview')));
      expect(repo.previews.single['tahun_pelajaran_id'], 2);
      expect(repo.previews.single['kelas_id'], isNull);
      final start = DateTime.parse(repo.previews.single['tanggal_mulai']);
      expect(start.isBefore(DateTime(2027, 7)), isFalse);
      expect(start.isAfter(DateTime(2028, 6, 30)), isFalse);
    },
  );
  testWidgets(
    'preview conflict requires explicit new preview and retains form inputs',
    (t) async {
      final repo = _Repo()
        ..applyError = const ValidationException(
          'Pratinjau berubah.',
          errors: {
            'token': ['Data presensi berubah. Buat pratinjau terbaru.'],
          },
        );
      await _mount(
        t,
        const AttendanceExceptionFormView(
          reference: _references,
          accountId: 10,
        ),
        repo,
      );
      await _enterReason(
        t,
        'PJJ akibat bencana asap sesuai keputusan sekolah.',
      );
      await _tap(t, find.byKey(const Key('exception-preview')));
      await _tap(t, find.byKey(const Key('exception-confirm')));
      await _tap(t, find.byKey(const Key('exception-apply')));
      expect(repo.applied, ['opaque-token']);
      expect(find.text('Buat pratinjau baru'), findsOneWidget);
      expect(find.byKey(const Key('exception-apply')), findsNothing);
      await _tap(t, find.text('Buat pratinjau baru'));
      expect(
        t
            .widget<TextFormField>(find.byKey(const Key('exception-reason')))
            .controller!
            .text,
        'PJJ akibat bencana asap sesuai keputusan sekolah.',
      );
      expect(repo.previews, hasLength(1));
      await _tap(t, find.byKey(const Key('exception-preview')));
      expect(repo.previews, hasLength(2));
      expect(
        t
            .widget<FilledButton>(find.byKey(const Key('exception-apply')))
            .onPressed,
        isNull,
      );
    },
  );
  testWidgets(
    'network retry is manual and uses the same token, busy prevents double submit',
    (t) async {
      final repo = _Repo()
        ..applyError = const NetworkException('Koneksi putus.');
      await _mount(
        t,
        AttendanceExceptionPreviewView(preview: _preview, accountId: 10),
        repo,
      );
      await _tap(t, find.byKey(const Key('exception-confirm')));
      await _tap(t, find.byKey(const Key('exception-apply')));
      expect(repo.applied, ['opaque-token']);
      repo.applyError = null;
      repo.pendingApply = Completer<ExceptionData>();
      await t.tap(find.byKey(const Key('exception-apply')));
      await t.pump();
      expect(
        t
            .widget<FilledButton>(find.byKey(const Key('exception-apply')))
            .onPressed,
        isNull,
      );
      expect(repo.applied, ['opaque-token', 'opaque-token']);
      repo.pendingApply!.complete(_record);
      await t.pumpAndSettle();
      expect(t.takeException(), isNull);
    },
  );
  testWidgets(
    'cancellation requires reason and confirmation, preserving it on failure',
    (t) async {
      final repo = _Repo()
        ..cancelError = const NetworkException('Koneksi putus.');
      await _mount(t, const AttendanceExceptionDetailView(id: 7), repo);
      expect(repo.cancellations, isEmpty);
      await _tap(t, find.byKey(const Key('exception-cancel')));
      expect(
        t
            .widget<FilledButton>(
              find.byKey(const Key('exception-save-cancel')),
            )
            .onPressed,
        isNull,
      );
      await _tap(t, find.byKey(const Key('exception-confirm-cancel')));
      await _tap(t, find.byKey(const Key('exception-save-cancel')));
      expect(repo.cancellations, isEmpty);
      final f = find.byKey(const Key('exception-cancel-reason'));
      await t.ensureVisible(f);
      await t.enterText(f, 'Periode harus disesuaikan keputusan sekolah.');
      await _tap(t, find.byKey(const Key('exception-save-cancel')));
      expect(repo.cancellations.single, (
        7,
        'Periode harus disesuaikan keputusan sekolah.',
      ));
      expect(
        t.widget<TextFormField>(f).controller!.text,
        'Periode harus disesuaikan keputusan sekolah.',
      );
      repo.cancelError = null;
      await _tap(t, find.byKey(const Key('exception-save-cancel')));
      expect(repo.cancellations, hasLength(2));
      expect(find.byType(AttendanceExceptionCancelView), findsNothing);
      expect(find.byKey(const Key('exception-cancel')), findsNothing);
    },
  );
  testWidgets(
    'preview search, affected-only and local pagination preserve confirmation token',
    (t) async {
      final data = {
        ..._preview,
        'siswa': [
          for (var i = 0; i < 45; i++)
            {
              'nama': 'Siswa $i',
              'kelas': 'VIII.A',
              'nomor_absen': i + 1,
              'alfa_dibatalkan': i.isEven ? 4 : 0,
            },
        ],
      };
      final repo = _Repo();
      await _mount(
        t,
        AttendanceExceptionPreviewView(preview: data, accountId: 10),
        repo,
      );
      await _tap(t, find.text('Muat siswa berikutnya'));
      expect(find.text('Siswa 20'), findsOneWidget);
      await t.drag(find.byType(ListView).first, const Offset(0, 3000));
      await t.pumpAndSettle();
      await _tap(t, find.text('Hanya terdampak'));
      await t.drag(find.byType(ListView).first, const Offset(0, 3000));
      await t.pumpAndSettle();
      await _tap(t, find.byKey(const Key('exception-preview-search')));
      await t.enterText(
        find.byKey(const Key('exception-preview-search')),
        'Siswa 42',
      );
      await t.pumpAndSettle();
      expect(find.text('1 siswa'), findsOneWidget);
      expect(find.widgetWithText(ListTile, 'Siswa 42'), findsOneWidget);
      expect(repo.applied, isEmpty);
      expect(repo.previews, isEmpty);
    },
  );
  testWidgets(
    'account change hides preview and prevents using the previous token',
    (t) async {
      final repo = _Repo();
      final c = await _mount(
        t,
        AttendanceExceptionPreviewView(preview: _preview, accountId: 10),
        repo,
      );
      await _tap(t, find.byKey(const Key('exception-confirm')));
      (c.read(authControllerProvider.notifier) as _Auth).change(_student);
      await t.pumpAndSettle();
      expect(find.byKey(const Key('exception-apply')), findsNothing);
      expect(repo.applied, isEmpty);
    },
  );
  for (final size in [
    const Size(320, 760),
    const Size(390, 844),
    const Size(768, 1024),
  ]) {
    testWidgets('history, form, detail, preview and cancellation fit $size', (
      t,
    ) async {
      final repo = _Repo();
      await _mount(
        t,
        const AttendanceExceptionsView(),
        repo,
        size: size,
        scale: 1.35,
      );
      await _screenshot(t, 'history-${size.width.toInt()}');
      expect(t.takeException(), isNull);
      await _mount(
        t,
        const AttendanceExceptionFormView(
          reference: _references,
          accountId: 10,
        ),
        repo,
        size: size,
        scale: 1.35,
      );
      await _screenshot(t, 'form-${size.width.toInt()}');
      expect(t.takeException(), isNull);
      await _mount(
        t,
        AttendanceExceptionPreviewView(preview: _preview, accountId: 10),
        repo,
        size: size,
        scale: 1.35,
      );
      await _screenshot(t, 'preview-${size.width.toInt()}');
      expect(t.takeException(), isNull);
      await _mount(
        t,
        const AttendanceExceptionDetailView(id: 7),
        repo,
        size: size,
        scale: 1.35,
      );
      await _screenshot(t, 'detail-${size.width.toInt()}');
      await _tap(t, find.byKey(const Key('exception-cancel')));
      await _screenshot(t, 'cancel-${size.width.toInt()}');
      expect(t.takeException(), isNull);
    });
  }
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

Future<void> _enterReason(WidgetTester t, String reason) async {
  final f = find.byKey(const Key('exception-reason'));
  await t.ensureVisible(f);
  await t.enterText(f, reason);
  await t.pumpAndSettle();
}

Future<void> _screenshot(WidgetTester t, String name) async {
  if (Platform.environment['NUSA_CAPTURE_PENGECUALIAN_UI'] != '1') return;
  await t.pumpAndSettle();
  final boundary =
      _capture.currentContext!.findRenderObject()! as RenderRepaintBoundary;
  await t.runAsync(() async {
    final image = await boundary.toImage();
    final data = await image.toByteData(format: ui.ImageByteFormat.png);
    image.dispose();
    final dir = Directory('build/pengecualian-presensi-qa');
    await dir.create(recursive: true);
    await File('${dir.path}/$name.png')
        .writeAsBytes(data!.buffer.asUint8List());
  });
}

Future<ProviderContainer> _mount(
  WidgetTester t,
  Widget child,
  _Repo repo, {
  Pengguna user = _admin,
  String? route,
  Size size = const Size(390, 844),
  double scale = 1,
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
      attendanceExceptionsRepositoryProvider.overrideWithValue(repo),
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
    const style = WidgetStatePropertyAll(
      TextStyle(fontFamily: 'Roboto', fontWeight: FontWeight.w600),
    );
    theme = theme.copyWith(
      filledButtonTheme: FilledButtonThemeData(
        style: theme.filledButtonTheme.style?.copyWith(textStyle: style),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: theme.outlinedButtonTheme.style?.copyWith(textStyle: style),
      ),
    );
  }
  await t.pumpWidget(
    UncontrolledProviderScope(
      container: c,
      child: RepaintBoundary(
        key: _capture,
        child: route == null
            ? MaterialApp(
                theme: theme,
                initialRoute: '/test-feature',
                routes: {
                  '/': (_) => const SizedBox(),
                  '/test-feature': (_) => child,
                },
              )
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

class _Repo extends AttendanceExceptionsRepository {
  _Repo() : super(Dio());
  final reads = <String>[];
  final filters = <ExceptionData>[];
  final previews = <ExceptionData>[];
  final applied = <String>[];
  final cancellations = <(int, String)>[];
  Object? applyError;
  Object? cancelError;
  Completer<ExceptionData>? pendingApply;
  bool cancelled = false;
  @override
  Future<ExceptionData> references() async {
    reads.add('references');
    return _references;
  }

  @override
  Future<ExceptionData> list(ExceptionData filter) async {
    reads.add('list');
    filters.add(Map.of(filter));
    return {
      'items': [
        {
          ..._record,
          if (filter['halaman'] == 2)
            'alasan': 'PJJ akibat bencana asap - kedua.',
        },
      ],
      'paginasi': {
        'halaman': filter['halaman'],
        'total': 2,
        'ada_halaman_berikutnya': filter['halaman'] == 1,
      },
      'ringkasan': {'total': 2, 'aktif': 2, 'dibatalkan': 0},
      'hak_akses': {'dapat_kelola': true},
    };
  }

  @override
  Future<ExceptionData> detail(int id) async {
    reads.add('detail/$id');
    return {
      ..._record,
      'aktif': !cancelled,
      'hak_akses': {'dapat_batalkan': !cancelled},
    };
  }

  @override
  Future<ExceptionData> preview(ExceptionData values) async {
    previews.add(Map.of(values));
    return {..._preview, 'isian': values};
  }

  @override
  Future<ExceptionData> apply(String token) async {
    applied.add(token);
    if (applyError != null) throw applyError!;
    return pendingApply == null ? _record : pendingApply!.future;
  }

  @override
  Future<ExceptionData> cancel(int id, String reason) async {
    cancellations.add((id, reason));
    if (cancelError != null) throw cancelError!;
    cancelled = true;
    return _record;
  }
}

const _references = <String, dynamic>{
  'tahun_pelajaran': [
    {
      'id': 1,
      'nama': '2026/2027',
      'aktif': true,
      'tanggal_mulai': '2026-07-01',
      'tanggal_selesai': '2027-06-30',
    },
    {
      'id': 2,
      'nama': '2027/2028',
      'aktif': false,
      'tanggal_mulai': '2027-07-01',
      'tanggal_selesai': '2028-06-30',
    },
  ],
  'kelas': [
    {'id': 101, 'nama': 'VIII.A', 'tahun_pelajaran_id': 1},
    {'id': 201, 'nama': 'IX.A', 'tahun_pelajaran_id': 2},
  ],
  'jenis': {'pjj': 'PJJ', 'gangguan_scanner': 'Gangguan scanner'},
  'hak_akses': {'dapat_kelola': true},
};
const _record = <String, dynamic>{
  'id': 7,
  'aktif': true,
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'cakupan_label': 'Seluruh sekolah',
  'jenis_label': 'PJJ',
  'tanggal_mulai': '2026-09-14',
  'tanggal_selesai': '2026-09-18',
  'alasan': 'PJJ akibat bencana asap sesuai keputusan sekolah.',
  'hak_akses': {'dapat_batalkan': true},
  'pembuat': {'id': 10, 'nama': 'Pengelola Presensi'},
  'dibuat_pada': '2026-10-13T10:00:00+07:00',
  'dampak_pratinjau': {
    'siswa_dalam_cakupan': 3,
    'siswa_terdampak': 3,
    'alfa_otomatis_dibatalkan': 12,
    'hari_aktif_terlewati': 5,
    'catatan_dipertahankan': 3,
    'alfa_manual': 1,
  },
};
final _preview = <String, dynamic>{
  'token': 'opaque-token',
  'berlaku_sampai': '2026-10-13T10:15:00+07:00',
  'isian': {
    'tanggal_mulai': '2026-09-14',
    'tanggal_selesai': '2026-09-18',
    'alasan': _record['alasan'],
  },
  'tahun_pelajaran': _record['tahun_pelajaran'],
  'cakupan_label': 'Seluruh sekolah',
  'jenis_label': 'PJJ',
  'dampak': _record['dampak_pratinjau'],
  'peringatan': [
    'Pengecualian scan bukan bukti hadir. Alfa manual tetap berlaku.',
    'Periksa ulang rekap rapor yang telah disimpan.',
  ],
  'siswa': [
    for (var i = 0; i < 3; i++)
      {
        'nama': 'Siswa $i',
        'kelas': 'VIII.A',
        'nomor_absen': i + 1,
        'alfa_dibatalkan': 4,
      },
  ],
};
