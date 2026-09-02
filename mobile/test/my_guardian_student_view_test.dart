import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_guardian_students/data/my_guardian_student_remote_data_source.dart';
import 'package:nusa/features/my_guardian_students/domain/my_guardian_student.dart';
import 'package:nusa/features/my_guardian_students/presentation/my_guardian_student_detail_view.dart';
import 'package:nusa/features/my_guardian_students/presentation/my_guardian_student_list_view.dart';

void main() {
  test('domain membaca ringkasan, siswa, kontak, dan laporan terbaru', () {
    final page = MyGuardianStudentPage.fromJson(_pageJson());
    final detail = MyGuardianStudentDetail.fromJson(_detailJson());

    expect(page.summary.students, 2);
    expect(page.items.single.schoolClass?.name, 'VII.SW');
    expect(page.items.single.totalPoints, 8);
    expect(detail.student.parentContact.fatherName, 'Ayah Siswa Wali');
    expect(detail.latestReports.single.statusLabel, 'Pemeriksaan BK');
    expect(detail.access.canViewPointRecap, isTrue);
  });

  testWidgets('daftar siswa wali rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeMyGuardianStudentRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          myGuardianStudentRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const MyGuardianStudentListView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Siswa Wali Saya'), findsOneWidget);
    expect(find.text('Andi Siswa Wali Native'), findsWidgets);
    expect(
      find.byKey(const Key('my-guardian-student-grade-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('my-guardian-student-class-filter')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('detail menampilkan identitas dan pemantauan secara responsif', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeMyGuardianStudentRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          myGuardianStudentRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const MyGuardianStudentDetailView(studentId: 1),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Detail Siswa Wali'), findsOneWidget);
    expect(find.text('Andi Siswa Wali Native'), findsWidgets);
    expect(
      find.byKey(const Key('my-guardian-student-point-recap')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);

    await tester.scrollUntilVisible(
      find.text('Ayah Siswa Wali'),
      420,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    expect(find.text('Ayah Siswa Wali'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.scrollUntilVisible(
      find.byKey(const Key('my-guardian-student-report-99')),
      520,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    expect(find.text('PB-SW-NATIVE-001'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _FakeMyGuardianStudentRemoteDataSource
    implements MyGuardianStudentRemoteDataSource {
  @override
  Future<MyGuardianStudentPage> fetch({
    required String query,
    required int? grade,
    required int? classId,
    required int page,
  }) async => MyGuardianStudentPage.fromJson(_pageJson());

  @override
  Future<MyGuardianStudentDetail> detail(int studentId) async =>
      MyGuardianStudentDetail.fromJson(_detailJson());
}

Map<String, dynamic> _pageJson() => {
  'items': const [
    {
      'id': 1,
      'nama': 'Andi Siswa Wali Native',
      'nis': 'NIS-0077111001',
      'nisn': '0077111001',
      'foto_url': null,
      'jenis_kelamin': 'L',
      'label_jenis_kelamin': 'Laki-laki',
      'kelas': {'id': 3, 'nama': 'VII.SW', 'tingkat': 7, 'nomor_absen': 1},
      'total_poin': 8,
      'jumlah_laporan': 1,
      'tanggal_mulai_didampingi': '2026-07-15',
    },
  ],
  'ringkasan': {
    'jumlah_siswa': 2,
    'jumlah_kelas': 2,
    'laki_laki': 1,
    'perempuan': 1,
    'memiliki_poin': 1,
  },
  'tahun_pelajaran': {'id': 2, 'nama': '2026/2027', 'aktif': true},
  'pilihan': {
    'tingkat': const [
      {'nilai': 7, 'label': 'VII'},
      {'nilai': 8, 'label': 'VIII'},
      {'nilai': 9, 'label': 'IX'},
    ],
    'kelas': const [
      {'id': 3, 'nama': 'VII.SW', 'tingkat': 7},
      {'id': 4, 'nama': 'VIII.SW', 'tingkat': 8},
    ],
  },
  'filter': {'kata_kunci': '', 'tingkat': null, 'kelas_id': null},
  'paginasi': {
    'halaman': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
  'hak_akses': {'dapat_melihat_rekap_poin': true},
};

Map<String, dynamic> _detailJson() => {
  'siswa': {
    'id': 1,
    'nama': 'Andi Siswa Wali Native',
    'nis': 'NIS-0077111001',
    'nisn': '0077111001',
    'nik': '1374010203040005',
    'foto_url': null,
    'jenis_kelamin': 'L',
    'label_jenis_kelamin': 'Laki-laki',
    'tempat_lahir': 'Padang Panjang',
    'tanggal_lahir': '2013-05-04',
    'agama': 'Islam',
    'sekolah_asal': 'SD Negeri Contoh',
    'status_dalam_keluarga': 'Anak kandung',
    'anak_ke': 1,
    'aktif': true,
    'orang_tua_wali': {
      'nama_ayah': 'Ayah Siswa Wali',
      'nomor_wa_ayah': '081234567890',
      'pekerjaan_ayah': 'Wiraswasta',
      'nama_ibu': 'Ibu Siswa Wali',
      'nomor_wa_ibu': '081234567891',
      'pekerjaan_ibu': 'Guru',
      'nama_wali': null,
      'hubungan_wali': null,
      'nomor_wa_wali': null,
      'kontak_absensi_utama': 'ayah',
      'label_kontak_absensi_utama': 'Ayah',
    },
    'alamat': 'Padang Panjang',
    'keterangan': 'Perlu komunikasi rutin dengan orang tua.',
  },
  'kelas': {'id': 3, 'nama': 'VII.SW', 'tingkat': 7, 'nomor_absen': 1},
  'penugasan': {
    'id': 9,
    'tanggal_mulai': '2026-07-15',
    'nomor_sk': 'SK/GW/SW/001',
    'catatan': 'Pendampingan aktif Guru Wali.',
  },
  'tahun_pelajaran': {'id': 2, 'nama': '2026/2027', 'aktif': true},
  'ringkasan': {'total_poin': 8, 'jumlah_laporan': 1},
  'laporan_terbaru': const [
    {
      'id': 99,
      'nomor': 'PB-SW-NATIVE-001',
      'tanggal': '2026-08-20',
      'jenis': 'pelanggaran',
      'label_jenis': 'Pelanggaran Berpoin',
      'kategori': 'Kedisiplinan',
      'kelas': 'VII.SW',
      'status': 'pemeriksaan_bk',
      'label_status': 'Pemeriksaan BK',
      'poin': 8,
    },
  ],
  'hak_akses': {'dapat_melihat_rekap_poin': true},
};
