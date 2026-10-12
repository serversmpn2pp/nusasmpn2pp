import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/cbt_center/data/cbt_center_remote_data_source.dart';
import 'package:nusa/features/cbt_center/domain/cbt_center.dart';
import 'package:nusa/features/cbt_center/presentation/cbt_center_view.dart';

void main() {
  test('domain membaca tiga konteks pusat CBT', () {
    final data = CbtCenterData.fromJson(_response());

    expect(data.access.canManage, isTrue);
    expect(data.management?.summary.readyQuestions, 48);
    expect(data.supervisor?.tasks.single.room, 'Labor Komputer 1');
    expect(data.student?.exams.single.startAt?.isUtc, isFalse);
  });

  testWidgets(
    'pusat CBT rapi pada layar kecil dan pintasan memberi umpan balik',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final router = GoRouter(
        initialLocation: '/',
        routes: [
          GoRoute(
            path: '/',
            builder: (context, state) =>
                const CbtCenterView(focus: CbtCenterFocus.management),
          ),
          GoRoute(
            path: '/bank-soal',
            builder: (context, state) =>
                const Scaffold(body: Center(child: Text('Bank Soal Native'))),
          ),
          GoRoute(
            path: '/asesmen-kelas',
            builder: (context, state) => const Scaffold(
              body: Center(child: Text('Asesmen Kelas Native')),
            ),
          ),
          GoRoute(
            path: '/tugas-pengawas-ujian/:id',
            builder: (context, state) => const Scaffold(
              body: Center(child: Text('Detail Pengawas Native')),
            ),
          ),
        ],
      );
      addTearDown(router.dispose);

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            cbtCenterRemoteDataSourceProvider.overrideWithValue(
              _FakeCbtCenterRemoteDataSource(),
            ),
          ],
          child: MaterialApp.router(
            theme: AppTheme.light,
            routerConfig: router,
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Ujian & Asesmen'), findsOneWidget);
      expect(find.text('Ringkasan Pengelolaan'), findsOneWidget);
      expect(find.text('48'), findsOneWidget);

      await tester.scrollUntilVisible(
        find.byKey(const Key('cbt-tool-bank-soal')),
        250,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Bank Soal'));
      await tester.pumpAndSettle();
      expect(find.text('Bank Soal Native'), findsOneWidget);
      router.pop();
      await tester.pumpAndSettle();

      await tester.scrollUntilVisible(
        find.byKey(const Key('supervisor-task-1')),
        250,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      expect(find.text('Labor Komputer 1'), findsOneWidget);
      await tester.tap(find.byKey(const Key('supervisor-task-1')));
      await tester.pumpAndSettle();
      expect(find.text('Detail Pengawas Native'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('ujian siswa menempatkan konteks siswa sebagai bagian pertama', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          cbtCenterRemoteDataSourceProvider.overrideWithValue(
            _FakeCbtCenterRemoteDataSource(),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const CbtCenterView(focus: CbtCenterFocus.student),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.widgetWithText(AppBar, 'Ujian Saya'), findsOneWidget);
    final studentTitle = tester.getTopLeft(find.text('Ujian Saya').last).dy;
    final managementTitle = tester
        .getTopLeft(find.text('Ringkasan Pengelolaan'))
        .dy;
    expect(studentTitle, lessThan(managementTitle));
    expect(find.text('Siap dimulai'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  test(
    'katalog membaca tiga tahap dan menolak rute native yang tidak cocok',
    () {
      final data = CbtCenterData.fromJson(_centralResponse());
      final steps = data.management!.flows.first.steps;
      expect(steps.map((step) => step.stepNumber), [1, 2, 3]);
      expect(steps[1].nativeRoute, '/pelaksanaan-ujian-terpusat');
      expect(steps[1].description, isNotEmpty);
      expect(
        CbtTool.fromJson({
          'kode': 'ujian-terpusat',
          'status': 'tersedia',
          'rute': '/rute-belum-dikenal',
        }).nativeRoute,
        isNull,
      );
      expect(
        const CbtTool(
          code: 'hasil-ujian-terpusat',
          label: 'Nilai & Hasil',
          status: 'segera_hadir',
          route: '/hasil-ujian-terpusat',
        ).nativeRoute,
        isNull,
      );
    },
  );

  testWidgets('ketiga tahap ujian membuka halaman yang sesuai', (tester) async {
    final router = GoRouter(
      routes: [
        GoRoute(
          path: '/',
          builder: (context, state) =>
              const CbtCenterView(focus: CbtCenterFocus.management),
        ),
        for (final code in [
          'ujian-terpusat',
          'pelaksanaan-ujian-terpusat',
          'hasil-ujian-terpusat',
        ])
          GoRoute(
            path: '/$code',
            builder: (context, state) => Scaffold(body: Text('Modul $code')),
          ),
      ],
    );
    addTearDown(router.dispose);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          cbtCenterRemoteDataSourceProvider.overrideWithValue(
            _FakeCbtCenterRemoteDataSource(_centralResponse()),
          ),
        ],
        child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();
    for (final code in [
      'ujian-terpusat',
      'pelaksanaan-ujian-terpusat',
      'hasil-ujian-terpusat',
    ]) {
      final card = find.byKey(Key('cbt-tool-$code'));
      await _reveal(tester, card);
      await tester.tap(card);
      await tester.pumpAndSettle();
      expect(find.text('Modul $code'), findsOneWidget);
      router.pop();
      await tester.pumpAndSettle();
    }
    expect(tester.takeException(), isNull);
  });

  for (final scale in [1.0, 1.5, 2.0]) {
    testWidgets('alur terpusat rapi di layar kecil pada skala teks $scale', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            cbtCenterRemoteDataSourceProvider.overrideWithValue(
              _FakeCbtCenterRemoteDataSource(_centralResponse()),
            ),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context)
                  .copyWith(textScaler: TextScaler.linear(scale)),
              child: child!,
            ),
            home: const CbtCenterView(focus: CbtCenterFocus.management),
          ),
        ),
      );
      await tester.pumpAndSettle();
      for (final (index, code) in [
        'ujian-terpusat',
        'pelaksanaan-ujian-terpusat',
        'hasil-ujian-terpusat',
      ].indexed) {
        await _reveal(tester, find.byKey(Key('cbt-tool-$code')));
        expect(find.text('TAHAP ${index + 1}'), findsOneWidget);
      }
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets(
    'izin hasil saja tidak menciptakan tahap persiapan atau pelaksanaan',
    (tester) async {
      final response = _centralResponse();
      final management = response['pengelolaan'] as Map<String, dynamic>;
      final tools = management['alat'] as List<Map<String, dynamic>>;
      management['alat'] = tools
          .where((tool) => tool['kode'] == 'hasil-ujian-terpusat')
          .toList();
      // Langkah dari payload yang tidak konsisten juga tidak menambah akses UI.
      await _pumpManagement(tester, response);
      expect(
        find.byKey(const Key('cbt-tool-hasil-ujian-terpusat')),
        findsOneWidget,
      );
      expect(find.byKey(const Key('cbt-tool-ujian-terpusat')), findsNothing);
      expect(
        find.byKey(const Key('cbt-tool-pelaksanaan-ujian-terpusat')),
        findsNothing,
      );
      expect(find.text('TAHAP 3'), findsOneWidget);
    },
  );

  testWidgets('katalog yang belum tersedia tidak membuka rute', (tester) async {
    final response = _centralResponse();
    final management = response['pengelolaan'] as Map<String, dynamic>;
    management['alur'] = <Map<String, dynamic>>[];
    management['alat'] = [
      {
        'kode': 'hasil-ujian-terpusat',
        'label': 'Nilai & Hasil',
        'status': 'segera_hadir',
        'rute': '/hasil-ujian-terpusat',
      },
      {
        'kode': 'modul-baru',
        'label': 'Modul Belum Dikenal',
        'status': 'tersedia',
        'rute': '/modul-baru',
      },
    ];
    await _pumpManagement(tester, response);
    for (final code in ['hasil-ujian-terpusat', 'modul-baru']) {
      final card = tester.widget<InkWell>(find.byKey(Key('cbt-tool-$code')));
      expect(card.onTap, isNull);
    }
    expect(find.text('Belum tersedia di aplikasi ini'), findsNWidgets(2));
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'flag akses siswa tidak merender payload pengelolaan yang keliru',
    (tester) async {
      final response = _response();
      (response['akses'] as Map<String, dynamic>)['dapat_mengelola'] = false;
      await _pumpManagement(tester, response);
      expect(find.text('Ringkasan Pengelolaan'), findsNothing);
      expect(find.byKey(const Key('cbt-tool-bank-soal')), findsNothing);
    },
  );

  // Pratinjau opt-in menggunakan data contoh, tanpa akun/API sekolah.
  if (Platform.environment['NUSA_CBT_PREVIEW'] == '1') {
    testWidgets('export pusat ujian dengan data contoh', (tester) async {
      final fontDirectory = Platform.environment['NUSA_UI_FONT_DIR']!;
      await tester.runAsync(() async {
        final textFont = FontLoader('Roboto');
        for (final weight in ['regular', 'medium', 'bold', 'black']) {
          final bytes = await File('$fontDirectory/roboto-$weight.ttf')
              .readAsBytes();
          textFont.addFont(Future.value(ByteData.sublistView(bytes)));
        }
        await textFont.load();
        final icons = await File('$fontDirectory/materialicons-regular.otf')
            .readAsBytes();
        await (FontLoader(
          'MaterialIcons',
        )..addFont(Future.value(ByteData.sublistView(icons)))).load();
      });
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      const boundaryKey = Key('cbt-preview-boundary');
      final response = _centralResponse();
      final management = response['pengelolaan'] as Map<String, dynamic>;
      management['alat'] = <Map<String, dynamic>>[
        ...(management['alat'] as List<Map<String, dynamic>>),
        ...((_response()['pengelolaan'] as Map<String, dynamic>)['alat']
            as List<Map<String, dynamic>>),
        {
          'kode': 'presensi-ujian',
          'label': 'Presensi Ujian',
          'status': 'tersedia',
          'rute': '/presensi-ujian',
        },
      ];
      management['alur'] = <Map<String, dynamic>>[
        ...(management['alur'] as List<Map<String, dynamic>>),
        {
          'kode': 'asesmen-kelas',
          'judul': 'Asesmen Kelas',
          'deskripsi':
              'Bank soal dan asesmen harian untuk kelas yang Anda ajar.',
          'warna': 'biru',
        },
      ];
      await tester.pumpWidget(
        RepaintBoundary(
          key: boundaryKey,
          child: ProviderScope(
            overrides: [
              cbtCenterRemoteDataSourceProvider.overrideWithValue(
                _FakeCbtCenterRemoteDataSource(response),
              ),
            ],
            child: MaterialApp(
              debugShowCheckedModeBanner: false,
              theme: AppTheme.light,
              home: const CbtCenterView(focus: CbtCenterFocus.management),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await _exportPreview(tester, boundaryKey, 'cbt-center');
      await tester.drag(find.byType(ListView), const Offset(0, -390));
      await tester.pumpAndSettle();
      await _exportPreview(tester, boundaryKey, 'cbt-central-flow');
      expect(tester.takeException(), isNull);
    });
  }
}

Future<void> _exportPreview(WidgetTester tester, Key key, String name) async {
  final boundary = tester.renderObject<RenderRepaintBoundary>(find.byKey(key));
  await tester.runAsync(() async {
    final image = await boundary.toImage(pixelRatio: 2);
    final data = (await image.toByteData(format: ui.ImageByteFormat.png))!;
    final directory = Directory('build/ui-preview');
    await directory.create(recursive: true);
    await File('${directory.path}/$name.png')
        .writeAsBytes(data.buffer.asUint8List());
    image.dispose();
  });
}

class _FakeCbtCenterRemoteDataSource implements CbtCenterRemoteDataSource {
  _FakeCbtCenterRemoteDataSource([this.response]);
  final Map<String, dynamic>? response;

  @override
  Future<CbtCenterData> fetch() async =>
      CbtCenterData.fromJson(response ?? _response());
}

Future<void> _pumpManagement(
  WidgetTester tester,
  Map<String, dynamic> response,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        cbtCenterRemoteDataSourceProvider.overrideWithValue(
          _FakeCbtCenterRemoteDataSource(response),
        ),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const CbtCenterView(focus: CbtCenterFocus.management),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _reveal(WidgetTester tester, Finder target) async {
  for (var attempt = 0; attempt < 50; attempt++) {
    if (target.hitTestable().evaluate().isNotEmpty) return;
    await tester.drag(find.byType(ListView).first, const Offset(0, -180));
    await tester.pumpAndSettle();
  }
  fail('Pintasan tidak terlihat setelah menggulir: $target');
}

Map<String, dynamic> _centralResponse() {
  final response = _response();
  response['akses'] = {
    'dapat_mengelola': true,
    'memiliki_tugas_pengawas': false,
    'akun_siswa': false,
  };
  response['pengawas'] = null;
  response['siswa'] = null;
  final steps = <Map<String, dynamic>>[
    {
      'kode': 'ujian-terpusat',
      'label': 'Ujian Terpusat',
      'nomor': 1,
      'deskripsi':
          'Siapkan kegiatan, panitia, sesi, ruang, peserta, dan jadwal.',
      'status': 'tersedia',
      'rute': '/ujian-terpusat',
    },
    {
      'kode': 'pelaksanaan-ujian-terpusat',
      'label': 'Pelaksanaan Ujian',
      'nomor': 2,
      'deskripsi': 'Pantau ruang, pengawas, dan pengerjaan peserta ujian.',
      'status': 'tersedia',
      'rute': '/pelaksanaan-ujian-terpusat',
    },
    {
      'kode': 'hasil-ujian-terpusat',
      'label': 'Nilai & Hasil',
      'nomor': 3,
      'deskripsi':
          'Koreksi uraian, finalisasi, publikasi, dan penerapan nilai.',
      'status': 'tersedia',
      'rute': '/hasil-ujian-terpusat',
    },
  ];
  final management = response['pengelolaan'] as Map<String, dynamic>;
  management['alur'] = [
    {
      'kode': 'ujian-terpusat',
      'judul': 'Ujian Terpusat',
      'deskripsi':
          'Persiapan, pelaksanaan, hingga pengolahan hasil ujian sekolah.',
      'warna': 'kuning',
      'langkah': steps,
    },
  ];
  management['alat'] = steps;
  return response;
}

Map<String, dynamic> _response() => {
  'akses': {
    'dapat_mengelola': true,
    'memiliki_tugas_pengawas': true,
    'akun_siswa': true,
  },
  'pengelolaan': {
    'ringkasan': {
      'soal_siap': 48,
      'kegiatan_terpusat': 2,
      'asesmen_kelas': 5,
      'paket_terjadwal': 9,
    },
    'alur': [
      {
        'kode': 'asesmen-kelas',
        'judul': 'Asesmen Kelas',
        'deskripsi': 'Asesmen harian yang disiapkan guru.',
        'warna': 'biru',
      },
      {
        'kode': 'ujian-terpusat',
        'judul': 'Ujian Terpusat',
        'deskripsi': 'Ujian sekolah dengan ruang dan pengawas.',
        'warna': 'kuning',
      },
    ],
    'alat': [
      {
        'kode': 'asesmen-kelas',
        'label': 'Asesmen Kelas',
        'status': 'tersedia',
        'rute': '/asesmen-kelas',
      },
      {
        'kode': 'bank-soal',
        'label': 'Bank Soal',
        'status': 'tersedia',
        'rute': '/bank-soal',
      },
      {
        'kode': 'paket-soal',
        'label': 'Paket Soal',
        'status': 'tersedia',
        'rute': '/paket-soal',
      },
    ],
  },
  'pengawas': {
    'ringkasan': {'jumlah': 1, 'hari_ini': 1, 'perlu_bukti': 1},
    'items': [
      {
        'id': 1,
        'ruang_id': 11,
        'dapat_dibuka': true,
        'kegiatan': 'Simulasi Ujian Sekolah',
        'jenis_ujian': 'Simulasi',
        'mata_pelajaran': 'Matematika',
        'tanggal': '2026-09-02',
        'waktu': '07:30 - 09:00',
        'ruang': 'Labor Komputer 1',
        'peran': 'Pengawas utama',
        'status_bukti': 'belum_diunggah',
        'label_status_bukti': 'Belum diunggah',
        'jumlah_peserta': 30,
        'status': 'siap',
        'label_status': 'Siap',
      },
    ],
    'operasional_native': true,
  },
  'siswa': {
    'ringkasan': {'aktif': 1, 'akan_datang': 0, 'selesai': 0, 'total': 1},
    'items': [
      {
        'id': 7,
        'ujian_id': 3,
        'nama': 'Asesmen Bab 1',
        'kode': 'MAT-01',
        'jenis_ujian': 'Asesmen Harian',
        'mata_pelajaran': 'Matematika',
        'kelompok': 'aktif',
        'label_status': 'Siap dimulai',
        'nada_status': 'aktif',
        'waktu_mulai': '2026-09-02T07:30:00+07:00',
        'waktu_selesai': '2026-09-02T09:00:00+07:00',
        'tanggal': '2026-09-02',
        'waktu': '07:30 - 09:00',
        'durasi_menit': 90,
        'nomor_peserta': '26001',
      },
    ],
    'pengerjaan_native': true,
  },
};
