import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/guardian_assignment/data/guardian_assignment_remote_data_source.dart';
import 'package:nusa/features/guardian_assignment/domain/guardian_assignment.dart';
import 'package:nusa/features/guardian_assignment/presentation/guardian_assignment_create_view.dart';
import 'package:nusa/features/guardian_assignment/presentation/guardian_assignment_list_view.dart';

void main() {
  test('domain membaca ringkasan, penugasan, dan siswa yang akan dipindah', () {
    final page = GuardianAssignmentPage.fromJson(_pageJson());

    expect(page.summary.unassignedStudents, 1);
    expect(page.items.single.guardian.name, 'Guru Wali Native Baru');
    expect(
      page.options.students.first.activeAssignment?.guardian.name,
      'Guru Wali Native Lama',
    );
    expect(page.options.employees.first.activeStudentCount, 1);
  });

  testWidgets('daftar penugasan rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeGuardianAssignmentRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          guardianAssignmentRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const GuardianAssignmentListView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Penugasan Guru Wali'), findsOneWidget);
    expect(find.text('Siswa Guru Wali Native A'), findsOneWidget);
    expect(find.text('Guru Wali Native Baru'), findsWidgets);
    expect(find.byKey(const Key('guardian-assignment-filter')), findsOneWidget);
    expect(find.byKey(const Key('guardian-assignment-create')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('form massal mengonfirmasi pemindahan siswa Guru Wali', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeGuardianAssignmentRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          guardianAssignmentRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: Builder(
            builder: (context) => Scaffold(
              body: Center(
                child: FilledButton(
                  key: const Key('open-create'),
                  onPressed: () => Navigator.push<void>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const GuardianAssignmentCreateView(),
                    ),
                  ),
                  child: const Text('Buka'),
                ),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.byKey(const Key('open-create')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);

    await tester.tap(find.byKey(const Key('guardian-assignment-guardian')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Guru Wali Native Baru (1 siswa)').last);
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.scrollUntilVisible(
      find.byKey(const Key('guardian-assignment-student-1')),
      450,
      scrollable: find.byType(Scrollable).first,
    );
    expect(tester.takeException(), isNull);
    await tester.tap(find.byKey(const Key('guardian-assignment-student-1')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.tap(find.byKey(const Key('guardian-assignment-submit')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);

    expect(find.text('Pindahkan Guru Wali?'), findsOneWidget);
    await tester.tap(
      find.byKey(const Key('guardian-assignment-confirm-transfer')),
    );
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(remote.lastPayload?.guardianId, 5);
    expect(remote.lastPayload?.studentIds, [1]);
    expect(tester.takeException(), isNull);
  });
}

class _FakeGuardianAssignmentRemoteDataSource
    implements GuardianAssignmentRemoteDataSource {
  int createCalls = 0;
  GuardianAssignmentPayload? lastPayload;

  @override
  Future<GuardianAssignmentPage> fetch({
    required String query,
    required int? guardianId,
    required int page,
  }) async => GuardianAssignmentPage.fromJson(_pageJson());

  @override
  Future<GuardianAssignmentResult> create(
    GuardianAssignmentPayload payload,
  ) async {
    createCalls++;
    lastPayload = payload;
    return const GuardianAssignmentResult(
      message: '1 siswa berhasil ditugaskan.',
      created: 0,
      transferred: 1,
      unchanged: 0,
    );
  }

  @override
  Future<GuardianAssignmentMutation> end(int id) async =>
      GuardianAssignmentMutation(
        message: 'Penugasan berhasil diakhiri.',
        item: GuardianAssignmentItem.fromJson({
          ..._itemJson(),
          'aktif': false,
          'tanggal_selesai': '2026-09-01',
        }),
      );
}

Map<String, dynamic> _pageJson() => {
  'items': [_itemJson()],
  'ringkasan': {
    'jumlah_siswa_aktif': 2,
    'jumlah_ditugaskan': 1,
    'jumlah_belum_ditugaskan': 1,
    'jumlah_guru_wali': 1,
  },
  'pilihan': {
    'pegawai': const [
      {
        'id': 5,
        'nama': 'Guru Wali Native Baru',
        'nip': '198201012026091001',
        'jabatan': 'Guru Mata Pelajaran',
        'jumlah_siswa_aktif': 1,
      },
      {
        'id': 6,
        'nama': 'Guru Wali Native Lama',
        'nip': '198101012026091002',
        'jabatan': 'Guru Mata Pelajaran',
        'jumlah_siswa_aktif': 1,
      },
    ],
    'siswa': const [
      {
        'id': 1,
        'nama': 'Siswa Guru Wali Native A',
        'nis': 'NIS-01',
        'nisn': '0099776601',
        'kelas': {'id': 3, 'nama': 'VIII.GW', 'tingkat': 8},
        'penugasan_aktif': {
          'id': 9,
          'guru_wali': {
            'id': 6,
            'nama': 'Guru Wali Native Lama',
            'nip': '198101012026091002',
          },
        },
      },
      {
        'id': 2,
        'nama': 'Siswa Guru Wali Native B',
        'nis': 'NIS-02',
        'nisn': '0099776602',
        'kelas': {'id': 3, 'nama': 'VIII.GW', 'tingkat': 8},
        'penugasan_aktif': null,
      },
    ],
    'kelas': const [
      {'id': 3, 'nama': 'VIII.GW', 'tingkat': 8},
    ],
  },
  'filter': {'kata_kunci': '', 'guru_wali_pegawai_id': null},
  'paginasi': {
    'halaman': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
  'hak_akses': {'dapat_kelola': true},
};

Map<String, dynamic> _itemJson() => {
  'id': 7,
  'siswa': {
    'id': 1,
    'nama': 'Siswa Guru Wali Native A',
    'nis': 'NIS-01',
    'nisn': '0099776601',
  },
  'kelas': {'id': 3, 'nama': 'VIII.GW', 'tingkat': 8},
  'guru_wali': {
    'id': 5,
    'nama': 'Guru Wali Native Baru',
    'nip': '198201012026091001',
    'jabatan': 'Guru Mata Pelajaran',
  },
  'tanggal_mulai': '2026-08-01',
  'tanggal_selesai': null,
  'nomor_sk': 'SK/GW/001',
  'catatan': 'Pendampingan lintas kelas.',
  'aktif': true,
  'dibuat_oleh': 'Admin NUSA',
};
