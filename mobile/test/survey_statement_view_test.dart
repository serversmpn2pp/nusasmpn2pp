import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/survey_statement/data/survey_statement_remote_data_source.dart';
import 'package:nusa/features/survey_statement/domain/survey_statement.dart';
import 'package:nusa/features/survey_statement/presentation/survey_statement_view.dart';

void main() {
  testWidgets('daftar pernyataan survei rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeSurveyStatementRemoteDataSource());

    expect(find.text('Pernyataan Survei'), findsOneWidget);
    expect(find.byKey(const Key('survey-statement-search')), findsOneWidget);
    expect(find.text('Guru menjelaskan materi dengan jelas.'), findsOneWidget);
    expect(find.byKey(const Key('add-survey-statement')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin dapat menambah, mengubah, dan mengganti status', (
    tester,
  ) async {
    final remote = _FakeSurveyStatementRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('add-survey-statement')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('survey-statement-form-text')),
      'Guru menggunakan media belajar yang sesuai.',
    );
    await tester.tap(find.byKey(const Key('save-survey-statement')));
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(
      find.text('Guru menggunakan media belajar yang sesuai.'),
      findsOneWidget,
    );

    await tester.tap(find.byKey(const Key('survey-statement-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ubah').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('survey-statement-form-text')),
      'Guru menjelaskan materi secara runtut.',
    );
    await tester.tap(find.byKey(const Key('save-survey-statement')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(find.text('Guru menjelaskan materi secara runtut.'), findsOneWidget);

    await tester.tap(find.byKey(const Key('survey-statement-menu-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Nonaktifkan').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('confirm-survey-statement-status')));
    await tester.pumpAndSettle();

    expect(remote.statusCalls, 1);
    expect(find.text('Nonaktif'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  SurveyStatementRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        surveyStatementRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const SurveyStatementView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeSurveyStatementRemoteDataSource
    implements SurveyStatementRemoteDataSource {
  final List<SurveyStatement> _items = [
    const SurveyStatement(
      id: 1,
      code: 'kejelasan_materi',
      statement: 'Guru menjelaskan materi dengan jelas.',
      order: 1,
      active: true,
    ),
    const SurveyStatement(
      id: 2,
      code: 'umpan_balik',
      statement: 'Guru memberikan umpan balik terhadap hasil belajar.',
      order: 2,
      active: true,
    ),
  ];

  int createCalls = 0;
  int updateCalls = 0;
  int statusCalls = 0;

  @override
  Future<SurveyStatementPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    final normalized = query.toLowerCase();
    final filtered = _items.where((item) {
      final matchesQuery =
          normalized.isEmpty ||
          item.statement.toLowerCase().contains(normalized) ||
          item.code.toLowerCase().contains(normalized);
      final matchesStatus =
          status == 'semua' ||
          (status == 'aktif' && item.active) ||
          (status == 'nonaktif' && !item.active);
      return matchesQuery && matchesStatus;
    }).toList()..sort((a, b) => a.order.compareTo(b.order));

    return SurveyStatementPage(
      items: filtered,
      summary: SurveyStatementSummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
      ),
      pagination: SurveyStatementPagination(
        page: 1,
        total: filtered.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
      nextOrder:
          _items.fold<int>(
            0,
            (value, item) => value > item.order ? value : item.order,
          ) +
          1,
    );
  }

  @override
  Future<void> create(SurveyStatementFormValue value) async {
    createCalls++;
    _items.add(
      SurveyStatement(
        id: _items.length + 1,
        code: 'survei_baru',
        statement: value.statement,
        order: value.order,
        active: value.active,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required SurveyStatementFormValue value,
  }) async {
    updateCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    final current = _items[index];
    _items[index] = SurveyStatement(
      id: current.id,
      code: current.code,
      statement: value.statement,
      order: value.order,
      active: current.active,
    );
  }

  @override
  Future<void> updateStatus({required int id, required bool active}) async {
    statusCalls++;
    final index = _items.indexWhere((item) => item.id == id);
    final current = _items[index];
    _items[index] = SurveyStatement(
      id: current.id,
      code: current.code,
      statement: current.statement,
      order: current.order,
      active: active,
    );
  }
}
