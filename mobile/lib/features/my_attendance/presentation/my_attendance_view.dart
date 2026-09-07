import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_attendance/application/my_attendance_controller.dart';
import 'package:nusa/features/my_attendance/domain/my_attendance.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/shared/widgets/nusa_section_title.dart';

class MyAttendanceView extends ConsumerWidget {
  const MyAttendanceView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final result = ref.watch(myAttendanceControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Kehadiranku'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: result.isLoading
                ? null
                : ref.read(myAttendanceControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: result.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => _ErrorState(
            message: _errorMessage(error),
            onRetry: ref.read(myAttendanceControllerProvider.notifier).refresh,
          ),
          data: (page) => _AttendanceContent(page: page),
        ),
      ),
    );
  }
}

class _AttendanceContent extends ConsumerWidget {
  const _AttendanceContent({required this.page});

  final MyAttendancePage page;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(myAttendanceControllerProvider.notifier);
    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        key: const PageStorageKey<String>('my-attendance-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
        children: [
          _IdentityCard(page: page),
          if (page.isParent && page.students.length > 1) ...[
            const SizedBox(height: 10),
            NusaDropdownField<int>(
              fieldKey: const Key('my-attendance-student-filter'),
              value: page.filter.studentId,
              options: page.students
                  .map(
                    (student) => NusaDropdownOption<int>(
                      value: student.id,
                      label: student.name,
                    ),
                  )
                  .toList(growable: false),
              decoration: const InputDecoration(
                labelText: 'Pilih anak',
                prefixIcon: Icon(Icons.account_circle_outlined),
              ),
              onChanged: controller.selectStudent,
            ),
          ],
          const SizedBox(height: 10),
          NusaDropdownField<int>(
            fieldKey: const Key('my-attendance-year-filter'),
            value: page.filter.academicYearId,
            options: page.academicYears
                .map(
                  (year) => NusaDropdownOption<int>(
                    value: year.id,
                    label: year.label,
                  ),
                )
                .toList(growable: false),
            decoration: const InputDecoration(
              labelText: 'Tahun pelajaran',
              prefixIcon: Icon(Icons.school_outlined),
            ),
            onChanged: page.academicYears.isEmpty
                ? null
                : controller.selectAcademicYear,
          ),
          const SizedBox(height: 10),
          _MonthSelector(
            month: page.filter.month,
            label: page.monthLabel,
            onChanged: controller.selectMonth,
          ),
          const SizedBox(height: 14),
          _TodayCard(today: page.today, parentMode: page.isParent),
          const SizedBox(height: 20),
          NusaSectionTitle(title: 'Rekap ${page.monthLabel}'),
          const SizedBox(height: 10),
          _SummaryCard(summary: page.summary),
          const SizedBox(height: 20),
          Row(
            children: [
              const Expanded(
                child: NusaSectionTitle(title: 'Riwayat Kehadiran'),
              ),
              Text(
                '${page.summary.total} catatan',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (page.records.isEmpty)
            _EmptyState(message: page.emptyMessage)
          else
            for (var index = 0; index < page.records.length; index++) ...[
              _AttendanceRecordCard(record: page.records[index]),
              if (index < page.records.length - 1) const SizedBox(height: 8),
            ],
        ],
      ),
    );
  }
}

class _IdentityCard extends StatelessWidget {
  const _IdentityCard({required this.page});

  final MyAttendancePage page;

  @override
  Widget build(BuildContext context) {
    final student = page.student;
    final schoolClass = page.schoolClass;
    return Container(
      key: const Key('my-attendance-identity'),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: NusaColors.primary.withValues(alpha: 0.18),
            blurRadius: 16,
            offset: const Offset(0, 7),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(15),
              border: Border.all(color: Colors.white24),
            ),
            child: const Icon(
              Icons.how_to_reg_rounded,
              color: NusaColors.accent,
              size: 28,
            ),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  page.isParent ? 'Kehadiran Anak' : 'Kehadiran Siswa',
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  student?.name ?? 'Data siswa belum tersedia',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  [
                    if (schoolClass != null) schoolClass.name,
                    if (schoolClass?.attendanceNumber != null)
                      'Absen ${schoolClass!.attendanceNumber}',
                    if ((student?.nisn ?? '').isNotEmpty)
                      'NISN ${student!.nisn}',
                  ].join(' · '),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white70, fontSize: 10.5),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MonthSelector extends StatelessWidget {
  const _MonthSelector({
    required this.month,
    required this.label,
    required this.onChanged,
  });

  final String month;
  final String label;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final selected = DateTime.tryParse('$month-01') ?? DateTime.now();
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Row(
        children: [
          IconButton(
            key: const Key('my-attendance-previous-month'),
            tooltip: 'Bulan sebelumnya',
            onPressed: () => onChanged(_monthValue(selected, -1)),
            icon: const Icon(Icons.chevron_left_rounded),
          ),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text(
                  'Bulan rekap',
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            key: const Key('my-attendance-next-month'),
            tooltip: 'Bulan berikutnya',
            onPressed: () => onChanged(_monthValue(selected, 1)),
            icon: const Icon(Icons.chevron_right_rounded),
          ),
        ],
      ),
    );
  }

  String _monthValue(DateTime selected, int offset) {
    final value = DateTime(selected.year, selected.month + offset);
    return '${value.year}-${value.month.toString().padLeft(2, '0')}';
  }
}

class _TodayCard extends StatelessWidget {
  const _TodayCard({required this.today, required this.parentMode});

  final MyAttendanceToday today;
  final bool parentMode;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(today.status);
    return Container(
      key: const Key('my-attendance-today'),
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: today.recorded ? color.withValues(alpha: 0.08) : Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(
          color: today.recorded
              ? color.withValues(alpha: 0.28)
              : NusaColors.outline,
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.13),
              shape: BoxShape.circle,
            ),
            child: Icon(
              today.recorded ? Icons.verified_rounded : Icons.schedule_rounded,
              color: color,
              size: 27,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  parentMode ? 'Status Hari Ini' : 'Kehadiran Hari Ini',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  today.statusLabel,
                  style: TextStyle(
                    color: color,
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  _todayDetail(today),
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _todayDetail(MyAttendanceToday item) {
    if (!item.recorded) return 'Belum ada catatan dari mesin presensi.';
    final parts = <String>[
      if (item.checkIn != null) 'Masuk ${item.checkIn} WIB',
      if (item.checkOut != null) 'Pulang ${item.checkOut} WIB',
      if (item.lateMinutes > 0) 'Terlambat ${item.lateMinutes} menit',
    ];
    return parts.isEmpty ? 'Kehadiran sudah tercatat.' : parts.join(' · ');
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});

  final MyAttendanceSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('my-attendance-summary'),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                '${_formatPercentage(summary.presentPercentage)}% hadir',
                style: const TextStyle(
                  color: NusaColors.primary,
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            Text(
              '${summary.total} hari tercatat',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10.5,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        ClipRRect(
          borderRadius: BorderRadius.circular(20),
          child: LinearProgressIndicator(
            value: (summary.presentPercentage / 100).clamp(0, 1),
            minHeight: 7,
            color: NusaColors.success,
            backgroundColor: NusaColors.outline,
          ),
        ),
        const SizedBox(height: 13),
        LayoutBuilder(
          builder: (context, constraints) => GridView.count(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisCount: constraints.maxWidth >= 480 ? 4 : 2,
            mainAxisSpacing: 8,
            crossAxisSpacing: 8,
            childAspectRatio: constraints.maxWidth >= 480 ? 1.65 : 2.2,
            children: [
              _Metric(
                label: 'Hadir',
                value: summary.present,
                color: NusaColors.success,
              ),
              _Metric(
                label: 'Sakit',
                value: summary.sick,
                color: const Color(0xFF7A56B3),
              ),
              _Metric(
                label: 'Izin',
                value: summary.permitted,
                color: const Color(0xFF2676C8),
              ),
              _Metric(
                label: 'Alfa',
                value: summary.absent,
                color: const Color(0xFFD34B4B),
              ),
            ],
          ),
        ),
        if (summary.late > 0 || summary.earlyLeave > 0) ...[
          const SizedBox(height: 11),
          const Divider(height: 1),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _CompactSummary(
                  icon: Icons.timer_outlined,
                  text:
                      '${summary.late}× terlambat · ${summary.lateMinutes} menit',
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _CompactSummary(
                  icon: Icons.exit_to_app_rounded,
                  text:
                      '${summary.earlyLeave}× pulang cepat · ${summary.earlyLeaveMinutes} menit',
                ),
              ),
            ],
          ),
        ],
      ],
    ),
  );

  String _formatPercentage(double value) => value == value.roundToDouble()
      ? value.toInt().toString()
      : value.toStringAsFixed(1).replaceAll('.', ',');
}

class _Metric extends StatelessWidget {
  const _Metric({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: color,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    ),
  );
}

class _CompactSummary extends StatelessWidget {
  const _CompactSummary({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, size: 16, color: NusaColors.textSecondary),
      const SizedBox(width: 5),
      Expanded(
        child: Text(
          text,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
            height: 1.3,
          ),
        ),
      ),
    ],
  );
}

class _AttendanceRecordCard extends StatelessWidget {
  const _AttendanceRecordCard({required this.record});

  final MyAttendanceRecord record;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(record.status);
    final details = <String>[
      if (record.checkIn != null) 'Masuk ${record.checkIn}',
      if (record.checkOut != null) 'Pulang ${record.checkOut}',
      if (record.lateMinutes > 0) 'Terlambat ${record.lateMinutes} menit',
      if (record.earlyLeaveMinutes > 0)
        'Pulang cepat ${record.earlyLeaveMinutes} menit',
    ];
    return Container(
      key: Key('my-attendance-record-${record.id}'),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 39,
            height: 39,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(_statusIcon(record.status), color: color, size: 21),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        record.dateLabel,
                        style: const TextStyle(
                          color: NusaColors.textPrimary,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      record.statusLabel,
                      style: TextStyle(
                        color: color,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  details.isEmpty ? 'Tidak ada jam scan.' : details.join(' · '),
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  'Sumber: ${record.sourceLabel}',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 9.5,
                  ),
                ),
                if ((record.notes ?? '').trim().isNotEmpty) ...[
                  const SizedBox(height: 5),
                  Text(
                    record.notes!,
                    style: const TextStyle(
                      color: NusaColors.textPrimary,
                      fontSize: 10.5,
                      fontStyle: FontStyle.italic,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(28),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      children: [
        const Icon(
          Icons.event_busy_outlined,
          size: 40,
          color: NusaColors.primary,
        ),
        const SizedBox(height: 9),
        Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 11,
            height: 1.4,
          ),
        ),
      ],
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 43),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

Color _statusColor(String? status) => switch (status) {
  'hadir' => NusaColors.success,
  'sakit' => const Color(0xFF7A56B3),
  'izin' => const Color(0xFF2676C8),
  'alfa' => const Color(0xFFD34B4B),
  _ => NusaColors.textSecondary,
};

IconData _statusIcon(String? status) => switch (status) {
  'hadir' => Icons.check_circle_rounded,
  'sakit' => Icons.healing_rounded,
  'izin' => Icons.description_rounded,
  'alfa' => Icons.cancel_rounded,
  _ => Icons.help_outline_rounded,
};

String _errorMessage(Object error) =>
    error is AppException ? error.message : 'Kehadiran belum dapat dimuat.';
