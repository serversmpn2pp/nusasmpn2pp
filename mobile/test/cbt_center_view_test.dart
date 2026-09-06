import 'package:flutter/material.dart';
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

      await tester.drag(find.byType(ListView), const Offset(0, -700));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Bank Soal'));
      await tester.pumpAndSettle();
      expect(find.text('Bank Soal Native'), findsOneWidget);
      router.pop();
      await tester.pumpAndSettle();

      await tester.drag(find.byType(ListView), const Offset(0, -700));
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
}

class _FakeCbtCenterRemoteDataSource implements CbtCenterRemoteDataSource {
  @override
  Future<CbtCenterData> fetch() async => CbtCenterData.fromJson(_response());
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
