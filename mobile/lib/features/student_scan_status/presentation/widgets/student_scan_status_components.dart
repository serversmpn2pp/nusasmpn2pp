import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_scan_status/domain/student_scan_status.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

const _warningColor = Color(0xFF9A7600);
const _errorColor = Color(0xFFB42318);

class ScanServerStatusCard extends StatelessWidget {
  const ScanServerStatusCard({required this.dashboard, super.key});

  final StudentScanStatusDashboard dashboard;

  @override
  Widget build(BuildContext context) {
    final schedule = dashboard.schedule;
    final phaseColor = _phaseColor(schedule.phase);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: NusaColors.primary.withValues(alpha: 0.18),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: const Icon(
                  Icons.sensors_rounded,
                  color: NusaColors.accent,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Monitoring dari server sekolah',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      dashboard.dateLabel,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.74),
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
            decoration: BoxDecoration(
              color: phaseColor.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: phaseColor.withValues(alpha: 0.65)),
            ),
            child: Text(
              schedule.phaseLabel,
              style: TextStyle(
                color: phaseColor,
                fontSize: 9,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              _ServerTag(
                icon: Icons.schedule_rounded,
                label: 'Server ${_timeLabel(dashboard.serverTime)} WIB',
              ),
              _ServerTag(
                icon: Icons.sync_rounded,
                label: 'Otomatis ${dashboard.nextRefreshSeconds} detik',
              ),
              const _ServerTag(
                icon: Icons.visibility_outlined,
                label: 'Hanya monitoring',
              ),
            ],
          ),
          if (schedule.available) ...[
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.09),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _ScheduleLine(
                    icon: Icons.login_rounded,
                    label:
                        'Masuk ${schedule.checkInWindow} · batas ${schedule.checkInTime}',
                  ),
                  const SizedBox(height: 5),
                  if (schedule.separateFridayCheckOut) ...[
                    _ScheduleLine(
                      key: const Key('friday-female-check-out-schedule'),
                      icon: Icons.woman_rounded,
                      label:
                          'Siswi ${schedule.femaleCheckOutWindow} · resmi ${schedule.femaleCheckOutTime}',
                    ),
                    const SizedBox(height: 5),
                    _ScheduleLine(
                      key: const Key('friday-male-check-out-schedule'),
                      icon: Icons.man_rounded,
                      label:
                          'Siswa laki-laki ${schedule.checkOutWindow} · resmi ${schedule.checkOutTime}',
                    ),
                  ] else
                    _ScheduleLine(
                      icon: Icons.logout_rounded,
                      label:
                          'Pulang ${schedule.checkOutWindow} · resmi ${schedule.checkOutTime}',
                    ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ScheduleLine extends StatelessWidget {
  const _ScheduleLine({required this.icon, required this.label, super.key});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, size: 14, color: NusaColors.accent),
      const SizedBox(width: 6),
      Expanded(
        child: Text(
          label,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 10,
            height: 1.35,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    ],
  );
}

class _ServerTag extends StatelessWidget {
  const _ServerTag({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 13, color: NusaColors.accent),
        const SizedBox(width: 5),
        Text(
          label,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 9.5,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class StudentScanSummaryGrid extends StatelessWidget {
  const StudentScanSummaryGrid({required this.summary, super.key});

  final StudentScanSummary summary;

  @override
  Widget build(BuildContext context) {
    final items = [
      _SummaryData(
        label: 'Sudah masuk',
        value: summary.checkedIn,
        icon: Icons.login_rounded,
        color: NusaColors.success,
      ),
      _SummaryData(
        label: 'Terlambat',
        value: summary.late,
        icon: Icons.timer_off_outlined,
        color: const Color(0xFFB7791F),
      ),
      _SummaryData(
        label: 'Sudah pulang',
        value: summary.checkedOut,
        icon: Icons.logout_rounded,
        color: NusaColors.primary,
      ),
      _SummaryData(
        label: 'Belum scan masuk',
        value: summary.notCheckedIn,
        icon: Icons.hourglass_empty_rounded,
        color: NusaColors.textSecondary,
      ),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 520 ? 4 : 2;
        final width = (constraints.maxWidth - ((columns - 1) * 9)) / columns;
        return Wrap(
          spacing: 9,
          runSpacing: 9,
          children: [
            for (final item in items)
              SizedBox(
                width: width,
                child: _SummaryTile(data: item),
              ),
          ],
        );
      },
    );
  }
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile({required this.data});

  final _SummaryData data;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      children: [
        Container(
          width: 35,
          height: 35,
          decoration: BoxDecoration(
            color: data.color.withValues(alpha: 0.09),
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(data.icon, size: 18, color: data.color),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${data.value}',
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                data.label,
                maxLines: 2,
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

class _SummaryData {
  const _SummaryData({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final int value;
  final IconData icon;
  final Color color;
}

class ScanActivityHealthCard extends StatelessWidget {
  const ScanActivityHealthCard({required this.summary, super.key});

  final StudentScanSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 10),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Wrap(
      spacing: 12,
      runSpacing: 7,
      children: [
        _HealthItem(
          color: NusaColors.success,
          label: '${summary.successfulScans} berhasil',
        ),
        _HealthItem(
          color: _warningColor,
          label: '${summary.alreadyRecorded} sudah tercatat',
        ),
        _HealthItem(
          color: _errorColor,
          label: '${summary.needsAttention} perlu perhatian',
        ),
      ],
    ),
  );
}

class _HealthItem extends StatelessWidget {
  const _HealthItem({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Container(
        width: 8,
        height: 8,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      ),
      const SizedBox(width: 5),
      Text(
        label,
        style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700),
      ),
    ],
  );
}

class StudentScanStatusFilters extends StatelessWidget {
  const StudentScanStatusFilters({
    required this.searchController,
    required this.classes,
    required this.selectedClassId,
    required this.status,
    required this.onSearchChanged,
    required this.onClassChanged,
    required this.onStatusChanged,
    required this.onClearSearch,
    super.key,
  });

  final TextEditingController searchController;
  final List<ScanClassOption> classes;
  final int? selectedClassId;
  final String status;
  final ValueChanged<String> onSearchChanged;
  final ValueChanged<int?> onClassChanged;
  final ValueChanged<String> onStatusChanged;
  final VoidCallback onClearSearch;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      NusaTextField(
        fieldKey: const Key('student-scan-status-search'),
        controller: searchController,
        hintText: 'Cari siswa, NIS, NISN, atau status',
        prefixIcon: Icons.search_rounded,
        onChanged: onSearchChanged,
        suffixIcon: searchController.text.isEmpty
            ? null
            : IconButton(
                onPressed: onClearSearch,
                icon: const Icon(Icons.close_rounded),
              ),
      ),
      const SizedBox(height: 9),
      LayoutBuilder(
        builder: (context, constraints) {
          final fields = [
            NusaDropdownField<int>(
              fieldKey: const Key('student-scan-status-class-filter'),
              value: selectedClassId ?? 0,
              decoration: const InputDecoration(
                labelText: 'Kelas',
                prefixIcon: Icon(Icons.class_outlined),
              ),
              options: [
                const NusaDropdownOption(value: 0, label: 'Semua kelas'),
                for (final item in classes)
                  NusaDropdownOption(value: item.id, label: item.name),
              ],
              onChanged: (value) =>
                  onClassChanged(value == null || value == 0 ? null : value),
            ),
            NusaDropdownField<String>(
              fieldKey: const Key('student-scan-status-result-filter'),
              value: status,
              decoration: const InputDecoration(
                labelText: 'Hasil scan',
                prefixIcon: Icon(Icons.filter_alt_outlined),
              ),
              options: const [
                NusaDropdownOption(value: 'semua', label: 'Semua hasil'),
                NusaDropdownOption(value: 'berhasil', label: 'Berhasil'),
                NusaDropdownOption(
                  value: 'sudah_tercatat',
                  label: 'Sudah tercatat',
                ),
                NusaDropdownOption(
                  value: 'perlu_perhatian',
                  label: 'Perlu perhatian',
                ),
                NusaDropdownOption(value: 'masuk', label: 'Scan masuk'),
                NusaDropdownOption(value: 'pulang', label: 'Scan pulang'),
                NusaDropdownOption(value: 'terlambat', label: 'Terlambat'),
              ],
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
      ),
    ],
  );
}

class ScanActivitySectionHeader extends StatelessWidget {
  const ScanActivitySectionHeader({required this.count, super.key});

  final int count;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      const Expanded(
        child: Text(
          'Aktivitas Scan Terbaru',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
        ),
      ),
      Text(
        '$count aktivitas',
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
      ),
    ],
  );
}

class StudentScanActivityCard extends StatelessWidget {
  const StudentScanActivityCard({required this.activity, super.key});

  final StudentScanActivity activity;

  @override
  Widget build(BuildContext context) {
    final color = activity.successful
        ? NusaColors.success
        : activity.alreadyRecorded || activity.scheduleWarning
        ? _warningColor
        : _errorColor;
    final icon = activity.successful
        ? Icons.check_circle_rounded
        : activity.alreadyRecorded
        ? Icons.info_rounded
        : activity.scheduleWarning
        ? Icons.schedule_rounded
        : Icons.error_rounded;
    final student = activity.student;

    return Container(
      key: Key('student-scan-activity-${activity.id}'),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withValues(alpha: 0.22)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _StudentAvatar(student: student, color: color),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      student?.name ?? 'Kartu tidak dikenali',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _studentMeta(student),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 7),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    activity.scanTime,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    activity.scannerId ?? 'Scanner utama',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 9,
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 10),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.075),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(icon, size: 18, color: color),
                const SizedBox(width: 7),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        activity.statusLabel,
                        style: TextStyle(
                          color: color,
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      if (activity.message?.trim().isNotEmpty == true)
                        Text(
                          activity.message!,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 10, height: 1.3),
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  activity.scanTypeLabel,
                  style: TextStyle(
                    color: color,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          if ((activity.attendance?.lateMinutes ?? 0) > 0 ||
              (activity.attendance?.earlyLeaveMinutes ?? 0) > 0) ...[
            const SizedBox(height: 8),
            Wrap(
              spacing: 7,
              runSpacing: 6,
              children: [
                if ((activity.attendance?.lateMinutes ?? 0) > 0)
                  _AttendanceTag(
                    icon: Icons.timer_off_outlined,
                    label:
                        'Terlambat ${activity.attendance!.lateMinutes} menit',
                  ),
                if ((activity.attendance?.earlyLeaveMinutes ?? 0) > 0)
                  _AttendanceTag(
                    icon: Icons.running_with_errors_outlined,
                    label:
                        'Pulang cepat ${activity.attendance!.earlyLeaveMinutes} menit',
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _StudentAvatar extends StatelessWidget {
  const _StudentAvatar({required this.student, required this.color});

  final ScannedStudent? student;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    width: 42,
    height: 42,
    clipBehavior: Clip.antiAlias,
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(13),
    ),
    child: student?.photoUrl?.trim().isNotEmpty == true
        ? Image.network(
            student!.photoUrl!,
            fit: BoxFit.cover,
            errorBuilder: (context, error, stackTrace) =>
                _AvatarFallback(student: student, color: color),
          )
        : _AvatarFallback(student: student, color: color),
  );
}

class _AvatarFallback extends StatelessWidget {
  const _AvatarFallback({required this.student, required this.color});

  final ScannedStudent? student;
  final Color color;

  @override
  Widget build(BuildContext context) => Center(
    child: student == null
        ? Icon(Icons.credit_card_off_rounded, size: 20, color: color)
        : Text(
            student!.initials,
            style: TextStyle(
              color: color,
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
  );
}

class _AttendanceTag extends StatelessWidget {
  const _AttendanceTag({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: NusaColors.accent.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 12, color: _warningColor),
        const SizedBox(width: 4),
        Text(
          label,
          style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.w700),
        ),
      ],
    ),
  );
}

class StudentScanStatusEmpty extends StatelessWidget {
  const StudentScanStatusEmpty({super.key});

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.fromLTRB(40, 28, 40, 80),
    child: Column(
      mainAxisAlignment: MainAxisAlignment.start,
      children: [
        Icon(Icons.sensors_off_rounded, size: 48, color: NusaColors.primary),
        SizedBox(height: 11),
        Text(
          'Belum ada aktivitas scan yang sesuai dengan filter.',
          textAlign: TextAlign.center,
          style: TextStyle(color: NusaColors.textSecondary),
        ),
      ],
    ),
  );
}

class StudentScanStatusError extends StatelessWidget {
  const StudentScanStatusError({
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

Color _phaseColor(String phase) => switch (phase) {
  'scan_masuk' || 'scan_pulang' => const Color(0xFF86EFAC),
  'tidak_tersedia' => const Color(0xFFFCA5A5),
  _ => NusaColors.accent,
};

String _timeLabel(DateTime? value) {
  if (value == null) return '--:--:--';
  return '${value.hour.toString().padLeft(2, '0')}:'
      '${value.minute.toString().padLeft(2, '0')}:'
      '${value.second.toString().padLeft(2, '0')}';
}

String _studentMeta(ScannedStudent? student) {
  if (student == null) return 'Periksa kartu dan mesin scanner';
  final identifier = student.nationalStudentNumber ?? student.studentNumber;
  return [
    if (student.className?.trim().isNotEmpty == true) student.className!,
    if (identifier?.trim().isNotEmpty == true) identifier!,
  ].join(' · ');
}
