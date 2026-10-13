import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/shared/domain/exam_sections.dart';
import 'package:nusa/shared/widgets/nusa_exam_tabs.dart';

void main() {
  test('groups follow server and dates only sort, never decide access', () {
    final rows = [
      (id: 1, group: 'selesai', start: DateTime(2099), end: null),
      (id: 2, group: 'aktif', start: DateTime(2000), end: DateTime(2000)),
      (id: 3, group: 'akan_datang', start: DateTime(2001), end: null),
    ];
    final sections = ExamSections(
      rows,
      group: (e) => e.group,
      start: (e) => e.start,
      end: (e) => e.end,
    );
    expect(sections.active.map((e) => e.id), [2]);
    expect(sections.upcoming.map((e) => e.id), [3]);
    expect(sections.history.map((e) => e.id), [1]);
  });
  test('schedule is earliest first, history newest first, null dates last', () {
    final sections = ExamSections(
      [
        (id: 1, group: 'aktif', date: DateTime(2026, 10, 15)),
        (id: 2, group: 'aktif', date: null),
        (id: 3, group: 'aktif', date: DateTime(2026, 10, 13)),
        (id: 4, group: 'selesai', date: DateTime(2026, 10, 12)),
        (id: 5, group: 'selesai', date: null),
        (id: 6, group: 'selesai', date: DateTime(2026, 10, 13)),
        (id: 7, group: 'selesai', date: DateTime(2026, 10, 13)),
      ],
      group: (e) => e.group,
      start: (e) => e.date,
      end: (e) => e.date,
    );
    expect(sections.active.map((e) => e.id), [3, 1, 2]);
    expect(sections.history.map((e) => e.id), [6, 7, 4, 5]);
    expect(sections.scheduledCount, 3);
  });
  test('unknown group is retained in schedule instead of silently lost', () {
    final sections = ExamSections(
      ['unrecognized'],
      group: (e) => e,
      start: (_) => null,
      end: (_) => null,
    );
    expect(sections.upcoming, ['unrecognized']);
    expect(sections.history, isEmpty);
  });
  test('submitted, expired and cancelled statuses have distinct colors', () {
    expect(
      nusaExamStatusColor(
        group: 'selesai',
        tone: 'selesai',
        label: 'Waktu berakhir',
        workStatus: 'aktif',
      ),
      NusaColors.textSecondary,
    );
    expect(
      nusaExamStatusColor(
        group: 'selesai',
        tone: 'selesai',
        label: 'Selesai dikerjakan',
        workStatus: 'selesai',
      ),
      NusaColors.success,
    );
    expect(
      nusaExamStatusColor(
        group: 'selesai',
        tone: 'bahaya',
        label: 'Dibatalkan',
      ),
      NusaColors.danger,
    );
    expect(
      nusaExamStatusColor(
        group: 'selesai',
        tone: 'selesai',
        label: 'Selesai dikerjakan',
      ),
      NusaColors.success,
    );
  });
}
