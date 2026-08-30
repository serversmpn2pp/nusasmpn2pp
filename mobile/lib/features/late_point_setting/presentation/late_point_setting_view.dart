import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/late_point_setting/application/late_point_setting_controller.dart';
import 'package:nusa/features/late_point_setting/domain/late_point_setting.dart';
import 'package:nusa/features/late_point_setting/presentation/widgets/late_point_setting_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class LatePointSettingView extends ConsumerStatefulWidget {
  const LatePointSettingView({super.key});

  @override
  ConsumerState<LatePointSettingView> createState() =>
      _LatePointSettingViewState();
}

class _LatePointSettingViewState extends ConsumerState<LatePointSettingView> {
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
    final settings = ref.watch(latePointSettingControllerProvider);
    final current = settings.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Poin Keterlambatan'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: settings.isLoading || _saving
                ? null
                : ref.read(latePointSettingControllerProvider.notifier).refresh,
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
                    _LatePointSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('late-point-search'),
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
                      fieldKey: const Key('late-point-status-filter'),
                      value: current.status,
                      options: const [
                        NusaDropdownOption(
                          value: 'semua',
                          label: 'Semua status otomatisasi',
                        ),
                        NusaDropdownOption(
                          value: 'aktif',
                          label: 'Otomatis aktif',
                        ),
                        NusaDropdownOption(
                          value: 'nonaktif',
                          label: 'Belum aktif',
                        ),
                      ],
                      decoration: const InputDecoration(
                        labelText: 'Status otomatisasi',
                        prefixIcon: Icon(Icons.toggle_on_outlined),
                      ),
                      enabled: !settings.isLoading && !_saving,
                      onChanged: (value) {
                        if (value != null) {
                          ref
                              .read(latePointSettingControllerProvider.notifier)
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
                error: (error, stackTrace) => _LatePointError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(latePointSettingControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _LatePointResults(
                  page: page,
                  saving: _saving,
                  onRefresh: ref
                      .read(latePointSettingControllerProvider.notifier)
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
        ref.read(latePointSettingControllerProvider.notifier).search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(latePointSettingControllerProvider.notifier).search('');
  }

  Future<void> _openEditor(LatePointSetting setting) async {
    final value = await showModalBottomSheet<LatePointSettingFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => LatePointSettingFormSheet(setting: setting),
    );
    if (value == null || !mounted) return;

    setState(() => _saving = true);
    try {
      await ref
          .read(latePointSettingActionsProvider)
          .update(academicYearId: setting.academicYear.id, value: value);
      await ref.read(latePointSettingControllerProvider.notifier).refresh();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            content: Text(
              'Poin keterlambatan ${setting.academicYear.name} berhasil disimpan.',
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

class _LatePointSummary extends StatelessWidget {
  const _LatePointSummary({required this.summary});

  final LatePointSettingSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 12),
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
        _SummaryItem(label: 'Otomatis', value: summary.automaticActiveCount),
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
            fontSize: 20,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.72),
            fontSize: 10,
          ),
        ),
      ],
    ),
  );
}

class _LatePointResults extends StatelessWidget {
  const _LatePointResults({
    required this.page,
    required this.saving,
    required this.onRefresh,
    required this.onConfigure,
  });

  final LatePointSettingPage page;
  final bool saving;
  final Future<void> Function() onRefresh;
  final ValueChanged<LatePointSetting> onConfigure;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(42),
          children: const [
            Icon(Icons.more_time_rounded, size: 50, color: NusaColors.primary),
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
        key: const PageStorageKey<String>('late-point-setting-list'),
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
                  Icon(Icons.verified_user_outlined, size: 18),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Laporan otomatis tetap menunggu pemeriksaan BK dan pengesahan sebelum poin ditetapkan.',
                      style: TextStyle(fontSize: 11.5, height: 1.35),
                    ),
                  ),
                ],
              ),
            );
          }
          final item = page.items[index - 1];
          return _LatePointCard(
            setting: item,
            canConfigure: page.access.canManage && !saving,
            onConfigure: () => onConfigure(item),
          );
        },
      ),
    );
  }
}

class _LatePointCard extends StatelessWidget {
  const _LatePointCard({
    required this.setting,
    required this.canConfigure,
    required this.onConfigure,
  });

  final LatePointSetting setting;
  final bool canConfigure;
  final VoidCallback onConfigure;

  @override
  Widget build(BuildContext context) {
    final year = setting.academicYear;
    return Card(
      key: Key('late-point-setting-${year.id}'),
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
                    Icons.calendar_month_rounded,
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
                  label: setting.automaticActive
                      ? 'Otomatis aktif'
                      : 'Belum aktif',
                  color: setting.automaticActive
                      ? NusaColors.success
                      : NusaColors.textSecondary,
                ),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final range in setting.ranges) _RangeChip(range: range),
              ],
            ),
            const SizedBox(height: 10),
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
                  key: Key('configure-late-point-${year.id}'),
                  onPressed: onConfigure,
                  icon: const Icon(Icons.tune_rounded),
                  label: const Text('Atur Poin'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _RangeChip extends StatelessWidget {
  const _RangeChip({required this.range});

  final LatePointRange range;

  @override
  Widget build(BuildContext context) {
    final color = range.points == 0
        ? NusaColors.textSecondary
        : NusaColors.primary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Text(
        '${range.label}: ${range.points} poin',
        style: TextStyle(
          color: color,
          fontSize: 9.5,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
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

class _LatePointError extends StatelessWidget {
  const _LatePointError({required this.message, required this.onRetry});

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
      : 'Pengaturan poin keterlambatan belum dapat diproses.';
}
