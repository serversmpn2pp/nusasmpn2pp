import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/survey_statement/application/survey_statement_controller.dart';
import 'package:nusa/features/survey_statement/domain/survey_statement.dart';
import 'package:nusa/features/survey_statement/presentation/widgets/survey_statement_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class SurveyStatementView extends ConsumerStatefulWidget {
  const SurveyStatementView({super.key});

  @override
  ConsumerState<SurveyStatementView> createState() =>
      _SurveyStatementViewState();
}

class _SurveyStatementViewState extends ConsumerState<SurveyStatementView> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  bool _loadingMore = false;
  bool _mutating = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final statements = ref.watch(surveyStatementControllerProvider);
    final current = statements.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pernyataan Survei'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: statements.isLoading || _mutating
                ? null
                : ref.read(surveyStatementControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current == null
          ? null
          : FloatingActionButton.extended(
              key: const Key('add-survey-statement'),
              onPressed: _mutating
                  ? null
                  : () => _openForm(nextOrder: current.nextOrder),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah'),
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
                    _StatementSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    const _SnapshotNotice(),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('survey-statement-search'),
                      controller: _searchController,
                      hintText: 'Cari pernyataan atau kode',
                      prefixIcon: Icons.search_rounded,
                      enabled: !statements.isLoading && !_mutating,
                      onChanged: _search,
                      suffixIcon: _searchController.text.isEmpty
                          ? null
                          : IconButton(
                              onPressed: _clearSearch,
                              icon: const Icon(Icons.close_rounded),
                            ),
                    ),
                    const SizedBox(height: 7),
                    _StatusFilter(
                      selected: current.status,
                      enabled: !statements.isLoading && !_mutating,
                      onSelected: (value) => ref
                          .read(surveyStatementControllerProvider.notifier)
                          .filterStatus(value),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: statements.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _StatementError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(surveyStatementControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _StatementResults(
                  page: page,
                  mutating: _mutating,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(surveyStatementControllerProvider.notifier)
                      .refresh,
                  onLoadMore: _loadMore,
                  onEdit: (item) =>
                      _openForm(existing: item, nextOrder: page.nextOrder),
                  onChangeStatus: _confirmStatusChange,
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
        ref.read(surveyStatementControllerProvider.notifier).search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(surveyStatementControllerProvider.notifier).search('');
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(surveyStatementControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm({
    required int nextOrder,
    SurveyStatement? existing,
  }) async {
    final value = await showModalBottomSheet<SurveyStatementFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          SurveyStatementFormSheet(existing: existing, nextOrder: nextOrder),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? 'Pernyataan survei berhasil ditambahkan.'
          : 'Pernyataan diperbarui. Survei lama tetap memakai teks sebelumnya.',
      operation: existing == null
          ? () => ref.read(surveyStatementActionsProvider).create(value)
          : () => ref
                .read(surveyStatementActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _confirmStatusChange(SurveyStatement item) async {
    final activate = !item.active;
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: Icon(
              activate
                  ? Icons.play_circle_outline_rounded
                  : Icons.pause_circle_outline_rounded,
              color: NusaColors.primary,
            ),
            title: Text(
              activate ? 'Aktifkan pernyataan?' : 'Nonaktifkan pernyataan?',
            ),
            content: Text(
              activate
                  ? 'Pernyataan ini akan muncul pada survei siswa berikutnya.'
                  : 'Pernyataan tidak lagi muncul pada survei berikutnya. '
                        'Jawaban yang sudah dikirim tetap tersimpan.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-survey-statement-status'),
                onPressed: () => Navigator.pop(context, true),
                child: Text(activate ? 'Aktifkan' : 'Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    await _runMutation(
      successMessage: activate
          ? 'Pernyataan survei berhasil diaktifkan.'
          : 'Pernyataan survei berhasil dinonaktifkan.',
      operation: () => ref
          .read(surveyStatementActionsProvider)
          .updateStatus(id: item.id, active: activate),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(surveyStatementControllerProvider.notifier).refresh();
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

class _StatementSummary extends StatelessWidget {
  const _StatementSummary({required this.summary});

  final SurveyStatementSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 13),
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

class _SnapshotNotice extends StatelessWidget {
  const _SnapshotNotice();

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(
      color: NusaColors.accent.withValues(alpha: 0.11),
      borderRadius: BorderRadius.circular(13),
      border: Border.all(color: NusaColors.accent.withValues(alpha: 0.35)),
    ),
    child: const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.verified_user_outlined, size: 18, color: NusaColors.primary),
        SizedBox(width: 8),
        Expanded(
          child: Text(
            'Perubahan master hanya berlaku untuk survei berikutnya; '
            'riwayat jawaban lama tidak berubah.',
            style: TextStyle(fontSize: 10.5, height: 1.35),
          ),
        ),
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
              key: Key('survey-statement-filter-${item.$1}'),
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

class _StatementResults extends StatelessWidget {
  const _StatementResults({
    required this.page,
    required this.mutating,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onEdit,
    required this.onChangeStatus,
  });

  final SurveyStatementPage page;
  final bool mutating;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<SurveyStatement> onEdit;
  final ValueChanged<SurveyStatement> onChangeStatus;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(42),
          children: const [
            Icon(Icons.ballot_outlined, size: 50, color: NusaColors.primary),
            SizedBox(height: 12),
            Text(
              'Belum ada pernyataan survei pada filter ini.',
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
        key: const PageStorageKey<String>('survey-statement-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 92),
        itemCount: page.items.length + 1,
        separatorBuilder: (context, index) => const SizedBox(height: 9),
        itemBuilder: (context, index) {
          if (index == page.items.length) {
            return page.pagination.hasNextPage
                ? OutlinedButton.icon(
                    onPressed: loadingMore ? null : onLoadMore,
                    icon: loadingMore
                        ? const SizedBox.square(
                            dimension: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.expand_more_rounded),
                    label: Text(
                      loadingMore ? 'Memuat...' : 'Muat lebih banyak',
                    ),
                  )
                : Text(
                    '${page.pagination.total} pernyataan ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }

          final item = page.items[index];
          return _StatementCard(
            item: item,
            onEdit: mutating ? null : () => onEdit(item),
            onChangeStatus: mutating ? null : () => onChangeStatus(item),
          );
        },
      ),
    );
  }
}

class _StatementCard extends StatelessWidget {
  const _StatementCard({required this.item, this.onEdit, this.onChangeStatus});

  final SurveyStatement item;
  final VoidCallback? onEdit;
  final VoidCallback? onChangeStatus;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('survey-statement-${item.id}'),
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: NusaColors.primary.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              '${item.order}',
              style: const TextStyle(
                color: NusaColors.primary,
                fontSize: 16,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.statement,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 13,
                    height: 1.4,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  item.code,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 9.5,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 5),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              _StatusBadge(active: item.active),
              PopupMenuButton<String>(
                key: Key('survey-statement-menu-${item.id}'),
                tooltip: 'Aksi pernyataan',
                onSelected: (value) {
                  if (value == 'edit') onEdit?.call();
                  if (value == 'status') onChangeStatus?.call();
                },
                itemBuilder: (context) => [
                  PopupMenuItem(
                    value: 'edit',
                    enabled: onEdit != null,
                    child: const Text('Ubah'),
                  ),
                  PopupMenuItem(
                    value: 'status',
                    enabled: onChangeStatus != null,
                    child: Text(item.active ? 'Nonaktifkan' : 'Aktifkan'),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    ),
  );
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

class _StatementError extends StatelessWidget {
  const _StatementError({required this.message, required this.onRetry});

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
      : 'Pernyataan survei belum dapat diproses.';
}
