import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/domain/pengguna.dart';
import 'package:nusa/features/home/domain/home_dashboard.dart';
import 'package:nusa/features/home/presentation/widgets/home_components.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';
import 'package:nusa/features/menu/presentation/menu_visuals.dart';
import 'package:nusa/shared/widgets/nusa_section_title.dart';

class HomeDashboardView extends StatelessWidget {
  const HomeDashboardView({
    required this.pengguna,
    required this.dashboard,
    required this.menu,
    required this.onRefresh,
    required this.onOpenActivity,
    required this.onOpenNusa,
    required this.onOpenNotifications,
    required this.onOpenMenuGroup,
    required this.onOpenMenuEntry,
    required this.onUnavailable,
    super.key,
  });

  final Pengguna pengguna;
  final AsyncValue<HomeDashboard> dashboard;
  final AsyncValue<MenuCatalog> menu;
  final Future<void> Function() onRefresh;
  final VoidCallback onOpenActivity;
  final VoidCallback onOpenNusa;
  final VoidCallback onOpenNotifications;
  final ValueChanged<MenuGroup> onOpenMenuGroup;
  final ValueChanged<MenuEntry> onOpenMenuEntry;
  final ValueChanged<String> onUnavailable;

  @override
  Widget build(BuildContext context) {
    return dashboard.when(
      loading: () => const NusaLoadingState(),
      error: (error, stackTrace) =>
          NusaErrorState(error: error, onRetry: onRefresh),
      data: (data) {
        final catalog = menu.value;
        final quickActions = _quickActions(catalog);
        final allActions = _groupActions(catalog);

        return RefreshIndicator(
          onRefresh: onRefresh,
          color: NusaColors.primary,
          child: SafeArea(
            bottom: false,
            child: ListView(
              key: const PageStorageKey<String>('home-dashboard-scroll'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(18, 10, 18, 28),
              children: [
                HomeHeader(
                  unreadCount: data.notifications.unreadCount,
                  onNotifications: onOpenNotifications,
                ),
                HomeGreeting(
                  greeting: data.greeting,
                  name: pengguna.nama,
                  dayName: data.dayName,
                  dateLabel: data.dateLabel,
                ),
                AttendanceCard(
                  attendance: data.attendance?.today,
                  onTap: onOpenActivity,
                ),
                const SizedBox(height: 18),
                const NusaSectionTitle(title: 'Akses Cepat'),
                const SizedBox(height: 10),
                if (menu.isLoading)
                  const _MenuLoadingPlaceholder()
                else if (quickActions.isEmpty)
                  _MenuEmptyState(onOpenNusa: onOpenNusa)
                else
                  _QuickActionGrid(actions: quickActions),
                const SizedBox(height: 18),
                const NusaSectionTitle(title: 'Semua Menu'),
                const SizedBox(height: 10),
                if (menu.isLoading)
                  const _MenuLoadingPlaceholder()
                else if (menu.hasError)
                  _MenuErrorCard(onRetry: onRefresh)
                else if (allActions.isEmpty)
                  _MenuEmptyState(onOpenNusa: onOpenNusa)
                else
                  _AllMenuGrid(actions: allActions),
                const SizedBox(height: 18),
                NusaSectionTitle(
                  title: 'Jadwal Hari Ini',
                  actionLabel: 'Lihat Semua',
                  onAction: () => onUnavailable('Jadwal'),
                ),
                const SizedBox(height: 8),
                LayoutBuilder(
                  builder: (context, constraints) {
                    final twoColumns = constraints.maxWidth >= 350;
                    final cardWidth = twoColumns
                        ? (constraints.maxWidth - 10) / 2
                        : constraints.maxWidth;

                    return Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        SizedBox(
                          width: cardWidth,
                          child: const ScheduleCard(
                            time: '07.30 - 08.15',
                            subject: 'Bahasa Indonesia',
                            className: 'Kelas 8A',
                            color: Color(0xFF2676C8),
                          ),
                        ),
                        SizedBox(
                          width: cardWidth,
                          child: const ScheduleCard(
                            time: '08.20 - 09.05',
                            subject: 'Matematika',
                            className: 'Kelas 8A',
                            color: Color(0xFFF0B20B),
                          ),
                        ),
                      ],
                    );
                  },
                ),
                if (data.attendance case final attendance?) ...[
                  const SizedBox(height: 20),
                  NusaSectionTitle(title: 'Ringkasan ${data.monthLabel}'),
                  const SizedBox(height: 10),
                  GridView.count(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisCount: 3,
                    mainAxisSpacing: 8,
                    crossAxisSpacing: 8,
                    childAspectRatio: 1.08,
                    children: [
                      AttendanceMetricCard(
                        label: 'Hadir',
                        value: attendance.month.present,
                        icon: Icons.check_circle_outline_rounded,
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
                    ],
                  ),
                ],
                if (data.duty != null ||
                    data.guardianship?.hasAssignments == true) ...[
                  const SizedBox(height: 20),
                  const NusaSectionTitle(title: 'Informasi Anda'),
                  const SizedBox(height: 10),
                  if (data.duty case final duty?)
                    _CompactInfoCard(
                      icon: Icons.shield_outlined,
                      title: 'Piket ${duty.dayLabel}',
                      detail:
                          duty.notes ?? 'Anda terjadwal sebagai guru piket.',
                    ),
                  if (data.duty != null &&
                      data.guardianship?.hasAssignments == true)
                    const SizedBox(height: 8),
                  if (data.guardianship case final guardianship?
                      when guardianship.hasAssignments)
                    _CompactInfoCard(
                      icon: Icons.groups_outlined,
                      title: 'Perwalian',
                      detail: guardianship.classCount > 0
                          ? '${guardianship.classCount} kelas · ${guardianship.classStudentCount} siswa'
                          : '${guardianship.menteeCount} siswa guru wali',
                    ),
                ],
              ],
            ),
          ),
        );
      },
    );
  }

  List<NusaMenuAction> _quickActions(MenuCatalog? catalog) {
    if (catalog == null) {
      return const [];
    }

    final actions = <NusaMenuAction>[];
    final siswa = catalog.entryByCode('siswa');
    final kelas = catalog.entryByCode('kelas');
    final jadwalPelajaran = catalog.entryByCode('jadwal-pelajaran');
    final kehadiran = catalog.groupByCode('kehadiran');
    final akademik = catalog.groupByCode('akademik');
    final nilaiSaya = catalog.entryByCode('nilai-saya');

    if (siswa?.isAvailable == true) {
      actions.add(
        NusaMenuAction(
          label: 'Siswa',
          icon: Icons.school_rounded,
          color: const Color(0xFF2676C8),
          onTap: () => onOpenMenuEntry(siswa!),
        ),
      );
    }
    if (kehadiran != null) {
      actions.add(
        NusaMenuAction(
          label: 'Presensi',
          icon: Icons.fact_check_rounded,
          color: NusaColors.success,
          onTap: onOpenActivity,
        ),
      );
    }
    if (akademik?.items.any((item) => item.code.contains('jadwal')) == true) {
      actions.add(
        NusaMenuAction(
          label: 'Jadwal',
          icon: Icons.calendar_month_rounded,
          color: const Color(0xFF2676C8),
          onTap: jadwalPelajaran?.isAvailable == true
              ? () => onOpenMenuEntry(jadwalPelajaran!)
              : () => onOpenMenuGroup(akademik!),
        ),
      );
    }
    if (kelas?.isAvailable == true) {
      actions.add(
        NusaMenuAction(
          label: 'Kelas',
          icon: Icons.groups_rounded,
          color: const Color(0xFF2468B1),
          onTap: () => onOpenMenuEntry(kelas!),
        ),
      );
    }
    if (actions.length < 4 &&
        akademik?.items.any((item) => item.subgroup == 'Penilaian') == true) {
      actions.add(
        NusaMenuAction(
          label: 'Nilai',
          icon: Icons.workspace_premium_rounded,
          color: const Color(0xFFEFAF08),
          onTap: nilaiSaya?.isAvailable == true
              ? () => onOpenMenuEntry(nilaiSaya!)
              : () => onOpenMenuGroup(akademik!),
        ),
      );
    }

    return actions.take(4).toList(growable: false);
  }

  List<NusaMenuAction> _groupActions(MenuCatalog? catalog) {
    return catalog?.groups
            .map(
              (group) => NusaMenuAction(
                label: group.label,
                icon: nusaMenuGroupIcon(group.icon),
                color: nusaMenuGroupColor(group.code),
                onTap: () => onOpenMenuGroup(group),
              ),
            )
            .toList(growable: false) ??
        const [];
  }
}

class _QuickActionGrid extends StatelessWidget {
  const _QuickActionGrid({required this.actions});

  final List<NusaMenuAction> actions;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (var index = 0; index < actions.length; index++) ...[
          Expanded(child: QuickMenuCard(action: actions[index])),
          if (index < actions.length - 1) const SizedBox(width: 8),
        ],
      ],
    );
  }
}

class _AllMenuGrid extends StatelessWidget {
  const _AllMenuGrid({required this.actions});

  final List<NusaMenuAction> actions;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth < 330 ? 3 : 4;

        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            mainAxisSpacing: 8,
            crossAxisSpacing: 8,
            childAspectRatio: columns == 4 ? 0.98 : 1.05,
          ),
          itemCount: actions.length,
          itemBuilder: (context, index) => MenuGridItem(action: actions[index]),
        );
      },
    );
  }
}

class _MenuLoadingPlaceholder extends StatelessWidget {
  const _MenuLoadingPlaceholder();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 82,
      child: Center(
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: const [
            SizedBox.square(
              dimension: 18,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
            SizedBox(width: 10),
            Text('Memuat menu sesuai hak akses...'),
          ],
        ),
      ),
    );
  }
}

class _MenuErrorCard extends StatelessWidget {
  const _MenuErrorCard({required this.onRetry});

  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Row(
        children: [
          const Expanded(child: Text('Menu belum dapat dimuat.')),
          TextButton(onPressed: onRetry, child: const Text('Coba lagi')),
        ],
      ),
    );
  }
}

class _MenuEmptyState extends StatelessWidget {
  const _MenuEmptyState({required this.onOpenNusa});

  final VoidCallback onOpenNusa;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: NusaColors.surfaceBlue,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          const Expanded(
            child: Text('Belum ada menu untuk hak akses akun ini.'),
          ),
          TextButton(onPressed: onOpenNusa, child: const Text('Periksa')),
        ],
      ),
    );
  }
}

class _CompactInfoCard extends StatelessWidget {
  const _CompactInfoCard({
    required this.icon,
    required this.title,
    required this.detail,
  });

  final IconData icon;
  final String title;
  final String detail;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: NusaColors.surfaceBlue,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: NusaColors.primary),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w800),
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
        ],
      ),
    );
  }
}

class NusaLoadingState extends StatelessWidget {
  const NusaLoadingState({super.key});

  @override
  Widget build(BuildContext context) {
    return const Center(child: CircularProgressIndicator());
  }
}

class NusaErrorState extends StatelessWidget {
  const NusaErrorState({required this.error, required this.onRetry, super.key});

  final Object error;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    final message = error is AppException
        ? (error as AppException).message
        : 'Beranda NUSA belum dapat dimuat.';

    return SafeArea(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.cloud_off_outlined,
                size: 52,
                color: NusaColors.primary,
              ),
              const SizedBox(height: 14),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton.tonalIcon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('Coba lagi'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
