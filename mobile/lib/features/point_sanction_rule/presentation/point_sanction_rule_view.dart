import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/point_sanction_rule/application/point_sanction_rule_controller.dart';
import 'package:nusa/features/point_sanction_rule/domain/point_sanction_rule.dart';
import 'package:nusa/features/point_sanction_rule/presentation/widgets/point_sanction_rule_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class PointSanctionRuleView extends ConsumerStatefulWidget {
  const PointSanctionRuleView({super.key});

  @override
  ConsumerState<PointSanctionRuleView> createState() =>
      _PointSanctionRuleViewState();
}

class _PointSanctionRuleViewState extends ConsumerState<PointSanctionRuleView> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  bool _mutating = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final rules = ref.watch(pointSanctionRuleControllerProvider);
    final current = rules.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Aturan Sanksi Poin'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: rules.isLoading || _mutating
                ? null
                : ref
                      .read(pointSanctionRuleControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-sanction-rule'),
              onPressed: _mutating ? null : _openForm,
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (current != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Column(
                  children: [
                    _RuleSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('sanction-rule-search'),
                      controller: _searchController,
                      hintText: 'Cari nama atau penjelasan sanksi',
                      prefixIcon: Icons.search_rounded,
                      enabled: !rules.isLoading && !_mutating,
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
                      fieldKey: const Key('sanction-rule-status-filter'),
                      value: current.status,
                      options: const [
                        NusaDropdownOption(
                          value: 'semua',
                          label: 'Semua status',
                        ),
                        NusaDropdownOption(value: 'aktif', label: 'Aktif'),
                        NusaDropdownOption(
                          value: 'nonaktif',
                          label: 'Nonaktif',
                        ),
                      ],
                      decoration: const InputDecoration(
                        labelText: 'Status aturan',
                        prefixIcon: Icon(Icons.toggle_on_outlined),
                      ),
                      enabled: !rules.isLoading && !_mutating,
                      onChanged: (value) {
                        if (value != null) {
                          ref
                              .read(
                                pointSanctionRuleControllerProvider.notifier,
                              )
                              .filterStatus(value);
                        }
                      },
                    ),
                  ],
                ),
              ),
            Expanded(
              child: rules.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _RuleError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(pointSanctionRuleControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _RuleResults(
                  page: page,
                  mutating: _mutating,
                  onRefresh: ref
                      .read(pointSanctionRuleControllerProvider.notifier)
                      .refresh,
                  onEdit: (item) => _openForm(existing: item),
                  onDeactivate: _confirmDeactivate,
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
        ref.read(pointSanctionRuleControllerProvider.notifier).search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(pointSanctionRuleControllerProvider.notifier).search('');
  }

  Future<void> _openForm({PointSanctionRule? existing}) async {
    final value = await showModalBottomSheet<PointSanctionRuleFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => PointSanctionRuleFormSheet(existing: existing),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? 'Aturan sanksi berhasil ditambahkan.'
          : 'Aturan sanksi diperbarui; sanksi terpicu tetap tersimpan.',
      operation: existing == null
          ? () => ref.read(pointSanctionRuleActionsProvider).create(value)
          : () => ref
                .read(pointSanctionRuleActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _confirmDeactivate(PointSanctionRule item) async {
    final usage = item.triggeredCount == 0
        ? 'Aturan tidak akan membentuk sanksi baru.'
        : 'Aturan telah memicu ${item.triggeredCount} sanksi. Seluruh sanksi '
              'tersebut dan poin saat terpicu tetap tersimpan.';
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.pause_circle_outline_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Nonaktifkan aturan sanksi?'),
            content: Text(
              '${item.pointThreshold} poin · ${item.name}\n\n$usage',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-sanction-rule-deactivate'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    await _runMutation(
      successMessage: 'Aturan dinonaktifkan; sanksi terpicu tetap aman.',
      operation: () =>
          ref.read(pointSanctionRuleActionsProvider).deactivate(item.id),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(pointSanctionRuleControllerProvider.notifier).refresh();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(successMessage)));
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  void _showError(Object error) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
  }
}

class _RuleSummary extends StatelessWidget {
  const _RuleSummary({required this.summary});

  final PointSanctionRuleSummary summary;

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
        _SummaryItem(label: 'Total', value: summary.total),
        _SummaryItem(label: 'Aktif', value: summary.active),
        _SummaryItem(label: 'Nonaktif', value: summary.inactive),
        _SummaryItem(label: 'Terpicu', value: summary.triggeredCount),
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
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.72),
            fontSize: 9.5,
          ),
        ),
      ],
    ),
  );
}

class _RuleResults extends StatelessWidget {
  const _RuleResults({
    required this.page,
    required this.mutating,
    required this.onRefresh,
    required this.onEdit,
    required this.onDeactivate,
  });

  final PointSanctionRulePage page;
  final bool mutating;
  final Future<void> Function() onRefresh;
  final ValueChanged<PointSanctionRule> onEdit;
  final ValueChanged<PointSanctionRule> onDeactivate;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(42),
          children: const [
            Icon(Icons.policy_rounded, size: 50, color: NusaColors.primary),
            SizedBox(height: 12),
            Text(
              'Belum ada aturan sanksi pada filter ini.',
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
        key: const PageStorageKey<String>('sanction-rule-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 3, 16, 92),
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
                      'Tindak lanjut dibuat otomatis saat total poin pertama kali mencapai setiap ambang aktif.',
                      style: TextStyle(fontSize: 11.5, height: 1.35),
                    ),
                  ),
                ],
              ),
            );
          }

          final item = page.items[index - 1];
          final canManage = page.access.canManage && !mutating;
          return _RuleCard(
            item: item,
            onEdit: canManage ? () => onEdit(item) : null,
            onDeactivate: canManage && item.active
                ? () => onDeactivate(item)
                : null,
          );
        },
      ),
    );
  }
}

class _RuleCard extends StatelessWidget {
  const _RuleCard({required this.item, this.onEdit, this.onDeactivate});

  final PointSanctionRule item;
  final VoidCallback? onEdit;
  final VoidCallback? onDeactivate;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('sanction-rule-${item.id}'),
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 54,
            padding: const EdgeInsets.symmetric(vertical: 9),
            decoration: BoxDecoration(
              color: NusaColors.primary.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              children: [
                Text(
                  '${item.pointThreshold}',
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const Text(
                  'POIN',
                  style: TextStyle(
                    color: NusaColors.primary,
                    fontSize: 8,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  item.description,
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 11.5, height: 1.35),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 6,
                  runSpacing: 5,
                  children: [
                    _Badge(
                      label: item.active ? 'Aktif' : 'Nonaktif',
                      color: item.active
                          ? NusaColors.success
                          : NusaColors.textSecondary,
                    ),
                    _Badge(
                      label: '${item.triggeredCount} sanksi terpicu',
                      color: const Color(0xFFB57900),
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (onEdit != null || onDeactivate != null)
            PopupMenuButton<String>(
              key: Key('sanction-rule-menu-${item.id}'),
              tooltip: 'Aksi aturan sanksi',
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

class _RuleError extends StatelessWidget {
  const _RuleError({required this.message, required this.onRetry});

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
      : 'Aturan sanksi belum dapat diproses.';
}
