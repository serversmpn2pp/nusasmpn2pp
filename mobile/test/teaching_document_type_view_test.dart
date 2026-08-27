import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_document_type/data/teaching_document_type_remote_data_source.dart';
import 'package:nusa/features/teaching_document_type/domain/teaching_document_type.dart';
import 'package:nusa/features/teaching_document_type/presentation/teaching_document_type_view.dart';

void main() {
  testWidgets('daftar jenis perangkat ajar rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeTeachingDocumentTypeRemoteDataSource());

    expect(find.text('Jenis Perangkat Ajar'), findsOneWidget);
    expect(
      find.byKey(const Key('teaching-document-type-status-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('teaching-document-type-requirement-filter')),
      findsOneWidget,
    );
    expect(find.text('Modul Ajar Mobile'), findsOneWidget);
    expect(find.byKey(const Key('add-teaching-document-type')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin dapat menambah, mengubah, dan menonaktifkan jenis', (
    tester,
  ) async {
    final remote = _FakeTeachingDocumentTypeRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('add-teaching-document-type')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('teaching-document-type-form-name')),
      'Rencana Pembelajaran Mobile',
    );
    await tester.enterText(
      find.byKey(const Key('teaching-document-type-form-code')),
      'RPP MOBILE',
    );
    await tester.tap(find.byKey(const Key('save-teaching-document-type')));
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(find.text('Rencana Pembelajaran Mobile'), findsOneWidget);

    await tester.tap(find.byKey(const Key('teaching-document-type-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ubah').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('teaching-document-type-form-name')),
      'Modul Ajar Mobile Baru',
    );
    await tester.tap(find.byKey(const Key('save-teaching-document-type')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(find.text('Modul Ajar Mobile Baru'), findsOneWidget);

    await tester.tap(find.byKey(const Key('teaching-document-type-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Nonaktifkan').last);
    await tester.pumpAndSettle();
    expect(
      find.textContaining('4 dokumen lama tetap tersimpan'),
      findsOneWidget,
    );
    await tester.tap(
      find.byKey(const Key('confirm-teaching-document-type-deactivate')),
    );
    await tester.pumpAndSettle();

    expect(remote.deactivateCalls, 1);
    expect(find.text('Nonaktif'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  TeachingDocumentTypeRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        teachingDocumentTypeRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const TeachingDocumentTypeView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeTeachingDocumentTypeRemoteDataSource
    implements TeachingDocumentTypeRemoteDataSource {
  final List<TeachingDocumentType> _items = [
    const TeachingDocumentType(
      id: 1,
      code: 'MODUL_AJAR_MOBILE',
      name: 'Modul Ajar Mobile',
      description: 'Dokumen utama pelaksanaan pembelajaran.',
      mandatory: true,
      order: 1,
      active: true,
      documentCount: 4,
    ),
    const TeachingDocumentType(
      id: 2,
      code: 'LAMPIRAN_MOBILE',
      name: 'Lampiran Mobile',
      mandatory: false,
      order: 2,
      active: true,
      documentCount: 0,
    ),
  ];

  int createCalls = 0;
  int updateCalls = 0;
  int deactivateCalls = 0;

  @override
  Future<TeachingDocumentTypePage> fetch({
    required String query,
    required String status,
    required String requirement,
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
      final matchesRequirement =
          requirement == 'semua' ||
          (requirement == 'wajib' && item.mandatory) ||
          (requirement == 'opsional' && !item.mandatory);
      return matchesQuery && matchesStatus && matchesRequirement;
    }).toList()..sort((a, b) => a.order.compareTo(b.order));

    return TeachingDocumentTypePage(
      items: filtered,
      summary: TeachingDocumentTypeSummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        mandatory: _items.where((item) => item.mandatory).length,
      ),
      pagination: TeachingDocumentTypePagination(
        page: 1,
        total: filtered.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
      requirement: requirement,
      nextOrder:
          _items.fold<int>(
            0,
            (value, item) => value > item.order ? value : item.order,
          ) +
          1,
    );
  }

  @override
  Future<void> create(TeachingDocumentTypeFormValue value) async {
    createCalls++;
    _items.add(
      TeachingDocumentType(
        id: _items.length + 1,
        code: value.code,
        name: value.name,
        description: value.description,
        mandatory: value.mandatory,
        order: value.order,
        active: value.active,
        documentCount: 0,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required TeachingDocumentTypeFormValue value,
  }) async {
    updateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    final current = _items[index];
    _items[index] = TeachingDocumentType(
      id: current.id,
      code: value.code,
      name: value.name,
      description: value.description,
      mandatory: value.mandatory,
      order: value.order,
      active: value.active,
      documentCount: current.documentCount,
    );
  }

  @override
  Future<void> deactivate(int id) async {
    deactivateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    final current = _items[index];
    _items[index] = TeachingDocumentType(
      id: current.id,
      code: current.code,
      name: current.name,
      description: current.description,
      mandatory: current.mandatory,
      order: current.order,
      active: false,
      documentCount: current.documentCount,
    );
  }
}
