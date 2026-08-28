import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_attendance_report/application/student_attendance_report_controller.dart';
import 'package:nusa/features/student_attendance_report/domain/student_attendance_report.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentAttendanceReportView extends ConsumerStatefulWidget {
  const StudentAttendanceReportView({super.key});
  @override
  ConsumerState<StudentAttendanceReportView> createState() =>
      _StudentAttendanceReportViewState();
}

class _StudentAttendanceReportViewState
    extends ConsumerState<StudentAttendanceReportView> {
  final _search = TextEditingController();
  bool _exporting = false;
  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(studentAttendanceReportControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Laporan Presensi Siswa'),
        actions: [
          if (async.value?.canExport ?? false)
            IconButton(
              key: const Key('attendance-report-export'),
              tooltip: 'Export Excel',
              onPressed: _exporting ? null : () => _export(async.requireValue),
              icon: _exporting
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.download_rounded),
            ),
          IconButton(
            tooltip: 'Perbarui',
            onPressed: async.isLoading
                ? null
                : () => ref
                      .read(studentAttendanceReportControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: async.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => _ErrorState(
            message: _errorMessage(error),
            onRetry: () => ref
                .read(studentAttendanceReportControllerProvider.notifier)
                .refresh(),
          ),
          data: (data) => RefreshIndicator(
            onRefresh: () => ref
                .read(studentAttendanceReportControllerProvider.notifier)
                .refresh(),
            child: CustomScrollView(
              slivers: [
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                  sliver: SliverToBoxAdapter(
                    child: Column(
                      children: [
                        _ReportHeader(data: data),
                        const SizedBox(height: 10),
                        _Filters(
                          data: data,
                          search: _search,
                          onDate: () => _pickDate(data),
                          onMonth: () => _pickMonth(data),
                          onRange: () => _pickRange(data),
                        ),
                        if (data.guardianScope)
                          const Padding(
                            padding: EdgeInsets.only(top: 9),
                            child: _GuardianNotice(),
                          ),
                      ],
                    ),
                  ),
                ),
                if (data.items.isEmpty)
                  const SliverFillRemaining(
                    hasScrollBody: false,
                    child: _EmptyState(),
                  )
                else
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                    sliver: SliverList.separated(
                      itemCount: data.items.length + (data.hasMore ? 1 : 0),
                      separatorBuilder: (_, _) => const SizedBox(height: 8),
                      itemBuilder: (context, index) {
                        if (index == data.items.length) {
                          return OutlinedButton.icon(
                            onPressed: () => ref
                                .read(
                                  studentAttendanceReportControllerProvider
                                      .notifier,
                                )
                                .loadMore(),
                            icon: const Icon(Icons.expand_more_rounded),
                            label: const Text('Muat siswa berikutnya'),
                          );
                        }
                        final item = data.items[index];
                        return _StudentCard(
                          item: item,
                          onTap: () => _openDetail(data, item),
                        );
                      },
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _export(StudentAttendanceReportPage page) async {
    setState(() => _exporting = true);
    try {
      final saved = await ref
          .read(studentAttendanceReportActionsProvider)
          .export(page);
      if (mounted && saved) _show('Laporan Excel berhasil disimpan.');
    } catch (error) {
      if (mounted) _show(_errorMessage(error), error: true);
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }

  Future<void> _openDetail(
    StudentAttendanceReportPage page,
    StudentAttendanceReportItem item,
  ) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _DetailSheet(
        future: ref
            .read(studentAttendanceReportActionsProvider)
            .detail(page, item.classMemberId),
      ),
    );
  }

  Future<void> _pickDate(StudentAttendanceReportPage data) async {
    final initial = DateTime.tryParse(data.date) ?? DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2015),
      lastDate: DateTime.now(),
    );
    if (date != null) {
      await ref
          .read(studentAttendanceReportControllerProvider.notifier)
          .filterDate(_iso(date));
    }
  }

  Future<void> _pickMonth(StudentAttendanceReportPage data) async {
    final initial = DateTime.tryParse('${data.month}-01') ?? DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2015),
      lastDate: DateTime.now(),
      helpText: 'Pilih salah satu tanggal pada bulan laporan',
    );
    if (date != null) {
      await ref
          .read(studentAttendanceReportControllerProvider.notifier)
          .filterMonth(_month(date));
    }
  }

  Future<void> _pickRange(StudentAttendanceReportPage data) async {
    final range = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2015),
      lastDate: DateTime.now(),
      initialDateRange: DateTimeRange(
        start: DateTime.tryParse(data.startDate) ?? DateTime.now(),
        end: DateTime.tryParse(data.endDate) ?? DateTime.now(),
      ),
    );
    if (range != null) {
      await ref
          .read(studentAttendanceReportControllerProvider.notifier)
          .filterRange(_iso(range.start), _iso(range.end));
    }
  }

  void _show(String message, {bool error = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: error ? Theme.of(context).colorScheme.error : null,
        ),
      );
  }
}

class _Filters extends ConsumerWidget {
  const _Filters({
    required this.data,
    required this.search,
    required this.onDate,
    required this.onMonth,
    required this.onRange,
  });
  final StudentAttendanceReportPage data;
  final TextEditingController search;
  final VoidCallback onDate, onMonth, onRange;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(
      studentAttendanceReportControllerProvider.notifier,
    );
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            NusaDropdownField<String>(
              fieldKey: const Key('attendance-report-period'),
              value: data.period,
              options: const [
                NusaDropdownOption(value: 'harian', label: 'Harian'),
                NusaDropdownOption(value: 'bulanan', label: 'Bulanan'),
                NusaDropdownOption(value: 'semester', label: 'Semester'),
                NusaDropdownOption(value: 'rentang', label: 'Rentang tanggal'),
              ],
              decoration: const InputDecoration(
                labelText: 'Periode',
                prefixIcon: Icon(Icons.date_range_rounded),
              ),
              onChanged: (value) {
                if (value != null) controller.filterPeriod(value);
              },
            ),
            const SizedBox(height: 9),
            if (data.period == 'harian')
              _PickerField(
                key: const Key('attendance-report-date'),
                label: 'Tanggal',
                value: _dateLabel(data.date),
                icon: Icons.today_rounded,
                onTap: onDate,
              ),
            if (data.period == 'bulanan')
              _PickerField(
                key: const Key('attendance-report-month'),
                label: 'Bulan',
                value: _monthLabel(data.month),
                icon: Icons.calendar_month_rounded,
                onTap: onMonth,
              ),
            if (data.period == 'semester')
              NusaDropdownField<String>(
                fieldKey: const Key('attendance-report-semester'),
                value: data.semester,
                options: const [
                  NusaDropdownOption(value: 'ganjil', label: 'Semester Ganjil'),
                  NusaDropdownOption(value: 'genap', label: 'Semester Genap'),
                ],
                decoration: const InputDecoration(labelText: 'Semester'),
                onChanged: (value) {
                  if (value != null) controller.filterSemester(value);
                },
              ),
            if (data.period == 'rentang')
              _PickerField(
                key: const Key('attendance-report-range'),
                label: 'Rentang tanggal',
                value:
                    '${_dateLabel(data.startDate)} – ${_dateLabel(data.endDate)}',
                icon: Icons.event_available_rounded,
                onTap: onRange,
              ),
            const SizedBox(height: 9),
            Row(
              children: [
                Expanded(
                  child: NusaDropdownField<int>(
                    fieldKey: const Key('attendance-report-year'),
                    value: data.academicYearId,
                    options: data.academicYears
                        .map(
                          (item) => NusaDropdownOption(
                            value: item.id,
                            label:
                                '${item.name}${item.active ? ' · Aktif' : ''}',
                          ),
                        )
                        .toList(),
                    decoration: const InputDecoration(
                      labelText: 'Tahun pelajaran',
                    ),
                    onChanged: controller.filterYear,
                  ),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: NusaDropdownField<int?>(
                    fieldKey: const Key('attendance-report-class'),
                    value: data.classId,
                    options: [
                      if (!data.guardianScope)
                        const NusaDropdownOption<int?>(
                          value: null,
                          label: 'Semua kelas',
                        ),
                      ...data.classes.map(
                        (item) => NusaDropdownOption<int?>(
                          value: item.id,
                          label: item.name,
                        ),
                      ),
                    ],
                    decoration: const InputDecoration(labelText: 'Kelas'),
                    onChanged: controller.filterClass,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 9),
            TextField(
              key: const Key('attendance-report-search'),
              controller: search,
              textInputAction: TextInputAction.search,
              onSubmitted: controller.search,
              decoration: const InputDecoration(
                hintText: 'Cari nama, NIS, atau NISN',
                prefixIcon: Icon(Icons.search_rounded),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PickerField extends StatelessWidget {
  const _PickerField({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    required this.onTap,
  });
  final String label, value;
  final IconData icon;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: Icon(icon),
          suffixIcon: const Icon(Icons.keyboard_arrow_down_rounded),
        ),
        child: Text(value, maxLines: 1, overflow: TextOverflow.ellipsis),
      ),
    ),
  );
}

class _ReportHeader extends StatelessWidget {
  const _ReportHeader({required this.data});
  final StudentAttendanceReportPage data;
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: .16),
          blurRadius: 15,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(Icons.description_rounded, color: NusaColors.accent),
            SizedBox(width: 9),
            Expanded(
              child: Text(
                'Laporan Presensi Siswa',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 15,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 3),
        Text(
          data.periodLabel,
          style: TextStyle(
            color: Colors.white.withValues(alpha: .72),
            fontSize: 10.5,
          ),
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 6,
          runSpacing: 6,
          children: [
            _SummaryTile(label: 'Siswa', value: '${data.summary.students}'),
            _SummaryTile(
              label: 'Hari efektif',
              value: '${data.summary.effectiveDays}',
            ),
            _SummaryTile(
              label: 'Kehadiran',
              value: _percent(data.summary.averageAttendance),
            ),
            _SummaryTile(label: 'Hadir', value: '${data.summary.present}'),
            _SummaryTile(label: 'Izin', value: '${data.summary.permitted}'),
            _SummaryTile(label: 'Sakit', value: '${data.summary.sick}'),
            _SummaryTile(label: 'Alfa', value: '${data.summary.absent}'),
            _SummaryTile(label: 'Terlambat', value: '${data.summary.late}'),
          ],
        ),
      ],
    ),
  );
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile({required this.label, required this.value});
  final String label, value;
  @override
  Widget build(BuildContext context) => Container(
    width: (MediaQuery.sizeOf(context).width - 68) / 4,
    padding: const EdgeInsets.symmetric(vertical: 7, horizontal: 2),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: .1),
      borderRadius: BorderRadius.circular(10),
    ),
    child: Column(
      children: [
        Text(
          value,
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w900,
            fontSize: 14,
          ),
          maxLines: 1,
        ),
        Text(
          label,
          style: TextStyle(
            color: Colors.white.withValues(alpha: .7),
            fontSize: 8,
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ],
    ),
  );
}

class _StudentCard extends StatelessWidget {
  const _StudentCard({required this.item, required this.onTap});
  final StudentAttendanceReportItem item;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      key: Key('attendance-report-student-${item.classMemberId}'),
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            CircleAvatar(
              radius: 23,
              backgroundColor: NusaColors.surfaceBlue,
              backgroundImage: item.photoUrl == null
                  ? null
                  : NetworkImage(item.photoUrl!),
              child: item.photoUrl == null
                  ? Text(
                      item.initials,
                      style: const TextStyle(
                        color: NusaColors.primary,
                        fontWeight: FontWeight.w800,
                      ),
                    )
                  : null,
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.name,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  Text(
                    '${item.className} · NIS ${item.studentNumber ?? '-'}',
                    style: const TextStyle(
                      fontSize: 10.5,
                      color: NusaColors.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 9,
                    runSpacing: 3,
                    children: [
                      _Count(
                        label: 'H',
                        value: item.present,
                        color: const Color(0xFF24954D),
                      ),
                      _Count(
                        label: 'I',
                        value: item.permitted,
                        color: const Color(0xFF2B6CB0),
                      ),
                      _Count(
                        label: 'S',
                        value: item.sick,
                        color: const Color(0xFFD97706),
                      ),
                      _Count(
                        label: 'A',
                        value: item.absent,
                        color: const Color(0xFFC2413B),
                      ),
                      if (item.late > 0)
                        _Count(
                          label: 'T',
                          value: item.late,
                          color: const Color(0xFF9A6700),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 6),
            Column(
              children: [
                Text(
                  _percent(item.attendancePercentage),
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                ),
                const Text(
                  'kehadiran',
                  style: TextStyle(
                    fontSize: 8.5,
                    color: NusaColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 5),
                const Icon(
                  Icons.chevron_right_rounded,
                  color: NusaColors.textSecondary,
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _Count extends StatelessWidget {
  const _Count({required this.label, required this.value, required this.color});
  final String label;
  final int value;
  final Color color;
  @override
  Widget build(BuildContext context) => Text(
    '$label $value',
    style: TextStyle(fontSize: 10, color: color, fontWeight: FontWeight.w800),
  );
}

class _DetailSheet extends StatelessWidget {
  const _DetailSheet({required this.future});
  final Future<StudentAttendanceReportDetail> future;
  @override
  Widget build(BuildContext context) => DraggableScrollableSheet(
    expand: false,
    initialChildSize: .86,
    minChildSize: .55,
    maxChildSize: .96,
    builder: (context, controller) =>
        FutureBuilder<StudentAttendanceReportDetail>(
          future: future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return _ErrorState(
                message: _errorMessage(snapshot.error!),
                onRetry: () => Navigator.pop(context),
              );
            }
            final data = snapshot.requireData;
            return ListView(
              controller: controller,
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                Center(
                  child: Container(
                    width: 44,
                    height: 4,
                    decoration: BoxDecoration(
                      color: const Color(0xFFD0D8E2),
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  data.student.name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 20,
                  ),
                ),
                Text(
                  '${data.student.className} · ${data.periodLabel}',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 11,
                  ),
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: NusaColors.surfaceBlue,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      _MiniSummary(label: 'Hadir', value: data.student.present),
                      _MiniSummary(
                        label: 'Izin',
                        value: data.student.permitted,
                      ),
                      _MiniSummary(label: 'Sakit', value: data.student.sick),
                      _MiniSummary(label: 'Alfa', value: data.student.absent),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                const Text(
                  'Rincian Harian',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                ),
                const SizedBox(height: 7),
                if (data.days.isEmpty)
                  const Padding(
                    padding: EdgeInsets.all(24),
                    child: Center(
                      child: Text('Tidak ada hari efektif pada periode ini.'),
                    ),
                  ),
                ...data.days.map((day) => _DayCard(day: day)),
              ],
            );
          },
        ),
  );
}

class _MiniSummary extends StatelessWidget {
  const _MiniSummary({required this.label, required this.value});
  final String label;
  final int value;
  @override
  Widget build(BuildContext context) => Column(
    children: [
      Text(
        '$value',
        style: const TextStyle(
          color: NusaColors.primary,
          fontWeight: FontWeight.w900,
          fontSize: 17,
        ),
      ),
      Text(
        label,
        style: const TextStyle(fontSize: 9, color: NusaColors.textSecondary),
      ),
    ],
  );
}

class _DayCard extends StatelessWidget {
  const _DayCard({required this.day});
  final StudentAttendanceReportDay day;
  @override
  Widget build(BuildContext context) {
    final color = _statusColor(day.status);
    return Container(
      margin: const EdgeInsets.only(bottom: 7),
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE5EAF0)),
      ),
      child: Row(
        children: [
          Container(
            width: 4,
            height: 42,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(6),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  day.dateLabel,
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 11.5,
                  ),
                ),
                Text(
                  day.inferred
                      ? 'Tidak ada catatan presensi · Alfa inferensi'
                      : 'Masuk ${day.checkIn ?? '-'} · Pulang ${day.checkOut ?? '-'}',
                  style: const TextStyle(
                    fontSize: 9.5,
                    color: NusaColors.textSecondary,
                  ),
                ),
                if (day.lateMinutes > 0 || day.earlyLeaveMinutes > 0)
                  Text(
                    [
                      if (day.lateMinutes > 0)
                        'Terlambat ${day.lateMinutes} menit',
                      if (day.earlyLeaveMinutes > 0)
                        'Pulang cepat ${day.earlyLeaveMinutes} menit',
                    ].join(' · '),
                    style: const TextStyle(
                      fontSize: 9,
                      color: Color(0xFFD97706),
                      fontWeight: FontWeight.w700,
                    ),
                  ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
            decoration: BoxDecoration(
              color: color.withValues(alpha: .1),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              day.statusLabel,
              style: TextStyle(
                color: color,
                fontSize: 9,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _GuardianNotice extends StatelessWidget {
  const _GuardianNotice();
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF8DD),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: NusaColors.accent.withValues(alpha: .5)),
    ),
    child: const Row(
      children: [
        Icon(Icons.shield_outlined, size: 18, color: Color(0xFF8A6A00)),
        SizedBox(width: 8),
        Expanded(
          child: Text(
            'Laporan dibatasi pada kelas yang Anda wali.',
            style: TextStyle(
              fontSize: 10,
              color: Color(0xFF765B00),
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    ),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();
  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(
      padding: EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.description_outlined,
            size: 52,
            color: NusaColors.textSecondary,
          ),
          SizedBox(height: 10),
          Text(
            'Tidak ada siswa sesuai filter.',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
        ],
      ),
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.error_outline_rounded,
            size: 48,
            color: NusaColors.textSecondary,
          ),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
          ),
        ],
      ),
    ),
  );
}

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Terjadi kesalahan. Silakan coba lagi.';
String _iso(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
String _month(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}';
String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  if (date == null) return value;
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

String _monthLabel(String value) {
  final date = DateTime.tryParse('$value-01');
  if (date == null) return value;
  const months = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
  ];
  return '${months[date.month - 1]} ${date.year}';
}

String _percent(double value) =>
    '${value.toStringAsFixed(value % 1 == 0 ? 0 : 1)}%';
Color _statusColor(String status) => switch (status) {
  'hadir' => const Color(0xFF24954D),
  'izin' => const Color(0xFF2B6CB0),
  'sakit' => const Color(0xFFD97706),
  _ => const Color(0xFFC2413B),
};
