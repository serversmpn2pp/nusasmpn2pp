import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/attendance_exceptions/domain/attendance_exception.dart';
import 'package:nusa/features/attendance_exceptions/presentation/attendance_exception_widgets.dart';

class AttendanceExceptionSummary extends StatelessWidget {
  const AttendanceExceptionSummary({required this.summary, super.key});
  final ExceptionData? summary;

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('exception-summary'),
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 14),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.16),
          blurRadius: 14,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final item in const [
          ('Total', 'total'),
          ('Aktif', 'aktif'),
          ('Dibatalkan', 'dibatalkan'),
        ])
          Expanded(
            child: Column(
              children: [
                Text(
                  exceptionText(summary?[item.$2], '—'),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  item.$1,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 10,
                    color: Colors.white.withValues(alpha: 0.76),
                  ),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}

class AttendanceExceptionInfoBanner extends StatelessWidget {
  const AttendanceExceptionInfoBanner({super.key});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.primary.withValues(alpha: 0.12)),
    ),
    child: const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.info_outline_rounded, color: NusaColors.primary, size: 21),
        SizedBox(width: 9),
        Expanded(
          child: Text(
            'Pengecualian scan bukan bukti hadir; alfa manual '
            'tetap berlaku. Ringkasan mengikuti pencarian dan filter.',
            style: TextStyle(fontSize: 11.5, height: 1.4),
          ),
        ),
      ],
    ),
  );
}

class AttendanceExceptionCard extends StatelessWidget {
  const AttendanceExceptionCard({
    required this.item,
    required this.onOpen,
    super.key,
  });
  final ExceptionData item;
  final VoidCallback? onOpen;

  @override
  Widget build(BuildContext context) {
    final active = item['aktif'] == true;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 20;
    final badge = ExceptionStatus(active, compact: true);
    return Material(
      key: Key('attendance-exception-${item['id']}'),
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(17),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(17),
            border: Border.all(
              color: active
                  ? NusaColors.primary.withValues(alpha: 0.16)
                  : NusaColors.outline,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: active
                          ? NusaColors.surfaceBlue
                          : NusaColors.background,
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Icon(
                      Icons.event_busy_outlined,
                      size: 22,
                      color: active
                          ? NusaColors.primary
                          : NusaColors.textSecondary,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          exceptionText(item['cakupan_label']),
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          exceptionText(
                            exceptionMap(item['tahun_pelajaran'])['nama'],
                          ),
                          style: const TextStyle(
                            fontSize: 10.5,
                            color: NusaColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (!largeText) ...[const SizedBox(width: 8), badge],
                  if (onOpen != null) ...[
                    const SizedBox(width: 5),
                    const Icon(
                      Icons.chevron_right_rounded,
                      size: 21,
                      color: NusaColors.primary,
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 7,
                runSpacing: 6,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  if (largeText) badge,
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: NusaColors.surfaceBlue,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      exceptionText(item['jenis_label']),
                      style: const TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                        color: NusaColors.primary,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              LayoutBuilder(
                builder: (context, constraints) {
                  final start = _ExceptionDateBlock(
                    label: 'Mulai',
                    date: exceptionDate(item['tanggal_mulai']),
                    icon: Icons.calendar_today_outlined,
                  );
                  final end = _ExceptionDateBlock(
                    label: 'Selesai',
                    date: exceptionDate(item['tanggal_selesai']),
                    icon: Icons.event_available_outlined,
                  );
                  if (largeText) {
                    return Column(
                      children: [start, const SizedBox(height: 8), end],
                    );
                  }
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(child: start),
                      const SizedBox(width: 9),
                      Expanded(child: end),
                    ],
                  );
                },
              ),
              const SizedBox(height: 11),
              Text(
                exceptionText(item['alasan']),
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 12,
                  height: 1.45,
                  color: NusaColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ExceptionDateBlock extends StatelessWidget {
  const _ExceptionDateBlock({
    required this.label,
    required this.date,
    required this.icon,
  });
  final String label, date;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 16, color: NusaColors.primary),
        const SizedBox(width: 7),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(
                  fontSize: 9.5,
                  color: NusaColors.textSecondary,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                date,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}
