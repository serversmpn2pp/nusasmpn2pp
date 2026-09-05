import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_attendance_settings/domain/student_attendance_settings.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class AttendanceSettingsSummaryCard extends StatelessWidget {
  const AttendanceSettingsSummaryCard({required this.summary, super.key});

  final StudentAttendanceSettingsSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 13),
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
      children: [
        for (final item in [
          ('Hari diatur', summary.total),
          ('Aktif', summary.active),
          ('Belum diatur', summary.unconfigured),
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
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.76),
                    fontSize: 9.5,
                  ),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}

class AttendanceSettingsInfoBanner extends StatelessWidget {
  const AttendanceSettingsInfoBanner({required this.allConfigured, super.key});

  final bool allConfigured;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.primary.withValues(alpha: 0.12)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.info_outline_rounded, color: NusaColors.primary),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            allConfigured
                ? 'Semua hari sudah memiliki jadwal. Pemindaian kartu tetap dilakukan melalui mesin scanner sekolah.'
                : 'Atur jam setiap hari sebelum dipakai oleh mesin scanner presensi sekolah.',
            style: const TextStyle(fontSize: 11.5, height: 1.35),
          ),
        ),
      ],
    ),
  );
}

class AttendanceSettingsFilters extends StatelessWidget {
  const AttendanceSettingsFilters({
    required this.days,
    required this.selectedDay,
    required this.status,
    required this.enabled,
    required this.onDayChanged,
    required this.onStatusChanged,
    super.key,
  });

  final List<AttendanceDay> days;
  final String selectedDay;
  final String status;
  final bool enabled;
  final ValueChanged<String> onDayChanged;
  final ValueChanged<String> onStatusChanged;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final fields = [
        NusaDropdownField<String>(
          fieldKey: const Key('attendance-settings-day-filter'),
          value: selectedDay,
          decoration: const InputDecoration(
            labelText: 'Hari',
            prefixIcon: Icon(Icons.calendar_today_outlined),
          ),
          options: [
            const NusaDropdownOption(value: 'semua', label: 'Semua hari'),
            for (final day in days)
              NusaDropdownOption(value: day.code, label: day.label),
          ],
          enabled: enabled,
          onChanged: (value) {
            if (value != null) onDayChanged(value);
          },
        ),
        NusaDropdownField<String>(
          fieldKey: const Key('attendance-settings-status-filter'),
          value: status,
          decoration: const InputDecoration(
            labelText: 'Status',
            prefixIcon: Icon(Icons.toggle_on_outlined),
          ),
          options: const [
            NusaDropdownOption(value: 'semua', label: 'Semua status'),
            NusaDropdownOption(value: 'aktif', label: 'Aktif'),
            NusaDropdownOption(value: 'nonaktif', label: 'Nonaktif'),
          ],
          enabled: enabled,
          onChanged: (value) {
            if (value != null) onStatusChanged(value);
          },
        ),
      ];

      if (constraints.maxWidth < 390) {
        return Column(
          children: [fields.first, const SizedBox(height: 8), fields.last],
        );
      }
      return Row(
        children: [
          Expanded(child: fields.first),
          const SizedBox(width: 9),
          Expanded(child: fields.last),
        ],
      );
    },
  );
}

class AttendanceSettingsResults extends StatelessWidget {
  const AttendanceSettingsResults({
    required this.catalog,
    required this.onRefresh,
    required this.enabled,
    this.onEdit,
    super.key,
  });

  final StudentAttendanceSettingsCatalog catalog;
  final Future<void> Function() onRefresh;
  final ValueChanged<StudentAttendanceSetting>? onEdit;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    if (catalog.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(40, 52, 40, 110),
          children: const [
            Icon(Icons.event_busy_rounded, size: 52, color: NusaColors.primary),
            SizedBox(height: 12),
            Text(
              'Belum ada pengaturan presensi pada filter ini.',
              textAlign: TextAlign.center,
              style: TextStyle(color: NusaColors.textSecondary),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        key: const PageStorageKey<String>('student-attendance-settings-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 100),
        itemCount: catalog.items.length,
        separatorBuilder: (context, index) => const SizedBox(height: 10),
        itemBuilder: (context, index) {
          final item = catalog.items[index];
          return AttendanceSettingCard(
            setting: item,
            onTap: onEdit == null || !enabled ? null : () => onEdit!(item),
          );
        },
      ),
    );
  }
}

class AttendanceSettingCard extends StatelessWidget {
  const AttendanceSettingCard({required this.setting, this.onTap, super.key});

  final StudentAttendanceSetting setting;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Material(
    key: Key('student-attendance-setting-${setting.id}'),
    color: Colors.white,
    borderRadius: BorderRadius.circular(17),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(17),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(17),
          border: Border.all(
            color: setting.active
                ? NusaColors.primary.withValues(alpha: 0.16)
                : NusaColors.outline,
          ),
        ),
        child: Column(
          children: [
            Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: setting.active
                        ? NusaColors.surfaceBlue
                        : NusaColors.background,
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Icon(
                    Icons.calendar_month_rounded,
                    color: setting.active
                        ? NusaColors.primary
                        : NusaColors.textSecondary,
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        setting.dayLabel,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        setting.notes?.trim().isNotEmpty == true
                            ? setting.notes!
                            : 'Jadwal presensi harian',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10.5,
                        ),
                      ),
                    ],
                  ),
                ),
                _StatusBadge(active: setting.active),
                if (onTap != null) ...[
                  const SizedBox(width: 7),
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: NusaColors.primary,
                  ),
                ],
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _TimeSummary(
                    icon: Icons.login_rounded,
                    title: 'Masuk ${setting.checkInTime}',
                    subtitle: 'Scan ${setting.checkInWindow}',
                    color: NusaColors.success,
                  ),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: _TimeSummary(
                    icon: Icons.logout_rounded,
                    title: setting.separateFridayCheckOut
                        ? 'Laki-laki ${setting.checkOutTime}'
                        : 'Pulang ${setting.checkOutTime}',
                    subtitle: 'Scan ${setting.checkOutWindow}',
                    color: NusaColors.primary,
                  ),
                ),
              ],
            ),
            if (setting.separateFridayCheckOut) ...[
              const SizedBox(height: 9),
              _FridayCheckOutSummary(setting: setting),
            ],
          ],
        ),
      ),
    ),
  );
}

class _FridayCheckOutSummary extends StatelessWidget {
  const _FridayCheckOutSummary({required this.setting});

  final StudentAttendanceSetting setting;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
    decoration: BoxDecoration(
      color: NusaColors.accent.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: NusaColors.accent.withValues(alpha: 0.28)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.woman_rounded, size: 18, color: Color(0xFF9A7600)),
        const SizedBox(width: 7),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Siswi pulang ${setting.femaleCheckOutTime ?? '-'}',
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                'Scan ${setting.femaleCheckOutWindow} · khusus Jumat',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9,
                ),
              ),
            ],
          ),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
          decoration: BoxDecoration(
            color: NusaColors.accent.withValues(alpha: 0.18),
            borderRadius: BorderRadius.circular(20),
          ),
          child: const Text(
            'Jumat',
            style: TextStyle(
              color: Color(0xFF7A5E00),
              fontSize: 9,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    ),
  );
}

class _TimeSummary extends StatelessWidget {
  const _TimeSummary({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 9),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      children: [
        Icon(icon, size: 18, color: color),
        const SizedBox(width: 7),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: (active ? NusaColors.success : NusaColors.textSecondary)
          .withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      active ? 'Aktif' : 'Nonaktif',
      style: TextStyle(
        color: active ? NusaColors.success : NusaColors.textSecondary,
        fontSize: 9,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class AttendanceSettingsError extends StatelessWidget {
  const AttendanceSettingsError({
    required this.message,
    required this.onRetry,
    super.key,
  });

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 48,
            color: NusaColors.primary,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
          ),
        ],
      ),
    ),
  );
}
