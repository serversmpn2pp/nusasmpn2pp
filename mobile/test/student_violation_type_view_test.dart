import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_violation_type/data/student_violation_type_remote_data_source.dart';
import 'package:nusa/features/student_violation_type/domain/student_violation_type.dart';
import 'package:nusa/features/student_violation_type/presentation/student_violation_type_view.dart';

void main() {
  test(
    'domain membaca ringkasan, referensi, kategori, dan jumlah pemakaian',
    () {
      final page = StudentViolationTypePage.fromJson(_pageJson());

      expect(page.summary.total, 2);
      expect(page.summary.byLevel['ringan'], 1);
      expect(page.references.levels, hasLength(3));
      expect(page.references.categories.first.code, 'KEDISIPLINAN');
      expect(page.items.first.category?.name, 'Kedisiplinan');
      expect(page.items.first.usageCount, 4);
    },
  );

  testWidgets('daftar pelanggaran rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeStudentViolationTypeRemoteDataSource());

    expect(find.text('Jenis Pelanggaran & Poin'), findsOneWidget);
    expect(
      find.byKey(const Key('violation-type-level-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('violation-type-status-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('violation-type-category-filter')),
      findsOneWidget,
    );
    expect(find.textContaining('Terlambat datang'), findsOneWidget);
    expect(find.text('15'), findsOneWidget);
    expect(find.byKey(const Key('add-violation-type')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin dapat menambah mengubah dan menonaktifkan jenis', (
    tester,
  ) async {
    final remote = _FakeStudentViolationTypeRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('add-violation-type')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('violation-type-form-code')),
      'R025',
    );
    await tester.enterText(
      find.byKey(const Key('violation-type-form-name')),
      'Mengganggu ketenangan perpustakaan',
    );
    await tester.ensureVisible(
      find.byKey(const Key('violation-type-form-points')),
    );
    await tester.enterText(
      find.byKey(const Key('violation-type-form-points')),
      '10',
    );
    await tester.ensureVisible(find.byKey(const Key('save-violation-type')));
    await tester.tap(find.byKey(const Key('save-violation-type')));
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(find.textContaining('Mengganggu ketenangan'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('violation-type-menu-1')));
    await tester.tap(find.byKey(const Key('violation-type-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ubah').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('violation-type-form-name')),
      'Terlambat hadir ke sekolah',
    );
    await tester.ensureVisible(find.byKey(const Key('save-violation-type')));
    await tester.tap(find.byKey(const Key('save-violation-type')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(find.textContaining('Terlambat hadir'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('violation-type-menu-1')));
    await tester.tap(find.byKey(const Key('violation-type-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Nonaktifkan').last);
    await tester.pumpAndSettle();
    expect(find.textContaining('4 laporan'), findsWidgets);
    await tester.tap(
      find.byKey(const Key('confirm-violation-type-deactivate')),
    );
    await tester.pumpAndSettle();

    expect(remote.deactivateCalls, 1);
    expect(find.text('Nonaktif'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  StudentViolationTypeRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        studentViolationTypeRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const StudentViolationTypeView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

const _levels = [
  StudentViolationLevel(code: 'ringan', label: 'Ringan'),
  StudentViolationLevel(code: 'sedang', label: 'Sedang'),
  StudentViolationLevel(code: 'berat', label: 'Berat'),
];

const _disciplineCategory = StudentViolationCategory(
  id: 1,
  name: 'Kedisiplinan',
  code: 'KEDISIPLINAN',
  active: true,
);

const _categories = [_disciplineCategory];

Map<String, dynamic> _pageJson() => {
  'ringkasan': {
    'total': 2,
    'aktif': 2,
    'nonaktif': 0,
    'per_tingkat': {'ringan': 1, 'sedang': 1, 'berat': 0},
  },
  'filter': {
    'cari': '',
    'status': 'semua',
    'tingkat': 'semua',
    'kategori_id': null,
  },
  'hak_akses': {'dapat_kelola': true},
  'referensi': {
    'tingkat': [
      {'kode': 'ringan', 'label': 'Ringan'},
      {'kode': 'sedang', 'label': 'Sedang'},
      {'kode': 'berat', 'label': 'Berat'},
    ],
    'kategori': [
      {'id': 1, 'nama': 'Kedisiplinan', 'kode': 'KEDISIPLINAN', 'aktif': true},
    ],
  },
  'items': [
    {
      'id': 1,
      'kode': 'R001',
      'nama': 'Terlambat datang ke sekolah',
      'tingkat': 'ringan',
      'tingkat_label': 'Ringan',
      'poin': 15,
      'urutan': 1,
      'aktif': true,
      'kategori': {
        'id': 1,
        'nama': 'Kedisiplinan',
        'kode': 'KEDISIPLINAN',
        'aktif': true,
      },
      'jumlah_pemakaian': 4,
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

final class _FakeStudentViolationTypeRemoteDataSource
    implements StudentViolationTypeRemoteDataSource {
  final List<StudentViolationType> _items = [
    const StudentViolationType(
      id: 1,
      code: 'R001',
      name: 'Terlambat datang ke sekolah',
      level: 'ringan',
      levelLabel: 'Ringan',
      points: 15,
      order: 1,
      active: true,
      usageCount: 4,
      category: _disciplineCategory,
    ),
  ];
  int createCalls = 0;
  int updateCalls = 0;
  int deactivateCalls = 0;

  @override
  Future<StudentViolationTypePage> fetch({
    required String query,
    required String status,
    required String level,
    required int? categoryId,
    required int page,
    int perPage = 15,
  }) async {
    final normalized = query.toLowerCase();
    final filtered = _items.where((item) {
      final matchesQuery =
          normalized.isEmpty ||
          item.name.toLowerCase().contains(normalized) ||
          item.code.toLowerCase().contains(normalized);
      final matchesStatus =
          status == 'semua' ||
          (status == 'aktif' && item.active) ||
          (status == 'nonaktif' && !item.active);
      final matchesLevel = level == 'semua' || item.level == level;
      final matchesCategory =
          categoryId == null || item.category?.id == categoryId;
      return matchesQuery && matchesStatus && matchesLevel && matchesCategory;
    }).toList();

    return StudentViolationTypePage(
      items: filtered,
      summary: StudentViolationTypeSummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
        byLevel: {
          for (final value in ['ringan', 'sedang', 'berat'])
            value: _items.where((item) => item.level == value).length,
        },
      ),
      pagination: StudentViolationTypePagination(
        page: 1,
        total: filtered.length,
        hasNextPage: false,
      ),
      access: const StudentViolationTypeAccess(canManage: true),
      references: const StudentViolationTypeReferences(
        levels: _levels,
        categories: _categories,
      ),
      query: query,
      status: status,
      level: level,
      categoryId: categoryId,
    );
  }

  @override
  Future<void> create(StudentViolationTypeFormValue value) async {
    createCalls++;
    _items.add(
      StudentViolationType(
        id: _items.length + 1,
        code: value.code,
        name: value.name,
        level: value.level,
        levelLabel: _levelLabel(value.level),
        points: value.points,
        order: value.order,
        active: value.active,
        usageCount: 0,
        category: _category(value.categoryId),
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required StudentViolationTypeFormValue value,
  }) async {
    updateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    final current = _items[index];
    _items[index] = StudentViolationType(
      id: current.id,
      code: value.code,
      name: value.name,
      level: value.level,
      levelLabel: _levelLabel(value.level),
      points: value.points,
      order: value.order,
      active: value.active,
      usageCount: current.usageCount,
      category: _category(value.categoryId),
    );
  }

  @override
  Future<void> deactivate(int id) async {
    deactivateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    final current = _items[index];
    _items[index] = StudentViolationType(
      id: current.id,
      code: current.code,
      name: current.name,
      level: current.level,
      levelLabel: current.levelLabel,
      points: current.points,
      order: current.order,
      active: false,
      usageCount: current.usageCount,
      category: current.category,
    );
  }

  StudentViolationCategory? _category(int? id) =>
      id == null ? null : _categories.firstWhere((item) => item.id == id);

  String _levelLabel(String code) =>
      _levels.firstWhere((item) => item.code == code).label;
}
