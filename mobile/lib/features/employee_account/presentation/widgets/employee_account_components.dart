import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_account/domain/employee_account.dart';

class EmployeeAccountSummaryStrip extends StatelessWidget {
  const EmployeeAccountSummaryStrip({required this.counts, super.key});

  final EmployeeAccountCounts counts;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 12),
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
        _SummaryValue(value: counts.activeEmployees, label: 'Pegawai\nAktif'),
        _SummaryValue(value: counts.withNip, label: 'Punya\nNIP'),
        _SummaryValue(value: counts.accounts, label: 'Akun\nPegawai'),
        _SummaryValue(value: counts.withoutAccount, label: 'Belum\nAkun'),
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
            fontSize: 17,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Colors.white70,
            fontSize: 9,
            height: 1.15,
          ),
        ),
      ],
    ),
  );
}

class EmployeeAccountAvatar extends StatelessWidget {
  const EmployeeAccountAvatar({
    required this.employee,
    this.size = 52,
    super.key,
  });

  final AccountEmployee employee;
  final double size;

  @override
  Widget build(BuildContext context) {
    final fallback = Center(
      child: Text(
        employee.initials,
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
      child: employee.photoUrl?.isNotEmpty == true
          ? Image.network(
              employee.photoUrl!,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) => fallback,
            )
          : fallback,
    );
  }
}

class AccountStatusBadge extends StatelessWidget {
  const AccountStatusBadge({
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
      'tanpa_nip' => (
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

class EmployeeAccountCard extends StatelessWidget {
  const EmployeeAccountCard({
    required this.item,
    required this.onTap,
    super.key,
  });

  final EmployeeAccountItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    elevation: 0,
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(16),
      side: const BorderSide(color: NusaColors.outline),
    ),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      key: Key('employee-account-${item.employee.id}'),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            EmployeeAccountAvatar(employee: item.employee),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.employee.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textPrimary,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      height: 1.2,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    item.account.available
                        ? 'Username ${item.account.username ?? '-'}'
                        : 'NIP ${item.employee.nip ?? '-'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11.5,
                    ),
                  ),
                  const SizedBox(height: 7),
                  AccountStatusBadge(
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
