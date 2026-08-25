import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/parent_account/domain/parent_account.dart';

class ParentAccountSummaryStrip extends StatelessWidget {
  const ParentAccountSummaryStrip({required this.counts, super.key});

  final ParentAccountCounts counts;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.16),
          blurRadius: 15,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: Row(
      children: [
        _SummaryValue(value: counts.students, label: 'Jumlah\nSiswa'),
        _SummaryValue(value: counts.activeAccounts, label: 'Akun\nAktif'),
        _SummaryValue(value: counts.inactiveAccounts, label: 'Akun\nNonaktif'),
        _SummaryValue(value: counts.withoutAccount, label: 'Belum\nAkun'),
        _SummaryValue(value: counts.withoutNisn, label: 'Tanpa\nNISN'),
      ],
    ),
  );
}

class _SummaryValue extends StatelessWidget {
  const _SummaryValue({required this.value, required this.label});

  final int value;
  final String label;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Colors.white70,
            fontSize: 8.5,
            height: 1.15,
          ),
        ),
      ],
    ),
  );
}

class ParentAccountAvatar extends StatelessWidget {
  const ParentAccountAvatar({required this.student, this.size = 52, super.key});

  final ParentAccountStudent student;
  final double size;

  @override
  Widget build(BuildContext context) {
    final fallback = Center(
      child: Text(
        student.initials,
        style: TextStyle(
          color: NusaColors.primary,
          fontSize: size * 0.3,
          fontWeight: FontWeight.w800,
        ),
      ),
    );

    return Container(
      width: size,
      height: size,
      clipBehavior: Clip.antiAlias,
      decoration: const BoxDecoration(
        color: NusaColors.surfaceBlue,
        shape: BoxShape.circle,
      ),
      child: student.photoUrl?.isNotEmpty == true
          ? Image.network(
              student.photoUrl!,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) => fallback,
            )
          : fallback,
    );
  }
}

class ParentAccountStatusBadge extends StatelessWidget {
  const ParentAccountStatusBadge({
    required this.status,
    required this.label,
    super.key,
  });

  final String status;
  final String label;

  @override
  Widget build(BuildContext context) {
    final (color, background, icon) = switch (status) {
      'aktif' => (
        NusaColors.success,
        NusaColors.success.withValues(alpha: 0.11),
        Icons.check_circle_rounded,
      ),
      'nonaktif' => (
        const Color(0xFFB42318),
        const Color(0xFFFFECEA),
        Icons.block_rounded,
      ),
      'tanpa_nisn' => (
        NusaColors.textSecondary,
        const Color(0xFFF0F3F7),
        Icons.badge_outlined,
      ),
      _ => (
        const Color(0xFFB57900),
        const Color(0xFFFFF5D8),
        Icons.pending_rounded,
      ),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 4),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: color,
                fontSize: 10.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class ParentAccountCard extends StatelessWidget {
  const ParentAccountCard({required this.item, required this.onTap, super.key});

  final ParentAccountItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final parentLabel = item.parent.available
        ? '${item.parent.relationshipLabel}: ${item.parent.name ?? '-'}'
        : item.student.nisn?.trim().isNotEmpty == true
        ? 'Username: ORT-${item.student.nisn}'
        : 'Identitas akun belum dapat dibuat';

    return Card(
      margin: EdgeInsets.zero,
      elevation: 0,
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: NusaColors.outline),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        key: Key('parent-account-${item.student.id}'),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              ParentAccountAvatar(student: item.student),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.student.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textPrimary,
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        height: 1.2,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      parentLabel,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11.5,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${item.membership.schoolClass.name} • No. ${item.membership.attendanceNumber ?? '-'} • NISN ${item.student.nisn ?? '-'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                    const SizedBox(height: 7),
                    ParentAccountStatusBadge(
                      status: item.status,
                      label: item.statusLabel,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
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
