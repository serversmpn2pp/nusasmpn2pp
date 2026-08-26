import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/grade_weight_scheme/application/grade_weight_scheme_controller.dart';
import 'package:nusa/features/grade_weight_scheme/domain/grade_weight_scheme.dart';
import 'package:nusa/features/grade_weight_scheme/presentation/widgets/grade_weight_scheme_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GradeWeightSchemeView extends ConsumerStatefulWidget {
  const GradeWeightSchemeView({super.key});

  @override
  ConsumerState<GradeWeightSchemeView> createState() =>
      _GradeWeightSchemeViewState();
}

class _GradeWeightSchemeViewState extends ConsumerState<GradeWeightSchemeView> {
  bool _loadingMore = false;
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final schemes = ref.watch(gradeWeightSchemeControllerProvider);
    final current = schemes.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Skema Bobot Nilai'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: schemes.isLoading
                ? null
                : ref
                      .read(gradeWeightSchemeControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-weight-scheme'),
              onPressed: _mutating
                  ? null
                  : () => _openForm(years: current!.academicYears),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah Skema'),
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
                    _SchemeSummary(summary: current.summary),
                    const SizedBox(height: 10),
                    _SchemeFilters(
                      page: current,
                      enabled: !schemes.isLoading && !_mutating,
                      notifier: ref.read(
                        gradeWeightSchemeControllerProvider.notifier,
                      ),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: schemes.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _SchemeError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(gradeWeightSchemeControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _SchemeResults(
                  page: page,
                  loadingMore: _loadingMore,
                  mutating: _mutating,
                  onRefresh: ref
                      .read(gradeWeightSchemeControllerProvider.notifier)
                      .refresh,
                  onLoadMore: _loadMore,
                  onEdit: page.canManage
                      ? (item) =>
                            _openForm(years: page.academicYears, existing: item)
                      : null,
                  onDeactivate: page.canManage ? _confirmDeactivate : null,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(gradeWeightSchemeControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm({
    required List<SchemeAcademicYear> years,
    GradeWeightScheme? existing,
  }) async {
    final value = await showModalBottomSheet<GradeWeightSchemeFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          GradeWeightSchemeFormSheet(academicYears: years, existing: existing),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? 'Skema bobot nilai berhasil ditambahkan.'
          : 'Skema bobot nilai berhasil diperbarui.',
      operation: existing == null
          ? () => ref.read(gradeWeightSchemeActionsProvider).create(value)
          : () => ref
                .read(gradeWeightSchemeActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _confirmDeactivate(GradeWeightScheme item) async {
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.pause_circle_outline_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Nonaktifkan skema?'),
            content: Text(
              'Skema ${item.academicYear.name}, semester '
              '${item.semesterLabel}, ${item.gradeLabel} akan dinonaktifkan. '
              'Publikasi nilai terkait dikembalikan menjadi draf.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-deactivate-weight-scheme'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    await _runMutation(
      successMessage: 'Skema bobot nilai berhasil dinonaktifkan.',
      operation: () =>
          ref.read(gradeWeightSchemeActionsProvider).deactivate(item.id),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(gradeWeightSchemeControllerProvider.notifier).refresh();
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

class _SchemeSummary extends StatelessWidget {
  const _SchemeSummary({required this.summary});

  final GradeWeightSchemeSummary summary;

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

class _SchemeFilters extends StatelessWidget {
  const _SchemeFilters({
    required this.page,
    required this.enabled,
    required this.notifier,
  });

  final GradeWeightSchemePage page;
  final bool enabled;
  final GradeWeightSchemeController notifier;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      NusaDropdownField<int?>(
        fieldKey: const Key('weight-scheme-year-filter'),
        value: page.filter.academicYearId,
        enabled: enabled,
        decoration: const InputDecoration(
          labelText: 'Tahun pelajaran',
          prefixIcon: Icon(Icons.calendar_month_rounded),
        ),
        options: [
          const NusaDropdownOption<int?>(
            value: null,
            label: 'Semua tahun pelajaran',
          ),
          for (final year in page.academicYears)
            NusaDropdownOption<int?>(
              value: year.id,
              label: '${year.name}${year.active ? ' · Aktif' : ''}',
            ),
        ],
        onChanged: notifier.filterAcademicYear,
      ),
      const SizedBox(height: 8),
      Row(
        children: [
          Expanded(
            child: NusaDropdownField<String>(
              fieldKey: const Key('weight-scheme-semester-filter'),
              value: page.filter.semester,
              enabled: enabled,
              decoration: const InputDecoration(labelText: 'Semester'),
              options: const [
                NusaDropdownOption(value: 'semua', label: 'Semua'),
                NusaDropdownOption(value: 'ganjil', label: 'Ganjil'),
                NusaDropdownOption(value: 'genap', label: 'Genap'),
              ],
              onChanged: (value) {
                if (value != null) notifier.filterSemester(value);
              },
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: NusaDropdownField<String>(
              fieldKey: const Key('weight-scheme-grade-filter'),
              value: page.filter.grade,
              enabled: enabled,
              decoration: const InputDecoration(labelText: 'Tingkat'),
              options: const [
                NusaDropdownOption(value: 'semua', label: 'Semua'),
                NusaDropdownOption(value: '7', label: 'VII'),
                NusaDropdownOption(value: '8', label: 'VIII'),
                NusaDropdownOption(value: '9', label: 'IX'),
              ],
              onChanged: (value) {
                if (value != null) notifier.filterGrade(value);
              },
            ),
          ),
        ],
      ),
      const SizedBox(height: 7),
      Row(
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
                  selected: page.filter.status == item.$1,
                  showCheckmark: false,
                  onSelected: enabled
                      ? (_) => notifier.filterStatus(item.$1)
                      : null,
                ),
              ),
            ),
        ],
      ),
    ],
  );
}

class _SchemeResults extends StatelessWidget {
  const _SchemeResults({
    required this.page,
    required this.loadingMore,
    required this.mutating,
    required this.onRefresh,
    required this.onLoadMore,
    this.onEdit,
    this.onDeactivate,
  });

  final GradeWeightSchemePage page;
  final bool loadingMore;
  final bool mutating;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<GradeWeightScheme>? onEdit;
  final ValueChanged<GradeWeightScheme>? onDeactivate;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(44),
          children: const [
            Icon(Icons.balance_rounded, size: 50, color: NusaColors.primary),
            SizedBox(height: 12),
            Text(
              'Belum ada skema bobot nilai pada filter ini.',
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
        key: const PageStorageKey<String>('weight-scheme-list'),
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
                    '${page.pagination.total} skema ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }

          final item = page.items[index];
          return _SchemeCard(
            item: item,
            onEdit: onEdit == null || mutating ? null : () => onEdit!(item),
            onDeactivate: onDeactivate == null || mutating || !item.active
                ? null
                : () => onDeactivate!(item),
          );
        },
      ),
    );
  }
}

class _SchemeCard extends StatelessWidget {
  const _SchemeCard({required this.item, this.onEdit, this.onDeactivate});

  final GradeWeightScheme item;
  final VoidCallback? onEdit;
  final VoidCallback? onDeactivate;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('weight-scheme-${item.id}'),
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 43,
                height: 43,
                decoration: BoxDecoration(
                  color: item.active
                      ? NusaColors.successSurface
                      : NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  Icons.balance_rounded,
                  color: item.active ? NusaColors.success : NusaColors.primary,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.academicYear.name,
                      style: const TextStyle(
                        color: NusaColors.textPrimary,
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      '${item.semesterLabel} · ${item.gradeLabel}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
              ),
              _StatusBadge(active: item.active),
              if (onEdit != null || onDeactivate != null)
                PopupMenuButton<String>(
                  key: Key('weight-scheme-menu-${item.id}'),
                  tooltip: 'Aksi skema',
                  onSelected: (value) {
                    if (value == 'edit') onEdit?.call();
                    if (value == 'deactivate') onDeactivate?.call();
                  },
                  itemBuilder: (context) => [
                    if (onEdit != null)
                      const PopupMenuItem(value: 'edit', child: Text('Ubah')),
                    if (onDeactivate != null)
                      const PopupMenuItem(
                        value: 'deactivate',
                        child: Text('Nonaktifkan'),
                      ),
                  ],
                ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _WeightTile(label: 'Formatif', value: item.formativeWeight),
              _WeightTile(label: 'Sumatif', value: item.summativeWeight),
              _WeightTile(label: 'STS', value: item.midtermWeight),
              _WeightTile(label: item.finalLabel, value: item.finalWeight),
            ],
          ),
          const SizedBox(height: 9),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
            decoration: BoxDecoration(
              color: item.totalWeight == 100
                  ? NusaColors.successSurface
                  : NusaColors.accent.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              'Total bobot ${item.totalWeight}%',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: item.totalWeight == 100
                    ? NusaColors.success
                    : NusaColors.textPrimary,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _WeightTile extends StatelessWidget {
  const _WeightTile({required this.label, required this.value});

  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value%',
          style: const TextStyle(
            color: NusaColors.primary,
            fontSize: 15,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 8.5,
          ),
        ),
      ],
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

class _SchemeError extends StatelessWidget {
  const _SchemeError({required this.message, required this.onRetry});

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
      : 'Skema bobot nilai belum dapat diproses.';
}
