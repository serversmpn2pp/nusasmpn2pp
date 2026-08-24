import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/home/domain/home_dashboard.dart';
import 'package:nusa/shared/widgets/nusa_illustrations.dart';
import 'package:nusa/shared/widgets/nusa_logo.dart';

class HomeHeader extends StatelessWidget {
  const HomeHeader({
    required this.unreadCount,
    required this.onNotifications,
    super.key,
  });

  final int unreadCount;
  final VoidCallback onNotifications;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const NusaBrand(logoSize: 40, compact: true),
        const Spacer(),
        IconButton(
          onPressed: onNotifications,
          tooltip: 'Notifikasi',
          style: IconButton.styleFrom(
            foregroundColor: NusaColors.primary,
            backgroundColor: NusaColors.surfaceBlue,
          ),
          icon: Stack(
            clipBehavior: Clip.none,
            children: [
              const Icon(Icons.notifications_none_rounded),
              if (unreadCount > 0)
                const Positioned(
                  right: 0,
                  top: 0,
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      color: NusaColors.accent,
                      shape: BoxShape.circle,
                    ),
                    child: SizedBox.square(dimension: 8),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class HomeGreeting extends StatelessWidget {
  const HomeGreeting({
    required this.greeting,
    required this.name,
    required this.dayName,
    required this.dateLabel,
    super.key,
  });

  final String greeting;
  final String name;
  final String dayName;
  final String dateLabel;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final narrow = constraints.maxWidth < 340;

        return SizedBox(
          height: narrow ? 148 : 124,
          child: Row(
            children: [
              Expanded(
                flex: 6,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '$greeting,',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: NusaColors.textPrimary,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(
                            color: NusaColors.textPrimary,
                            fontSize: narrow ? 20 : null,
                            fontWeight: FontWeight.w800,
                            height: 1.08,
                          ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      '$dayName, $dateLabel',
                      maxLines: 2,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 4),
              const Expanded(flex: 5, child: NusaSchoolIllustration()),
            ],
          ),
        );
      },
    );
  }
}

class AttendanceCard extends StatelessWidget {
  const AttendanceCard({required this.attendance, this.onTap, super.key});

  final TodayAttendance? attendance;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final recorded = attendance?.recorded ?? false;
    final status = recorded
        ? attendance!.statusLabel.toLowerCase() == 'hadir'
              ? 'Sudah Hadir'
              : attendance!.statusLabel
        : 'Belum Tercatat';
    final detail = recorded
        ? 'Masuk ${attendance?.checkIn ?? '-'} WIB'
        : 'Presensi hari ini belum tersedia';
    final color = recorded ? NusaColors.success : NusaColors.primary;
    final surface = recorded
        ? NusaColors.successSurface
        : NusaColors.surfaceBlue;

    return Material(
      color: surface,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        key: const Key('home-attendance-card'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: color.withValues(alpha: 0.22)),
            boxShadow: [
              BoxShadow(
                color: color.withValues(alpha: 0.08),
                blurRadius: 18,
                offset: const Offset(0, 7),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                child: Icon(
                  recorded ? Icons.verified_rounded : Icons.schedule_rounded,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Presensi Hari Ini',
                      style: TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 12,
                      ),
                    ),
                    Text(
                      status,
                      style: TextStyle(
                        color: color,
                        fontSize: 19,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      detail,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
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

class NusaMenuAction {
  const NusaMenuAction({
    required this.label,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;
}

class QuickMenuCard extends StatelessWidget {
  const QuickMenuCard({required this.action, super.key});

  final NusaMenuAction action;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(15),
      elevation: 0,
      child: InkWell(
        onTap: action.onTap,
        borderRadius: BorderRadius.circular(15),
        child: Container(
          height: 82,
          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(15),
            border: Border.all(color: NusaColors.outline),
            boxShadow: [
              BoxShadow(
                color: NusaColors.primary.withValues(alpha: 0.05),
                blurRadius: 12,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(action.icon, color: action.color, size: 29),
              const SizedBox(height: 6),
              FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  action.label,
                  maxLines: 1,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class MenuGridItem extends StatelessWidget {
  const MenuGridItem({required this.action, super.key});

  final NusaMenuAction action;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: action.onTap,
        borderRadius: BorderRadius.circular(14),
        child: DecoratedBox(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: NusaColors.outline),
            boxShadow: [
              BoxShadow(
                color: NusaColors.primary.withValues(alpha: 0.04),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 9),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(action.icon, color: action.color, size: 27),
                const SizedBox(height: 6),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    action.label,
                    maxLines: 1,
                    style: const TextStyle(
                      color: NusaColors.textPrimary,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class ScheduleCard extends StatelessWidget {
  const ScheduleCard({
    required this.time,
    required this.subject,
    required this.className,
    required this.color,
    super.key,
  });

  final String time;
  final String subject;
  final String className;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 92),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: NusaColors.outline),
        boxShadow: [
          BoxShadow(
            color: NusaColors.primary.withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 5,
            decoration: BoxDecoration(
              color: color,
              borderRadius: const BorderRadius.horizontal(
                left: Radius.circular(14),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 11),
            child: Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(9),
              ),
              child: Icon(Icons.menu_book_rounded, color: color, size: 21),
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(0, 12, 10, 12),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    time,
                    style: const TextStyle(
                      color: NusaColors.textPrimary,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subject,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textPrimary,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    className,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class AttendanceMetricCard extends StatelessWidget {
  const AttendanceMetricCard({
    required this.label,
    required this.value,
    required this.icon,
    super.key,
  });

  final String label;
  final int value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: NusaColors.surfaceBlue,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, size: 21, color: NusaColors.primary),
          const SizedBox(height: 4),
          Text(
            '$value',
            style: const TextStyle(
              color: NusaColors.textPrimary,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10,
            ),
          ),
        ],
      ),
    );
  }
}
