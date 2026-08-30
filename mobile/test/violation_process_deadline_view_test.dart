import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/violation_process_deadline/data/violation_process_deadline_remote_data_source.dart';
import 'package:nusa/features/violation_process_deadline/domain/violation_process_deadline.dart';
import 'package:nusa/features/violation_process_deadline/presentation/violation_process_deadline_view.dart';

void main() {
  test('domain membaca nilai bawaan tenggat dan ringkasan per tahun', () {
    final page = ViolationProcessDeadlinePage.fromJson(_pageJson());

    expect(page.summary.academicYearCount, 1);
    expect(page.summary.defaultCount, 1);
    expect(page.items.first.saved, isFalse);
    expect(page.items.first.counselingDays, 2);
    expect(page.items.first.approvalDays, 2);
    expect(page.items.first.reminderNotificationActive, isTrue);
  });

  testWidgets('daftar batas proses rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakeViolationDeadlineRemoteDataSource());

    expect(find.text('Batas Proses Pelanggaran'), findsOneWidget);
    expect(
      find.byKey(const Key('violation-deadline-status-filter')),
      findsOneWidget,
    );
    expect(find.text('2026/2027'), findsOneWidget);
    expect(find.text('Pemeriksaan BK'), findsOneWidget);
    expect(find.text('Pengesahan Wakil'), findsOneWidget);
    expect(find.text('Pengingat 1 hari sebelum batas'), findsOneWidget);
    expect(
      find.byKey(const Key('configure-violation-deadline-1')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('admin mengubah tenggat dan notifikasi secara native', (
    tester,
  ) async {
    final remote = _FakeViolationDeadlineRemoteDataSource();
    await _pumpView(tester, remote);

    await tester.tap(find.byKey(const Key('configure-violation-deadline-1')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('violation-deadline-counseling-days')),
      '4',
    );
    await tester.enterText(
      find.byKey(const Key('violation-deadline-approval-days')),
      '3',
    );
    await tester.drag(
      find.byKey(const Key('violation-deadline-form-scroll')),
      const Offset(0, -480),
    );
    await tester.pumpAndSettle();
    await tester.tap(
      find.byKey(const Key('violation-deadline-overdue-active')),
    );
    await tester.tap(find.byKey(const Key('save-violation-deadline')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(remote.lastValue?.counselingDays, 4);
    expect(remote.lastValue?.approvalDays, 3);
    expect(remote.lastValue?.reminderDaysBefore, 1);
    expect(remote.lastValue?.overdueNotificationActive, isFalse);
    expect(find.text('4 hari'), findsOneWidget);
    expect(find.text('3 hari'), findsOneWidget);
    expect(find.text('Pemberitahuan keterlambatan nonaktif'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  ViolationProcessDeadlineRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        violationProcessDeadlineRemoteDataSourceProvider.overrideWithValue(
          remote,
        ),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const ViolationProcessDeadlineView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Map<String, dynamic> _pageJson() => {
  'ringkasan': {
    'jumlah_tahun': 1,
    'tahun_aktif_id': 1,
    'sudah_diatur': 0,
    'memakai_bawaan': 1,
    'pengingat_aktif': 1,
    'terlambat_aktif': 1,
  },
  'filter': {'cari': '', 'status': 'semua'},
  'hak_akses': {'dapat_kelola': true},
  'items': [
    {
      'tahun_pelajaran': {
        'id': 1,
        'nama': '2026/2027',
        'tanggal_mulai': '2026-07-01',
        'tanggal_selesai': '2027-06-30',
        'aktif': true,
      },
      'tersimpan': false,
      'batas_hari_pemeriksaan_bk': 2,
      'batas_hari_persetujuan': 2,
      'pengingat_hari_sebelum_batas': 1,
      'notifikasi_pengingat_aktif': true,
      'notifikasi_terlambat_aktif': true,
      'diperbarui_oleh': null,
      'diperbarui_pada': null,
    },
  ],
};

final class _FakeViolationDeadlineRemoteDataSource
    implements ViolationProcessDeadlineRemoteDataSource {
  ViolationProcessDeadline _deadline = ViolationProcessDeadlinePage.fromJson(
    _pageJson(),
  ).items.first;
  int updateCalls = 0;
  ViolationProcessDeadlineFormValue? lastValue;

  @override
  Future<ViolationProcessDeadlinePage> fetch({
    required String query,
    required String status,
  }) async {
    final matchesQuery =
        query.isEmpty || _deadline.academicYear.name.contains(query);
    final matchesStatus =
        status == 'semua' ||
        (status == 'diatur' && _deadline.saved) ||
        (status == 'bawaan' && !_deadline.saved);

    return ViolationProcessDeadlinePage(
      items: matchesQuery && matchesStatus
          ? [_deadline]
          : <ViolationProcessDeadline>[],
      summary: ViolationProcessDeadlineSummary(
        academicYearCount: 1,
        activeAcademicYearId: 1,
        configuredCount: _deadline.saved ? 1 : 0,
        defaultCount: _deadline.saved ? 0 : 1,
        reminderActiveCount: _deadline.reminderNotificationActive ? 1 : 0,
        overdueActiveCount: _deadline.overdueNotificationActive ? 1 : 0,
      ),
      access: const ViolationProcessDeadlineAccess(canManage: true),
      query: query,
      status: status,
    );
  }

  @override
  Future<void> update({
    required int academicYearId,
    required ViolationProcessDeadlineFormValue value,
  }) async {
    updateCalls++;
    lastValue = value;
    _deadline = ViolationProcessDeadline(
      academicYear: _deadline.academicYear,
      saved: true,
      counselingDays: value.counselingDays,
      approvalDays: value.approvalDays,
      reminderDaysBefore: value.reminderDaysBefore,
      reminderNotificationActive: value.reminderNotificationActive,
      overdueNotificationActive: value.overdueNotificationActive,
      updatedBy: 'Administrator',
      updatedAt: DateTime(2026, 8, 30, 13),
    );
  }
}
