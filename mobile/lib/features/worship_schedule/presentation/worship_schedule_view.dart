import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_schedule/application/worship_schedule_controller.dart';
import 'package:nusa/features/worship_schedule/domain/worship_schedule.dart';
import 'package:nusa/features/worship_schedule/presentation/widgets/worship_schedule_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class WorshipScheduleView extends ConsumerStatefulWidget {
  const WorshipScheduleView({super.key});

  @override
  ConsumerState<WorshipScheduleView> createState() =>
      _WorshipScheduleViewState();
}

class _WorshipScheduleViewState extends ConsumerState<WorshipScheduleView> {
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final schedules = ref.watch(worshipScheduleControllerProvider);
    final page = schedules.value;
    final canCreate = page?.selectedActivity?.active == true;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text(
          'Jadwal Ibadah',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: schedules.isLoading || _mutating
                ? null
                : ref.read(worshipScheduleControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: page == null || !canCreate
          ? null
          : FloatingActionButton.extended(
              key: const Key('add-worship-schedule'),
              onPressed: _mutating ? null : () => _openForm(page),
              icon: const Icon(Icons.add_alarm_rounded),
              label: const Text('Atur'),
            ),
      body: SafeArea(
        top: false,
        child: schedules.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ScheduleError(
            message: _errorMessage(error),
            onRetry: ref
                .read(worshipScheduleControllerProvider.notifier)
                .refresh,
          ),
          data: (data) => _ScheduleContent(
            page: data,
            mutating: _mutating,
            onRefresh: ref
                .read(worshipScheduleControllerProvider.notifier)
                .refresh,
            onAcademicYearChanged: (value) => ref
                .read(worshipScheduleControllerProvider.notifier)
                .selectAcademicYear(value),
            onActivityChanged: (value) => ref
                .read(worshipScheduleControllerProvider.notifier)
                .selectActivity(value),
            onConfigureDay: (day) => _openForm(data, initialDay: day),
            onEdit: (schedule) => _openForm(data, existing: schedule),
            onDeactivate: _confirmDeactivate,
          ),
        ),
      ),
    );
  }

  Future<void> _openForm(
    WorshipSchedulePage page, {
    WorshipDay? initialDay,
    WorshipSchedule? existing,
  }) async {
    if (existing == null && page.selectedActivity?.active != true) {
      _showError('Kegiatan ibadah yang dipilih sudah tidak aktif.');
      return;
    }
    final value = await showModalBottomSheet<WorshipScheduleFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => WorshipScheduleFormSheet(
        page: page,
        initialDay: initialDay,
        existing: existing,
      ),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? '${value.days.length} jadwal berhasil diterapkan.'
          : 'Jadwal ${existing.dayLabel} berhasil diperbarui.',
      operation: existing == null
          ? () => ref.read(worshipScheduleActionsProvider).create(value)
          : () => ref
                .read(worshipScheduleActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _confirmDeactivate(WorshipSchedule item) async {
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.pause_circle_outline_rounded,
              color: NusaColors.primary,
            ),
            title: Text('Nonaktifkan jadwal ${item.dayLabel}?'),
            content: const Text(
              'Scanner tidak lagi menerima presensi dari jadwal ini. '
              'Data presensi lama tetap tersimpan.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-worship-schedule-deactivate'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    await _runMutation(
      successMessage: 'Jadwal ${item.dayLabel} berhasil dinonaktifkan.',
      operation: () =>
          ref.read(worshipScheduleActionsProvider).deactivate(item.id),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(worshipScheduleControllerProvider.notifier).refresh();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(successMessage)));
    } catch (error) {
      if (mounted) _showError(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  void _showError(Object message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message.toString())));
  }
}

class _ScheduleContent extends StatelessWidget {
  const _ScheduleContent({
    required this.page,
    required this.mutating,
    required this.onRefresh,
    required this.onAcademicYearChanged,
    required this.onActivityChanged,
    required this.onConfigureDay,
    required this.onEdit,
    required this.onDeactivate,
  });

  final WorshipSchedulePage page;
  final bool mutating;
  final Future<void> Function() onRefresh;
  final ValueChanged<int> onAcademicYearChanged;
  final ValueChanged<int> onActivityChanged;
  final ValueChanged<WorshipDay> onConfigureDay;
  final ValueChanged<WorshipSchedule> onEdit;
  final ValueChanged<WorshipSchedule> onDeactivate;

  @override
  Widget build(BuildContext context) {
    if (page.academicYears.isEmpty || page.activities.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(36),
          children: const [
            Icon(
              Icons.event_busy_outlined,
              size: 52,
              color: NusaColors.primary,
            ),
            SizedBox(height: 12),
            Text(
              'Tahun pelajaran atau kegiatan ibadah belum tersedia.',
              textAlign: TextAlign.center,
              style: TextStyle(color: NusaColors.textSecondary),
            ),
          ],
        ),
      );
    }

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
          child: Column(
            children: [
              _ScheduleSummary(page: page),
              const SizedBox(height: 9),
              NusaDropdownField<int>(
                fieldKey: const Key('worship-schedule-academic-year-filter'),
                value: page.selectedAcademicYearId,
                options: page.academicYears
                    .map(
                      (item) => NusaDropdownOption(
                        value: item.id,
                        label: '${item.name}${item.active ? ' · Aktif' : ''}',
                      ),
                    )
                    .toList(growable: false),
                decoration: const InputDecoration(
                  labelText: 'Tahun pelajaran',
                  prefixIcon: Icon(Icons.calendar_month_outlined),
                ),
                enabled: !mutating,
                onChanged: (value) {
                  if (value != null) onAcademicYearChanged(value);
                },
              ),
              const SizedBox(height: 8),
              NusaDropdownField<int>(
                fieldKey: const Key('worship-schedule-activity-filter'),
                value: page.selectedActivityId,
                options: page.activities
                    .map(
                      (item) => NusaDropdownOption(
                        value: item.id,
                        label:
                            '${item.name}${item.active ? '' : ' · Nonaktif'}',
                      ),
                    )
                    .toList(growable: false),
                decoration: const InputDecoration(
                  labelText: 'Kegiatan ibadah',
                  prefixIcon: Icon(Icons.self_improvement_rounded),
                ),
                enabled: !mutating,
                onChanged: (value) {
                  if (value != null) onActivityChanged(value);
                },
              ),
              if (page.selectedActivity?.active == false) ...[
                const SizedBox(height: 8),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: NusaColors.accent.withValues(alpha: 0.13),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Text(
                    'Kegiatan ini nonaktif. Jadwal lama masih dapat dilihat dan diubah, tetapi jadwal baru tidak dapat dibuat.',
                    style: TextStyle(fontSize: 11.5, height: 1.3),
                  ),
                ),
              ],
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: onRefresh,
            child: ListView.separated(
              key: const PageStorageKey<String>('worship-schedule-list'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 92),
              itemCount: page.days.length,
              separatorBuilder: (context, index) => const SizedBox(height: 9),
              itemBuilder: (context, index) {
                final day = page.days[index];
                final schedule = page.scheduleFor(day.code);
                return _DayCard(
                  day: day,
                  schedule: schedule,
                  canConfigure:
                      !mutating && page.selectedActivity?.active == true,
                  onConfigure: () => onConfigureDay(day),
                  onEdit: schedule == null || mutating
                      ? null
                      : () => onEdit(schedule),
                  onDeactivate: schedule == null || !schedule.active || mutating
                      ? null
                      : () => onDeactivate(schedule),
                );
              },
            ),
          ),
        ),
      ],
    );
  }
}

class _ScheduleSummary extends StatelessWidget {
  const _ScheduleSummary({required this.page});

  final WorshipSchedulePage page;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.fromLTRB(15, 13, 12, 13),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Icon(Icons.schedule_rounded, color: NusaColors.accent),
        ),
        const SizedBox(width: 11),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                page.selectedActivity?.name ?? '-',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                '${page.summary.active}/${page.summary.dayCount} hari aktif · ${page.summary.configured} sudah diatur',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.73),
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

class _DayCard extends StatelessWidget {
  const _DayCard({
    required this.day,
    required this.schedule,
    required this.canConfigure,
    required this.onConfigure,
    this.onEdit,
    this.onDeactivate,
  });

  final WorshipDay day;
  final WorshipSchedule? schedule;
  final bool canConfigure;
  final VoidCallback onConfigure;
  final VoidCallback? onEdit;
  final VoidCallback? onDeactivate;

  @override
  Widget build(BuildContext context) {
    final item = schedule;
    return Card(
      key: Key('worship-schedule-day-card-${day.code}'),
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: item == null
            ? Row(
                children: [
                  _DayIcon(label: day.label, active: false),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          day.label,
                          style: const TextStyle(
                            color: NusaColors.textPrimary,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 3),
                        const Text(
                          'Belum ada jadwal',
                          style: TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                  TextButton(
                    key: Key('configure-worship-schedule-${day.code}'),
                    onPressed: canConfigure ? onConfigure : null,
                    child: const Text('Atur'),
                  ),
                ],
              )
            : Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _DayIcon(label: day.label, active: item.active),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                day.label,
                                style: const TextStyle(
                                  color: NusaColors.textPrimary,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            _StatusBadge(active: item.active),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 7,
                          runSpacing: 6,
                          children: [
                            _TimeBadge(
                              icon: Icons.self_improvement_rounded,
                              label: 'Pelaksanaan ${item.eventTime}',
                            ),
                            _TimeBadge(
                              icon: Icons.qr_code_scanner_rounded,
                              label: '${item.scanStart}–${item.scanEnd}',
                            ),
                          ],
                        ),
                        if (item.notes?.isNotEmpty == true) ...[
                          const SizedBox(height: 7),
                          Text(
                            item.notes!,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: NusaColors.textSecondary,
                              fontSize: 11,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  PopupMenuButton<String>(
                    key: Key('worship-schedule-menu-${item.id}'),
                    tooltip: 'Aksi jadwal ${day.label}',
                    onSelected: (value) {
                      if (value == 'edit') onEdit?.call();
                      if (value == 'deactivate') onDeactivate?.call();
                    },
                    itemBuilder: (context) => [
                      PopupMenuItem(
                        value: 'edit',
                        enabled: onEdit != null,
                        child: const Text('Ubah'),
                      ),
                      if (item.active)
                        PopupMenuItem(
                          value: 'deactivate',
                          enabled: onDeactivate != null,
                          child: const Text('Nonaktifkan'),
                        ),
                    ],
                  ),
                ],
              ),
      ),
    );
  }
}

class _DayIcon extends StatelessWidget {
  const _DayIcon({required this.label, required this.active});

  final String label;
  final bool active;

  @override
  Widget build(BuildContext context) => Container(
    width: 44,
    height: 44,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: (active ? NusaColors.primary : NusaColors.textSecondary)
          .withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(13),
    ),
    child: Text(
      label.substring(0, 2).toUpperCase(),
      style: TextStyle(
        color: active ? NusaColors.primary : NusaColors.textSecondary,
        fontWeight: FontWeight.w900,
      ),
    ),
  );
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) {
    final color = active ? NusaColors.success : NusaColors.textSecondary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        active ? 'Aktif' : 'Nonaktif',
        style: TextStyle(
          color: color,
          fontSize: 9,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _TimeBadge extends StatelessWidget {
  const _TimeBadge({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 5),
    decoration: BoxDecoration(
      color: NusaColors.primary.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(9),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 13, color: NusaColors.primary),
        const SizedBox(width: 4),
        Flexible(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 9.5),
          ),
        ),
      ],
    ),
  );
}

class _ScheduleError extends StatelessWidget {
  const _ScheduleError({required this.message, required this.onRetry});

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

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Jadwal kegiatan ibadah belum dapat diproses.';
}
