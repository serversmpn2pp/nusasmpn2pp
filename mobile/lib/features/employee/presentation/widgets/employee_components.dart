import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee/domain/employee.dart';

class EmployeeAvatar extends StatelessWidget {
  const EmployeeAvatar({required this.employee, this.size = 54, super.key});

  final EmployeeSummary employee;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      shape: BoxShape.circle,
      border: Border.all(color: NusaColors.primary.withValues(alpha: 0.12)),
    ),
    clipBehavior: Clip.antiAlias,
    child: employee.photoUrl?.isNotEmpty == true
        ? Image.network(
            employee.photoUrl!,
            fit: BoxFit.cover,
            errorBuilder: (context, error, stackTrace) =>
                _EmployeeInitials(employee: employee, size: size),
          )
        : _EmployeeInitials(employee: employee, size: size),
  );
}

class _EmployeeInitials extends StatelessWidget {
  const _EmployeeInitials({required this.employee, required this.size});

  final EmployeeSummary employee;
  final double size;

  @override
  Widget build(BuildContext context) => Center(
    child: Text(
      employee.initials,
      style: TextStyle(
        color: NusaColors.primary,
        fontSize: size * 0.28,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class EmployeeStatusBadge extends StatelessWidget {
  const EmployeeStatusBadge({required this.active, super.key});

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

class EmployeeSummaryStrip extends StatelessWidget {
  const EmployeeSummaryStrip({required this.counts, super.key});

  final EmployeeCounts counts;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        for (final item in [
          ('Total', counts.total),
          ('Aktif', counts.active),
          ('Nonaktif', counts.inactive),
        ])
          Expanded(
            child: Column(
              children: [
                Text(
                  '${item.$2}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  item.$1,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.76),
                    fontSize: 10,
                  ),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}

class EmployeeListCard extends StatelessWidget {
  const EmployeeListCard({
    required this.employee,
    required this.onTap,
    super.key,
  });

  final EmployeeSummary employee;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    borderRadius: BorderRadius.circular(16),
    child: InkWell(
      key: Key('employee-item-${employee.id}'),
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          border: Border.all(color: NusaColors.outline),
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: NusaColors.primary.withValues(alpha: 0.035),
              blurRadius: 12,
              offset: const Offset(0, 5),
            ),
          ],
        ),
        child: Row(
          children: [
            EmployeeAvatar(employee: employee),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          employee.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(width: 6),
                      EmployeeStatusBadge(active: employee.active),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    employee.identityLabel,
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
                        Icons.work_outline_rounded,
                        size: 14,
                        color: NusaColors.primary,
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          employee.roleLabel,
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
