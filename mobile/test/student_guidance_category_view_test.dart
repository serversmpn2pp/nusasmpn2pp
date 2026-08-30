import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_guidance_category/data/student_guidance_category_remote_data_source.dart';
import 'package:nusa/features/student_guidance_category/domain/student_guidance_category.dart';
import 'package:nusa/features/student_guidance_category/presentation/student_guidance_category_view.dart';

void main() {
  test('domain membaca ringkasan, hak akses, dan jumlah pemakaian', () {
    final page = StudentGuidanceCategoryPage.fromJson(_pageJson());

    expect(page.summary.total, 2);
    expect(page.access.canManage, isTrue);
    expect(page.items.first.reportCount, 3);
    expect(page.items.first.violationTypeCount, 2);
  });

  testWidgets('daftar kategori rapi pada layar Android sempit', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeStudentGuidanceCategoryRemoteDataSource());

    expect(find.text('Kategori Pembinaan Non-Poin'), findsOneWidget);
    expect(
      find.byKey(const Key('guidance-category-status-filter')),
      findsOneWidget,
    );
    expect(find.text('Kedisiplinan'), findsOneWidget);
    expect(find.text('3 laporan'), findsOneWidget);
    expect(find.byKey(const Key('add-guidance-category')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin dapat menambah mengubah dan menonaktifkan kategori', (
    tester,
  ) async {
    final remote = _FakeStudentGuidanceCategoryRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('add-guidance-category')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('guidance-category-form-name')),
      'Komunikasi Positif',
    );
    await tester.enterText(
      find.byKey(const Key('guidance-category-form-code')),
      'komunikasi positif',
    );
    await tester.tap(find.byKey(const Key('save-guidance-category')));
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(find.text('Komunikasi Positif'), findsOneWidget);

    await tester.tap(find.byKey(const Key('guidance-category-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ubah').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('guidance-category-form-name')),
      'Disiplin Positif',
    );
    await tester.tap(find.byKey(const Key('save-guidance-category')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(find.text('Disiplin Positif'), findsOneWidget);

    await tester.tap(find.byKey(const Key('guidance-category-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Nonaktifkan').last);
    await tester.pumpAndSettle();
    expect(
      find.textContaining('Semua riwayat tetap tersimpan'),
      findsOneWidget,
    );
    await tester.tap(
      find.byKey(const Key('confirm-guidance-category-deactivate')),
    );
    await tester.pumpAndSettle();

    expect(remote.deactivateCalls, 1);
    expect(find.text('Nonaktif'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('akun lihat tidak menerima tombol pengelolaan', (tester) async {
    await _pumpView(
      tester,
      _FakeStudentGuidanceCategoryRemoteDataSource(canManage: false),
    );

    expect(find.byKey(const Key('add-guidance-category')), findsNothing);
    expect(find.byKey(const Key('guidance-category-menu-1')), findsNothing);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  StudentGuidanceCategoryRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        studentGuidanceCategoryRemoteDataSourceProvider.overrideWithValue(
          remote,
        ),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const StudentGuidanceCategoryView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Map<String, dynamic> _pageJson({bool canManage = true}) => {
  'ringkasan': {'total': 2, 'aktif': 2, 'nonaktif': 0},
  'filter': {'cari': '', 'status': 'semua'},
  'hak_akses': {'dapat_kelola': canManage},
  'items': [
    {
      'id': 1,
      'nama': 'Kedisiplinan',
      'kode': 'KEDISIPLINAN',
      'deskripsi': 'Pembinaan terkait kedisiplinan siswa.',
      'aktif': true,
      'jumlah_laporan': 3,
      'jumlah_jenis_pelanggaran': 2,
      'dibuat_pada': '2026-08-30T08:00:00+07:00',
      'diperbarui_pada': '2026-08-30T08:00:00+07:00',
    },
  ],
  'paginasi': {
    'halaman': 1,
    'halaman_terakhir': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
};

final class _FakeStudentGuidanceCategoryRemoteDataSource
    implements StudentGuidanceCategoryRemoteDataSource {
  _FakeStudentGuidanceCategoryRemoteDataSource({this.canManage = true});

  final bool canManage;
  final List<StudentGuidanceCategory> _items = [
    const StudentGuidanceCategory(
      id: 1,
      name: 'Kedisiplinan',
      code: 'KEDISIPLINAN',
      description: 'Pembinaan terkait kedisiplinan siswa.',
      active: true,
      reportCount: 3,
      violationTypeCount: 2,
    ),
  ];
  int createCalls = 0;
  int updateCalls = 0;
  int deactivateCalls = 0;

  @override
  Future<StudentGuidanceCategoryPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    final normalized = query.toLowerCase();
    final filtered = _items.where((item) {
      final matchesQuery =
          normalized.isEmpty ||
          item.name.toLowerCase().contains(normalized) ||
          item.code.toLowerCase().contains(normalized) ||
          (item.description?.toLowerCase().contains(normalized) ?? false);
      final matchesStatus =
          status == 'semua' ||
          (status == 'aktif' && item.active) ||
          (status == 'nonaktif' && !item.active);
      return matchesQuery && matchesStatus;
    }).toList();

    return StudentGuidanceCategoryPage(
      items: filtered,
      summary: StudentGuidanceCategorySummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
      ),
      pagination: StudentGuidanceCategoryPagination(
        page: 1,
        lastPage: 1,
        total: filtered.length,
        hasNextPage: false,
      ),
      access: StudentGuidanceCategoryAccess(canManage: canManage),
      query: query,
      status: status,
    );
  }

  @override
  Future<void> create(StudentGuidanceCategoryFormValue value) async {
    createCalls++;
    _items.add(
      StudentGuidanceCategory(
        id: _items.length + 1,
        name: value.name,
        code: value.code.toUpperCase().replaceAll(' ', '_'),
        description: value.description,
        active: value.active,
        reportCount: 0,
        violationTypeCount: 0,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required StudentGuidanceCategoryFormValue value,
  }) async {
    updateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    final current = _items[index];
    _items[index] = StudentGuidanceCategory(
      id: current.id,
      name: value.name,
      code: value.code.toUpperCase().replaceAll(' ', '_'),
      description: value.description,
      active: value.active,
      reportCount: current.reportCount,
      violationTypeCount: current.violationTypeCount,
    );
  }

  @override
  Future<void> deactivate(int id) async {
    deactivateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    final current = _items[index];
    _items[index] = StudentGuidanceCategory(
      id: current.id,
      name: current.name,
      code: current.code,
      description: current.description,
      active: false,
      reportCount: current.reportCount,
      violationTypeCount: current.violationTypeCount,
    );
  }
}
