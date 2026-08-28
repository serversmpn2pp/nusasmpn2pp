import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teacher_duty/application/teacher_duty_controller.dart';
import 'package:nusa/features/teacher_duty/domain/teacher_duty.dart';
import 'package:nusa/features/teacher_duty/presentation/widgets/duty_schedule_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class TeacherDutyScheduleView extends ConsumerStatefulWidget {
  const TeacherDutyScheduleView({super.key});
  @override
  ConsumerState<TeacherDutyScheduleView> createState() =>
      _TeacherDutyScheduleViewState();
}

class _TeacherDutyScheduleViewState
    extends ConsumerState<TeacherDutyScheduleView> {
  final _search = TextEditingController();
  bool _mutating = false;
  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(dutyScheduleControllerProvider);
    final catalog = async.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Jadwal Guru Piket'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: async.isLoading
                ? null
                : () => ref
                      .read(dutyScheduleControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: catalog?.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-duty-schedule'),
              onPressed: _mutating ? null : () => _openForm(catalog!),
              icon: const Icon(Icons.person_add_alt_1_rounded),
              label: const Text('Tambah Guru'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (catalog != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
                child: Column(
                  children: [
                    _Summary(summary: catalog.summary),
                    const SizedBox(height: 10),
                    NusaDropdownField<int>(
                      fieldKey: const Key('duty-year-filter'),
                      value: catalog.academicYearId,
                      options: catalog.academicYears
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
                        prefixIcon: Icon(Icons.calendar_month_rounded),
                      ),
                      enabled: !async.isLoading,
                      onChanged: (value) => ref
                          .read(dutyScheduleControllerProvider.notifier)
                          .filterYear(value),
                    ),
                    const SizedBox(height: 9),
                    Row(
                      children: [
                        Expanded(
                          child: NusaDropdownField<String>(
                            fieldKey: const Key('duty-day-filter'),
                            value: catalog.day,
                            options: [
                              const NusaDropdownOption(
                                value: 'semua',
                                label: 'Semua hari',
                              ),
                              ...catalog.days.map(
                                (item) => NusaDropdownOption(
                                  value: item.code,
                                  label: item.label,
                                ),
                              ),
                            ],
                            decoration: const InputDecoration(
                              labelText: 'Hari',
                            ),
                            enabled: !async.isLoading,
                            onChanged: (value) {
                              if (value != null) {
                                ref
                                    .read(
                                      dutyScheduleControllerProvider.notifier,
                                    )
                                    .filterDay(value);
                              }
                            },
                          ),
                        ),
                        const SizedBox(width: 9),
                        Expanded(
                          child: NusaDropdownField<String>(
                            fieldKey: const Key('duty-status-filter'),
                            value: catalog.status,
                            options: const [
                              NusaDropdownOption(
                                value: 'semua',
                                label: 'Semua status',
                              ),
                              NusaDropdownOption(
                                value: 'aktif',
                                label: 'Aktif',
                              ),
                              NusaDropdownOption(
                                value: 'nonaktif',
                                label: 'Nonaktif',
                              ),
                            ],
                            decoration: const InputDecoration(
                              labelText: 'Status',
                            ),
                            enabled: !async.isLoading,
                            onChanged: (value) {
                              if (value != null) {
                                ref
                                    .read(
                                      dutyScheduleControllerProvider.notifier,
                                    )
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
                          .read(dutyScheduleControllerProvider.notifier)
                          .search(value),
                      decoration: InputDecoration(
                        hintText: 'Cari nama guru atau NIP',
                        prefixIcon: const Icon(Icons.search_rounded),
                        suffixIcon: _search.text.isEmpty
                            ? null
                            : IconButton(
                                onPressed: () {
                                  _search.clear();
                                  ref
                                      .read(
                                        dutyScheduleControllerProvider.notifier,
                                      )
                                      .search('');
                                },
                                icon: const Icon(Icons.close_rounded),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: async.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => _Error(
                  message: _message(error),
                  onRetry: () => ref
                      .read(dutyScheduleControllerProvider.notifier)
                      .refresh(),
                ),
                data: (data) => data.items.isEmpty
                    ? const _Empty()
                    : RefreshIndicator(
                        onRefresh: () => ref
                            .read(dutyScheduleControllerProvider.notifier)
                            .refresh(),
                        child: ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 2, 16, 100),
                          itemCount: data.items.length,
                          separatorBuilder: (_, _) => const SizedBox(height: 9),
                          itemBuilder: (context, index) => _ScheduleCard(
                            item: data.items[index],
                            enabled: !_mutating,
                            onEdit: () =>
                                _openForm(data, existing: data.items[index]),
                            onDelete: () => _delete(data.items[index]),
                          ),
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openForm(
    DutyScheduleCatalog catalog, {
    DutySchedule? existing,
  }) async {
    try {
      final reference = await ref
          .read(teacherDutyActionsProvider)
          .reference(existing?.academicYear.id ?? catalog.academicYearId);
      if (!mounted) return;
      final value = await showModalBottomSheet<DutyScheduleFormValue>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        builder: (_) =>
            DutyScheduleFormSheet(reference: reference, existing: existing),
      );
      if (value == null || !mounted) return;
      await _run(
        () => existing == null
            ? ref.read(teacherDutyActionsProvider).create(value)
            : ref.read(teacherDutyActionsProvider).update(existing.id, value),
        existing == null
            ? 'Jadwal guru piket berhasil disimpan.'
            : 'Jadwal guru piket berhasil diperbarui.',
      );
    } catch (error) {
      _show(_message(error), error: true);
    }
  }

  Future<void> _delete(DutySchedule item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Hapus dari jadwal?'),
        content: Text(
          '${item.teacher.name} akan dikeluarkan dari piket ${item.dayLabel}.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Hapus'),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      await _run(
        () => ref.read(teacherDutyActionsProvider).delete(item.id),
        'Guru berhasil dikeluarkan dari jadwal piket.',
      );
    }
  }

  Future<void> _run(Future<void> Function() operation, String success) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(dutyScheduleControllerProvider.future);
      if (mounted) _show(success);
    } catch (error) {
      if (mounted) _show(_message(error), error: true);
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  void _show(String message, {bool error = false}) {
    if (!mounted) return;
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

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final DutyScheduleSummary summary;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        _Metric(label: 'Jadwal aktif', value: summary.activeSchedules),
        _Metric(label: 'Guru unik', value: summary.teachers),
        _Metric(label: 'Hari terisi', value: summary.filledDays),
      ],
    ),
  );
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});
  final String label;
  final int value;
  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 20,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: TextStyle(
            color: Colors.white.withValues(alpha: .7),
            fontSize: 10,
          ),
        ),
      ],
    ),
  );
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({
    required this.item,
    required this.enabled,
    required this.onEdit,
    required this.onDelete,
  });
  final DutySchedule item;
  final bool enabled;
  final VoidCallback onEdit;
  final VoidCallback onDelete;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color:
                  (item.active ? NusaColors.primary : NusaColors.textSecondary)
                      .withValues(alpha: .09),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(
              Icons.badge_rounded,
              color: item.active
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
                  item.teacher.name,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 3),
                Text(
                  '${item.dayLabel} · ${item.teacher.employeeNumber ?? 'NIP belum tersedia'}',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 11,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (item.notes?.isNotEmpty == true)
                  Padding(
                    padding: const EdgeInsets.only(top: 3),
                    child: Text(
                      item.notes!,
                      style: const TextStyle(fontSize: 11),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
            decoration: BoxDecoration(
              color:
                  (item.active ? NusaColors.success : NusaColors.textSecondary)
                      .withValues(alpha: .1),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              item.active ? 'Aktif' : 'Nonaktif',
              style: TextStyle(
                color: item.active
                    ? NusaColors.success
                    : NusaColors.textSecondary,
                fontSize: 9,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          PopupMenuButton<String>(
            enabled: enabled,
            onSelected: (value) => value == 'edit' ? onEdit() : onDelete(),
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'edit', child: Text('Ubah')),
              PopupMenuItem(value: 'delete', child: Text('Hapus')),
            ],
          ),
        ],
      ),
    ),
  );
}

class _Empty extends StatelessWidget {
  const _Empty();
  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(
      padding: EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.event_busy_rounded,
            size: 50,
            color: NusaColors.textSecondary,
          ),
          SizedBox(height: 10),
          Text(
            'Belum ada jadwal sesuai filter.',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
        ],
      ),
    ),
  );
}

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.onRetry});
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

String _message(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final values = error.errors.values.expand((e) => e);
    if (values.isNotEmpty) return values.first;
  }
  return error is AppException
      ? error.message
      : 'Data guru piket belum dapat diproses.';
}
