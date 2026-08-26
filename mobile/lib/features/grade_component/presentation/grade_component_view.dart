import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/grade_component/application/grade_component_controller.dart';
import 'package:nusa/features/grade_component/domain/grade_component.dart';
import 'package:nusa/features/grade_component/presentation/widgets/grade_component_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GradeComponentView extends ConsumerStatefulWidget {
  const GradeComponentView({super.key});

  @override
  ConsumerState<GradeComponentView> createState() => _GradeComponentViewState();
}

class _GradeComponentViewState extends ConsumerState<GradeComponentView> {
  final _searchController = TextEditingController();
  bool _loadingMore = false;
  bool _mutating = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final components = ref.watch(gradeComponentControllerProvider);
    final current = components.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Komponen Nilai'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: components.isLoading
                ? null
                : ref.read(gradeComponentControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-grade-component'),
              onPressed: _mutating || current!.assignments.isEmpty
                  ? null
                  : () => _openForm(assignments: current.assignments),
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
                    _ComponentSummary(summary: current.summary),
                    const SizedBox(height: 10),
                    _ComponentFilters(
                      page: current,
                      searchController: _searchController,
                      enabled: !components.isLoading && !_mutating,
                      notifier: ref.read(
                        gradeComponentControllerProvider.notifier,
                      ),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: components.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _ComponentError(
                  message: _errorMessage(error),
                  onRetry: ref
                      .read(gradeComponentControllerProvider.notifier)
                      .refresh,
                ),
                data: (page) => _ComponentResults(
                  page: page,
                  loadingMore: _loadingMore,
                  mutating: _mutating,
                  onRefresh: ref
                      .read(gradeComponentControllerProvider.notifier)
                      .refresh,
                  onLoadMore: _loadMore,
                  onEdit: page.canManage
                      ? (item) => _openForm(
                          assignments: page.assignments,
                          existing: item,
                        )
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
      await ref.read(gradeComponentControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm({
    required List<GradeComponentAssignment> assignments,
    GradeComponent? existing,
  }) async {
    final value = await showModalBottomSheet<GradeComponentFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          GradeComponentFormSheet(assignments: assignments, existing: existing),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? 'Komponen nilai berhasil ditambahkan.'
          : 'Komponen nilai berhasil diperbarui.',
      operation: existing == null
          ? () => ref.read(gradeComponentActionsProvider).create(value)
          : () => ref
                .read(gradeComponentActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _confirmDeactivate(GradeComponent item) async {
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.pause_circle_outline_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Nonaktifkan komponen?'),
            content: Text(
              '${item.name} (${item.typeLabel}) akan dinonaktifkan dan tidak '
              'lagi muncul pada input nilai. Publikasi terkait dikembalikan '
              'menjadi draf.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-deactivate-grade-component'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    await _runMutation(
      successMessage: 'Komponen nilai berhasil dinonaktifkan.',
      operation: () =>
          ref.read(gradeComponentActionsProvider).deactivate(item.id),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(gradeComponentControllerProvider.notifier).refresh();
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

class _ComponentSummary extends StatelessWidget {
  const _ComponentSummary({required this.summary});

  final GradeComponentSummary summary;

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

class _ComponentFilters extends StatelessWidget {
  const _ComponentFilters({
    required this.page,
    required this.searchController,
    required this.enabled,
    required this.notifier,
  });

  final GradeComponentPage page;
  final TextEditingController searchController;
  final bool enabled;
  final GradeComponentController notifier;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      TextField(
        key: const Key('grade-component-search'),
        controller: searchController,
        enabled: enabled,
        onChanged: notifier.search,
        textInputAction: TextInputAction.search,
        decoration: const InputDecoration(
          hintText: 'Cari komponen, guru, mapel, atau kelas',
          prefixIcon: Icon(Icons.search_rounded),
        ),
      ),
      const SizedBox(height: 8),
      NusaDropdownField<int?>(
        fieldKey: const Key('grade-component-year-filter'),
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
              fieldKey: const Key('grade-component-semester-filter'),
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
              fieldKey: const Key('grade-component-type-filter'),
              value: page.filter.type,
              enabled: enabled,
              decoration: const InputDecoration(labelText: 'Jenis'),
              options: const [
                NusaDropdownOption(value: 'semua', label: 'Semua'),
                NusaDropdownOption(value: 'formatif', label: 'Formatif'),
                NusaDropdownOption(value: 'sumatif', label: 'Sumatif'),
                NusaDropdownOption(value: 'sts', label: 'STS'),
                NusaDropdownOption(value: 'sas_saj', label: 'SAS/SAJ'),
              ],
              onChanged: (value) {
                if (value != null) notifier.filterType(value);
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

class _ComponentResults extends StatelessWidget {
  const _ComponentResults({
    required this.page,
    required this.loadingMore,
    required this.mutating,
    required this.onRefresh,
    required this.onLoadMore,
    this.onEdit,
    this.onDeactivate,
  });

  final GradeComponentPage page;
  final bool loadingMore;
  final bool mutating;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<GradeComponent>? onEdit;
  final ValueChanged<GradeComponent>? onDeactivate;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(42),
          children: [
            const Icon(
              Icons.fact_check_outlined,
              size: 50,
              color: NusaColors.primary,
            ),
            const SizedBox(height: 12),
            Text(
              page.assignments.isEmpty
                  ? 'Belum ada penugasan guru mata pelajaran aktif.'
                  : 'Belum ada komponen nilai pada filter ini.',
              textAlign: TextAlign.center,
              style: const TextStyle(color: NusaColors.textSecondary),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        key: const PageStorageKey<String>('grade-component-list'),
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
                    '${page.pagination.total} komponen ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }

          final item = page.items[index];
          return _ComponentCard(
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

class _ComponentCard extends StatelessWidget {
  const _ComponentCard({required this.item, this.onEdit, this.onDeactivate});

  final GradeComponent item;
  final VoidCallback? onEdit;
  final VoidCallback? onDeactivate;

  @override
  Widget build(BuildContext context) {
    final color = _componentColor(item.type);
    return Card(
      key: Key('grade-component-${item.id}'),
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
                    color: color.withValues(alpha: 0.11),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(_componentIcon(item.type), color: color),
                ),
                const SizedBox(width: 10),
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
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        '${item.typeLabel} · ${item.semesterLabel}'
                        '${item.order > 0 ? ' · Urutan ${item.order}' : ''}',
                        style: TextStyle(
                          color: color,
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
                _StatusBadge(active: item.active),
                if (onEdit != null || onDeactivate != null)
                  PopupMenuButton<String>(
                    key: Key('grade-component-menu-${item.id}'),
                    tooltip: 'Aksi komponen',
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
            const SizedBox(height: 11),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: NusaColors.surfaceBlue,
                borderRadius: BorderRadius.circular(11),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.assignment.subject.name,
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${item.assignment.schoolClass.name} · '
                    '${item.assignment.employee.name}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                  Text(
                    item.assignment.academicYear.name,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 9.5,
                    ),
                  ),
                ],
              ),
            ),
            if (item.assessmentDateLabel != null) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(
                    Icons.event_available_rounded,
                    color: NusaColors.textSecondary,
                    size: 15,
                  ),
                  const SizedBox(width: 5),
                  Text(
                    'Penilaian ${item.assessmentDateLabel}',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                ],
              ),
            ],
          ],
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

class _ComponentError extends StatelessWidget {
  const _ComponentError({required this.message, required this.onRetry});

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

Color _componentColor(String type) => switch (type) {
  'formatif' => NusaColors.success,
  'sumatif' => const Color(0xFF2676C8),
  'sts' => const Color(0xFF7A56B3),
  'sas_saj' => const Color(0xFFE6A600),
  _ => NusaColors.primary,
};

IconData _componentIcon(String type) => switch (type) {
  'formatif' => Icons.checklist_rounded,
  'sumatif' => Icons.assignment_rounded,
  'sts' => Icons.fact_check_rounded,
  'sas_saj' => Icons.workspace_premium_rounded,
  _ => Icons.edit_note_rounded,
};

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Komponen nilai belum dapat diproses.';
}
