import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';

enum NusaExamTab { schedule, history }

class NusaExamTabs extends StatelessWidget {
  const NusaExamTabs({
    required this.selected,
    required this.scheduleCount,
    required this.historyCount,
    required this.onChanged,
    super.key,
  });
  final NusaExamTab selected;
  final int scheduleCount, historyCount;
  final ValueChanged<NusaExamTab> onChanged;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(4),
    decoration: BoxDecoration(
      color: const Color(0xFFEDF2F8),
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      children: [
        for (final tab in NusaExamTab.values) ...[
          if (tab == NusaExamTab.history) const SizedBox(width: 4),
          Expanded(
            child: Semantics(
              selected: selected == tab,
              child: Material(
                color: selected == tab ? Colors.white : Colors.transparent,
                borderRadius: BorderRadius.circular(12),
                child: InkWell(
                  key: Key('exam-tab-${tab.name}'),
                  onTap: () => onChanged(tab),
                  borderRadius: BorderRadius.circular(12),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 12,
                    ),
                    child: LayoutBuilder(
                      builder: (_, constraints) {
                        final label = Text(
                          tab == NusaExamTab.schedule ? 'Jadwal' : 'Riwayat',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: selected == tab
                                ? NusaColors.primary
                                : NusaColors.textSecondary,
                          ),
                        );
                        final count = Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 7,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: selected == tab
                                ? NusaColors.primary
                                : NusaColors.outline,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            '${tab == NusaExamTab.schedule ? scheduleCount : historyCount}',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: selected == tab
                                  ? Colors.white
                                  : NusaColors.textSecondary,
                            ),
                          ),
                        );
                        return constraints.maxWidth < 170 &&
                                MediaQuery.textScalerOf(context).scale(14) > 20
                            ? Column(
                                children: [
                                  label,
                                  const SizedBox(height: 6),
                                  count,
                                ],
                              )
                            : Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Flexible(child: label),
                                  const SizedBox(width: 8),
                                  count,
                                ],
                              );
                      },
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ],
    ),
  );
}

class NusaExamGroupTitle extends StatelessWidget {
  const NusaExamGroupTitle(
    this.title, {
    required this.count,
    this.history = false,
    super.key,
  });
  final String title;
  final int count;
  final bool history;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 16, bottom: 10),
    child: Row(
      children: [
        Icon(
          history ? Icons.history_rounded : Icons.event_available_rounded,
          size: 19,
          color: NusaColors.primary,
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
          ),
        ),
        Text(
          '$count',
          style: const TextStyle(fontSize: 12, color: NusaColors.textSecondary),
        ),
      ],
    ),
  );
}

class NusaExamEmptySchedule extends StatelessWidget {
  const NusaExamEmptySchedule({
    required this.historyCount,
    required this.onHistory,
    super.key,
  });
  final int historyCount;
  final VoidCallback onHistory;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 14),
    child: Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: [
            const Icon(
              Icons.event_available_outlined,
              size: 36,
              color: NusaColors.primary,
            ),
            const SizedBox(height: 12),
            const Text(
              'Belum ada jadwal aktif',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 6),
            const Text(
              'Tidak ada ujian berlangsung atau akan datang.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: NusaColors.textSecondary),
            ),
            if (historyCount > 0) ...[
              const SizedBox(height: 14),
              TextButton.icon(
                key: const Key('exam-open-history'),
                onPressed: onHistory,
                icon: const Icon(Icons.history_rounded),
                label: const Text('Lihat riwayat'),
              ),
            ],
          ],
        ),
      ),
    ),
  );
}

Color nusaExamStatusColor({
  required String group,
  required String tone,
  required String label,
  String? workStatus,
}) {
  if (tone == 'bahaya') return NusaColors.danger;
  if (tone == 'menunggu') return const Color(0xFFE59A00);
  // Older servers do not include status_pengerjaan in the student catalog.
  final submitted =
      workStatus == 'selesai' ||
      ['Selesai dikerjakan', 'Susulan selesai'].contains(label);
  if (group == 'selesai' && !submitted) return NusaColors.textSecondary;
  return switch (tone) {
    'aktif' || 'selesai' => NusaColors.success,
    _ => NusaColors.primary,
  };
}
