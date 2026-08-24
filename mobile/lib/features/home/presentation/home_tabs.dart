import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';
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

class NotificationsPage extends StatelessWidget {
  const NotificationsPage({
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
          key: const PageStorageKey<String>('notifications-scroll'),
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 16, 18, 28),
          children: [
            Text(
              '${data.notifications.unreadCount} belum dibaca',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 13,
              ),
            ),
            const SizedBox(height: 12),
            if (data.notifications.items.isEmpty)
              const NusaEmptyCard(
                icon: Icons.notifications_none_rounded,
                message: 'Belum ada notifikasi untuk Anda.',
              )
            else
              for (final item in data.notifications.items) ...[
                NotificationCard(notification: item),
                const SizedBox(height: 9),
              ],
          ],
        ),
      ),
    );
  }
}

class NotificationCard extends StatelessWidget {
  const NotificationCard({required this.notification, super.key});

  final AppNotification notification;

  @override
  Widget build(BuildContext context) {
    final color = switch (notification.type) {
      'penting' => Theme.of(context).colorScheme.error,
      'peringatan' => const Color(0xFFD99508),
      'berhasil' => NusaColors.success,
      _ => NusaColors.primary,
    };

    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: notification.unread
            ? color.withValues(alpha: 0.055)
            : Colors.white,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: NusaColors.outline),
      ),
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
            child: Icon(Icons.notifications_outlined, color: color, size: 21),
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
          if (notification.unread)
            Container(
              width: 7,
              height: 7,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            ),
        ],
      ),
    );
  }
}

class ProfilePage extends StatelessWidget {
  const ProfilePage({
    required this.pengguna,
    required this.employee,
    required this.onRefresh,
    required this.onLogout,
    required this.isLoggingOut,
    super.key,
  });

  final Pengguna pengguna;
  final EmployeeSummary? employee;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onLogout;
  final bool isLoggingOut;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        key: const PageStorageKey<String>('profile-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(18, 20, 18, 28),
        children: [
          Center(
            child: Container(
              width: 86,
              height: 86,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [NusaColors.primary, NusaColors.primaryLight],
                ),
                shape: BoxShape.circle,
                border: Border.all(color: NusaColors.accent, width: 3),
              ),
              child: Text(
                pengguna.nama.trim().isEmpty
                    ? 'N'
                    : pengguna.nama.trim()[0].toUpperCase(),
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 30,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            pengguna.nama,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: NusaColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
          Text(
            pengguna.jenisAkun,
            textAlign: TextAlign.center,
            style: const TextStyle(color: NusaColors.textSecondary),
          ),
          const SizedBox(height: 22),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(17),
              child: Column(
                children: [
                  ProfileRow(label: 'Username', value: pengguna.username),
                  if (employee?.nip case final nip?)
                    ProfileRow(label: 'NIP', value: nip),
                  if (employee?.position case final position?)
                    ProfileRow(label: 'Jabatan', value: position),
                  if (employee?.email case final email?)
                    ProfileRow(label: 'Email', value: email),
                  if (employee?.phone case final phone?)
                    ProfileRow(label: 'Nomor HP', value: phone),
                ],
              ),
            ),
          ),
          if (pengguna.peran.isNotEmpty) ...[
            const SizedBox(height: 18),
            const NusaSectionTitle(title: 'Peran'),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: pengguna.peran
                  .map((role) => Chip(label: Text(labelCode(role))))
                  .toList(),
            ),
          ],
          const SizedBox(height: 24),
          OutlinedButton.icon(
            onPressed: isLoggingOut ? null : onLogout,
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Keluar dari NUSA'),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(50),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class ProfileRow extends StatelessWidget {
  const ProfileRow({required this.label, required this.value, super.key});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 88,
            child: Text(
              label,
              style: const TextStyle(color: NusaColors.textSecondary),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ],
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

String labelCode(String value) {
  return value
      .split('_')
      .map(
        (part) => part.isEmpty
            ? part
            : '${part[0].toUpperCase()}${part.substring(1)}',
      )
      .join(' ');
}
