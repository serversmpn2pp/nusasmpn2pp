import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_preparation/application/central_exam_document_service.dart';
import 'package:nusa/features/central_exam_preparation/application/central_exam_preparation_controller.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';
import 'package:nusa/features/central_exam_preparation/presentation/central_exam_distribution_detail_view.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('membuat PDF daftar peserta dan label meja', () async {
    final detail = _detail();
    final builder = CentralExamPdfBuilder();

    final participantList = await builder.buildParticipantList(detail);
    final deskLabels = await builder.buildDeskLabels(
      detail,
      detail.rooms.first,
    );

    expect(String.fromCharCodes(participantList.take(4)), '%PDF');
    expect(String.fromCharCodes(deskLabels.take(4)), '%PDF');
    expect(participantList.length, greaterThan(1000));
    expect(deskLabels.length, greaterThan(1000));
  });

  testWidgets('dokumen pembagian tetap rapi dan dapat dibagikan', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 740);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final detail = _detail();
    final documents = _FakeDocumentService();
    final request = (eventId: 7, groupId: 31);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          centralExamDistributionDetailProvider(request)
              .overrideWith((ref) async => detail),
          centralExamDocumentServiceProvider.overrideWithValue(documents),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const CentralExamDistributionDetailView(
            eventId: 7,
            groupId: 31,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byTooltip('Cetak dan bagikan dokumen'));
    await tester.pumpAndSettle();

    expect(find.text('Dokumen Pembagian Peserta'), findsOneWidget);
    expect(find.text('Daftar Seluruh Peserta'), findsOneWidget);
    expect(find.text('R01 · Ruang 1'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.widgetWithText(FilledButton, 'Bagikan').first);
    await tester.pumpAndSettle();

    expect(documents.sharedParticipantList, isTrue);
    expect(find.text('Dokumen siap dibagikan.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

CentralExamDistributionDetail _detail() => CentralExamDistributionDetail(
  eventName: 'SAS Ganjil 2026/2027',
  eventCode: 'UT-2026-001',
  grade: 7,
  sessionName: 'Sesi Pagi',
  timeLabel: '07:30 - 09:30',
  classNames: const ['VII.A'],
  participantCount: 2,
  totalCapacity: 20,
  rooms: [
    CentralExamDistributionRoom(
      id: 11,
      code: 'R01',
      name: 'Ruang 1',
      location: 'Kelas VII.A',
      capacity: 20,
      occupiedCount: 2,
      participants: const [
        CentralExamDistributedParticipant(
          id: 101,
          seatNumber: 1,
          seatCode: 'R01-001',
          participantNumber: 'UT-0001',
          name: 'Ananda Satu',
          nisn: '0012345678',
          className: 'VII.A',
        ),
        CentralExamDistributedParticipant(
          id: 102,
          seatNumber: 2,
          seatCode: 'R01-002',
          participantNumber: 'UT-0002',
          name: 'Ananda Dua',
          nisn: '0012345679',
          className: 'VII.A',
        ),
      ],
    ),
  ],
);

final class _FakeDocumentService implements CentralExamDocumentService {
  bool sharedParticipantList = false;

  @override
  Future<bool> printDeskLabels(
    CentralExamDistributionDetail detail,
    CentralExamDistributionRoom room,
  ) async => true;

  @override
  Future<bool> printParticipantList(
    CentralExamDistributionDetail detail,
  ) async => true;

  @override
  Future<bool> shareDeskLabels(
    CentralExamDistributionDetail detail,
    CentralExamDistributionRoom room,
  ) async => true;

  @override
  Future<bool> shareParticipantList(
    CentralExamDistributionDetail detail,
  ) async {
    sharedParticipantList = true;
    return true;
  }
}
