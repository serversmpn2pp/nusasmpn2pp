import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student/domain/student.dart';

class StudentAvatar extends StatelessWidget {
  const StudentAvatar({required this.student, this.size = 54, super.key});

  final StudentSummary student;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: NusaColors.surfaceBlue,
        shape: BoxShape.circle,
        border: Border.all(color: NusaColors.primary.withValues(alpha: 0.12)),
      ),
      clipBehavior: Clip.antiAlias,
      child: student.photoUrl?.isNotEmpty == true
          ? Image.network(
              student.photoUrl!,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) =>
                  _Initials(student: student, size: size),
            )
          : _Initials(student: student, size: size),
    );
  }
}

class _Initials extends StatelessWidget {
  const _Initials({required this.student, required this.size});

  final StudentSummary student;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Text(
        student.initials,
        style: TextStyle(
          color: NusaColors.primary,
          fontSize: size * 0.3,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class StudentStatusBadge extends StatelessWidget {
  const StudentStatusBadge({required this.active, super.key});

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

class StudentSummaryStrip extends StatelessWidget {
  const StudentSummaryStrip({required this.counts, super.key});

  final StudentCounts counts;

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

class StudentListCard extends StatelessWidget {
  const StudentListCard({
    required this.student,
    required this.onTap,
    super.key,
  });

  final StudentSummary student;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        key: Key('student-item-${student.id}'),
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
              StudentAvatar(student: student),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            student.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: NusaColors.textPrimary,
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        const SizedBox(width: 6),
                        StudentStatusBadge(active: student.active),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      student.identityLabel,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11.5,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        const Icon(
                          Icons.class_outlined,
                          size: 15,
                          color: NusaColors.primary,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            student.activeClass?.name ?? 'Belum ditempatkan',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: NusaColors.primary,
                              fontSize: 11.5,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 4),
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
