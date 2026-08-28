import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teacher_duty/application/teacher_duty_controller.dart';
import 'package:nusa/features/teacher_duty/domain/teacher_duty.dart';
import 'package:nusa/features/teacher_duty/presentation/widgets/duty_schedule_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class MyTeacherDutyView extends ConsumerStatefulWidget {
  const MyTeacherDutyView({super.key});
  @override
  ConsumerState<MyTeacherDutyView> createState() => _MyTeacherDutyViewState();
}

class _MyTeacherDutyViewState extends ConsumerState<MyTeacherDutyView> {
  final _search = TextEditingController();
  bool _mutating = false;
  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(myDutyControllerProvider);
    final dashboard = async.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Piket Saya & Kehadiran'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: async.isLoading
                ? null
                : () => ref.read(myDutyControllerProvider.notifier).refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (dashboard != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
                child: Column(
                  children: [
                    _DutyStatusCard(dashboard: dashboard),
                    if (dashboard.canRecordToday) ...[
                      const SizedBox(height: 10),
                      _AttendanceSummary(summary: dashboard.summary),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(
                            child: NusaDropdownField<int?>(
                              fieldKey: const Key('my-duty-class'),
                              value: dashboard.classId,
                              options: [
                                const NusaDropdownOption<int?>(
                                  value: null,
                                  label: 'Semua kelas',
                                ),
                                ...dashboard.classes.map(
                                  (item) => NusaDropdownOption<int?>(
                                    value: item.id,
                                    label: item.name,
                                  ),
                                ),
                              ],
                              decoration: const InputDecoration(
                                labelText: 'Kelas',
                              ),
                              enabled: !async.isLoading,
                              onChanged: (value) => ref
                                  .read(myDutyControllerProvider.notifier)
                                  .filterClass(value),
                            ),
                          ),
                          const SizedBox(width: 9),
                          Expanded(
                            child: NusaDropdownField<String>(
                              fieldKey: const Key('my-duty-status'),
                              value: dashboard.status,
                              options: const [
                                NusaDropdownOption(
                                  value: 'semua',
                                  label: 'Semua status',
                                ),
                                NusaDropdownOption(
                                  value: 'belum_scan',
                                  label: 'Belum scan',
                                ),
                                NusaDropdownOption(
                                  value: 'hadir',
                                  label: 'Hadir',
                                ),
                                NusaDropdownOption(
                                  value: 'sakit',
                                  label: 'Sakit',
                                ),
                                NusaDropdownOption(
                                  value: 'izin',
                                  label: 'Izin',
                                ),
                                NusaDropdownOption(
                                  value: 'alfa',
                                  label: 'Alfa',
                                ),
                              ],
                              decoration: const InputDecoration(
                                labelText: 'Status',
                              ),
                              enabled: !async.isLoading,
                              onChanged: (value) {
                                if (value != null) {
                                  ref
                                      .read(myDutyControllerProvider.notifier)
                                      .filterStatus(value);
                                }
                              },
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 9),
                      TextField(
                        controller: _search,
                        textInputAction: TextInputAction.search,
                        onSubmitted: (value) => ref
                            .read(myDutyControllerProvider.notifier)
                            .search(value),
                        decoration: const InputDecoration(
                          hintText: 'Cari nama, NIS, atau NISN',
                          prefixIcon: Icon(Icons.search_rounded),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            Expanded(
              child: async.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => _DutyError(
                  message: _message(error),
                  onRetry: () =>
                      ref.read(myDutyControllerProvider.notifier).refresh(),
                ),
                data: (data) {
                  if (!data.canRecordToday) {
                    return _NotOnDuty(
                      dashboard: data,
                      onRefresh: () =>
                          ref.read(myDutyControllerProvider.notifier).refresh(),
                    );
                  }
                  if (data.items.isEmpty) {
                    return const _NoStudents();
                  }
                  return RefreshIndicator(
                    onRefresh: () =>
                        ref.read(myDutyControllerProvider.notifier).refresh(),
                    child: ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 2, 16, 24),
                      itemCount: data.items.length + (data.hasMore ? 1 : 0),
                      separatorBuilder: (_, _) => const SizedBox(height: 8),
                      itemBuilder: (context, index) {
                        if (index == data.items.length) {
                          return OutlinedButton.icon(
                            onPressed: () => ref
                                .read(myDutyControllerProvider.notifier)
                                .loadMore(),
                            icon: const Icon(Icons.expand_more_rounded),
                            label: const Text('Muat siswa berikutnya'),
                          );
                        }
                        return _StudentCard(
                          student: data.items[index],
                          enabled: !_mutating,
                          onTap: () => _record(data.items[index]),
                        );
                      },
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _record(MyDutyStudent student) async {
    if (!student.canRecord) {
      _show(
        student.status == 'hadir'
            ? 'Siswa sudah scan masuk dan tidak dapat diubah dari menu guru piket.'
            : 'Presensi ini hanya dapat diubah melalui petugas koreksi yang berwenang.',
        error: true,
      );
      return;
    }
    final result = await showModalBottomSheet<(String, String)>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => DutyAttendanceFormSheet(student: student),
    );
    if (result == null || !mounted) return;
    setState(() => _mutating = true);
    try {
      await ref
          .read(teacherDutyActionsProvider)
          .record(
            classMemberId: student.classMemberId,
            status: result.$1,
            notes: result.$2,
          );
      await ref.read(myDutyControllerProvider.future);
      if (mounted) {
        _show(
          'Kehadiran ${student.name} berhasil dicatat sebagai ${result.$1}.',
        );
      }
    } catch (error) {
      if (mounted) _show(_message(error), error: true);
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

class _DutyStatusCard extends StatelessWidget {
  const _DutyStatusCard({required this.dashboard});
  final MyDutyDashboard dashboard;
  @override
  Widget build(BuildContext context) {
    final active = dashboard.canRecordToday;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: active
              ? const [NusaColors.primary, NusaColors.primaryDark]
              : const [Color(0xFF516579), Color(0xFF30465C)],
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
                ),
                child: Icon(
                  active ? Icons.how_to_reg_rounded : Icons.event_busy_rounded,
                  color: NusaColors.accent,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      active
                          ? 'Anda bertugas piket hari ini'
                          : 'Tidak bertugas piket hari ini',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                      ),
                    ),
                    Text(
                      '${dashboard.today.label} · ${dashboard.dateLabel}',
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
          const SizedBox(height: 11),
          Text(
            'Tahun pelajaran ${dashboard.academicYear.name}',
            style: TextStyle(
              color: Colors.white.withValues(alpha: .8),
              fontSize: 11,
            ),
          ),
          const SizedBox(height: 9),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: dashboard.mySchedules.isEmpty
                ? [const _DayChip(label: 'Belum memiliki jadwal piket')]
                : dashboard.mySchedules
                      .map(
                        (item) => _DayChip(
                          label: item.label,
                          highlighted: item.code == dashboard.today.code,
                        ),
                      )
                      .toList(),
          ),
        ],
      ),
    );
  }
}

class _DayChip extends StatelessWidget {
  const _DayChip({required this.label, this.highlighted = false});
  final String label;
  final bool highlighted;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: highlighted
          ? NusaColors.accent
          : Colors.white.withValues(alpha: .11),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(
        color: highlighted ? NusaColors.primaryDark : Colors.white,
        fontSize: 10,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _AttendanceSummary extends StatelessWidget {
  const _AttendanceSummary({required this.summary});
  final MyDutySummary summary;
  @override
  Widget build(BuildContext context) {
    final items = [
      ('Total', summary.total, NusaColors.primary),
      ('Hadir', summary.present, NusaColors.success),
      ('Sakit', summary.sick, const Color(0xFFD97706)),
      ('Izin', summary.permitted, const Color(0xFF7A56B3)),
      ('Belum', summary.notScanned, NusaColors.textSecondary),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = (constraints.maxWidth - 16) / 5;
        return Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: items
              .map(
                (item) => Container(
                  width: width,
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: Border.all(color: NusaColors.outline),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    children: [
                      Text(
                        '${item.$2}',
                        style: TextStyle(
                          color: item.$3,
                          fontWeight: FontWeight.w900,
                          fontSize: 16,
                        ),
                      ),
                      Text(
                        item.$1,
                        style: const TextStyle(
                          fontSize: 8.5,
                          color: NusaColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              )
              .toList(),
        );
      },
    );
  }
}

class _StudentCard extends StatelessWidget {
  const _StudentCard({
    required this.student,
    required this.enabled,
    required this.onTap,
  });
  final MyDutyStudent student;
  final bool enabled;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    final color = _statusColor(student.status);
    return Card(
      child: InkWell(
        onTap: enabled ? onTap : null,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              CircleAvatar(
                radius: 23,
                backgroundColor: NusaColors.surfaceBlue,
                backgroundImage: student.photoUrl == null
                    ? null
                    : NetworkImage(student.photoUrl!),
                child: student.photoUrl == null
                    ? Text(
                        student.initials,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          color: NusaColors.primary,
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
                      student.name,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${student.schoolClass} · NIS ${student.studentNumber ?? '-'}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
                    if (student.notes?.isNotEmpty == true)
                      Padding(
                        padding: const EdgeInsets.only(top: 3),
                        child: Text(
                          student.notes!,
                          style: const TextStyle(fontSize: 10),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                  ],
                ),
              ),
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
                      student.statusLabel,
                      style: TextStyle(
                        color: color,
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(height: 5),
                  Icon(
                    student.canRecord
                        ? Icons.edit_note_rounded
                        : Icons.lock_outline_rounded,
                    size: 18,
                    color: student.canRecord
                        ? NusaColors.primary
                        : NusaColors.textSecondary,
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

class _NotOnDuty extends StatelessWidget {
  const _NotOnDuty({required this.dashboard, required this.onRefresh});
  final MyDutyDashboard dashboard;
  final Future<void> Function() onRefresh;
  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const SizedBox(height: 28),
        const Icon(
          Icons.admin_panel_settings_outlined,
          size: 58,
          color: NusaColors.textSecondary,
        ),
        const SizedBox(height: 12),
        Text(
          dashboard.activeSubjectTeacher
              ? 'Pencatatan akan terbuka otomatis pada hari jadwal piket Anda.'
              : 'Akun ini belum menjadi guru pengampu aktif pada tahun pelajaran berjalan.',
          textAlign: TextAlign.center,
          style: const TextStyle(color: NusaColors.textSecondary, height: 1.45),
        ),
        const SizedBox(height: 12),
        const Text(
          'Presensi tetap diproses oleh mesin scanner di sekolah. Menu ini hanya untuk mencatat siswa sakit atau izin.',
          textAlign: TextAlign.center,
          style: TextStyle(fontSize: 11, color: NusaColors.textSecondary),
        ),
      ],
    ),
  );
}

class _NoStudents extends StatelessWidget {
  const _NoStudents();
  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(
      padding: EdgeInsets.all(24),
      child: Text(
        'Tidak ada siswa sesuai filter.',
        style: TextStyle(fontWeight: FontWeight.w700),
      ),
    ),
  );
}

class _DutyError extends StatelessWidget {
  const _DutyError({required this.message, required this.onRetry});
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

Color _statusColor(String status) => switch (status) {
  'hadir' => NusaColors.success,
  'sakit' => const Color(0xFFD97706),
  'izin' => const Color(0xFF7A56B3),
  'alfa' => const Color(0xFFB42318),
  _ => NusaColors.textSecondary,
};
String _message(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final values = error.errors.values.expand((e) => e);
    if (values.isNotEmpty) return values.first;
  }
  return error is AppException
      ? error.message
      : 'Data piket belum dapat diproses.';
}
