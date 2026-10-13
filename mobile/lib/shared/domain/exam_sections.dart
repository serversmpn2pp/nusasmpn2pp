/// Groups only by the server decision, never by the phone's clock. An older
/// scheduled date may still have an authorized extension or make-up session.
class ExamSections<T> {
  ExamSections(
    Iterable<T> exams, {
    required String Function(T) group,
    required DateTime? Function(T) start,
    required DateTime? Function(T) end,
  }) {
    final indexed = exams.indexed.toList();
    List<T> sorted(String section, {bool latestFirst = false}) {
      final rows = indexed
          .where(
            (entry) => section == 'akan_datang'
                ? !['aktif', 'selesai'].contains(group(entry.$2))
                : group(entry.$2) == section,
          )
          .toList();
      rows.sort((a, b) {
        final left = latestFirst ? end(a.$2) ?? start(a.$2) : start(a.$2);
        final right = latestFirst ? end(b.$2) ?? start(b.$2) : start(b.$2);
        final comparison = left == null
            ? (right == null ? 0 : 1)
            : right == null
            ? -1
            : latestFirst
            ? right.compareTo(left)
            : left.compareTo(right);
        return comparison == 0 ? a.$1.compareTo(b.$1) : comparison;
      });
      return List.unmodifiable(rows.map((entry) => entry.$2));
    }

    active = sorted('aktif');
    upcoming = sorted('akan_datang');
    history = sorted('selesai', latestFirst: true);
  }

  late final List<T> active, upcoming, history;
  int get scheduledCount => active.length + upcoming.length;
}
