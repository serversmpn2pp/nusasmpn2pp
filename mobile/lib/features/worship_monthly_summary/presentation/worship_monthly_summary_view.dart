import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_monthly_summary/application/worship_monthly_summary_controller.dart';
import 'package:nusa/features/worship_monthly_summary/domain/worship_monthly_summary.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class WorshipMonthlySummaryView extends ConsumerWidget {
  const WorshipMonthlySummaryView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncPage = ref.watch(worshipMonthlySummaryControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Ringkasan Ibadah Bulanan'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: asyncPage.isLoading
                ? null
                : () => ref
                      .read(worshipMonthlySummaryControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: asyncPage.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ErrorState(
            message: _errorMessage(error),
            onRetry: () => ref
                .read(worshipMonthlySummaryControllerProvider.notifier)
                .refresh(),
          ),
          data: (page) => RefreshIndicator(
            onRefresh: ref
                .read(worshipMonthlySummaryControllerProvider.notifier)
                .refresh,
            child: ListView(
              key: const PageStorageKey<String>('worship-monthly-scroll'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
              children: [
                _HeaderCard(page: page),
                const SizedBox(height: 12),
                _FilterCard(
                  page: page,
                  onSelectMonth: () => _selectMonth(context, ref, page),
                  onActivityChanged: (value) {
                    if (value != null) {
                      ref
                          .read(
                            worshipMonthlySummaryControllerProvider.notifier,
                          )
                          .selectActivity(value);
                    }
                  },
                  onClassChanged: (value) => ref
                      .read(worshipMonthlySummaryControllerProvider.notifier)
                      .selectClass(value),
                ),
                const SizedBox(height: 12),
                if (!page.available)
                  const _StateCard(
                    icon: Icons.event_busy_rounded,
                    title: 'Data ibadah belum tersedia',
                    message: 'Pastikan tahun pelajaran aktif dan kegiatan ibadah sudah dibuat.',
                  )
                else ...[
                  _OverviewCard(
                    summary: page.summary,
                    maleOnly: page.selectedActivity?.maleOnly ?? false,
                  ),
                  const SizedBox(height: 12),
                  _ActivityDatesCard(page: page),
                  const SizedBox(height: 17),
                  const Text(
                    'Ringkasan per Kelas',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Tekan kelas untuk melihat capaian setiap siswa.',
                    style: TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11.5,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (page.classSummaries.isEmpty)
                    const _StateCard(
                      icon: Icons.class_outlined,
                      title: 'Belum ada kelas aktif',
                      message: 'Ringkasan akan tersedia setelah kelas dan anggotanya aktif.',
                    )
                  else
                    _ClassGrid(
                      items: page.classSummaries,
                      selectedClassId: page.selectedClass?.id,
                      onSelect: (value) => ref
                          .read(
                            worshipMonthlySummaryControllerProvider.notifier,
                          )
                          .selectClass(value),
                    ),
                  const SizedBox(height: 14),
                  _NoticeCard(
                    icon: Icons.info_outline_rounded,
                    message: page.calculationNote,
                    color: const Color(0xFFB57900),
                    background: const Color(0xFFFFF7DA),
                  ),
                  const SizedBox(height: 9),
                  _NoticeCard(
                    icon: Icons.shield_outlined,
                    message: page.privacyMessage,
                    color: NusaColors.primary,
                    background: NusaColors.surfaceBlue,
                  ),
                  const SizedBox(height: 17),
                  if (page.selectedClass == null)
                    const _StateCard(
                      icon: Icons.touch_app_rounded,
                      title: 'Pilih kelas untuk melihat siswa',
                      message: 'Ringkasan semua kelas sudah tampil di atas. Tekan salah satu kelas untuk membuka rinciannya.',
                    )
                  else ...[
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Capaian Siswa ${page.selectedClass!.name}',
                            style: const TextStyle(
                              fontSize: 15.5,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        Text(
                          '${page.students.length} siswa',
                          style: const TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 9),
                    if (page.students.isEmpty)
                      const _StateCard(
                        icon: Icons.people_outline_rounded,
                        title: 'Belum ada siswa aktif',
                        message:
                            'Belum ada anggota aktif pada kelas yang dipilih.',
                      )
                    else
                      ...page.students.map(
                        (student) => Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: _StudentCard(student: student),
                        ),
                      ),
                  ],
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _selectMonth(
    BuildContext context,
    WidgetRef ref,
    WorshipMonthlySummaryPage page,
  ) async {
    final selected = _parseMonth(page.month) ?? DateTime.now();
    final minimum =
        _parseMonth(page.minimumMonth) ??
        DateTime(selected.year - 3, selected.month);
    final maximum = _parseMonth(page.maximumMonth) ?? DateTime.now();
    final result = await showDialog<DateTime>(
      context: context,
      builder: (context) => _MonthPickerDialog(
        selected: selected,
        minimum: minimum,
        maximum: maximum,
      ),
    );
    if (result == null) return;
    final value =
        '${result.year.toString().padLeft(4, '0')}-'
        '${result.month.toString().padLeft(2, '0')}';
    await ref
        .read(worshipMonthlySummaryControllerProvider.notifier)
        .selectMonth(value);
  }
}

class _HeaderCard extends StatelessWidget {
  const _HeaderCard({required this.page});

  final WorshipMonthlySummaryPage page;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.18),
          blurRadius: 18,
          offset: const Offset(0, 8),
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
            border: Border.all(color: Colors.white.withValues(alpha: 0.16)),
          ),
          child: const Icon(
            Icons.calendar_view_month_rounded,
            color: NusaColors.accent,
            size: 29,
          ),
        ),
        const SizedBox(width: 13),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                page.monthLabel,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 19,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                page.selectedActivity?.name ?? 'Kegiatan ibadah',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Color(0xFFDCEBFA),
                  fontSize: 11.5,
                  height: 1.3,
                ),
              ),
              if (page.academicYear != null) ...[
                const SizedBox(height: 3),
                Text(
                  'Tahun Pelajaran ${page.academicYear!.name}',
                  style: const TextStyle(
                    color: NusaColors.accent,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
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

class _FilterCard extends StatelessWidget {
  const _FilterCard({
    required this.page,
    required this.onSelectMonth,
    required this.onActivityChanged,
    required this.onClassChanged,
  });

  final WorshipMonthlySummaryPage page;
  final VoidCallback onSelectMonth;
  final ValueChanged<int?> onActivityChanged;
  final ValueChanged<int?> onClassChanged;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      children: [
        Material(
          color: NusaColors.background,
          borderRadius: BorderRadius.circular(13),
          child: InkWell(
            key: const Key('worship-monthly-month'),
            onTap: onSelectMonth,
            borderRadius: BorderRadius.circular(13),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 13),
              child: Row(
                children: [
                  const Icon(
                    Icons.calendar_month_outlined,
                    color: NusaColors.primary,
                  ),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Bulan laporan',
                          style: TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 9.5,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          page.monthLabel,
                          style: const TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Icon(
                    Icons.expand_more_rounded,
                    color: NusaColors.textSecondary,
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 10),
        NusaDropdownField<int>(
          fieldKey: const Key('worship-monthly-activity'),
          value: page.selectedActivity?.id,
          options: page.activities
              .map(
                (item) => NusaDropdownOption<int>(
                  value: item.id,
                  label: item.active ? item.name : '${item.name} · nonaktif',
                ),
              )
              .toList(),
          decoration: const InputDecoration(
            labelText: 'Kegiatan ibadah',
            prefixIcon: Icon(Icons.self_improvement_rounded),
          ),
          onChanged: onActivityChanged,
        ),
        const SizedBox(height: 10),
        NusaDropdownField<int?>(
          fieldKey: const Key('worship-monthly-class'),
          value: page.selectedClass?.id,
          options: [
            const NusaDropdownOption<int?>(value: null, label: 'Semua kelas'),
            ...page.classes.map(
              (item) =>
                  NusaDropdownOption<int?>(value: item.id, label: item.name),
            ),
          ],
          decoration: const InputDecoration(
            labelText: 'Kelas',
            prefixIcon: Icon(Icons.class_outlined),
          ),
          onChanged: onClassChanged,
        ),
      ],
    ),
  );
}

class _OverviewCard extends StatelessWidget {
  const _OverviewCard({required this.summary, required this.maleOnly});

  final WorshipMonthlySummary summary;
  final bool maleOnly;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(15),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      children: [
        Row(
          children: [
            SizedBox.square(
              dimension: 70,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  CircularProgressIndicator(
                    value: summary.percentage.clamp(0, 100) / 100,
                    strokeWidth: 7,
                    color: summary.percentage >= 80
                        ? NusaColors.success
                        : NusaColors.primary,
                    backgroundColor: NusaColors.outline,
                  ),
                  Text(
                    _percentage(summary.percentage),
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontSize: 14,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Capaian Presensi',
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${summary.recorded} dari ${summary.target} target presensi telah tercatat.',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        Row(
          children: [
            _SummaryFact(value: summary.activityDays, label: 'Hari kegiatan'),
            _SummaryFact(
              value: summary.studentCount,
              label: maleOnly ? 'Siswa wajib' : 'Siswa',
            ),
            _SummaryFact(value: summary.recorded, label: 'Tercatat'),
            _SummaryFact(value: summary.missing, label: 'Belum'),
          ],
        ),
      ],
    ),
  );
}

class _SummaryFact extends StatelessWidget {
  const _SummaryFact({required this.value, required this.label});

  final int value;
  final String label;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: const TextStyle(
            color: NusaColors.primary,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 9),
        ),
      ],
    ),
  );
}

class _ActivityDatesCard extends StatelessWidget {
  const _ActivityDatesCard({required this.page});

  final WorshipMonthlySummaryPage page;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: NusaColors.primary.withValues(alpha: 0.14)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Icon(
              Icons.event_available_outlined,
              color: NusaColors.primary,
              size: 21,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                '${page.activityDates.length} tanggal kegiatan dihitung',
                style: const TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
        if (page.activityDates.isEmpty) ...[
          const SizedBox(height: 8),
          const Text(
            'Belum ada jadwal atau presensi pada bulan ini.',
            style: TextStyle(color: NusaColors.textSecondary, fontSize: 11),
          ),
        ] else ...[
          const SizedBox(height: 10),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: page.activityDates
                .map(
                  (item) => Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(9),
                      border: Border.all(color: NusaColors.outline),
                    ),
                    child: Text(
                      item.label,
                      style: const TextStyle(
                        color: NusaColors.primary,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                )
                .toList(),
          ),
        ],
      ],
    ),
  );
}

class _ClassGrid extends StatelessWidget {
  const _ClassGrid({
    required this.items,
    required this.selectedClassId,
    required this.onSelect,
  });

  final List<WorshipMonthlyClassSummary> items;
  final int? selectedClassId;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final columns = constraints.maxWidth < 290 ? 1 : 2;
      final width = (constraints.maxWidth - ((columns - 1) * 9)) / columns;
      return Wrap(
        spacing: 9,
        runSpacing: 9,
        children: items
            .map(
              (item) => SizedBox(
                width: width,
                child: _ClassCard(
                  item: item,
                  selected: selectedClassId == item.schoolClass.id,
                  onTap: () => onSelect(item.schoolClass.id),
                ),
              ),
            )
            .toList(),
      );
    },
  );
}

class _ClassCard extends StatelessWidget {
  const _ClassCard({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  final WorshipMonthlyClassSummary item;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final complete = item.target > 0 && item.missing == 0;
    final color = complete ? NusaColors.success : NusaColors.primary;
    return Material(
      color: selected ? NusaColors.surfaceBlue : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(15),
        side: BorderSide(
          color: selected ? NusaColors.primary : NusaColors.outline,
          width: selected ? 1.5 : 1,
        ),
      ),
      child: InkWell(
        key: Key('worship-monthly-class-${item.schoolClass.id}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(15),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      item.schoolClass.name,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                  Text(
                    _percentage(item.percentage),
                    style: TextStyle(
                      color: color,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                '${item.studentCount} siswa · ${item.recorded}/${item.target} tercatat',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9.5,
                ),
              ),
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: LinearProgressIndicator(
                  value: item.percentage.clamp(0, 100) / 100,
                  minHeight: 5,
                  color: color,
                  backgroundColor: NusaColors.outline,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StudentCard extends StatelessWidget {
  const _StudentCard({required this.student});

  final WorshipMonthlyStudentSummary student;

  @override
  Widget build(BuildContext context) {
    final color = student.percentage >= 80
        ? NusaColors.success
        : const Color(0xFFB57900);
    return Container(
      key: Key('worship-monthly-student-${student.memberId}'),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 22,
                backgroundColor: color.withValues(alpha: 0.1),
                child: Text(
                  student.student.initials,
                  style: TextStyle(color: color, fontWeight: FontWeight.w900),
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${student.rollNumber ?? '-'}. ${student.student.name}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      'NISN ${student.student.nisn ?? '-'}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  _percentage(student.percentage),
                  style: TextStyle(
                    color: color,
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 11),
          Row(
            children: [
              _StudentFact(label: 'Target', value: '${student.target}'),
              const SizedBox(width: 7),
              _StudentFact(label: 'Tercatat', value: '${student.recorded}'),
              const SizedBox(width: 7),
              _StudentFact(label: 'Belum', value: '${student.missing}'),
              const SizedBox(width: 7),
              _StudentFact(label: 'Manual', value: '${student.manual}'),
            ],
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(
              value: student.percentage.clamp(0, 100) / 100,
              minHeight: 6,
              color: color,
              backgroundColor: NusaColors.outline,
            ),
          ),
          const SizedBox(height: 7),
          Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'Presensi terakhir: ${student.lastDateLabel ?? '-'}',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StudentFact extends StatelessWidget {
  const _StudentFact({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
      decoration: BoxDecoration(
        color: NusaColors.background,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: const TextStyle(
              color: NusaColors.primary,
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 8.5,
            ),
          ),
        ],
      ),
    ),
  );
}

class _NoticeCard extends StatelessWidget {
  const _NoticeCard({
    required this.icon,
    required this.message,
    required this.color,
    required this.background,
  });

  final IconData icon;
  final String message;
  final Color color;
  final Color background;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: background,
      borderRadius: BorderRadius.circular(14),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 20, color: color),
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

class _StateCard extends StatelessWidget {
  const _StateCard({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 27),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      children: [
        Icon(icon, color: NusaColors.primary, size: 39),
        const SizedBox(height: 10),
        Text(
          title,
          textAlign: TextAlign.center,
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 5),
        Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 11.5,
            height: 1.4,
          ),
        ),
      ],
    ),
  );
}

class _MonthPickerDialog extends StatefulWidget {
  const _MonthPickerDialog({
    required this.selected,
    required this.minimum,
    required this.maximum,
  });

  final DateTime selected;
  final DateTime minimum;
  final DateTime maximum;

  @override
  State<_MonthPickerDialog> createState() => _MonthPickerDialogState();
}

class _MonthPickerDialogState extends State<_MonthPickerDialog> {
  late int _year = widget.selected.year;

  static const _months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agt',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];

  @override
  Widget build(BuildContext context) => AlertDialog(
    insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 24),
    titlePadding: const EdgeInsets.fromLTRB(18, 15, 10, 5),
    contentPadding: const EdgeInsets.fromLTRB(14, 8, 14, 8),
    actionsPadding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
    title: Row(
      children: [
        const Expanded(
          child: Text(
            'Pilih Bulan',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
        ),
        IconButton(
          tooltip: 'Tutup',
          onPressed: () => Navigator.pop(context),
          icon: const Icon(Icons.close_rounded),
        ),
      ],
    ),
    content: SizedBox(
      width: 330,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconButton(
                key: const Key('worship-monthly-previous-year'),
                onPressed: _year > widget.minimum.year
                    ? () => setState(() => _year--)
                    : null,
                icon: const Icon(Icons.chevron_left_rounded),
              ),
              SizedBox(
                width: 82,
                child: Text(
                  '$_year',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              IconButton(
                key: const Key('worship-monthly-next-year'),
                onPressed: _year < widget.maximum.year
                    ? () => setState(() => _year++)
                    : null,
                icon: const Icon(Icons.chevron_right_rounded),
              ),
            ],
          ),
          const SizedBox(height: 8),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 3,
              mainAxisExtent: 47,
              crossAxisSpacing: 8,
              mainAxisSpacing: 8,
            ),
            itemCount: 12,
            itemBuilder: (context, index) {
              final month = DateTime(_year, index + 1);
              final enabled =
                  !_beforeMonth(month, widget.minimum) &&
                  !_afterMonth(month, widget.maximum);
              final selected =
                  month.year == widget.selected.year &&
                  month.month == widget.selected.month;
              return Material(
                color: selected
                    ? NusaColors.primary
                    : enabled
                    ? NusaColors.background
                    : NusaColors.outline.withValues(alpha: 0.45),
                borderRadius: BorderRadius.circular(11),
                child: InkWell(
                  key: Key('worship-month-${month.year}-${month.month}'),
                  onTap: enabled ? () => Navigator.pop(context, month) : null,
                  borderRadius: BorderRadius.circular(11),
                  child: Center(
                    child: Text(
                      _months[index],
                      style: TextStyle(
                        color: selected
                            ? Colors.white
                            : enabled
                            ? NusaColors.textPrimary
                            : NusaColors.textSecondary.withValues(alpha: 0.5),
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Batal'),
      ),
    ],
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.calendar_view_month_outlined,
            size: 50,
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

DateTime? _parseMonth(String? value) {
  if (value == null || value.isEmpty) return null;
  return DateTime.tryParse('$value-01');
}

bool _beforeMonth(DateTime value, DateTime other) =>
    value.year < other.year ||
    (value.year == other.year && value.month < other.month);

bool _afterMonth(DateTime value, DateTime other) =>
    value.year > other.year ||
    (value.year == other.year && value.month > other.month);

String _percentage(double value) {
  final normalized = value == value.roundToDouble()
      ? value.toStringAsFixed(0)
      : value.toStringAsFixed(1);
  return '$normalized%';
}

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Ringkasan ibadah bulanan belum dapat dimuat. Silakan coba lagi.';
