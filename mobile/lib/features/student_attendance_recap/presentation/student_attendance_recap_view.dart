import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_attendance_recap/application/student_attendance_recap_controller.dart';
import 'package:nusa/features/student_attendance_recap/domain/student_attendance_recap.dart';
import 'package:nusa/features/student_attendance_recap/presentation/widgets/student_attendance_recap_sheets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentAttendanceRecapView extends ConsumerStatefulWidget {
  const StudentAttendanceRecapView({super.key});
  @override
  ConsumerState<StudentAttendanceRecapView> createState() =>
      _StudentAttendanceRecapViewState();
}

class _StudentAttendanceRecapViewState
    extends ConsumerState<StudentAttendanceRecapView> {
  final _search = TextEditingController();
  bool _mutating = false;
  bool _loadingWhatsApp = false;
  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(studentAttendanceRecapControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text(
          'Rekap & Koreksi Presensi',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          if (async.hasValue)
            IconButton(
              key: const Key('attendance-whatsapp-button'),
              tooltip: async.requireValue.canCopyWhatsApp
                  ? 'Pesan WA grup orang tua'
                  : 'Pilih kelas terlebih dahulu',
              onPressed: _loadingWhatsApp
                  ? null
                  : () => _openWhatsApp(async.requireValue),
              icon: _loadingWhatsApp
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Icon(
                      Icons.forum_rounded,
                      color: async.requireValue.canCopyWhatsApp
                          ? NusaColors.success
                          : NusaColors.textSecondary,
                    ),
            ),
          IconButton(
            tooltip: 'Perbarui',
            onPressed: async.isLoading
                ? null
                : () => ref
                      .read(studentAttendanceRecapControllerProvider.notifier)
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
                .read(studentAttendanceRecapControllerProvider.notifier)
                .refresh(),
          ),
          data: (data) => RefreshIndicator(
            onRefresh: () => ref
                .read(studentAttendanceRecapControllerProvider.notifier)
                .refresh(),
            child: CustomScrollView(
              slivers: [
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
                  sliver: SliverToBoxAdapter(
                    child: Column(
                      children: [
                        _RecapHeader(data: data),
                        const SizedBox(height: 10),
                        _DateFilter(data: data, onTap: () => _pickDate(data)),
                        const SizedBox(height: 9),
                        Row(
                          children: [
                            Expanded(
                              child: NusaDropdownField<int>(
                                fieldKey: const Key('attendance-recap-year'),
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
                                onChanged: (value) => ref
                                    .read(
                                      studentAttendanceRecapControllerProvider
                                          .notifier,
                                    )
                                    .filterYear(value),
                              ),
                            ),
                            const SizedBox(width: 9),
                            Expanded(
                              child: NusaDropdownField<int?>(
                                fieldKey: const Key('attendance-recap-class'),
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
                                decoration: const InputDecoration(
                                  labelText: 'Kelas',
                                ),
                                onChanged: (value) => ref
                                    .read(
                                      studentAttendanceRecapControllerProvider
                                          .notifier,
                                    )
                                    .filterClass(value),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 9),
                        NusaDropdownField<String>(
                          fieldKey: const Key('attendance-recap-status'),
                          value: data.status,
                          options: const [
                            NusaDropdownOption(
                              value: 'semua',
                              label: 'Semua status',
                            ),
                            NusaDropdownOption(value: 'hadir', label: 'Hadir'),
                            NusaDropdownOption(
                              value: 'belum_scan',
                              label: 'Belum scan',
                            ),
                            NusaDropdownOption(
                              value: 'terlambat',
                              label: 'Terlambat',
                            ),
                            NusaDropdownOption(value: 'sakit', label: 'Sakit'),
                            NusaDropdownOption(value: 'izin', label: 'Izin'),
                            NusaDropdownOption(value: 'alfa', label: 'Alfa'),
                            NusaDropdownOption(
                              value: 'belum_pulang',
                              label: 'Belum pulang',
                            ),
                            NusaDropdownOption(
                              value: 'pulang_cepat',
                              label: 'Pulang cepat',
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
                                    studentAttendanceRecapControllerProvider
                                        .notifier,
                                  )
                                  .filterStatus(value);
                            }
                          },
                        ),
                        const SizedBox(height: 9),
                        TextField(
                          key: const Key('attendance-recap-search'),
                          controller: _search,
                          textInputAction: TextInputAction.search,
                          onSubmitted: (value) => ref
                              .read(
                                studentAttendanceRecapControllerProvider
                                    .notifier,
                              )
                              .search(value),
                          decoration: const InputDecoration(
                            hintText: 'Cari nama, NIS, atau NISN',
                            prefixIcon: Icon(Icons.search_rounded),
                          ),
                        ),
                        if (data.todayOnly)
                          const Padding(
                            padding: EdgeInsets.only(top: 9),
                            child: _AccessNotice(),
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
                                  studentAttendanceRecapControllerProvider
                                      .notifier,
                                )
                                .loadMore(),
                            icon: const Icon(Icons.expand_more_rounded),
                            label: const Text('Muat siswa berikutnya'),
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

  Future<void> _pickDate(StudentAttendanceRecapPage data) async {
    if (data.todayOnly) {
      _show('Akun Guru PL hanya dapat membuka presensi hari ini.');
      return;
    }
    final current = DateTime.tryParse(data.date) ?? DateTime.now();
    final value = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime(2015),
      lastDate: DateTime.now(),
    );
    if (value != null) {
      await ref
          .read(studentAttendanceRecapControllerProvider.notifier)
          .filterDate(_isoDate(value));
    }
  }

  Future<void> _openWhatsApp(StudentAttendanceRecapPage page) async {
    if (!page.canCopyWhatsApp) {
      _show('Pilih satu kelas terlebih dahulu untuk membuat pesan WA grup.');
      return;
    }
    setState(() => _loadingWhatsApp = true);
    try {
      final data = await ref
          .read(studentAttendanceRecapActionsProvider)
          .whatsAppMessage(
            date: page.date,
            academicYearId: page.academicYearId,
            classId: page.classId,
          );
      if (!mounted) return;
      setState(() => _loadingWhatsApp = false);
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        builder: (_) => StudentAttendanceWhatsAppSheet(data: data),
      );
    } catch (error) {
      if (mounted) {
        setState(() => _loadingWhatsApp = false);
        _show(_errorMessage(error), error: true);
      }
    }
  }

  Future<void> _openDetail(
    StudentAttendanceRecapPage page,
    StudentAttendanceRecord item,
  ) async {
    final detail = await showModalBottomSheet<StudentAttendanceDetail>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => StudentAttendanceDetailSheet(
        future: ref
            .read(studentAttendanceRecapActionsProvider)
            .detail(classMemberId: item.classMemberId, date: page.date),
      ),
    );
    if (detail == null || !mounted) return;
    final correction = await showModalBottomSheet<AttendanceCorrectionValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => StudentAttendanceCorrectionSheet(detail: detail),
    );
    if (correction == null || !mounted) return;
    setState(() => _mutating = true);
    try {
      await ref
          .read(studentAttendanceRecapActionsProvider)
          .correct(
            classMemberId: item.classMemberId,
            date: page.date,
            value: correction,
          );
      await ref.read(studentAttendanceRecapControllerProvider.future);
      if (mounted) _show('Koreksi presensi ${item.name} berhasil disimpan.');
    } catch (error) {
      if (mounted) _show(_errorMessage(error), error: true);
    } finally {
      if (mounted) setState(() => _mutating = false);
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

class _RecapHeader extends StatelessWidget {
  const _RecapHeader({required this.data});
  final StudentAttendanceRecapPage data;
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
        Row(
          children: [
            const Icon(Icons.fact_check_rounded, color: NusaColors.accent),
            const SizedBox(width: 9),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Rekap Presensi Siswa',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                    ),
                  ),
                  Text(
                    data.dateLabel,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: .72),
                      fontSize: 10.5,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        _SummaryGrid(summary: data.summary),
      ],
    ),
  );
}

class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.summary});
  final StudentAttendanceSummary summary;
  @override
  Widget build(BuildContext context) {
    final items = [
      ('Total', summary.total),
      ('Hadir', summary.present),
      ('Sakit', summary.sick),
      ('Izin', summary.permitted),
      ('Alfa', summary.absent),
      ('Belum scan', summary.notScanned),
      ('Terlambat', summary.late),
      ('Belum pulang', summary.notCheckedOut),
    ];
    return Wrap(
      spacing: 6,
      runSpacing: 6,
      children: items
          .map(
            (item) => Container(
              width: (MediaQuery.sizeOf(context).width - 68) / 4,
              padding: const EdgeInsets.symmetric(vertical: 7),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: .1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Column(
                children: [
                  Text(
                    '${item.$2}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                      fontSize: 15,
                    ),
                  ),
                  Text(
                    item.$1,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: .7),
                      fontSize: 8,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          )
          .toList(),
    );
  }
}

class _DateFilter extends StatelessWidget {
  const _DateFilter({required this.data, required this.onTap});
  final StudentAttendanceRecapPage data;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    child: InkWell(
      key: const Key('attendance-recap-date'),
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: InputDecorator(
        decoration: const InputDecoration(
          labelText: 'Tanggal presensi',
          prefixIcon: Icon(Icons.event_rounded),
          suffixIcon: Icon(Icons.calendar_month_rounded),
        ),
        child: Text(
          data.dateLabel,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ),
    ),
  );
}

class _AccessNotice extends StatelessWidget {
  const _AccessNotice();
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
            'Akses Guru PL: hanya hari ini dan hasil scan tidak dapat dikoreksi.',
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

class _AttendanceCard extends StatelessWidget {
  const _AttendanceCard({
    required this.item,
    required this.enabled,
    required this.onTap,
  });
  final StudentAttendanceRecord item;
  final bool enabled;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    final color = attendanceStatusColor(item.status);
    return Card(
      child: InkWell(
        key: Key('attendance-record-${item.classMemberId}'),
        onTap: enabled ? onTap : null,
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
                    const SizedBox(height: 3),
                    Text(
                      '${item.className} · NIS ${item.studentNumber ?? '-'}',
                      style: const TextStyle(
                        fontSize: 10.5,
                        color: NusaColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Wrap(
                      spacing: 7,
                      children: [
                        Text(
                          'Masuk ${item.checkInTime ?? '-'}',
                          style: const TextStyle(fontSize: 10),
                        ),
                        Text(
                          'Pulang ${item.checkOutTime ?? '-'}',
                          style: const TextStyle(fontSize: 10),
                        ),
                        if (item.lateMinutes > 0)
                          Text(
                            '+${item.lateMinutes} menit',
                            style: const TextStyle(
                              fontSize: 10,
                              color: Color(0xFFD97706),
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
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
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(height: 5),
                  Row(
                    children: [
                      Icon(
                        item.correction.allowed
                            ? Icons.edit_note_rounded
                            : Icons.visibility_outlined,
                        size: 16,
                        color: item.correction.allowed
                            ? NusaColors.primary
                            : NusaColors.textSecondary,
                      ),
                      const SizedBox(width: 3),
                      Text(
                        item.sourceLabel,
                        style: const TextStyle(
                          fontSize: 8.5,
                          color: NusaColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
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
            Icons.event_busy_rounded,
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

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((value) => value);
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Rekap presensi belum dapat diproses.';
}

String _isoDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
