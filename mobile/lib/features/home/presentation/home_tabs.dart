import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/home/application/home_controller.dart';
import 'package:nusa/features/home/domain/home_dashboard.dart';
import 'package:nusa/features/home/presentation/home_dashboard_view.dart';
import 'package:nusa/features/home/presentation/widgets/home_components.dart';
import 'package:nusa/shared/widgets/nusa_logo.dart';
import 'package:nusa/shared/widgets/nusa_section_title.dart';

class NusaPageFrame extends StatelessWidget {
  const NusaPageFrame({
    required this.title,
    required this.child,
    this.action,
    super.key,
  });

  final String title;
  final Widget child;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      bottom: false,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 8, 10, 8),
            child: Row(
              children: [
                const NusaLogo(size: 34),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    title,
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      color: NusaColors.textPrimary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                ?action,
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(child: child),
        ],
      ),
    );
  }
}

class ActivityPage extends StatelessWidget {
  const ActivityPage({
    required this.dashboard,
    required this.onRefresh,
    super.key,
  });

  final AsyncValue<HomeDashboard> dashboard;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    return dashboard.when(
      loading: () => const NusaLoadingState(),
      error: (error, stackTrace) =>
          NusaErrorState(error: error, onRetry: onRefresh),
      data: (data) => RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          key: const PageStorageKey<String>('activity-scroll'),
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 16, 18, 28),
          children: [
            AttendanceCard(attendance: data.attendance?.today),
            const SizedBox(height: 20),
            NusaSectionTitle(title: 'Presensi ${data.monthLabel}'),
            const SizedBox(height: 10),
            if (data.attendance case final attendance?)
              GridView.count(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                crossAxisCount: 3,
                mainAxisSpacing: 8,
                crossAxisSpacing: 8,
                childAspectRatio: 1.05,
                children: [
                  AttendanceMetricCard(
                    label: 'Hadir',
                    value: attendance.month.present,
                    icon: Icons.check_circle_outline,
                  ),
                  AttendanceMetricCard(
                    label: 'Sakit',
                    value: attendance.month.sick,
                    icon: Icons.healing_outlined,
                  ),
                  AttendanceMetricCard(
                    label: 'Izin',
                    value: attendance.month.permitted,
                    icon: Icons.description_outlined,
                  ),
                  AttendanceMetricCard(
                    label: 'Alfa',
                    value: attendance.month.absent,
                    icon: Icons.cancel_outlined,
                  ),
                  AttendanceMetricCard(
                    label: 'Dinas',
                    value: attendance.month.officialDuty,
                    icon: Icons.badge_outlined,
                  ),
                  AttendanceMetricCard(
                    label: 'Terlambat',
                    value: attendance.month.late,
                    icon: Icons.timer_outlined,
                  ),
                ],
              )
            else
              const NusaEmptyCard(
                icon: Icons.fact_check_outlined,
                message: 'Belum ada data aktivitas presensi untuk akun ini.',
              ),
            if (data.duty case final duty?) ...[
              const SizedBox(height: 20),
              const NusaSectionTitle(title: 'Tugas Hari Ini'),
              const SizedBox(height: 10),
              Card(
                child: ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: NusaColors.surfaceBlue,
                    child: Icon(
                      Icons.shield_outlined,
                      color: NusaColors.primary,
                    ),
                  ),
                  title: Text('Piket ${duty.dayLabel}'),
                  subtitle: Text(
                    duty.notes ?? 'Anda terjadwal sebagai guru piket.',
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class NotificationsPage extends ConsumerStatefulWidget {
  const NotificationsPage({
    required this.dashboard,
    required this.onRefresh,
    super.key,
  });

  final AsyncValue<HomeDashboard> dashboard;
  final Future<void> Function() onRefresh;

  @override
  ConsumerState<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends ConsumerState<NotificationsPage> {
  int? _openingNotificationId;
  bool _markingAllRead = false;

  Future<void> _openNotification(AppNotification notification) async {
    if (_openingNotificationId != null || _markingAllRead) return;

    setState(() => _openingNotificationId = notification.id);

    try {
      if (notification.unread) {
        await ref
            .read(homeControllerProvider.notifier)
            .markNotificationRead(notification.id);
      }

      if (!mounted) return;

      setState(() => _openingNotificationId = null);
      final destination = notification.mobileDestination;
      if (destination == null) {
        _showMessage(
          notification.unread
              ? 'Notifikasi ditandai sudah dibaca. Halaman tujuannya belum tersedia di aplikasi mobile.'
              : 'Halaman tujuan notifikasi ini belum tersedia di aplikasi mobile.',
        );
        return;
      }

      context.push(destination);
    } catch (error) {
      if (!mounted) return;
      _showMessage(_errorMessage(error));
    } finally {
      if (mounted && _openingNotificationId == notification.id) {
        setState(() => _openingNotificationId = null);
      }
    }
  }

  Future<void> _markAllRead() async {
    if (_markingAllRead || _openingNotificationId != null) return;

    setState(() => _markingAllRead = true);
    try {
      await ref
          .read(homeControllerProvider.notifier)
          .markAllNotificationsRead();
      if (mounted) {
        _showMessage('Semua notifikasi telah ditandai sudah dibaca.');
      }
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _markingAllRead = false);
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  String _errorMessage(Object error) {
    return error is AppException
        ? error.message
        : 'Notifikasi belum dapat diperbarui. Silakan coba lagi.';
  }

  @override
  Widget build(BuildContext context) {
    return widget.dashboard.when(
      loading: () => const NusaLoadingState(),
      error: (error, stackTrace) =>
          NusaErrorState(error: error, onRetry: widget.onRefresh),
      data: (data) => RefreshIndicator(
        onRefresh: widget.onRefresh,
        child: ListView(
          key: const PageStorageKey<String>('notifications-scroll'),
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 16, 18, 28),
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${data.notifications.unreadCount} belum dibaca',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 13,
                    ),
                  ),
                ),
                if (data.notifications.unreadCount > 0)
                  TextButton.icon(
                    key: const Key('mark-all-notifications-read'),
                    onPressed: _markingAllRead || _openingNotificationId != null
                        ? null
                        : _markAllRead,
                    icon: _markingAllRead
                        ? const SizedBox.square(
                            dimension: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.done_all_rounded, size: 18),
                    label: const Text('Tandai semua dibaca'),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            const Text(
              'Ketuk notifikasi untuk membuka halaman terkait.',
              style: TextStyle(color: NusaColors.textSecondary, fontSize: 12),
            ),
            const SizedBox(height: 12),
            if (data.notifications.items.isEmpty)
              const NusaEmptyCard(
                icon: Icons.notifications_none_rounded,
                message: 'Belum ada notifikasi untuk Anda.',
              )
            else
              for (final item in data.notifications.items) ...[
                NotificationCard(
                  key: Key('notification-${item.id}'),
                  notification: item,
                  isOpening: _openingNotificationId == item.id,
                  onTap: () => _openNotification(item),
                ),
                const SizedBox(height: 9),
              ],
          ],
        ),
      ),
    );
  }
}

class NotificationCard extends StatelessWidget {
  const NotificationCard({
    required this.notification,
    required this.onTap,
    this.isOpening = false,
    super.key,
  });

  final AppNotification notification;
  final VoidCallback onTap;
  final bool isOpening;

  @override
  Widget build(BuildContext context) {
    final color = switch (notification.type) {
      'penting' => Theme.of(context).colorScheme.error,
      'peringatan' => const Color(0xFFD99508),
      'berhasil' => NusaColors.success,
      _ => NusaColors.primary,
    };

    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(15),
      side: const BorderSide(color: NusaColors.outline),
    );

    return Material(
      color: notification.unread
          ? color.withValues(alpha: 0.055)
          : Colors.white,
      shape: shape,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: isOpening ? null : onTap,
        child: Padding(
          padding: const EdgeInsets.all(15),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Icon(
                  Icons.notifications_outlined,
                  color: color,
                  size: 21,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      notification.title,
                      style: TextStyle(
                        fontWeight: notification.unread
                            ? FontWeight.w800
                            : FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      notification.message,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 7),
                    Text(
                      '${notification.typeLabel} · ${notification.relativeTime}',
                      style: TextStyle(
                        color: color,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              if (isOpening)
                SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(
                    color: color,
                    strokeWidth: 2,
                  ),
                )
              else
                Column(
                  children: [
                    if (notification.unread) ...[
                      Container(
                        width: 7,
                        height: 7,
                        decoration: BoxDecoration(
                          color: color,
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(height: 8),
                    ],
                    Icon(Icons.chevron_right_rounded, color: color, size: 21),
                  ],
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class NusaEmptyCard extends StatelessWidget {
  const NusaEmptyCard({required this.icon, required this.message, super.key});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Column(
        children: [
          Icon(icon, size: 36, color: NusaColors.textSecondary),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
        ],
      ),
    );
  }
}
