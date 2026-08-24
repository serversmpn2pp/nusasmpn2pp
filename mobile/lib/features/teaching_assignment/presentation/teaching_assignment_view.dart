import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_assignment/application/teaching_assignment_controller.dart';
import 'package:nusa/features/teaching_assignment/domain/teaching_assignment.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class TeachingAssignmentView extends ConsumerStatefulWidget {
  const TeachingAssignmentView({super.key});

  @override
  ConsumerState<TeachingAssignmentView> createState() =>
      _TeachingAssignmentViewState();
}

class _TeachingAssignmentViewState
    extends ConsumerState<TeachingAssignmentView> {
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
    final assignments = ref.watch(teachingAssignmentControllerProvider);
    final current = assignments.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Guru Mata Pelajaran'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: assignments.isLoading
                ? null
                : () => ref
                      .read(teachingAssignmentControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-teaching-assignment'),
              onPressed: _mutating ? null : () => _openForm(),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah Penugasan'),
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
                    _AssignmentSummary(counts: current.counts),
                    const SizedBox(height: 10),
                  ],
                  NusaTextField(
                    fieldKey: const Key('teaching-assignment-search'),
                    controller: _searchController,
                    hintText: 'Cari guru, mapel, atau kelas',
                    prefixIcon: Icons.search_rounded,
                    onChanged: _search,
                    suffixIcon: _searchController.text.isEmpty
                        ? null
                        : IconButton(
                            onPressed: () {
                              _searchController.clear();
                              setState(() {});
                              ref
                                  .read(
                                    teachingAssignmentControllerProvider
                                        .notifier,
                                  )
                                  .search('');
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                  ),
                  const SizedBox(height: 8),
                  if (current != null)
                    _AcademicYearFilter(
                      selectedId: current.academicYearId,
                      academicYears: current.academicYears,
                      enabled: !assignments.isLoading,
                      onSelected: (value) => ref
                          .read(teachingAssignmentControllerProvider.notifier)
                          .filterAcademicYear(value),
                    ),
                  const SizedBox(height: 8),
                  _AssignmentStatusFilter(
                    selected: current?.status ?? 'semua',
                    enabled: !assignments.isLoading,
                    onSelected: (value) => ref
                        .read(teachingAssignmentControllerProvider.notifier)
                        .filterStatus(value),
                  ),
                ],
              ),
            ),
            Expanded(
              child: assignments.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _AssignmentError(
                  message: _errorMessage(error),
                  onRetry: () => ref
                      .read(teachingAssignmentControllerProvider.notifier)
                      .refresh(),
                ),
                data: (page) => _AssignmentResults(
                  page: page,
                  loadingMore: _loadingMore,
                  mutating: _mutating,
                  onRefresh: () => ref
                      .read(teachingAssignmentControllerProvider.notifier)
                      .refresh(),
                  onLoadMore: _loadMore,
                  onEdit: page.canManage ? (item) => _openForm(item) : null,
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
        ref.read(teachingAssignmentControllerProvider.notifier).search(value);
      }
    });
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(teachingAssignmentControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm([TeachingAssignment? existing]) async {
    setState(() => _mutating = true);
    late TeachingAssignmentReference reference;
    try {
      reference = await ref.read(teachingAssignmentReferenceProvider.future);
    } catch (error) {
      if (mounted) _showError(error);
      return;
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
    if (!mounted) return;

    final value = await showModalBottomSheet<_AssignmentFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          _AssignmentFormSheet(reference: reference, existing: existing),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? 'Penugasan guru berhasil ditambahkan.'
          : 'Penugasan guru berhasil diperbarui.',
      operation: existing == null
          ? () => ref
                .read(teachingAssignmentActionsProvider)
                .create(
                  academicYearId: value.academicYearId,
                  classIds: value.classIds,
                  subjectId: value.subjectId,
                  employeeId: value.employeeId,
                  assignmentType: value.assignmentType,
                  active: value.active,
                  notes: value.notes,
                )
          : () => ref
                .read(teachingAssignmentActionsProvider)
                .update(
                  id: existing.id,
                  academicYearId: value.academicYearId,
                  classId: value.classIds.single,
                  subjectId: value.subjectId,
                  employeeId: value.employeeId,
                  assignmentType: value.assignmentType,
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
      await ref.read(teachingAssignmentControllerProvider.future);
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

class _AcademicYearFilter extends StatelessWidget {
  const _AcademicYearFilter({
    required this.selectedId,
    required this.academicYears,
    required this.enabled,
    required this.onSelected,
  });

  final int? selectedId;
  final List<AssignmentYearOption> academicYears;
  final bool enabled;
  final ValueChanged<int?> onSelected;

  @override
  Widget build(BuildContext context) => NusaDropdownField<int?>(
    fieldKey: const Key('teaching-assignment-year-filter'),
    value: selectedId,
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
      for (final year in academicYears)
        NusaDropdownOption<int?>(
          value: year.id,
          label: year.active ? '${year.name} • Aktif' : year.name,
        ),
    ],
    onChanged: onSelected,
  );
}

class _AssignmentSummary extends StatelessWidget {
  const _AssignmentSummary({required this.counts});

  final AssignmentCounts counts;

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

class _AssignmentStatusFilter extends StatelessWidget {
  const _AssignmentStatusFilter({
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

class _AssignmentResults extends StatelessWidget {
  const _AssignmentResults({
    required this.page,
    required this.loadingMore,
    required this.mutating,
    required this.onRefresh,
    required this.onLoadMore,
    this.onEdit,
  });

  final TeachingAssignmentPage page;
  final bool loadingMore;
  final bool mutating;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<TeachingAssignment>? onEdit;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(48),
          children: const [
            Icon(Icons.co_present_rounded, size: 52, color: NusaColors.primary),
            SizedBox(height: 12),
            Text(
              'Belum ada penugasan pada filter ini.',
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
        key: const PageStorageKey<String>('teaching-assignment-list'),
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
                    '${page.pagination.total} penugasan ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }

          final assignment = page.items[index];
          return _AssignmentCard(
            assignment: assignment,
            onTap: onEdit == null || mutating
                ? null
                : () => onEdit!(assignment),
          );
        },
      ),
    );
  }
}

class _AssignmentCard extends StatelessWidget {
  const _AssignmentCard({required this.assignment, this.onTap});

  final TeachingAssignment assignment;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Material(
    key: Key('teaching-assignment-${assignment.id}'),
    color: Colors.white,
    borderRadius: BorderRadius.circular(16),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          border: Border.all(color: NusaColors.outline),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: NusaColors.surfaceBlue,
                borderRadius: BorderRadius.circular(13),
              ),
              child: const Icon(
                Icons.menu_book_rounded,
                color: NusaColors.primary,
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    assignment.subject?.name ?? 'Mata pelajaran',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    assignment.employee?.name ?? 'Guru belum ditentukan',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 6,
                    runSpacing: 5,
                    children: [
                      _MiniTag(
                        icon: Icons.class_outlined,
                        label: assignment.schoolClass?.name ?? '-',
                      ),
                      _MiniTag(
                        icon: Icons.calendar_month_outlined,
                        label: assignment.academicYear?.name ?? '-',
                      ),
                      _MiniTag(
                        icon: Icons.assignment_ind_outlined,
                        label: assignment.assignmentTypeLabel,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 7),
            Column(
              children: [
                _AssignmentStatus(active: assignment.active),
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

class _MiniTag extends StatelessWidget {
  const _MiniTag({required this.icon, required this.label});
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

class _AssignmentStatus extends StatelessWidget {
  const _AssignmentStatus({required this.active});
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

class _AssignmentFormValue {
  const _AssignmentFormValue({
    required this.academicYearId,
    required this.classIds,
    required this.subjectId,
    required this.employeeId,
    required this.assignmentType,
    required this.active,
    this.notes,
  });

  final int academicYearId;
  final List<int> classIds;
  final int subjectId;
  final int employeeId;
  final String assignmentType;
  final bool active;
  final String? notes;
}

class _AssignmentFormSheet extends StatefulWidget {
  const _AssignmentFormSheet({required this.reference, this.existing});

  final TeachingAssignmentReference reference;
  final TeachingAssignment? existing;

  @override
  State<_AssignmentFormSheet> createState() => _AssignmentFormSheetState();
}

class _AssignmentFormSheetState extends State<_AssignmentFormSheet> {
  late int? _yearId;
  late int? _employeeId;
  late int? _subjectId;
  late Set<int> _classIds;
  late String _type;
  late bool _active;
  late final TextEditingController _notesController;
  String? _error;

  bool get _editing => widget.existing != null;

  AssignmentSubjectOption? get _subject => widget.reference.subjects
      .where((item) => item.id == _subjectId)
      .firstOrNull;

  List<AssignmentClassOption> get _availableClasses {
    final availableIds = _subject?.availableClassIds.toSet() ?? const <int>{};
    return widget.reference.classes
        .where(
          (item) =>
              item.academicYearId == _yearId && availableIds.contains(item.id),
        )
        .toList(growable: false);
  }

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _yearId =
        existing?.academicYear?.id ??
        widget.reference.academicYears
            .firstWhere(
              (item) => item.active,
              orElse: () => widget.reference.academicYears.first,
            )
            .id;
    _employeeId = existing?.employee?.id;
    _subjectId = existing?.subject?.id;
    _classIds = existing?.schoolClass == null
        ? {}
        : {existing!.schoolClass!.id};
    _type = existing?.assignmentType ?? 'pengampu';
    _active = existing?.active ?? true;
    _notesController = TextEditingController(text: existing?.notes);
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.92,
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
                    _editing ? 'Ubah Penugasan Guru' : 'Tambah Penugasan Guru',
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
                NusaDropdownField<int>(
                  fieldKey: const Key('assignment-form-year'),
                  value: _yearId,
                  decoration: const InputDecoration(
                    labelText: 'Tahun pelajaran',
                    prefixIcon: Icon(Icons.calendar_month_rounded),
                  ),
                  options: [
                    for (final year in widget.reference.academicYears)
                      NusaDropdownOption(value: year.id, label: year.name),
                  ],
                  onChanged: (value) => setState(() {
                    _yearId = value;
                    _classIds.clear();
                  }),
                ),
                const SizedBox(height: 11),
                NusaDropdownField<int>(
                  fieldKey: const Key('assignment-form-employee'),
                  value: _employeeId,
                  decoration: const InputDecoration(
                    labelText: 'Guru / pegawai',
                    hintText: 'Pilih guru / pegawai',
                    prefixIcon: Icon(Icons.person_outline_rounded),
                  ),
                  options: [
                    for (final employee in widget.reference.employees)
                      NusaDropdownOption(
                        value: employee.id,
                        label: employee.name,
                      ),
                  ],
                  onChanged: (value) => setState(() => _employeeId = value),
                ),
                const SizedBox(height: 11),
                NusaDropdownField<int>(
                  fieldKey: const Key('assignment-form-subject'),
                  value: _subjectId,
                  decoration: const InputDecoration(
                    labelText: 'Mata pelajaran',
                    hintText: 'Pilih mata pelajaran',
                    prefixIcon: Icon(Icons.menu_book_rounded),
                  ),
                  options: [
                    for (final subject in widget.reference.subjects)
                      NusaDropdownOption(
                        value: subject.id,
                        label: subject.name,
                      ),
                  ],
                  onChanged: (value) => setState(() {
                    _subjectId = value;
                    _classIds.clear();
                  }),
                ),
                const SizedBox(height: 14),
                const Text(
                  'Kelas yang diajar',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 4),
                Text(
                  _subjectId == null
                      ? 'Pilih mata pelajaran terlebih dahulu.'
                      : (_editing
                            ? 'Pilih satu kelas untuk penugasan ini.'
                            : 'Anda dapat memilih beberapa kelas sekaligus.'),
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
                const SizedBox(height: 8),
                if (_subjectId != null && _availableClasses.isEmpty)
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: NusaColors.accent.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: const Text(
                      'Mapel ini belum tersedia pada kelas di tahun pelajaran terpilih.',
                      style: TextStyle(fontSize: 11),
                    ),
                  )
                else
                  Wrap(
                    spacing: 7,
                    runSpacing: 7,
                    children: [
                      for (final schoolClass in _availableClasses)
                        FilterChip(
                          key: Key('assignment-class-${schoolClass.id}'),
                          label: Text(schoolClass.name),
                          selected: _classIds.contains(schoolClass.id),
                          onSelected: (selected) => setState(() {
                            if (_editing) _classIds.clear();
                            if (selected) {
                              _classIds.add(schoolClass.id);
                            } else {
                              _classIds.remove(schoolClass.id);
                            }
                          }),
                        ),
                    ],
                  ),
                const SizedBox(height: 14),
                NusaDropdownField<String>(
                  fieldKey: const Key('assignment-form-type'),
                  value: _type,
                  decoration: const InputDecoration(
                    labelText: 'Jenis penugasan',
                    prefixIcon: Icon(Icons.assignment_ind_outlined),
                  ),
                  options: [
                    for (final type in widget.reference.assignmentTypes)
                      NusaDropdownOption(value: type.code, label: type.label),
                  ],
                  onChanged: (value) => setState(() => _type = value ?? _type),
                ),
                const SizedBox(height: 8),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Penugasan aktif'),
                  subtitle: const Text(
                    'Pengampu aktif tersedia saat menyusun jadwal.',
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
                key: const Key('save-teaching-assignment'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Penugasan'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  void _submit() {
    if (_yearId == null || _employeeId == null || _subjectId == null) {
      setState(() => _error = 'Tahun, guru, dan mata pelajaran wajib dipilih.');
      return;
    }
    if (_classIds.isEmpty) {
      setState(() => _error = 'Pilih setidaknya satu kelas.');
      return;
    }

    Navigator.pop(
      context,
      _AssignmentFormValue(
        academicYearId: _yearId!,
        classIds: _classIds.toList(growable: false),
        subjectId: _subjectId!,
        employeeId: _employeeId!,
        assignmentType: _type,
        active: _active,
        notes: _notesController.text.trim().isEmpty
            ? null
            : _notesController.text.trim(),
      ),
    );
  }
}

class _AssignmentError extends StatelessWidget {
  const _AssignmentError({required this.message, required this.onRetry});

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
    : 'Penugasan guru mata pelajaran belum dapat diproses.';
