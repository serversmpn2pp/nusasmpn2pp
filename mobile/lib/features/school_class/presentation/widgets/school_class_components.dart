import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/school_class/domain/school_class.dart';

class SchoolClassStatusBadge extends StatelessWidget {
  const SchoolClassStatusBadge({required this.active, super.key});

  final bool active;

  @override
  Widget build(BuildContext context) {
    final color = active ? NusaColors.success : NusaColors.textSecondary;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        active ? 'Aktif' : 'Nonaktif',
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class SchoolClassSummaryStrip extends StatelessWidget {
  const SchoolClassSummaryStrip({required this.counts, super.key});

  final SchoolClassCounts counts;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _SummaryItem(
            label: 'Total',
            value: counts.total,
            color: NusaColors.primary,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _SummaryItem(
            label: 'Aktif',
            value: counts.active,
            color: NusaColors.success,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _SummaryItem(
            label: 'Nonaktif',
            value: counts.inactive,
            color: NusaColors.textSecondary,
          ),
        ),
      ],
    );
  }
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 11),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Column(
        children: [
          Text(
            '$value',
            style: TextStyle(
              color: color,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
            ),
          ),
        ],
      ),
    );
  }
}

class SchoolClassListCard extends StatelessWidget {
  const SchoolClassListCard({
    required this.schoolClass,
    required this.onTap,
    super.key,
  });

  final SchoolClassSummary schoolClass;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        key: Key('class-item-${schoolClass.id}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(13),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: NusaColors.outline),
            boxShadow: [
              BoxShadow(
                color: NusaColors.primary.withValues(alpha: 0.04),
                blurRadius: 12,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 54,
                height: 54,
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(15),
                ),
                child: const Icon(
                  Icons.class_rounded,
                  color: NusaColors.primary,
                  size: 28,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            schoolClass.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: NusaColors.textPrimary,
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        const SizedBox(width: 6),
                        SchoolClassStatusBadge(active: schoolClass.active),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      schoolClass.academicYear?.name ??
                          'Tahun pelajaran belum tersedia',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11.5,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        const Icon(
                          Icons.people_alt_outlined,
                          size: 15,
                          color: NusaColors.primary,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          schoolClass.capacityLabel,
                          style: const TextStyle(
                            color: NusaColors.primary,
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            schoolClass.homeroomTeacher?.name ??
                                'Wali kelas belum ditentukan',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            textAlign: TextAlign.right,
                            style: const TextStyle(
                              color: NusaColors.textSecondary,
                              fontSize: 11,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 3),
              const Icon(
                Icons.chevron_right_rounded,
                color: NusaColors.textSecondary,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
