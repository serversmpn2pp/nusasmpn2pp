import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_attendance_report/application/employee_attendance_report_controller.dart';
import 'package:nusa/features/employee_attendance_report/domain/employee_attendance_report.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class EmployeeAttendanceReportView extends ConsumerStatefulWidget {
  const EmployeeAttendanceReportView({super.key});

  @override
  ConsumerState<EmployeeAttendanceReportView> createState() =>
      _EmployeeAttendanceReportViewState();
}

class _EmployeeAttendanceReportViewState
    extends ConsumerState<EmployeeAttendanceReportView> {
  final _search = TextEditingController();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(employeeAttendanceReportControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text(
          'Laporan Presensi Pegawai',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: async.isLoading
                ? null
                : () => ref
                      .read(employeeAttendanceReportControllerProvider.notifier)
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
                .read(employeeAttendanceReportControllerProvider.notifier)
                .refresh(),
          ),
          data: (data) => RefreshIndicator(
            onRefresh: () => ref
                .read(employeeAttendanceReportControllerProvider.notifier)
                .refresh(),
            child: CustomScrollView(
              key: const PageStorageKey<String>(
                'employee-attendance-report-scroll',
              ),
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
                  sliver: SliverToBoxAdapter(
                    child: Column(
                      children: [
                        _ReportHeader(data: data),
                        const SizedBox(height: 10),
                        _SummaryGrid(summary: data.summary),
                        const SizedBox(height: 10),
                        _MonthPicker(data: data, onTap: () => _pickMonth(data)),
                        if (!data.privateScope) ...[
                          const SizedBox(height: 9),
                          Row(
                            children: [
                              Expanded(
                                child: NusaDropdownField<String?>(
                                  fieldKey: const Key('employee-report-type'),
                                  value: data.employeeType,
                                  options: [
                                    const NusaDropdownOption<String?>(
                                      value: null,
                                      label: 'Semua jenis',
                                    ),
                                    ...data.employeeTypes.map(
                                      (item) => NusaDropdownOption<String?>(
                                        value: item,
                                        label: item,
                                      ),
                                    ),
                                  ],
                                  decoration: const InputDecoration(
                                    labelText: 'Jenis pegawai',
                                  ),
                                  onChanged: (value) => ref
                                      .read(
                                        employeeAttendanceReportControllerProvider
                                            .notifier,
                                      )
                                      .filterEmployeeType(value),
                                ),
                              ),
                              const SizedBox(width: 9),
                              Expanded(
                                child: NusaDropdownField<String>(
                                  fieldKey: const Key(
                                    'employee-report-active-status',
                                  ),
                                  value: data.employeeStatus,
                                  options: const [
                                    NusaDropdownOption(
                                      value: 'aktif',
                                      label: 'Pegawai aktif',
                                    ),
                                    NusaDropdownOption(
                                      value: 'nonaktif',
                                      label: 'Nonaktif',
                                    ),
                                    NusaDropdownOption(
                                      value: 'semua',
                                      label: 'Semua pegawai',
                                    ),
                                  ],
                                  decoration: const InputDecoration(
                                    labelText: 'Status pegawai',
                                  ),
                                  onChanged: (value) {
                                    if (value != null) {
                                      ref
                                          .read(
                                            employeeAttendanceReportControllerProvider
                                                .notifier,
                                          )
                                          .filterEmployeeStatus(value);
                                    }
                                  },
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 9),
                          NusaDropdownField<int?>(
                            fieldKey: const Key('employee-report-employee'),
                            value: data.employeeId,
                            options: [
                              const NusaDropdownOption<int?>(
                                value: null,
                                label: 'Semua pegawai',
                              ),
                              ...data.employees.map(
                                (item) => NusaDropdownOption<int?>(
                                  value: item.id,
                                  label:
                                      '${item.name}${item.nip == null ? '' : ' · ${item.nip}'}',
                                ),
                              ),
                            ],
                            decoration: const InputDecoration(
                              labelText: 'Pilih pegawai',
                              prefixIcon: Icon(Icons.badge_outlined),
                            ),
                            onChanged: (value) => ref
                                .read(
                                  employeeAttendanceReportControllerProvider
                                      .notifier,
                                )
                                .filterEmployee(value),
                          ),
                          const SizedBox(height: 9),
                          TextField(
                            key: const Key('employee-report-search'),
                            controller: _search,
                            textInputAction: TextInputAction.search,
                            onSubmitted: (value) => ref
                                .read(
                                  employeeAttendanceReportControllerProvider
                                      .notifier,
                                )
                                .search(value),
                            decoration: InputDecoration(
                              hintText: 'Cari nama, NIP, atau jabatan',
                              prefixIcon: const Icon(Icons.search_rounded),
                              suffixIcon: _search.text.isEmpty
                                  ? null
                                  : IconButton(
                                      onPressed: _clearSearch,
                                      icon: const Icon(Icons.close_rounded),
                                    ),
                            ),
                          ),
                        ] else ...[
                          const SizedBox(height: 9),
                          const _PrivateNotice(),
                        ],
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
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
                    sliver: SliverList.separated(
                      itemCount: data.items.length + (data.hasMore ? 1 : 0),
                      separatorBuilder: (_, _) => const SizedBox(height: 8),
                      itemBuilder: (context, index) {
                        if (index == data.items.length) {
                          return OutlinedButton.icon(
                            onPressed: () => ref
                                .read(
                                  employeeAttendanceReportControllerProvider
                                      .notifier,
                                )
                                .loadMore(),
                            icon: const Icon(Icons.expand_more_rounded),
                            label: const Text('Muat pegawai berikutnya'),
                          );
                        }
                        final item = data.items[index];
                        return _EmployeeCard(
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

  Future<void> _pickMonth(EmployeeAttendanceReportPage data) async {
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
          .read(employeeAttendanceReportControllerProvider.notifier)
          .filterMonth(_month(date));
    }
  }

  Future<void> _openDetail(
    EmployeeAttendanceReportPage page,
    EmployeeAttendanceReportItem item,
  ) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _DetailSheet(
        future: ref
            .read(employeeAttendanceReportActionsProvider)
            .detail(page, item.employeeId),
      ),
    );
  }

  void _clearSearch() {
    _search.clear();
    setState(() {});
    ref.read(employeeAttendanceReportControllerProvider.notifier).search('');
  }
}

class _ReportHeader extends StatelessWidget {
  const _ReportHeader({required this.data});
  final EmployeeAttendanceReportPage data;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(15),
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
        Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: .12),
                borderRadius: BorderRadius.circular(13),
                border: Border.all(
                  color: NusaColors.accent.withValues(alpha: .75),
                ),
              ),
              child: const Icon(
                Icons.assessment_rounded,
                color: NusaColors.accent,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    data.privateScope
                        ? 'Laporan Presensi Saya'
                        : 'Laporan Bulanan Pegawai',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                    ),
                  ),
                  Text(
                    data.periodLabel,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: .78),
                      fontSize: 10.5,
                    ),
                  ),
                ],
              ),
            ),
            Text(
              _percent(data.summary.averageAttendance),
              style: const TextStyle(
                color: NusaColors.accent,
                fontWeight: FontWeight.w900,
                fontSize: 22,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Text(
          '${data.summary.employees} pegawai · ${data.summary.effectiveDays} total hari efektif',
          style: TextStyle(
            color: Colors.white.withValues(alpha: .78),
            fontSize: 10,
          ),
        ),
      ],
    ),
  );
}

class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.summary});
  final EmployeeAttendanceReportSummary summary;

  @override
  Widget build(BuildContext context) {
    final items = [
      ('Hadir', summary.present, const Color(0xFF24954D)),
      ('Izin/Sakit', summary.permitted + summary.sick, const Color(0xFFD97706)),
      ('Dinas/Cuti', summary.officialDuty + summary.leave, NusaColors.primary),
      ('Alfa', summary.absent, const Color(0xFFC2413B)),
      ('Terlambat', summary.late, const Color(0xFF9A6700)),
      ('Manual', summary.manual, const Color(0xFF7A56B3)),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = (constraints.maxWidth - 18) / 3;
        return Wrap(
          spacing: 9,
          runSpacing: 9,
          children: items
              .map(
                (item) => SizedBox(
                  width: width,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 10,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(13),
                      border: Border.all(color: NusaColors.outline),
                    ),
                    child: Column(
                      children: [
                        Text(
                          '${item.$2}',
                          style: TextStyle(
                            color: item.$3,
                            fontSize: 17,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        Text(
                          item.$1,
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
                ),
              )
              .toList(),
        );
      },
    );
  }
}

class _MonthPicker extends StatelessWidget {
  const _MonthPicker({required this.data, required this.onTap});
  final EmployeeAttendanceReportPage data;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    borderRadius: BorderRadius.circular(14),
    child: InkWell(
      key: const Key('employee-report-month'),
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 11),
        decoration: BoxDecoration(
          border: Border.all(color: NusaColors.outline),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(
          children: [
            const Icon(
              Icons.calendar_month_rounded,
              color: NusaColors.primary,
              size: 21,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Bulan laporan',
                    style: TextStyle(
                      fontSize: 9.5,
                      color: NusaColors.textSecondary,
                    ),
                  ),
                  Text(
                    data.periodLabel,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.expand_more_rounded),
          ],
        ),
      ),
    ),
  );
}

class _PrivateNotice extends StatelessWidget {
  const _PrivateNotice();

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(13),
      border: Border.all(color: NusaColors.outline),
    ),
    child: const Row(
      children: [
        Icon(Icons.verified_user_outlined, color: NusaColors.primary, size: 19),
        SizedBox(width: 8),
        Expanded(
          child: Text(
            'Laporan dibatasi otomatis pada data presensi akun pegawai Anda.',
            style: TextStyle(color: NusaColors.textSecondary, fontSize: 10),
          ),
        ),
      ],
    ),
  );
}

class _EmployeeCard extends StatelessWidget {
  const _EmployeeCard({required this.item, required this.onTap});
  final EmployeeAttendanceReportItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    key: Key('employee-report-item-${item.employeeId}'),
    color: Colors.white,
    borderRadius: BorderRadius.circular(16),
    child: InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: NusaColors.outline),
        ),
        child: Row(
          children: [
            _Avatar(item: item),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 13,
                    ),
                  ),
                  Text(
                    item.position ?? item.employeeType ?? item.nip ?? '-',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 10,
                      color: NusaColors.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Wrap(
                    spacing: 9,
                    runSpacing: 3,
                    children: [
                      _Count(
                        label: 'H',
                        value: item.summary.present,
                        color: const Color(0xFF24954D),
                      ),
                      _Count(
                        label: 'I',
                        value: item.summary.permitted,
                        color: const Color(0xFF2B6CB0),
                      ),
                      _Count(
                        label: 'S',
                        value: item.summary.sick,
                        color: const Color(0xFFD97706),
                      ),
                      _Count(
                        label: 'A',
                        value: item.summary.absent,
                        color: const Color(0xFFC2413B),
                      ),
                      if (item.summary.late > 0)
                        _Count(
                          label: 'T',
                          value: item.summary.late,
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
                  _percent(item.summary.averageAttendance),
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

class _Avatar extends StatelessWidget {
  const _Avatar({required this.item});
  final EmployeeAttendanceReportItem item;

  @override
  Widget build(BuildContext context) => CircleAvatar(
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
  final Future<EmployeeAttendanceReportDetail> future;

  @override
  Widget build(BuildContext context) => DraggableScrollableSheet(
    expand: false,
    initialChildSize: .88,
    minChildSize: .58,
    maxChildSize: .96,
    builder: (context, controller) => FutureBuilder<EmployeeAttendanceReportDetail>(
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
            Row(
              children: [
                _Avatar(item: data.employee),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        data.employee.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontWeight: FontWeight.w900,
                          fontSize: 18,
                        ),
                      ),
                      Text(
                        '${data.employee.position ?? data.employee.employeeType ?? '-'} · ${data.periodLabel}',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10.5,
                        ),
                      ),
                    ],
                  ),
                ),
                Text(
                  _percent(data.summary.averageAttendance),
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontWeight: FontWeight.w900,
                    fontSize: 19,
                  ),
                ),
              ],
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
                  _MiniSummary(label: 'Hadir', value: data.summary.present),
                  _MiniSummary(label: 'Izin', value: data.summary.permitted),
                  _MiniSummary(label: 'Sakit', value: data.summary.sick),
                  _MiniSummary(label: 'Alfa', value: data.summary.absent),
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
  final EmployeeAttendanceReportDay day;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(day.status);
    return Container(
      margin: const EdgeInsets.only(bottom: 7),
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 4,
            height: 54,
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
                if (day.scheduleName != null)
                  Text(
                    '${day.scheduleName} · ${day.scheduledCheckIn ?? '-'}–${day.scheduledCheckOut ?? '-'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 9,
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
                if (day.description != '-' && !day.inferred)
                  Text(
                    day.description,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 9,
                      color: NusaColors.textSecondary,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 6),
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

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(
      padding: EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.description_outlined,
            size: 50,
            color: NusaColors.textSecondary,
          ),
          SizedBox(height: 10),
          Text('Tidak ada pegawai sesuai filter laporan.'),
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
      padding: const EdgeInsets.all(28),
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

String _month(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}';
String _percent(double value) =>
    '${value.toStringAsFixed(value % 1 == 0 ? 0 : 1)}%';
String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Laporan presensi pegawai belum dapat dimuat.';
Color _statusColor(String status) => switch (status) {
  'hadir' => const Color(0xFF24954D),
  'izin' => const Color(0xFF2B6CB0),
  'sakit' => const Color(0xFFD97706),
  'dinas_luar' => NusaColors.primary,
  'cuti' => const Color(0xFF0F8B8D),
  _ => const Color(0xFFC2413B),
};
