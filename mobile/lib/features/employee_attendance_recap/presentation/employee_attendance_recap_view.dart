import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_attendance_recap/application/employee_attendance_recap_controller.dart';
import 'package:nusa/features/employee_attendance_recap/domain/employee_attendance_recap.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class EmployeeAttendanceRecapView extends ConsumerStatefulWidget {
  const EmployeeAttendanceRecapView({super.key});

  @override
  ConsumerState<EmployeeAttendanceRecapView> createState() =>
      _EmployeeAttendanceRecapViewState();
}

class _EmployeeAttendanceRecapViewState
    extends ConsumerState<EmployeeAttendanceRecapView> {
  final _search = TextEditingController();
  bool _mutating = false;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(employeeAttendanceRecapControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text(
          'Rekap Presensi Pegawai',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: async.isLoading
                ? null
                : () => ref
                      .read(employeeAttendanceRecapControllerProvider.notifier)
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
                .read(employeeAttendanceRecapControllerProvider.notifier)
                .refresh(),
          ),
          data: (data) => RefreshIndicator(
            onRefresh: () => ref
                .read(employeeAttendanceRecapControllerProvider.notifier)
                .refresh(),
            child: CustomScrollView(
              key: const PageStorageKey<String>(
                'employee-attendance-recap-scroll',
              ),
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
                  sliver: SliverToBoxAdapter(
                    child: Column(
                      children: [
                        _RecapHeader(data: data),
                        const SizedBox(height: 10),
                        _SummaryGrid(summary: data.summary),
                        const SizedBox(height: 10),
                        _DateFilter(data: data, onTap: () => _pickDate(data)),
                        if (!data.privateScope) ...[
                          const SizedBox(height: 9),
                          Row(
                            children: [
                              Expanded(
                                child: NusaDropdownField<String?>(
                                  fieldKey: const Key(
                                    'employee-attendance-type',
                                  ),
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
                                        employeeAttendanceRecapControllerProvider
                                            .notifier,
                                      )
                                      .filterEmployeeType(value),
                                ),
                              ),
                              const SizedBox(width: 9),
                              Expanded(
                                child: NusaDropdownField<String>(
                                  fieldKey: const Key(
                                    'employee-attendance-active-status',
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
                                            employeeAttendanceRecapControllerProvider
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
                            fieldKey: const Key('employee-attendance-employee'),
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
                                  employeeAttendanceRecapControllerProvider
                                      .notifier,
                                )
                                .filterEmployee(value),
                          ),
                          const SizedBox(height: 9),
                          NusaDropdownField<String>(
                            fieldKey: const Key('employee-attendance-status'),
                            value: data.status,
                            options: const [
                              NusaDropdownOption(
                                value: 'semua',
                                label: 'Semua status presensi',
                              ),
                              NusaDropdownOption(
                                value: 'hadir',
                                label: 'Hadir',
                              ),
                              NusaDropdownOption(
                                value: 'terlambat',
                                label: 'Terlambat',
                              ),
                              NusaDropdownOption(
                                value: 'belum_pulang',
                                label: 'Belum pulang',
                              ),
                              NusaDropdownOption(
                                value: 'pulang_cepat',
                                label: 'Pulang cepat',
                              ),
                              NusaDropdownOption(value: 'izin', label: 'Izin'),
                              NusaDropdownOption(
                                value: 'sakit',
                                label: 'Sakit',
                              ),
                              NusaDropdownOption(
                                value: 'dinas_luar',
                                label: 'Dinas luar',
                              ),
                              NusaDropdownOption(value: 'cuti', label: 'Cuti'),
                              NusaDropdownOption(
                                value: 'alfa',
                                label: 'Alfa / belum tercatat',
                              ),
                            ],
                            decoration: const InputDecoration(
                              labelText: 'Status presensi',
                              prefixIcon: Icon(Icons.filter_alt_outlined),
                            ),
                            onChanged: (value) {
                              if (value != null) {
                                ref
                                    .read(
                                      employeeAttendanceRecapControllerProvider
                                          .notifier,
                                    )
                                    .filterStatus(value);
                              }
                            },
                          ),
                          const SizedBox(height: 9),
                          TextField(
                            key: const Key('employee-attendance-search'),
                            controller: _search,
                            textInputAction: TextInputAction.search,
                            onSubmitted: (value) => ref
                                .read(
                                  employeeAttendanceRecapControllerProvider
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
                                  employeeAttendanceRecapControllerProvider
                                      .notifier,
                                )
                                .loadMore(),
                            icon: const Icon(Icons.expand_more_rounded),
                            label: const Text('Muat pegawai berikutnya'),
                          );
                        }
                        final item = data.items[index];
                        return _AttendanceCard(
                          item: item,
                          enabled: !_mutating,
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

  Future<void> _pickDate(EmployeeAttendanceRecapPage data) async {
    final current = DateTime.tryParse(data.date) ?? DateTime.now();
    final value = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime(2015),
      lastDate: DateTime.now(),
    );
    if (value != null) {
      await ref
          .read(employeeAttendanceRecapControllerProvider.notifier)
          .filterDate(_isoDate(value));
    }
  }

  Future<void> _openDetail(
    EmployeeAttendanceRecapPage page,
    EmployeeAttendanceRecord item,
  ) async {
    final detail = await showModalBottomSheet<EmployeeAttendanceDetail>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _DetailLoader(
        future: ref
            .read(employeeAttendanceRecapActionsProvider)
            .detail(employeeId: item.employeeId, date: page.date),
      ),
    );
    if (!mounted || detail == null || !detail.canCorrect) return;
    final correction =
        await showModalBottomSheet<EmployeeAttendanceCorrectionValue>(
          context: context,
          isScrollControlled: true,
          useSafeArea: true,
          builder: (_) => _CorrectionSheet(detail: detail),
        );
    if (!mounted || correction == null) return;
    setState(() => _mutating = true);
    try {
      await ref
          .read(employeeAttendanceRecapActionsProvider)
          .correct(
            employeeId: item.employeeId,
            date: page.date,
            value: correction,
          );
      if (mounted) _show('Koreksi presensi pegawai berhasil disimpan.');
    } catch (error) {
      if (mounted) _show(_errorMessage(error), error: true);
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  void _clearSearch() {
    _search.clear();
    setState(() {});
    ref.read(employeeAttendanceRecapControllerProvider.notifier).search('');
  }

  void _show(String message, {bool error = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: error ? const Color(0xFFB42318) : NusaColors.success,
        ),
      );
  }
}

class _RecapHeader extends StatelessWidget {
  const _RecapHeader({required this.data});
  final EmployeeAttendanceRecapPage data;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, Color(0xFF0B3764)],
      ),
      borderRadius: BorderRadius.circular(18),
      boxShadow: const [
        BoxShadow(
          color: Color(0x2415477A),
          blurRadius: 16,
          offset: Offset(0, 7),
        ),
      ],
    ),
    child: Row(
      children: [
        Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: .13),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: NusaColors.accent.withValues(alpha: .8)),
          ),
          child: const Icon(Icons.fact_check_rounded, color: Colors.white),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                data.privateScope ? 'Presensi Saya' : 'Rekap Harian Pegawai',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 17,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                data.dateLabel,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: .78),
                  fontSize: 10.5,
                ),
              ),
            ],
          ),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
          decoration: BoxDecoration(
            color: NusaColors.accent,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            '${data.summary.total} data',
            style: const TextStyle(
              color: NusaColors.primaryDark,
              fontSize: 10,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    ),
  );
}

class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.summary});
  final EmployeeAttendanceSummary summary;

  @override
  Widget build(BuildContext context) {
    final items = [
      ('Hadir', summary.present, NusaColors.success, Icons.how_to_reg_rounded),
      (
        'Alfa',
        summary.absent,
        const Color(0xFFB42318),
        Icons.person_off_outlined,
      ),
      (
        'Terlambat',
        summary.late,
        const Color(0xFFD97706),
        Icons.timer_outlined,
      ),
      (
        'Belum pulang',
        summary.notCheckedOut,
        NusaColors.primary,
        Icons.logout_rounded,
      ),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = (constraints.maxWidth - 9) / 2;
        return Wrap(
          spacing: 9,
          runSpacing: 9,
          children: items
              .map(
                (item) => SizedBox(
                  width: width,
                  child: _SummaryCard(
                    label: item.$1,
                    value: item.$2,
                    color: item.$3,
                    icon: item.$4,
                  ),
                ),
              )
              .toList(),
        );
      },
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
    required this.label,
    required this.value,
    required this.color,
    required this.icon,
  });
  final String label;
  final int value;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: color.withValues(alpha: .1),
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(icon, size: 18, color: color),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '$value',
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                  color: NusaColors.textPrimary,
                ),
              ),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
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

class _DateFilter extends StatelessWidget {
  const _DateFilter({required this.data, required this.onTap});
  final EmployeeAttendanceRecapPage data;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    borderRadius: BorderRadius.circular(14),
    child: InkWell(
      key: const Key('employee-attendance-date'),
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
                    'Tanggal presensi',
                    style: TextStyle(
                      fontSize: 9.5,
                      color: NusaColors.textSecondary,
                    ),
                  ),
                  Text(
                    data.dateLabel,
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
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: const Row(
      children: [
        Icon(Icons.verified_user_outlined, color: NusaColors.primary, size: 20),
        SizedBox(width: 9),
        Expanded(
          child: Text(
            'Data dibatasi otomatis pada presensi akun pegawai Anda.',
            style: TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
              height: 1.35,
            ),
          ),
        ),
      ],
    ),
  );
}

class _AttendanceCard extends StatelessWidget {
  const _AttendanceCard({
    required this.item,
    required this.enabled,
    required this.onTap,
  });
  final EmployeeAttendanceRecord item;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = employeeAttendanceStatusColor(item.status);
    return Material(
      key: Key('employee-attendance-record-${item.employeeId}'),
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: enabled ? onTap : null,
        child: Container(
          padding: const EdgeInsets.all(13),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: NusaColors.outline),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _Avatar(item: item),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            item.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 13,
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: color.withValues(alpha: .1),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            item.statusLabel,
                            style: TextStyle(
                              color: color,
                              fontSize: 9.5,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      item.position ?? item.employeeType ?? item.nip ?? '-',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                    const SizedBox(height: 9),
                    Wrap(
                      spacing: 6,
                      runSpacing: 5,
                      children: [
                        _TimeChip(
                          icon: Icons.login_rounded,
                          label: item.checkInTime ?? '-',
                          color: NusaColors.success,
                        ),
                        _TimeChip(
                          icon: Icons.logout_rounded,
                          label: item.checkOutTime ?? '-',
                          color: NusaColors.primary,
                        ),
                        if (item.lateMinutes > 0)
                          _TimeChip(
                            icon: Icons.timer_outlined,
                            label: '+${item.lateMinutes} mnt',
                            color: const Color(0xFFD97706),
                          ),
                        if (item.earlyLeaveMinutes > 0)
                          _TimeChip(
                            icon: Icons.fast_forward_rounded,
                            label: '-${item.earlyLeaveMinutes} mnt',
                            color: const Color(0xFFD97706),
                          ),
                      ],
                    ),
                    const SizedBox(height: 7),
                    Text(
                      item.sourceLabel,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 9.5,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 4),
              const Padding(
                padding: EdgeInsets.only(top: 32),
                child: Icon(
                  Icons.chevron_right_rounded,
                  color: NusaColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.item});
  final EmployeeAttendanceRecord item;

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

class _TimeChip extends StatelessWidget {
  const _TimeChip({
    required this.icon,
    required this.label,
    required this.color,
  });
  final IconData icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .08),
      borderRadius: BorderRadius.circular(8),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 12, color: color),
        const SizedBox(width: 3),
        Text(
          label,
          style: TextStyle(
            color: color,
            fontSize: 9.5,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class _DetailLoader extends StatelessWidget {
  const _DetailLoader({required this.future});
  final Future<EmployeeAttendanceDetail> future;

  @override
  Widget build(BuildContext context) => FutureBuilder<EmployeeAttendanceDetail>(
    future: future,
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const SizedBox(
          height: 280,
          child: Center(child: CircularProgressIndicator()),
        );
      }
      if (snapshot.hasError) {
        return SizedBox(
          height: 280,
          child: _ErrorState(
            message: _errorMessage(snapshot.error!),
            onRetry: () => Navigator.pop(context),
          ),
        );
      }
      return _DetailSheet(detail: snapshot.requireData);
    },
  );
}

class _DetailSheet extends StatelessWidget {
  const _DetailSheet({required this.detail});
  final EmployeeAttendanceDetail detail;

  @override
  Widget build(BuildContext context) {
    final item = detail.record;
    final color = employeeAttendanceStatusColor(item.status);
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .72,
      minChildSize: .5,
      maxChildSize: .92,
      builder: (context, controller) => Padding(
        padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
        child: Column(
          children: [
            Container(
              width: 42,
              height: 4,
              decoration: BoxDecoration(
                color: NusaColors.outline,
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            const SizedBox(height: 14),
            Row(
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
                          fontSize: 16,
                        ),
                      ),
                      Text(
                        '${detail.dateLabel} · ${item.position ?? item.employeeType ?? '-'}',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10.5,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: .1),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    item.statusLabel,
                    style: TextStyle(
                      color: color,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Expanded(
              child: ListView(
                controller: controller,
                children: [
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: NusaColors.surfaceBlue,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: NusaColors.outline),
                    ),
                    child: Column(
                      children: [
                        _DetailRow(
                          label: 'Sumber data',
                          value: item.sourceLabel,
                        ),
                        _DetailRow(
                          label: 'Jam masuk',
                          value: item.checkInTime ?? '-',
                        ),
                        _DetailRow(
                          label: 'Jam pulang',
                          value: item.checkOutTime ?? '-',
                        ),
                        _DetailRow(
                          label: 'Keterlambatan',
                          value: item.lateMinutes > 0
                              ? '${item.lateMinutes} menit'
                              : '-',
                        ),
                        _DetailRow(
                          label: 'Pulang cepat',
                          value: item.earlyLeaveMinutes > 0
                              ? '${item.earlyLeaveMinutes} menit'
                              : '-',
                        ),
                        _DetailRow(
                          label: 'Jadwal resmi',
                          value: detail.scheduleAvailable
                              ? '${detail.scheduleName ?? 'Jadwal'} · ${detail.officialCheckIn ?? '-'}–${detail.officialCheckOut ?? '-'}'
                              : 'Tidak tersedia',
                          last: item.notes == null,
                        ),
                        if (item.notes != null)
                          _DetailRow(
                            label: 'Catatan',
                            value: item.notes!,
                            last: true,
                          ),
                      ],
                    ),
                  ),
                  if (detail.privateScope) ...[
                    const SizedBox(height: 10),
                    const _PrivateNotice(),
                  ],
                ],
              ),
            ),
            if (detail.canCorrect) ...[
              const SizedBox(height: 12),
              NusaPrimaryButton(
                label: 'Koreksi Presensi',
                onPressed: () => Navigator.pop(context, detail),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.label,
    required this.value,
    this.last = false,
  });
  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 7),
    decoration: BoxDecoration(
      border: last
          ? null
          : const Border(bottom: BorderSide(color: NusaColors.outline)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 105,
          child: Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              color: NusaColors.textSecondary,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

class _CorrectionSheet extends StatefulWidget {
  const _CorrectionSheet({required this.detail});
  final EmployeeAttendanceDetail detail;

  @override
  State<_CorrectionSheet> createState() => _CorrectionSheetState();
}

class _CorrectionSheetState extends State<_CorrectionSheet> {
  String? _status;
  late final TextEditingController _checkIn;
  late final TextEditingController _checkOut;
  late final TextEditingController _notes;

  @override
  void initState() {
    super.initState();
    final current = widget.detail.record;
    _status =
        const {
          'hadir',
          'izin',
          'sakit',
          'dinas_luar',
          'cuti',
          'alfa',
        }.contains(current.status)
        ? current.status
        : null;
    _checkIn = TextEditingController(text: current.checkInTime);
    _checkOut = TextEditingController(text: current.checkOutTime);
    _notes = TextEditingController(text: current.notes);
  }

  @override
  void dispose() {
    _checkIn.dispose();
    _checkOut.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    final present = _status == 'hadir';
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 16, 20, bottom + 20),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Koreksi Presensi Pegawai',
              style: Theme.of(context).textTheme.titleLarge
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            Text(
              '${widget.detail.record.name} · ${widget.detail.dateLabel}',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 11,
              ),
            ),
            const SizedBox(height: 16),
            NusaDropdownField<String>(
              fieldKey: const Key('employee-attendance-correction-status'),
              value: _status,
              options: const [
                NusaDropdownOption(value: 'hadir', label: 'Hadir'),
                NusaDropdownOption(value: 'izin', label: 'Izin'),
                NusaDropdownOption(value: 'sakit', label: 'Sakit'),
                NusaDropdownOption(value: 'dinas_luar', label: 'Dinas luar'),
                NusaDropdownOption(value: 'cuti', label: 'Cuti'),
                NusaDropdownOption(value: 'alfa', label: 'Alfa'),
              ],
              decoration: const InputDecoration(
                labelText: 'Status kehadiran',
                prefixIcon: Icon(Icons.fact_check_outlined),
              ),
              onChanged: (value) => setState(() {
                _status = value;
                if (value != 'hadir') {
                  _checkIn.clear();
                  _checkOut.clear();
                }
              }),
            ),
            if (present) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _TimeField(
                      label: 'Jam masuk',
                      controller: _checkIn,
                      onTap: () => _pickTime(_checkIn),
                    ),
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: _TimeField(
                      label: 'Jam pulang',
                      controller: _checkOut,
                      onTap: () => _pickTime(_checkOut),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              const Text(
                'Jam masuk wajib. Jam pulang boleh kosong bila pegawai belum pulang.',
                style: TextStyle(fontSize: 10, color: NusaColors.textSecondary),
              ),
            ],
            const SizedBox(height: 12),
            TextField(
              key: const Key('employee-attendance-correction-notes'),
              controller: _notes,
              minLines: 3,
              maxLines: 5,
              decoration: const InputDecoration(
                labelText: 'Catatan koreksi (opsional)',
                hintText: 'Contoh: disesuaikan dengan catatan petugas',
              ),
            ),
            const SizedBox(height: 18),
            NusaPrimaryButton(label: 'Simpan Koreksi', onPressed: _submit),
          ],
        ),
      ),
    );
  }

  Future<void> _pickTime(TextEditingController controller) async {
    final initial = _parseTime(controller.text) ?? TimeOfDay.now();
    final value = await showTimePicker(context: context, initialTime: initial);
    if (value != null) {
      controller.text =
          '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
      setState(() {});
    }
  }

  void _submit() {
    if (_status == null) {
      _message('Pilih status kehadiran.');
      return;
    }
    if (_status == 'hadir' && _checkIn.text.isEmpty) {
      _message('Jam masuk wajib diisi untuk status hadir.');
      return;
    }
    final masuk = _parseTime(_checkIn.text);
    final pulang = _parseTime(_checkOut.text);
    if (masuk != null &&
        pulang != null &&
        pulang.hour * 60 + pulang.minute < masuk.hour * 60 + masuk.minute) {
      _message('Jam pulang tidak boleh lebih awal dari jam masuk.');
      return;
    }
    Navigator.pop(
      context,
      EmployeeAttendanceCorrectionValue(
        status: _status!,
        checkInTime: _checkIn.text.isEmpty ? null : _checkIn.text,
        checkOutTime: _checkOut.text.isEmpty ? null : _checkOut.text,
        notes: _notes.text.trim(),
      ),
    );
  }

  void _message(String value) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(value)));
}

class _TimeField extends StatelessWidget {
  const _TimeField({
    required this.label,
    required this.controller,
    required this.onTap,
  });
  final String label;
  final TextEditingController controller;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => TextField(
    controller: controller,
    readOnly: true,
    onTap: onTap,
    decoration: InputDecoration(
      labelText: label,
      prefixIcon: const Icon(Icons.schedule_rounded),
      suffixIcon: controller.text.isEmpty
          ? null
          : IconButton(
              onPressed: controller.clear,
              icon: const Icon(Icons.close_rounded),
            ),
    ),
  );
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
            Icons.event_busy_outlined,
            size: 48,
            color: NusaColors.textSecondary,
          ),
          SizedBox(height: 10),
          Text(
            'Data presensi pegawai tidak ditemukan.',
            textAlign: TextAlign.center,
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
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 46,
            color: NusaColors.textSecondary,
          ),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

TimeOfDay? _parseTime(String value) {
  final parts = value.split(':');
  if (parts.length < 2) return null;
  final hour = int.tryParse(parts[0]);
  final minute = int.tryParse(parts[1]);
  return hour == null || minute == null
      ? null
      : TimeOfDay(hour: hour, minute: minute);
}

String _isoDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Rekap presensi pegawai belum dapat dimuat.';

Color employeeAttendanceStatusColor(String status) => switch (status) {
  'hadir' => NusaColors.success,
  'izin' => const Color(0xFF7A56B3),
  'sakit' => const Color(0xFFD97706),
  'dinas_luar' => NusaColors.primary,
  'cuti' => const Color(0xFF0F8B8D),
  'alfa' => const Color(0xFFB42318),
  _ => NusaColors.textSecondary,
};
