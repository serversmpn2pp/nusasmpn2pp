import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/lesson_period/application/lesson_period_controller.dart';
import 'package:nusa/features/lesson_period/domain/lesson_period.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class LessonPeriodView extends ConsumerStatefulWidget {
  const LessonPeriodView({super.key});

  @override
  ConsumerState<LessonPeriodView> createState() => _LessonPeriodViewState();
}

class _LessonPeriodViewState extends ConsumerState<LessonPeriodView> {
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final periods = ref.watch(lessonPeriodControllerProvider);
    final current = periods.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Jam Pelajaran'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: periods.isLoading
                ? null
                : () => ref
                      .read(lessonPeriodControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current == null
          ? null
          : FloatingActionButton.extended(
              key: const Key('add-lesson-period'),
              onPressed: _mutating ? null : () => _create(current),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah Slot'),
            ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (current != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
                child: Column(
                  children: [
                    _PeriodSummary(counts: current.counts),
                    const SizedBox(height: 10),
                    _DayFilter(
                      days: current.days,
                      selected: current.selectedDay,
                      enabled: !periods.isLoading,
                      onSelected: (value) => ref
                          .read(lessonPeriodControllerProvider.notifier)
                          .filterDay(value),
                    ),
                    const SizedBox(height: 8),
                    _StatusFilter(
                      selected: current.status,
                      enabled: !periods.isLoading,
                      onSelected: (value) => ref
                          .read(lessonPeriodControllerProvider.notifier)
                          .filterStatus(value),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: periods.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _PeriodError(
                  message: _errorMessage(error),
                  onRetry: () => ref
                      .read(lessonPeriodControllerProvider.notifier)
                      .refresh(),
                ),
                data: (catalog) => _PeriodResults(
                  catalog: catalog,
                  mutating: _mutating,
                  onRefresh: () => ref
                      .read(lessonPeriodControllerProvider.notifier)
                      .refresh(),
                  onEdit: (period) => _edit(catalog, period),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _create(LessonPeriodCatalog catalog) async {
    final value = await showModalBottomSheet<_PeriodFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _PeriodFormSheet(
        title: 'Tambah Jam Pelajaran',
        days: catalog.days,
        types: catalog.types,
      ),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: 'Slot jam pelajaran berhasil ditambahkan.',
      operation: () => ref
          .read(lessonPeriodActionsProvider)
          .create(
            days: value.days,
            insertionPosition: value.insertionPosition,
            label: value.label,
            startTime: value.startTime,
            endTime: value.endTime,
            type: value.type,
            active: value.active,
            notes: value.notes,
          ),
    );
  }

  Future<void> _edit(LessonPeriodCatalog catalog, LessonPeriod period) async {
    final value = await showModalBottomSheet<_PeriodFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _PeriodFormSheet(
        title: 'Ubah ${period.displayLabel}',
        days: catalog.days,
        types: catalog.types,
        existing: period,
      ),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: '${period.displayLabel} berhasil diperbarui.',
      operation: () => ref
          .read(lessonPeriodActionsProvider)
          .update(
            id: period.id,
            label: value.label,
            startTime: value.startTime,
            endTime: value.endTime,
            type: value.type,
            active: value.active,
            notes: value.notes,
          ),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(lessonPeriodControllerProvider.future);
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(successMessage)));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }
}

class _PeriodSummary extends StatelessWidget {
  const _PeriodSummary({required this.counts});

  final LessonPeriodCounts counts;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        for (final item in [
          ('Total Slot', counts.total),
          ('Aktif', counts.active),
          ('Nonaktif', counts.inactive),
        ])
          Expanded(
            child: Column(
              children: [
                Text(
                  '${item.$2}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  item.$1,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.76),
                    fontSize: 10,
                  ),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}

class _DayFilter extends StatelessWidget {
  const _DayFilter({
    required this.days,
    required this.selected,
    required this.enabled,
    required this.onSelected,
  });

  final List<CodeLabel> days;
  final String selected;
  final bool enabled;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    scrollDirection: Axis.horizontal,
    child: Row(
      children: [
        for (final item in [
          const CodeLabel(code: 'semua', label: 'Semua'),
          ...days,
        ]) ...[
          ChoiceChip(
            key: Key('lesson-period-day-${item.code}'),
            label: Text(item.label),
            selected: selected == item.code,
            showCheckmark: false,
            onSelected: enabled ? (_) => onSelected(item.code) : null,
          ),
          const SizedBox(width: 7),
        ],
      ],
    ),
  );
}

class _StatusFilter extends StatelessWidget {
  const _StatusFilter({
    required this.selected,
    required this.enabled,
    required this.onSelected,
  });

  final String selected;
  final bool enabled;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      for (final item in const [
        ('semua', 'Semua'),
        ('aktif', 'Aktif'),
        ('nonaktif', 'Nonaktif'),
      ])
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(right: item.$1 == 'nonaktif' ? 0 : 7),
            child: FilterChip(
              label: SizedBox(
                width: double.infinity,
                child: Text(item.$2, textAlign: TextAlign.center),
              ),
              selected: selected == item.$1,
              showCheckmark: false,
              onSelected: enabled ? (_) => onSelected(item.$1) : null,
            ),
          ),
        ),
    ],
  );
}

class _PeriodResults extends StatelessWidget {
  const _PeriodResults({
    required this.catalog,
    required this.mutating,
    required this.onRefresh,
    required this.onEdit,
  });

  final LessonPeriodCatalog catalog;
  final bool mutating;
  final Future<void> Function() onRefresh;
  final ValueChanged<LessonPeriod> onEdit;

  @override
  Widget build(BuildContext context) {
    if (catalog.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(48),
          children: const [
            Icon(Icons.schedule_rounded, size: 52, color: NusaColors.primary),
            SizedBox(height: 12),
            Text(
              'Belum ada jam pelajaran pada filter ini.',
              textAlign: TextAlign.center,
              style: TextStyle(color: NusaColors.textSecondary),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        key: const PageStorageKey<String>('lesson-period-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 92),
        itemCount: catalog.items.length,
        separatorBuilder: (context, index) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final period = catalog.items[index];
          final showDay =
              index == 0 || catalog.items[index - 1].day != period.day;
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (showDay) ...[
                Padding(
                  padding: EdgeInsets.only(top: index == 0 ? 0 : 8, bottom: 7),
                  child: Text(
                    period.dayLabel,
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
              _PeriodCard(
                period: period,
                onTap: mutating ? null : () => onEdit(period),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _PeriodCard extends StatelessWidget {
  const _PeriodCard({required this.period, this.onTap});

  final LessonPeriod period;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final special = period.type != 'pelajaran';
    return Material(
      key: Key('lesson-period-${period.id}'),
      color: special ? NusaColors.accent.withValues(alpha: 0.06) : Colors.white,
      borderRadius: BorderRadius.circular(15),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(15),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            border: Border.all(color: NusaColors.outline),
            borderRadius: BorderRadius.circular(15),
          ),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: special
                      ? NusaColors.accent.withValues(alpha: 0.16)
                      : NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  special ? Icons.coffee_rounded : Icons.menu_book_rounded,
                  color: special ? const Color(0xFFC39100) : NusaColors.primary,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      period.displayLabel,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${period.timeLabel} • ${period.typeLabel}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                    if (period.activeScheduleCount > 0)
                      Text(
                        'Dipakai ${period.activeScheduleCount} jadwal aktif',
                        style: const TextStyle(
                          color: NusaColors.primary,
                          fontSize: 9.5,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                  ],
                ),
              ),
              _StatusBadge(active: period.active),
              const SizedBox(width: 6),
              const Icon(
                Icons.edit_outlined,
                size: 18,
                color: NusaColors.primary,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: (active ? NusaColors.success : NusaColors.textSecondary)
          .withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      active ? 'Aktif' : 'Nonaktif',
      style: TextStyle(
        color: active ? NusaColors.success : NusaColors.textSecondary,
        fontSize: 9,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _PeriodFormValue {
  const _PeriodFormValue({
    required this.days,
    required this.insertionPosition,
    required this.label,
    required this.startTime,
    required this.endTime,
    required this.type,
    required this.active,
    this.notes,
  });

  final List<String> days;
  final String insertionPosition;
  final String? label;
  final String startTime;
  final String endTime;
  final String type;
  final bool active;
  final String? notes;
}

class _PeriodFormSheet extends StatefulWidget {
  const _PeriodFormSheet({
    required this.title,
    required this.days,
    required this.types,
    this.existing,
  });

  final String title;
  final List<CodeLabel> days;
  final List<CodeLabel> types;
  final LessonPeriod? existing;

  @override
  State<_PeriodFormSheet> createState() => _PeriodFormSheetState();
}

class _PeriodFormSheetState extends State<_PeriodFormSheet> {
  late final TextEditingController _labelController;
  late final TextEditingController _notesController;
  late Set<String> _days;
  late TimeOfDay _start;
  late TimeOfDay _end;
  late String _type;
  String _position = 'akhir';
  late bool _active;
  String? _error;

  bool get _editing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _labelController = TextEditingController(text: existing?.label);
    _notesController = TextEditingController(text: existing?.notes);
    _days = existing == null
        ? {widget.days.firstOrNull?.code ?? 'senin'}
        : {existing.day};
    _start = _parseTime(existing?.startTime ?? '07:00');
    _end = _parseTime(existing?.endTime ?? '07:40');
    _type = existing?.type ?? 'pelajaran';
    _active = existing?.active ?? true;
  }

  @override
  void dispose() {
    _labelController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.88,
      child: Column(
        children: [
          const SizedBox(height: 10),
          Container(
            width: 42,
            height: 4,
            decoration: BoxDecoration(
              color: NusaColors.outline,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 8, 10),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    widget.title,
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (!_editing) ...[
                  const _FormLabel('Terapkan pada hari'),
                  const SizedBox(height: 7),
                  Wrap(
                    spacing: 7,
                    runSpacing: 7,
                    children: [
                      for (final day in widget.days.where(
                        (item) => item.code != 'minggu',
                      ))
                        FilterChip(
                          key: Key('period-form-day-${day.code}'),
                          label: Text(day.label),
                          selected: _days.contains(day.code),
                          onSelected: (selected) => setState(() {
                            if (selected) {
                              _days.add(day.code);
                            } else if (_days.length > 1) {
                              _days.remove(day.code);
                            }
                          }),
                        ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  NusaDropdownField<String>(
                    fieldKey: const Key('period-form-position'),
                    value: _position,
                    decoration: const InputDecoration(
                      labelText: 'Posisi slot',
                      prefixIcon: Icon(Icons.low_priority_rounded),
                    ),
                    options: const [
                      NusaDropdownOption(
                        value: 'akhir',
                        label: 'Di akhir hari',
                      ),
                      NusaDropdownOption(value: 'awal', label: 'Di awal hari'),
                    ],
                    onChanged: (value) => _position = value ?? 'akhir',
                  ),
                  const SizedBox(height: 12),
                ],
                TextField(
                  key: const Key('period-form-label'),
                  controller: _labelController,
                  maxLength: 100,
                  decoration: const InputDecoration(
                    labelText: 'Label slot',
                    hintText: 'Contoh: Jam ke-1 atau Istirahat',
                    prefixIcon: Icon(Icons.label_outline_rounded),
                  ),
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Expanded(
                      child: _TimeField(
                        label: 'Mulai',
                        value: _start,
                        onTap: () => _pickTime(true),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _TimeField(
                        label: 'Selesai',
                        value: _end,
                        onTap: () => _pickTime(false),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                NusaDropdownField<String>(
                  fieldKey: const Key('period-form-type'),
                  value: _type,
                  decoration: const InputDecoration(
                    labelText: 'Jenis slot',
                    prefixIcon: Icon(Icons.category_outlined),
                  ),
                  options: [
                    for (final type in widget.types)
                      NusaDropdownOption(value: type.code, label: type.label),
                  ],
                  onChanged: (value) => setState(() => _type = value ?? _type),
                ),
                const SizedBox(height: 10),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Slot aktif'),
                  subtitle: const Text(
                    'Slot aktif ditampilkan dalam susunan jadwal.',
                  ),
                  value: _active,
                  onChanged: (value) => setState(() => _active = value),
                ),
                TextField(
                  controller: _notesController,
                  minLines: 2,
                  maxLines: 3,
                  maxLength: 1000,
                  decoration: const InputDecoration(
                    labelText: 'Keterangan (opsional)',
                    prefixIcon: Icon(Icons.notes_rounded),
                    alignLabelWithHint: true,
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 6),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('save-lesson-period'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Tambah Slot'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  Future<void> _pickTime(bool start) async {
    final value = await showTimePicker(
      context: context,
      initialTime: start ? _start : _end,
      helpText: start ? 'Pilih jam mulai' : 'Pilih jam selesai',
    );
    if (value == null || !mounted) return;
    setState(() => start ? _start = value : _end = value);
  }

  void _submit() {
    final startMinutes = _start.hour * 60 + _start.minute;
    final endMinutes = _end.hour * 60 + _end.minute;
    if (endMinutes <= startMinutes) {
      setState(() => _error = 'Jam selesai harus lebih besar dari jam mulai.');
      return;
    }

    Navigator.pop(
      context,
      _PeriodFormValue(
        days: _days.toList(growable: false),
        insertionPosition: _position,
        label: _labelController.text.trim().isEmpty
            ? null
            : _labelController.text.trim(),
        startTime: _formatTime(_start),
        endTime: _formatTime(_end),
        type: _type,
        active: _active,
        notes: _notesController.text.trim().isEmpty
            ? null
            : _notesController.text.trim(),
      ),
    );
  }
}

class _FormLabel extends StatelessWidget {
  const _FormLabel(this.value);
  final String value;

  @override
  Widget build(BuildContext context) => Text(
    value,
    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
  );
}

class _TimeField extends StatelessWidget {
  const _TimeField({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final TimeOfDay value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(14),
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: const Icon(Icons.schedule_rounded),
      ),
      child: Text(
        _formatTime(value),
        style: const TextStyle(fontWeight: FontWeight.w700),
      ),
    ),
  );
}

class _PeriodError extends StatelessWidget {
  const _PeriodError({required this.message, required this.onRetry});

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

TimeOfDay _parseTime(String value) {
  final parts = value.split(':');
  return TimeOfDay(
    hour: int.tryParse(parts.firstOrNull ?? '') ?? 7,
    minute: int.tryParse(parts.elementAtOrNull(1) ?? '') ?? 0,
  );
}

String _formatTime(TimeOfDay value) =>
    '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Jam pelajaran belum dapat diproses.';
