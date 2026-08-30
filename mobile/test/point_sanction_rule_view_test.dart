import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/point_sanction_rule/data/point_sanction_rule_remote_data_source.dart';
import 'package:nusa/features/point_sanction_rule/domain/point_sanction_rule.dart';
import 'package:nusa/features/point_sanction_rule/presentation/point_sanction_rule_view.dart';

void main() {
  test('domain membaca ringkasan dan jumlah sanksi terpicu', () {
    final page = PointSanctionRulePage.fromJson(_pageJson());

    expect(page.summary.total, 2);
    expect(page.summary.triggeredCount, 4);
    expect(page.access.canManage, isTrue);
    expect(page.items.first.pointThreshold, 25);
    expect(page.items.first.triggeredCount, 4);
  });

  testWidgets('daftar aturan sanksi rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakePointSanctionRuleRemoteDataSource());

    expect(find.text('Aturan Sanksi Poin'), findsOneWidget);
    expect(
      find.byKey(const Key('sanction-rule-status-filter')),
      findsOneWidget,
    );
    expect(find.text('Teguran Lisan'), findsOneWidget);
    expect(find.text('25'), findsOneWidget);
    expect(find.text('4 sanksi terpicu'), findsOneWidget);
    expect(find.byKey(const Key('add-sanction-rule')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin dapat menambah mengubah dan menonaktifkan aturan', (
    tester,
  ) async {
    final remote = _FakePointSanctionRuleRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('add-sanction-rule')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('sanction-rule-form-threshold')),
      '60',
    );
    await tester.enterText(
      find.byKey(const Key('sanction-rule-form-name')),
      'Pembinaan Wali Kelas',
    );
    await tester.enterText(
      find.byKey(const Key('sanction-rule-form-description')),
      'Pembinaan terjadwal bersama wali kelas.',
    );
    await tester.ensureVisible(find.byKey(const Key('save-sanction-rule')));
    await tester.tap(find.byKey(const Key('save-sanction-rule')));
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(find.text('Pembinaan Wali Kelas'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('sanction-rule-menu-1')));
    await tester.tap(find.byKey(const Key('sanction-rule-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ubah').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('sanction-rule-form-name')),
      'Teguran dan Pembinaan Lisan',
    );
    await tester.ensureVisible(find.byKey(const Key('save-sanction-rule')));
    await tester.tap(find.byKey(const Key('save-sanction-rule')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(find.text('Teguran dan Pembinaan Lisan'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('sanction-rule-menu-1')));
    await tester.tap(find.byKey(const Key('sanction-rule-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Nonaktifkan').last);
    await tester.pumpAndSettle();
    expect(find.textContaining('Seluruh sanksi'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-sanction-rule-deactivate')));
    await tester.pumpAndSettle();

    expect(remote.deactivateCalls, 1);
    expect(find.text('Nonaktif'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  PointSanctionRuleRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        pointSanctionRuleRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const PointSanctionRuleView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Map<String, dynamic> _pageJson() => {
  'ringkasan': {
    'total': 2,
    'aktif': 2,
    'nonaktif': 0,
    'jumlah_sanksi_terpicu': 4,
  },
  'filter': {'cari': '', 'status': 'semua'},
  'hak_akses': {'dapat_kelola': true},
  'items': [
    {
      'id': 1,
      'batas_poin': 25,
      'nama': 'Teguran Lisan',
      'deskripsi': 'Teguran lisan dan pencatatan pembinaan.',
      'urutan': 1,
      'aktif': true,
      'jumlah_sanksi_terpicu': 4,
    },
  ],
};

final class _FakePointSanctionRuleRemoteDataSource
    implements PointSanctionRuleRemoteDataSource {
  final List<PointSanctionRule> _items = [
    const PointSanctionRule(
      id: 1,
      pointThreshold: 25,
      name: 'Teguran Lisan',
      description: 'Teguran lisan dan pencatatan pembinaan.',
      order: 1,
      active: true,
      triggeredCount: 4,
    ),
  ];
  int createCalls = 0;
  int updateCalls = 0;
  int deactivateCalls = 0;

  @override
  Future<PointSanctionRulePage> fetch({
    required String query,
    required String status,
  }) async {
    final normalized = query.toLowerCase();
    final filtered = _items.where((item) {
      final matchesQuery =
          normalized.isEmpty ||
          item.name.toLowerCase().contains(normalized) ||
          item.description.toLowerCase().contains(normalized);
      final matchesStatus =
          status == 'semua' ||
          (status == 'aktif' && item.active) ||
          (status == 'nonaktif' && !item.active);
      return matchesQuery && matchesStatus;
    }).toList();

    return PointSanctionRulePage(
      items: filtered,
      summary: PointSanctionRuleSummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
        triggeredCount: _items.fold(
          0,
          (total, item) => total + item.triggeredCount,
        ),
      ),
      access: const PointSanctionRuleAccess(canManage: true),
      query: query,
      status: status,
    );
  }

  @override
  Future<void> create(PointSanctionRuleFormValue value) async {
    createCalls++;
    _items.add(
      PointSanctionRule(
        id: _items.length + 1,
        pointThreshold: value.pointThreshold,
        name: value.name,
        description: value.description,
        order: value.order,
        active: value.active,
        triggeredCount: 0,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required PointSanctionRuleFormValue value,
  }) async {
    updateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    final current = _items[index];
    _items[index] = PointSanctionRule(
      id: current.id,
      pointThreshold: value.pointThreshold,
      name: value.name,
      description: value.description,
      order: value.order,
      active: value.active,
      triggeredCount: current.triggeredCount,
    );
  }

  @override
  Future<void> deactivate(int id) async {
    deactivateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    final current = _items[index];
    _items[index] = PointSanctionRule(
      id: current.id,
      pointThreshold: current.pointThreshold,
      name: current.name,
      description: current.description,
      order: current.order,
      active: false,
      triggeredCount: current.triggeredCount,
    );
  }
}
