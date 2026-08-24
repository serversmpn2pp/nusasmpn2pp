import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/academic_year/application/academic_year_controller.dart';
import 'package:nusa/features/academic_year/domain/academic_year.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class AcademicYearView extends ConsumerStatefulWidget {
  const AcademicYearView({super.key});

  @override
  ConsumerState<AcademicYearView> createState() => _AcademicYearViewState();
}

class _AcademicYearViewState extends ConsumerState<AcademicYearView> {
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
    final years = ref.watch(academicYearControllerProvider);
    final current = years.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Tahun Pelajaran'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: years.isLoading
                ? null
                : () => ref
                      .read(academicYearControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-academic-year'),
              onPressed: _mutating
                  ? null
                  : () => _openForm(activeYear: current?.activeYear),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah Tahun'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
              child: Column(
                children: [
                  if (current != null) ...[
                    _AcademicYearSummary(counts: current.counts),
                    const SizedBox(height: 9),
                    _ActiveYearBanner(activeYear: current.activeYear),
                    const SizedBox(height: 9),
                  ],
                  NusaTextField(
                    fieldKey: const Key('academic-year-search'),
                    controller: _searchController,
                    hintText: 'Cari tahun pelajaran',
                    prefixIcon: Icons.search_rounded,
                    onChanged: _search,
                    suffixIcon: _searchController.text.isEmpty
                        ? null
                        : IconButton(
                            onPressed: () {
                              _searchController.clear();
                              setState(() {});
                              ref
                                  .read(academicYearControllerProvider.notifier)
                                  .search('');
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                  ),
                  const SizedBox(height: 8),
                  _AcademicYearStatusFilter(
                    selected: current?.status ?? 'semua',
                    enabled: !years.isLoading,
                    onSelected: (value) => ref
                        .read(academicYearControllerProvider.notifier)
                        .filterStatus(value),
                  ),
                ],
              ),
            ),
            Expanded(
              child: years.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _AcademicYearError(
                  message: _errorMessage(error),
                  onRetry: () => ref
                      .read(academicYearControllerProvider.notifier)
                      .refresh(),
                ),
                data: (page) => _AcademicYearResults(
                  page: page,
                  loadingMore: _loadingMore,
                  mutating: _mutating,
                  onRefresh: () => ref
                      .read(academicYearControllerProvider.notifier)
                      .refresh(),
                  onLoadMore: _loadMore,
                  onEdit: page.canManage
                      ? (item) => _openForm(
                          existing: item,
                          activeYear: page.activeYear,
                        )
                      : null,
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
        ref.read(academicYearControllerProvider.notifier).search(value);
      }
    });
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(academicYearControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm({
    AcademicYearItem? existing,
    AcademicYearItem? activeYear,
  }) async {
    final value = await showModalBottomSheet<AcademicYearFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          _AcademicYearFormSheet(existing: existing, activeYear: activeYear),
    );
    if (value == null || !mounted) return;

    final replacesActiveYear =
        value.active && activeYear != null && activeYear.id != existing?.id;
    if (replacesActiveYear) {
      final confirmed = await _confirmActivation(
        newYearName: value.name,
        activeYearName: activeYear.name,
      );
      if (!confirmed || !mounted) return;
    }

    await _runMutation(
      successMessage: existing == null
          ? 'Tahun pelajaran berhasil ditambahkan.'
          : 'Tahun pelajaran berhasil diperbarui.',
      operation: existing == null
          ? () => ref.read(academicYearActionsProvider).create(value)
          : () => ref
                .read(academicYearActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<bool> _confirmActivation({
    required String newYearName,
    required String activeYearName,
  }) async {
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.swap_horiz_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Ganti tahun aktif?'),
            content: Text(
              '$newYearName akan menjadi tahun aktif. '
              '$activeYearName otomatis dinonaktifkan.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-academic-year-activation'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Ya, Aktifkan'),
              ),
            ],
          ),
        ) ??
        false;
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(academicYearControllerProvider.notifier).refresh();
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

class _AcademicYearSummary extends StatelessWidget {
  const _AcademicYearSummary({required this.counts});

  final AcademicYearCounts counts;

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
          ('Total', counts.total),
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

class _ActiveYearBanner extends StatelessWidget {
  const _ActiveYearBanner({required this.activeYear});

  final AcademicYearItem? activeYear;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
    decoration: BoxDecoration(
      color: activeYear == null
          ? NusaColors.accent.withValues(alpha: 0.1)
          : NusaColors.successSurface,
      border: Border.all(
        color: (activeYear == null ? NusaColors.accent : NusaColors.success)
            .withValues(alpha: 0.28),
      ),
      borderRadius: BorderRadius.circular(15),
    ),
    child: Row(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: (activeYear == null ? NusaColors.accent : NusaColors.success)
                .withValues(alpha: 0.13),
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(
            activeYear == null
                ? Icons.warning_amber_rounded
                : Icons.event_available_rounded,
            color: activeYear == null
                ? NusaColors.textPrimary
                : NusaColors.success,
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Tahun pelajaran aktif',
                style: TextStyle(color: NusaColors.textSecondary, fontSize: 10),
              ),
              Text(
                activeYear?.name ?? 'Belum ditetapkan',
                style: const TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
        if (activeYear != null)
          Text(
            '${activeYear!.classCount} kelas',
            style: const TextStyle(
              color: NusaColors.success,
              fontSize: 10.5,
              fontWeight: FontWeight.w700,
            ),
          ),
      ],
    ),
  );
}

class _AcademicYearStatusFilter extends StatelessWidget {
  const _AcademicYearStatusFilter({
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

class _AcademicYearResults extends StatelessWidget {
  const _AcademicYearResults({
    required this.page,
    required this.loadingMore,
    required this.mutating,
    required this.onRefresh,
    required this.onLoadMore,
    this.onEdit,
  });

  final AcademicYearPage page;
  final bool loadingMore;
  final bool mutating;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<AcademicYearItem>? onEdit;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(48),
          children: const [
            Icon(
              Icons.calendar_month_rounded,
              size: 52,
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
        key: const PageStorageKey<String>('academic-year-list'),
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
                    '${page.pagination.total} tahun pelajaran ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }

          final year = page.items[index];
          return _AcademicYearCard(
            year: year,
            onTap: onEdit == null || mutating ? null : () => onEdit!(year),
          );
        },
      ),
    );
  }
}

class _AcademicYearCard extends StatelessWidget {
  const _AcademicYearCard({required this.year, this.onTap});

  final AcademicYearItem year;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Material(
    key: Key('academic-year-${year.id}'),
    color: Colors.white,
    borderRadius: BorderRadius.circular(16),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          border: Border.all(
            color: year.active
                ? NusaColors.success.withValues(alpha: 0.34)
                : NusaColors.outline,
          ),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: year.active
                    ? NusaColors.successSurface
                    : NusaColors.surfaceBlue,
                borderRadius: BorderRadius.circular(13),
              ),
              child: Icon(
                year.active
                    ? Icons.event_available_rounded
                    : Icons.calendar_month_rounded,
                color: year.active ? NusaColors.success : NusaColors.primary,
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    year.name,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    _periodLabel(year.startDate, year.endDate),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Wrap(
                    spacing: 6,
                    runSpacing: 5,
                    children: [
                      _YearTag(
                        icon: Icons.class_outlined,
                        label: '${year.classCount} kelas',
                      ),
                      if (year.notes?.trim().isNotEmpty == true)
                        const _YearTag(
                          icon: Icons.notes_rounded,
                          label: 'Ada keterangan',
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 7),
            Column(
              children: [
                _YearStatus(active: year.active),
                if (onTap != null) ...[
                  const SizedBox(height: 12),
                  const Icon(
                    Icons.edit_outlined,
                    size: 18,
                    color: NusaColors.primary,
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _YearTag extends StatelessWidget {
  const _YearTag({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: NusaColors.background,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 11, color: NusaColors.primary),
        const SizedBox(width: 4),
        Text(
          label,
          style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.w600),
        ),
      ],
    ),
  );
}

class _YearStatus extends StatelessWidget {
  const _YearStatus({required this.active});

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

class _AcademicYearFormSheet extends StatefulWidget {
  const _AcademicYearFormSheet({this.existing, this.activeYear});

  final AcademicYearItem? existing;
  final AcademicYearItem? activeYear;

  @override
  State<_AcademicYearFormSheet> createState() => _AcademicYearFormSheetState();
}

class _AcademicYearFormSheetState extends State<_AcademicYearFormSheet> {
  late final TextEditingController _nameController;
  late final TextEditingController _notesController;
  late DateTime? _startDate;
  late DateTime? _endDate;
  late bool _active;
  String? _error;

  bool get _editing => widget.existing != null;

  bool get _replacesActiveYear =>
      _active &&
      widget.activeYear != null &&
      widget.activeYear!.id != widget.existing?.id;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _nameController = TextEditingController(text: existing?.name);
    _notesController = TextEditingController(text: existing?.notes);
    _startDate = existing?.startDate;
    _endDate = existing?.endDate;
    _active = existing?.active ?? false;
  }

  @override
  void dispose() {
    _nameController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.84,
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
                    _editing
                        ? 'Ubah Tahun Pelajaran'
                        : 'Tambah Tahun Pelajaran',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-academic-year-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('academic-year-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                  key: const Key('academic-year-form-name'),
                  controller: _nameController,
                  maxLength: 50,
                  decoration: const InputDecoration(
                    labelText: 'Nama tahun pelajaran',
                    hintText: 'Contoh: 2027/2028',
                    prefixIcon: Icon(Icons.school_outlined),
                    counterText: '',
                  ),
                ),
                const SizedBox(height: 12),
                _DatePickerField(
                  fieldKey: const Key('academic-year-form-start-date'),
                  clearKey: const Key('clear-academic-year-start-date'),
                  label: 'Tanggal mulai',
                  value: _startDate,
                  onTap: () => _pickDate(start: true),
                  onClear: _startDate == null
                      ? null
                      : () => setState(() => _startDate = null),
                ),
                const SizedBox(height: 12),
                _DatePickerField(
                  fieldKey: const Key('academic-year-form-end-date'),
                  clearKey: const Key('clear-academic-year-end-date'),
                  label: 'Tanggal selesai',
                  value: _endDate,
                  onTap: () => _pickDate(start: false),
                  onClear: _endDate == null
                      ? null
                      : () => setState(() => _endDate = null),
                ),
                const SizedBox(height: 7),
                SwitchListTile.adaptive(
                  key: const Key('academic-year-form-active'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Tahun pelajaran aktif'),
                  subtitle: const Text('Dipakai sebagai acuan kelas berjalan.'),
                  value: _active,
                  onChanged: (value) => setState(() => _active = value),
                ),
                if (_replacesActiveYear) ...[
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: NusaColors.accent.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(
                          Icons.info_outline_rounded,
                          color: NusaColors.textPrimary,
                        ),
                        const SizedBox(width: 9),
                        Expanded(
                          child: Text(
                            '${widget.activeYear!.name} akan otomatis '
                            'dinonaktifkan setelah Anda mengonfirmasi.',
                            style: const TextStyle(fontSize: 11.5),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
                TextField(
                  key: const Key('academic-year-form-notes'),
                  controller: _notesController,
                  minLines: 2,
                  maxLines: 4,
                  maxLength: 2000,
                  decoration: const InputDecoration(
                    labelText: 'Keterangan (opsional)',
                    prefixIcon: Icon(Icons.notes_rounded),
                    alignLabelWithHint: true,
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontSize: 12,
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
                key: const Key('save-academic-year'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Tahun'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  Future<void> _pickDate({required bool start}) async {
    final now = DateTime.now();
    final initialDate = start
        ? (_startDate ?? DateTime(now.year, 7))
        : (_endDate ?? _startDate ?? DateTime(now.year + 1, 6, 30));
    final value = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime(2000),
      lastDate: DateTime(now.year + 20, 12, 31),
    );
    if (value == null || !mounted) return;
    setState(() {
      if (start) {
        _startDate = value;
      } else {
        _endDate = value;
      }
    });
  }

  void _submit() {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      setState(() => _error = 'Nama tahun pelajaran wajib diisi.');
      return;
    }
    if (_startDate != null &&
        _endDate != null &&
        _endDate!.isBefore(_startDate!)) {
      setState(
        () => _error = 'Tanggal selesai tidak boleh mendahului tanggal mulai.',
      );
      return;
    }

    Navigator.pop(
      context,
      AcademicYearFormValue(
        name: name,
        startDate: _startDate,
        endDate: _endDate,
        active: _active,
        notes: _notesController.text.trim().isEmpty
            ? null
            : _notesController.text.trim(),
      ),
    );
  }
}

class _DatePickerField extends StatelessWidget {
  const _DatePickerField({
    required this.fieldKey,
    required this.clearKey,
    required this.label,
    required this.value,
    required this.onTap,
    this.onClear,
  });

  final Key fieldKey;
  final Key clearKey;
  final String label;
  final DateTime? value;
  final VoidCallback onTap;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    child: InkWell(
      key: fieldKey,
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: const Icon(Icons.calendar_today_outlined),
          suffixIcon: onClear == null
              ? const Icon(Icons.chevron_right_rounded)
              : IconButton(
                  key: clearKey,
                  tooltip: 'Kosongkan $label',
                  onPressed: onClear,
                  icon: const Icon(Icons.close_rounded),
                ),
        ),
        child: Text(
          value == null ? 'Belum dipilih' : _dateLabel(value),
          style: TextStyle(
            color: value == null
                ? NusaColors.textSecondary
                : NusaColors.textPrimary,
          ),
        ),
      ),
    ),
  );
}

class _AcademicYearError extends StatelessWidget {
  const _AcademicYearError({required this.message, required this.onRetry});

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

String _periodLabel(DateTime? start, DateTime? end) {
  if (start == null && end == null) return 'Periode belum ditentukan';
  return '${_dateLabel(start)} – ${_dateLabel(end)}';
}

String _dateLabel(DateTime? value) {
  if (value == null) return '-';
  const months = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
  ];
  return '${value.day} ${months[value.month - 1]} ${value.year}';
}

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Data tahun pelajaran belum dapat diproses.';
}
