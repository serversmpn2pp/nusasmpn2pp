import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/personal_worship/application/personal_worship_controller.dart';
import 'package:nusa/features/personal_worship/domain/personal_worship.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/shared/widgets/nusa_section_title.dart';

class PersonalWorshipView extends ConsumerStatefulWidget {
  const PersonalWorshipView({required this.pageTitle, super.key});

  final String pageTitle;

  @override
  ConsumerState<PersonalWorshipView> createState() =>
      _PersonalWorshipViewState();
}

class _PersonalWorshipViewState extends ConsumerState<PersonalWorshipView> {
  String _status = 'semua';

  @override
  Widget build(BuildContext context) {
    final result = ref.watch(personalWorshipControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: Text(widget.pageTitle),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: result.isLoading
                ? null
                : ref.read(personalWorshipControllerProvider.notifier).refresh,
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
            onRetry: ref
                .read(personalWorshipControllerProvider.notifier)
                .refresh,
          ),
          data: (page) => _Content(
            page: page,
            status: _status,
            onStatusChanged: (value) => setState(() => _status = value),
          ),
        ),
      ),
    );
  }
}

class _Content extends ConsumerWidget {
  const _Content({
    required this.page,
    required this.status,
    required this.onStatusChanged,
  });

  final PersonalWorshipPage page;
  final String status;
  final ValueChanged<String> onStatusChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(personalWorshipControllerProvider.notifier);
    final records = status == 'semua'
        ? page.records
        : page.records.where((record) => record.status == status).toList();

    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        key: const PageStorageKey<String>('personal-worship-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
        children: [
          _IdentityCard(page: page),
          if (page.isParent && page.students.length > 1) ...[
            const SizedBox(height: 10),
            NusaDropdownField<int>(
              fieldKey: const Key('personal-worship-student-filter'),
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
            fieldKey: const Key('personal-worship-year-filter'),
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
          const SizedBox(height: 16),
          NusaSectionTitle(title: 'Ringkasan ${page.monthLabel}'),
          const SizedBox(height: 10),
          _SummaryCard(summary: page.summary),
          const SizedBox(height: 10),
          _PrivacyNotice(message: page.privacyMessage),
          const SizedBox(height: 20),
          Row(
            children: [
              const Expanded(child: NusaSectionTitle(title: 'Riwayat Ibadah')),
              Text(
                '${records.length} kegiatan',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          _StatusFilters(value: status, onChanged: onStatusChanged),
          const SizedBox(height: 10),
          if (records.isEmpty)
            _EmptyState(
              message: status == 'semua'
                  ? page.emptyMessage
                  : 'Tidak ada riwayat dengan status ini pada ${page.monthLabel}.',
            )
          else
            for (var index = 0; index < records.length; index++) ...[
              _RecordCard(record: records[index]),
              if (index < records.length - 1) const SizedBox(height: 8),
            ],
        ],
      ),
    );
  }
}

class _IdentityCard extends StatelessWidget {
  const _IdentityCard({required this.page});

  final PersonalWorshipPage page;

  @override
  Widget build(BuildContext context) {
    final student = page.student;
    final schoolClass = page.schoolClass;
    return Container(
      key: const Key('personal-worship-identity'),
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
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(15),
              border: Border.all(color: Colors.white24),
            ),
            child: const Icon(
              Icons.self_improvement_rounded,
              color: NusaColors.accent,
              size: 30,
            ),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  page.isParent ? 'Ibadah Anak Saya' : 'Ibadah Saya',
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
    final today = DateTime.now();
    final canMoveForward =
        selected.year < today.year ||
        (selected.year == today.year && selected.month < today.month);
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Row(
        children: [
          IconButton(
            key: const Key('personal-worship-previous-month'),
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
            key: const Key('personal-worship-next-month'),
            tooltip: 'Bulan berikutnya',
            onPressed: canMoveForward
                ? () => onChanged(_monthValue(selected, 1))
                : null,
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

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});

  final PersonalWorshipSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('personal-worship-summary'),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                '${summary.percentage}% terlaksana',
                style: const TextStyle(
                  color: NusaColors.primary,
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            Text(
              '${summary.completed}/${summary.requiredCount} wajib',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10.5,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
        const SizedBox(height: 9),
        ClipRRect(
          borderRadius: BorderRadius.circular(99),
          child: LinearProgressIndicator(
            minHeight: 7,
            value: (summary.percentage / 100).clamp(0, 1),
            backgroundColor: NusaColors.surfaceBlue,
            color: NusaColors.success,
          ),
        ),
        const SizedBox(height: 13),
        LayoutBuilder(
          builder: (context, constraints) {
            final itemWidth = (constraints.maxWidth - 16) / 3;
            return Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _SummaryItem(
                  width: itemWidth,
                  value: summary.completed,
                  label: 'Sudah',
                  color: NusaColors.success,
                ),
                _SummaryItem(
                  width: itemWidth,
                  value: summary.missed,
                  label: 'Belum',
                  color: const Color(0xFFD34B4B),
                ),
                _SummaryItem(
                  width: itemWidth,
                  value: summary.excused,
                  label: 'Berhalangan',
                  color: const Color(0xFF7A56B3),
                ),
                _SummaryItem(
                  width: itemWidth,
                  value: summary.absentFromSchool,
                  label: 'Tidak hadir',
                  color: NusaColors.textSecondary,
                ),
                _SummaryItem(
                  width: itemWidth,
                  value: summary.notRequired,
                  label: 'Tidak wajib',
                  color: const Color(0xFF2676C8),
                ),
                _SummaryItem(
                  width: itemWidth,
                  value: summary.total,
                  label: 'Terjadwal',
                  color: NusaColors.primary,
                ),
              ],
            );
          },
        ),
      ],
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({
    required this.width,
    required this.value,
    required this.label,
    required this.color,
  });

  final double width;
  final int value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    width: width,
    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 9),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: color,
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    ),
  );
}

class _PrivacyNotice extends StatelessWidget {
  const _PrivacyNotice({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('personal-worship-privacy-notice'),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFFF6F1FC),
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: const Color(0xFFE4D7F1)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(
          Icons.privacy_tip_outlined,
          size: 18,
          color: Color(0xFF7A56B3),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
              height: 1.4,
            ),
          ),
        ),
      ],
    ),
  );
}

class _StatusFilters extends StatelessWidget {
  const _StatusFilters({required this.value, required this.onChanged});

  final String value;
  final ValueChanged<String> onChanged;

  static const options = <(String, String)>[
    ('semua', 'Semua'),
    ('sudah', 'Sudah'),
    ('belum', 'Belum'),
    ('berhalangan', 'Berhalangan'),
    ('tidak_hadir', 'Tidak hadir'),
    ('tidak_wajib', 'Tidak wajib'),
  ];

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    scrollDirection: Axis.horizontal,
    child: Row(
      children: [
        for (var index = 0; index < options.length; index++) ...[
          ChoiceChip(
            key: Key('personal-worship-filter-${options[index].$1}'),
            label: Text(options[index].$2),
            selected: value == options[index].$1,
            onSelected: (_) => onChanged(options[index].$1),
            showCheckmark: false,
            visualDensity: VisualDensity.compact,
          ),
          if (index < options.length - 1) const SizedBox(width: 7),
        ],
      ],
    ),
  );
}

class _RecordCard extends StatelessWidget {
  const _RecordCard({required this.record});

  final PersonalWorshipRecord record;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(record.status);
    final detail = switch (record.status) {
      'sudah' => 'Tercatat ${record.recordedAt ?? record.time} WIB',
      'berhalangan' =>
        'Tercatat privat ${record.recordedAt ?? record.time} WIB',
      'tidak_hadir' => record.schoolAttendanceLabel,
      'tidak_wajib' => 'Tidak menjadi kewajiban pada jadwal ini',
      _ => 'Jadwal ${record.time} WIB',
    };
    return Container(
      key: Key('personal-worship-record-${record.scheduleId}-${record.date}'),
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
            width: 41,
            height: 41,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(_statusIcon(record.status), color: color, size: 22),
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
                        record.activity.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textPrimary,
                          fontSize: 12.5,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      record.statusLabel,
                      style: TextStyle(
                        color: color,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  record.dateLabel,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  detail,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10,
                    height: 1.35,
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
          Icons.self_improvement_rounded,
          size: 42,
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

Color _statusColor(String status) => switch (status) {
  'sudah' => NusaColors.success,
  'belum' => const Color(0xFFD34B4B),
  'berhalangan' => const Color(0xFF7A56B3),
  'tidak_wajib' => const Color(0xFF2676C8),
  _ => NusaColors.textSecondary,
};

IconData _statusIcon(String status) => switch (status) {
  'sudah' => Icons.check_circle_rounded,
  'belum' => Icons.error_outline_rounded,
  'berhalangan' => Icons.privacy_tip_rounded,
  'tidak_wajib' => Icons.home_rounded,
  'tidak_hadir' => Icons.event_busy_rounded,
  _ => Icons.help_outline_rounded,
};

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Riwayat ibadah belum dapat dimuat.';
