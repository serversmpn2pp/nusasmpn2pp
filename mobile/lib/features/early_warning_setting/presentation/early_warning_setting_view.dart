import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/early_warning_setting/application/early_warning_setting_controller.dart';
import 'package:nusa/features/early_warning_setting/domain/early_warning_setting.dart';
import 'package:nusa/features/early_warning_setting/presentation/widgets/early_warning_setting_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class EarlyWarningSettingView extends ConsumerStatefulWidget {
  const EarlyWarningSettingView({super.key});

  @override
  ConsumerState<EarlyWarningSettingView> createState() =>
      _EarlyWarningSettingViewState();
}

class _EarlyWarningSettingViewState
    extends ConsumerState<EarlyWarningSettingView> {
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
    final settings = ref.watch(earlyWarningSettingControllerProvider);
    final current = settings.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Peringatan Dini Poin'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: settings.isLoading || _saving
                ? null
                : ref
                      .read(earlyWarningSettingControllerProvider.notifier)
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
                    _EarlyWarningSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('early-warning-search'),
                      controller: _searchController,
                      hintText: 'Cari tahun pelajaran',
                      prefixIcon: Icons.search_rounded,
                      enabled: !settings.isLoading && !_saving,
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
                      fieldKey: const Key('early-warning-status-filter'),
                      value: current.status,
                      options: const [
                        NusaDropdownOption(
                          value: 'semua',
                          label: 'Semua status deteksi',
                        ),
                        NusaDropdownOption(
                          value: 'aktif',
                          label: 'Deteksi aktif',
                        ),
                        NusaDropdownOption(
                          value: 'nonaktif',
                          label: 'Deteksi nonaktif',
                        ),
                      ],
                      decoration: const InputDecoration(
                        labelText: 'Status deteksi',
                        prefixIcon: Icon(Icons.notification_important_outlined),
                      ),
                      enabled: !settings.isLoading && !_saving,
                      onChanged: (value) {
                        if (value != null) {
                          ref
                              .read(
                                earlyWarningSettingControllerProvider.notifier,
                              )
                              .filterStatus(value);
                        }
                      },
                    ),
                  ],
                ),
              ),
            Expanded(
              child: settings.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _EarlyWarningError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(earlyWarningSettingControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _EarlyWarningResults(
                  page: page,
                  saving: _saving,
                  onRefresh: ref
                      .read(earlyWarningSettingControllerProvider.notifier)
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
        ref.read(earlyWarningSettingControllerProvider.notifier).search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(earlyWarningSettingControllerProvider.notifier).search('');
  }

  Future<void> _openEditor(EarlyWarningSetting setting) async {
    final value = await showModalBottomSheet<EarlyWarningSettingFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => EarlyWarningSettingFormSheet(setting: setting),
    );
    if (value == null || !mounted) return;

    setState(() => _saving = true);
    try {
      await ref
          .read(earlyWarningSettingActionsProvider)
          .update(academicYearId: setting.academicYear.id, value: value);
      await ref.read(earlyWarningSettingControllerProvider.notifier).refresh();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            content: Text(
              'Peringatan dini ${setting.academicYear.name} berhasil disimpan.',
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

class _EarlyWarningSummary extends StatelessWidget {
  const _EarlyWarningSummary({required this.summary});

  final EarlyWarningSettingSummary summary;

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
        _SummaryItem(label: 'Deteksi', value: summary.detectionActiveCount),
        _SummaryItem(
          label: 'Notifikasi',
          value: summary.notificationActiveCount,
        ),
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

class _EarlyWarningResults extends StatelessWidget {
  const _EarlyWarningResults({
    required this.page,
    required this.saving,
    required this.onRefresh,
    required this.onConfigure,
  });

  final EarlyWarningSettingPage page;
  final bool saving;
  final Future<void> Function() onRefresh;
  final ValueChanged<EarlyWarningSetting> onConfigure;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(42),
          children: const [
            Icon(
              Icons.notifications_off_outlined,
              size: 50,
              color: NusaColors.primary,
            ),
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
        key: const PageStorageKey<String>('early-warning-setting-list'),
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
                  Icon(Icons.info_outline_rounded, size: 18),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Proses otomatis membentuk peringatan untuk ditindaklanjuti pada pusat Peringatan Dini Siswa.',
                      style: TextStyle(fontSize: 11.5, height: 1.35),
                    ),
                  ),
                ],
              ),
            );
          }
          final item = page.items[index - 1];
          return _EarlyWarningCard(
            setting: item,
            canConfigure: page.access.canManage && !saving,
            onConfigure: () => onConfigure(item),
          );
        },
      ),
    );
  }
}

class _EarlyWarningCard extends StatelessWidget {
  const _EarlyWarningCard({
    required this.setting,
    required this.canConfigure,
    required this.onConfigure,
  });

  final EarlyWarningSetting setting;
  final bool canConfigure;
  final VoidCallback onConfigure;

  @override
  Widget build(BuildContext context) {
    final year = setting.academicYear;
    return Card(
      key: Key('early-warning-setting-${year.id}'),
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
                    Icons.notification_important_rounded,
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
                  label: setting.detectionActive ? 'Aktif' : 'Nonaktif',
                  color: setting.detectionActive
                      ? NusaColors.success
                      : NusaColors.textSecondary,
                ),
              ],
            ),
            const SizedBox(height: 12),
            _TriggerRow(
              icon: Icons.trending_up_rounded,
              label: 'Mendekati sanksi',
              value: '${setting.nearThresholdPercentage}% dari ambang',
            ),
            _TriggerRow(
              icon: Icons.report_problem_outlined,
              label: 'Pelanggaran berulang',
              value:
                  '${setting.repeatedViolationCount} kejadian / ${setting.violationPeriodDays} hari',
            ),
            _TriggerRow(
              icon: Icons.access_time_rounded,
              label: 'Keterlambatan berulang',
              value:
                  '${setting.repeatedLateCount} kali / ${setting.latePeriodDays} hari',
            ),
            const _TriggerRow(
              icon: Icons.policy_outlined,
              label: 'Sanksi belum selesai',
              value: 'Selalu dipantau',
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Icon(
                  setting.notificationActive
                      ? Icons.notifications_active_outlined
                      : Icons.notifications_off_outlined,
                  size: 16,
                  color: setting.notificationActive
                      ? NusaColors.primary
                      : NusaColors.textSecondary,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    setting.notificationActive
                        ? 'Notifikasi penerima aktif'
                        : 'Notifikasi penerima nonaktif',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 7),
            Text(
              setting.saved
                  ? 'Diperbarui ${_dateTime(setting.updatedAt)} · ${setting.updatedBy ?? '-'}'
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
                  key: Key('configure-early-warning-${year.id}'),
                  onPressed: onConfigure,
                  icon: const Icon(Icons.tune_rounded),
                  label: const Text('Atur Peringatan'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _TriggerRow extends StatelessWidget {
  const _TriggerRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 17, color: NusaColors.primary),
        const SizedBox(width: 7),
        Expanded(
          child: Text(
            label,
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
          ),
        ),
        const SizedBox(width: 8),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
              fontWeight: FontWeight.w600,
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

class _EarlyWarningError extends StatelessWidget {
  const _EarlyWarningError({required this.message, required this.onRetry});

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
      : 'Pengaturan peringatan dini belum dapat diproses.';
}
