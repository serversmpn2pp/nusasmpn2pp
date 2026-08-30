import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/violation_process_deadline/application/violation_process_deadline_controller.dart';
import 'package:nusa/features/violation_process_deadline/domain/violation_process_deadline.dart';
import 'package:nusa/features/violation_process_deadline/presentation/widgets/violation_process_deadline_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class ViolationProcessDeadlineView extends ConsumerStatefulWidget {
  const ViolationProcessDeadlineView({super.key});

  @override
  ConsumerState<ViolationProcessDeadlineView> createState() =>
      _ViolationProcessDeadlineViewState();
}

class _ViolationProcessDeadlineViewState
    extends ConsumerState<ViolationProcessDeadlineView> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  bool _saving = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final deadlines = ref.watch(violationProcessDeadlineControllerProvider);
    final current = deadlines.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Batas Proses Pelanggaran'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: deadlines.isLoading || _saving
                ? null
                : ref
                      .read(violationProcessDeadlineControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (current != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Column(
                  children: [
                    _DeadlineSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('violation-deadline-search'),
                      controller: _searchController,
                      hintText: 'Cari tahun pelajaran',
                      prefixIcon: Icons.search_rounded,
                      enabled: !deadlines.isLoading && !_saving,
                      onChanged: _search,
                      suffixIcon: _searchController.text.isEmpty
                          ? null
                          : IconButton(
                              onPressed: _clearSearch,
                              icon: const Icon(Icons.close_rounded),
                            ),
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<String>(
                      fieldKey: const Key('violation-deadline-status-filter'),
                      value: current.status,
                      options: const [
                        NusaDropdownOption(
                          value: 'semua',
                          label: 'Semua pengaturan',
                        ),
                        NusaDropdownOption(
                          value: 'diatur',
                          label: 'Sudah diatur',
                        ),
                        NusaDropdownOption(
                          value: 'bawaan',
                          label: 'Memakai nilai bawaan',
                        ),
                      ],
                      decoration: const InputDecoration(
                        labelText: 'Status pengaturan',
                        prefixIcon: Icon(Icons.tune_rounded),
                      ),
                      enabled: !deadlines.isLoading && !_saving,
                      onChanged: (value) {
                        if (value != null) {
                          ref
                              .read(
                                violationProcessDeadlineControllerProvider
                                    .notifier,
                              )
                              .filterStatus(value);
                        }
                      },
                    ),
                  ],
                ),
              ),
            Expanded(
              child: deadlines.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _DeadlineError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(violationProcessDeadlineControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _DeadlineResults(
                  page: page,
                  saving: _saving,
                  onRefresh: ref
                      .read(violationProcessDeadlineControllerProvider.notifier)
                      .refresh,
                  onConfigure: _openEditor,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _search(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) {
        ref
            .read(violationProcessDeadlineControllerProvider.notifier)
            .search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(violationProcessDeadlineControllerProvider.notifier).search('');
  }

  Future<void> _openEditor(ViolationProcessDeadline deadline) async {
    final value = await showModalBottomSheet<ViolationProcessDeadlineFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          ViolationProcessDeadlineFormSheet(deadline: deadline),
    );
    if (value == null || !mounted) return;

    setState(() => _saving = true);
    try {
      await ref
          .read(violationProcessDeadlineActionsProvider)
          .update(academicYearId: deadline.academicYear.id, value: value);
      await ref
          .read(violationProcessDeadlineControllerProvider.notifier)
          .refresh();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            content: Text(
              'Batas proses ${deadline.academicYear.name} berhasil disimpan.',
            ),
          ),
        );
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _DeadlineSummary extends StatelessWidget {
  const _DeadlineSummary({required this.summary});

  final ViolationProcessDeadlineSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        _SummaryItem(label: 'Tahun', value: summary.academicYearCount),
        _SummaryItem(label: 'Diatur', value: summary.configuredCount),
        _SummaryItem(label: 'Bawaan', value: summary.defaultCount),
        _SummaryItem(label: 'Pengingat', value: summary.reminderActiveCount),
      ],
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({required this.label, required this.value});

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
            fontSize: 19,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.72),
            fontSize: 9,
          ),
        ),
      ],
    ),
  );
}

class _DeadlineResults extends StatelessWidget {
  const _DeadlineResults({
    required this.page,
    required this.saving,
    required this.onRefresh,
    required this.onConfigure,
  });

  final ViolationProcessDeadlinePage page;
  final bool saving;
  final Future<void> Function() onRefresh;
  final ValueChanged<ViolationProcessDeadline> onConfigure;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(42),
          children: const [
            Icon(Icons.timer_off_outlined, size: 50, color: NusaColors.primary),
            SizedBox(height: 12),
            Text(
              'Belum ada tahun pelajaran pada filter ini.',
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
        key: const PageStorageKey<String>('violation-process-deadline-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 3, 16, 32),
        itemCount: page.items.length + 1,
        separatorBuilder: (context, index) => const SizedBox(height: 9),
        itemBuilder: (context, index) {
          if (index == 0) {
            return Container(
              padding: const EdgeInsets.all(11),
              decoration: BoxDecoration(
                color: NusaColors.accent.withValues(alpha: 0.13),
                borderRadius: BorderRadius.circular(13),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.lock_clock_outlined, size: 18),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Tenggat disalin saat laporan memasuki tahap. Perubahan tidak menggeser tenggat laporan yang sedang berjalan.',
                      style: TextStyle(fontSize: 11.5, height: 1.35),
                    ),
                  ),
                ],
              ),
            );
          }
          final item = page.items[index - 1];
          return _DeadlineCard(
            deadline: item,
            canConfigure: page.access.canManage && !saving,
            onConfigure: () => onConfigure(item),
          );
        },
      ),
    );
  }
}

class _DeadlineCard extends StatelessWidget {
  const _DeadlineCard({
    required this.deadline,
    required this.canConfigure,
    required this.onConfigure,
  });

  final ViolationProcessDeadline deadline;
  final bool canConfigure;
  final VoidCallback onConfigure;

  @override
  Widget build(BuildContext context) {
    final year = deadline.academicYear;
    return Card(
      key: Key('violation-deadline-${year.id}'),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: NusaColors.primary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Icon(
                    Icons.hourglass_bottom_rounded,
                    color: NusaColors.primary,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        year.name,
                        style: const TextStyle(
                          color: NusaColors.textPrimary,
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        year.active ? 'Tahun pelajaran aktif' : 'Arsip',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10.5,
                        ),
                      ),
                    ],
                  ),
                ),
                _Badge(
                  label: deadline.saved ? 'Diatur' : 'Bawaan',
                  color: deadline.saved
                      ? NusaColors.success
                      : NusaColors.textSecondary,
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _DeadlineValue(
                    icon: Icons.psychology_alt_outlined,
                    label: 'Pemeriksaan BK',
                    value: '${deadline.counselingDays} hari',
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _DeadlineValue(
                    icon: Icons.approval_outlined,
                    label: 'Pengesahan Wakil',
                    value: '${deadline.approvalDays} hari',
                  ),
                ),
              ],
            ),
            const SizedBox(height: 9),
            _NotificationRow(
              active: deadline.reminderNotificationActive,
              icon: Icons.notifications_active_outlined,
              activeLabel:
                  'Pengingat ${deadline.reminderDaysBefore == 0 ? 'pada hari jatuh tempo' : '${deadline.reminderDaysBefore} hari sebelum batas'}',
              inactiveLabel: 'Pengingat sebelum batas nonaktif',
            ),
            _NotificationRow(
              active: deadline.overdueNotificationActive,
              icon: Icons.notification_important_outlined,
              activeLabel: 'Pemberitahuan keterlambatan aktif',
              inactiveLabel: 'Pemberitahuan keterlambatan nonaktif',
            ),
            const SizedBox(height: 7),
            Text(
              deadline.saved
                  ? 'Diperbarui ${_dateTime(deadline.updatedAt)} · ${deadline.updatedBy ?? '-'}'
                  : 'Belum disimpan · menampilkan nilai bawaan',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10,
              ),
            ),
            if (canConfigure) ...[
              const SizedBox(height: 11),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  key: Key('configure-violation-deadline-${year.id}'),
                  onPressed: onConfigure,
                  icon: const Icon(Icons.tune_rounded),
                  label: const Text('Atur Batas'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _DeadlineValue extends StatelessWidget {
  const _DeadlineValue({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(
      color: NusaColors.primary.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 19, color: NusaColors.primary),
        const SizedBox(height: 6),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
          ),
        ),
        Text(
          value,
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
        ),
      ],
    ),
  );
}

class _NotificationRow extends StatelessWidget {
  const _NotificationRow({
    required this.active,
    required this.icon,
    required this.activeLabel,
    required this.inactiveLabel,
  });

  final bool active;
  final IconData icon;
  final String activeLabel;
  final String inactiveLabel;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(
      children: [
        Icon(
          active ? icon : Icons.notifications_off_outlined,
          size: 16,
          color: active ? NusaColors.primary : NusaColors.textSecondary,
        ),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            active ? activeLabel : inactiveLabel,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
            ),
          ),
        ),
      ],
    ),
  );
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w800),
    ),
  );
}

class _DeadlineError extends StatelessWidget {
  const _DeadlineError({required this.message, required this.onRetry});

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

String _dateTime(DateTime? value) {
  if (value == null) return '-';
  final local = value.toLocal();
  String twoDigits(int number) => number.toString().padLeft(2, '0');
  return '${twoDigits(local.day)}/${twoDigits(local.month)}/${local.year} '
      '${twoDigits(local.hour)}:${twoDigits(local.minute)}';
}

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Pengaturan batas proses belum dapat diproses.';
}
