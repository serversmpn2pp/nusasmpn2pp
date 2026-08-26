import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_teaching_schedule/domain/my_teaching_schedule.dart';

class TeachingScheduleSummaryCard extends StatelessWidget {
  const TeachingScheduleSummaryCard({required this.summary, super.key});

  final TeachingScheduleSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 14),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.14),
          blurRadius: 16,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: Row(
      children: [
        _SummaryItem(label: 'Jam', value: summary.teachingPeriods),
        _SummaryItem(label: 'Kelas', value: summary.classes),
        _SummaryItem(label: 'Mapel', value: summary.subjects),
        _SummaryItem(label: 'Hari', value: summary.teachingDays),
      ],
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({required this.label, required this.value});

  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 20,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.72),
            fontSize: 10,
          ),
        ),
      ],
    ),
  );
}

class TeachingEmployeeBanner extends StatelessWidget {
  const TeachingEmployeeBanner({required this.employee, super.key});

  final TeachingEmployee employee;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      border: Border.all(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(16),
    ),
    child: Row(
      children: [
        Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: NusaColors.primary,
            borderRadius: BorderRadius.circular(13),
          ),
          child: const Icon(Icons.co_present_rounded, color: Colors.white),
        ),
        const SizedBox(width: 11),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                employee.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                employee.position?.trim().isNotEmpty == true
                    ? employee.position!
                    : 'Guru mata pelajaran',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class TeachingDaySelector extends StatelessWidget {
  const TeachingDaySelector({
    required this.days,
    required this.selectedCode,
    required this.onSelected,
    super.key,
  });

  final List<TeachingScheduleDay> days;
  final String selectedCode;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 48,
    child: ListView.separated(
      scrollDirection: Axis.horizontal,
      itemCount: days.length,
      separatorBuilder: (context, index) => const SizedBox(width: 8),
      itemBuilder: (context, index) {
        final day = days[index];
        final selected = day.code == selectedCode;
        return Material(
          color: selected ? NusaColors.primary : Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(13),
            side: BorderSide(
              color: selected ? NusaColors.primary : NusaColors.outline,
            ),
          ),
          child: InkWell(
            key: Key('teaching-day-${day.code}'),
            onTap: () => onSelected(day.code),
            borderRadius: BorderRadius.circular(13),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 8),
              child: Row(
                children: [
                  Text(
                    day.label,
                    style: TextStyle(
                      color: selected ? Colors.white : NusaColors.textPrimary,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (day.count > 0) ...[
                    const SizedBox(width: 6),
                    Container(
                      constraints: const BoxConstraints(minWidth: 20),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 5,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: selected
                            ? NusaColors.accent
                            : NusaColors.surfaceBlue,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        '${day.count}',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: selected
                              ? NusaColors.primaryDark
                              : NusaColors.primary,
                          fontSize: 9,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        );
      },
    ),
  );
}

class TeachingScheduleCard extends StatelessWidget {
  const TeachingScheduleCard({required this.schedule, super.key});

  final TeachingScheduleItem schedule;

  @override
  Widget build(BuildContext context) {
    final period = schedule.period;
    final accent = schedule.ongoing ? NusaColors.accent : NusaColors.primary;

    return Container(
      key: Key('my-teaching-schedule-${schedule.id}'),
      decoration: BoxDecoration(
        color: schedule.ongoing
            ? NusaColors.accent.withValues(alpha: 0.07)
            : Colors.white,
        border: Border.all(
          color: schedule.ongoing
              ? NusaColors.accent.withValues(alpha: 0.55)
              : NusaColors.outline,
        ),
        borderRadius: BorderRadius.circular(17),
        boxShadow: [
          BoxShadow(
            color: NusaColors.primary.withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: IntrinsicHeight(
        child: Row(
          children: [
            Container(
              width: 6,
              decoration: BoxDecoration(
                color: accent,
                borderRadius: const BorderRadius.horizontal(
                  left: Radius.circular(17),
                ),
              ),
            ),
            SizedBox(
              width: 82,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 9),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      period?.start ?? '--:--',
                      style: const TextStyle(
                        color: NusaColors.textPrimary,
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      's.d. ${period?.end ?? '--:--'}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 9,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            Container(width: 1, color: NusaColors.outline),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(13),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            schedule.subject?.name ?? 'Mata pelajaran',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: NusaColors.textPrimary,
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        if (schedule.ongoing)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 7,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: NusaColors.accent,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Text(
                              'Sekarang',
                              style: TextStyle(
                                color: NusaColors.primaryDark,
                                fontSize: 8,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 7),
                    Wrap(
                      spacing: 7,
                      runSpacing: 5,
                      children: [
                        _SchedulePill(
                          icon: Icons.class_outlined,
                          label: schedule.schoolClass?.name ?? '-',
                        ),
                        _SchedulePill(
                          icon: Icons.schedule_rounded,
                          label: period?.label ?? '-',
                        ),
                      ],
                    ),
                    if (schedule.note?.trim().isNotEmpty == true) ...[
                      const SizedBox(height: 7),
                      Text(
                        schedule.note!,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SchedulePill extends StatelessWidget {
  const _SchedulePill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(9),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 13, color: NusaColors.primary),
        const SizedBox(width: 4),
        Text(
          label,
          style: const TextStyle(
            color: NusaColors.primary,
            fontSize: 10,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}
