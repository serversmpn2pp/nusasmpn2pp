import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_teaching_schedule/application/my_teaching_schedule_controller.dart';
import 'package:nusa/features/my_teaching_schedule/domain/my_teaching_schedule.dart';
import 'package:nusa/features/my_teaching_schedule/presentation/widgets/my_teaching_schedule_components.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/shared/widgets/nusa_section_title.dart';

class MyTeachingScheduleView extends ConsumerWidget {
  const MyTeachingScheduleView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final schedule = ref.watch(myTeachingScheduleControllerProvider);

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Jadwal Mengajar Saya'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: schedule.isLoading
                ? null
                : ref
                      .read(myTeachingScheduleControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: schedule.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ScheduleError(
            message: _errorMessage(error),
            onRetry: ref
                .read(myTeachingScheduleControllerProvider.notifier)
                .refresh,
          ),
          data: (page) => _ScheduleContent(page: page),
        ),
      ),
    );
  }
}

class _ScheduleContent extends ConsumerStatefulWidget {
  const _ScheduleContent({required this.page});

  final MyTeachingSchedulePage page;

  @override
  ConsumerState<_ScheduleContent> createState() => _ScheduleContentState();
}

class _ScheduleContentState extends ConsumerState<_ScheduleContent> {
  late String _selectedDayCode;

  @override
  void initState() {
    super.initState();
    _selectedDayCode = _initialDay(widget.page);
  }

  @override
  void didUpdateWidget(covariant _ScheduleContent oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.page.dayByCode(_selectedDayCode) == null) {
      _selectedDayCode = _initialDay(widget.page);
    }
  }

  @override
  Widget build(BuildContext context) {
    final page = widget.page;
    final selectedDay = page.dayByCode(_selectedDayCode);

    return RefreshIndicator(
      onRefresh: ref
          .read(myTeachingScheduleControllerProvider.notifier)
          .refresh,
      child: ListView(
        key: const PageStorageKey<String>('my-teaching-schedule-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
        children: [
          TeachingScheduleSummaryCard(summary: page.summary),
          const SizedBox(height: 12),
          if (page.employee != null) ...[
            TeachingEmployeeBanner(employee: page.employee!),
            const SizedBox(height: 12),
          ],
          NusaDropdownField<int>(
            fieldKey: const Key('my-teaching-year'),
            value: page.selectedAcademicYearId,
            decoration: const InputDecoration(
              labelText: 'Tahun pelajaran',
              prefixIcon: Icon(Icons.calendar_month_rounded),
            ),
            options: [
              for (final year in page.academicYears)
                NusaDropdownOption<int>(
                  value: year.id,
                  label: '${year.name}${year.active ? ' · Aktif' : ''}',
                ),
            ],
            onChanged: (value) {
              if (value != null) {
                ref
                    .read(myTeachingScheduleControllerProvider.notifier)
                    .selectAcademicYear(value);
              }
            },
          ),
          if (page.warnings.isNotEmpty) ...[
            const SizedBox(height: 12),
            _ScheduleNotice(messages: page.warnings),
          ],
          const SizedBox(height: 20),
          NusaSectionTitle(
            title: selectedDay?.today == true
                ? 'Hari Ini · ${selectedDay!.label}'
                : 'Jadwal ${selectedDay?.label ?? ''}',
          ),
          const SizedBox(height: 9),
          TeachingDaySelector(
            days: page.days,
            selectedCode: _selectedDayCode,
            onSelected: (value) => setState(() => _selectedDayCode = value),
          ),
          const SizedBox(height: 13),
          if (selectedDay == null || selectedDay.schedules.isEmpty)
            _EmptyDay(label: selectedDay?.label ?? 'ini')
          else
            for (final item in selectedDay.schedules) ...[
              TeachingScheduleCard(schedule: item),
              const SizedBox(height: 10),
            ],
        ],
      ),
    );
  }

  String _initialDay(MyTeachingSchedulePage page) {
    if (page.dayByCode(page.todayCode) != null) return page.todayCode;
    return page.days.isEmpty ? 'senin' : page.days.first.code;
  }
}

class _ScheduleNotice extends StatelessWidget {
  const _ScheduleNotice({required this.messages});

  final List<String> messages;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: NusaColors.accent.withValues(alpha: 0.1),
      border: Border.all(color: NusaColors.accent.withValues(alpha: 0.35)),
      borderRadius: BorderRadius.circular(15),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.info_outline_rounded, size: 20),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final message in messages)
                Text(
                  message,
                  style: const TextStyle(fontSize: 12, height: 1.35),
                ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _EmptyDay extends StatelessWidget {
  const _EmptyDay({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(26),
    decoration: BoxDecoration(
      color: Colors.white,
      border: Border.all(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Column(
      children: [
        const Icon(
          Icons.event_available_outlined,
          size: 39,
          color: NusaColors.textSecondary,
        ),
        const SizedBox(height: 9),
        Text(
          'Tidak ada jadwal mengajar pada hari $label.',
          textAlign: TextAlign.center,
          style: const TextStyle(color: NusaColors.textSecondary),
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

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Jadwal mengajar belum dapat dimuat.';
