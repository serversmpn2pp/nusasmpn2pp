import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/subject/application/subject_controller.dart';
import 'package:nusa/features/subject/domain/subject.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class SubjectView extends ConsumerStatefulWidget {
  const SubjectView({super.key});

  @override
  ConsumerState<SubjectView> createState() => _SubjectViewState();
}

class _SubjectViewState extends ConsumerState<SubjectView> {
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
    final subjects = ref.watch(subjectControllerProvider);
    final current = subjects.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Mata Pelajaran'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: subjects.isLoading
                ? null
                : () => ref.read(subjectControllerProvider.notifier).refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-subject'),
              onPressed: _mutating
                  ? null
                  : () => _openForm(initialYearId: current!.academicYearId),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah Mapel'),
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
                    _SubjectSummary(counts: current.counts),
                    const SizedBox(height: 10),
                  ],
                  NusaTextField(
                    fieldKey: const Key('subject-search'),
                    controller: _searchController,
                    hintText: 'Cari nama, kode, atau kelompok',
                    prefixIcon: Icons.search_rounded,
                    onChanged: _search,
                    suffixIcon: _searchController.text.isEmpty
                        ? null
                        : IconButton(
                            onPressed: () {
                              _searchController.clear();
                              setState(() {});
                              ref
                                  .read(subjectControllerProvider.notifier)
                                  .search('');
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                  ),
                  const SizedBox(height: 8),
                  if (current != null)
                    Row(
                      children: [
                        Expanded(
                          flex: 3,
                          child: NusaDropdownField<int>(
                            fieldKey: const Key('subject-year-filter'),
                            value: current.academicYearId,
                            enabled: !subjects.isLoading,
                            decoration: const InputDecoration(
                              labelText: 'Tahun pelajaran',
                              prefixIcon: Icon(Icons.calendar_month_rounded),
                            ),
                            options: [
                              for (final year in current.academicYears)
                                NusaDropdownOption(
                                  value: year.id,
                                  label: year.active
                                      ? '${year.name} • Aktif'
                                      : year.name,
                                ),
                            ],
                            onChanged: (value) {
                              if (value != null) {
                                ref
                                    .read(subjectControllerProvider.notifier)
                                    .filterAcademicYear(value);
                              }
                            },
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          flex: 2,
                          child: NusaDropdownField<String>(
                            fieldKey: const Key('subject-level-filter'),
                            value: current.level,
                            enabled: !subjects.isLoading,
                            decoration: const InputDecoration(
                              labelText: 'Tingkat',
                            ),
                            options: const [
                              NusaDropdownOption(
                                value: 'semua',
                                label: 'Semua',
                              ),
                              NusaDropdownOption(value: '7', label: 'VII'),
                              NusaDropdownOption(value: '8', label: 'VIII'),
                              NusaDropdownOption(value: '9', label: 'IX'),
                            ],
                            onChanged: (value) {
                              if (value != null) {
                                ref
                                    .read(subjectControllerProvider.notifier)
                                    .filterLevel(value);
                              }
                            },
                          ),
                        ),
                      ],
                    ),
                  const SizedBox(height: 8),
                  _SubjectStatusFilter(
                    selected: current?.status ?? 'semua',
                    enabled: !subjects.isLoading,
                    onSelected: (value) => ref
                        .read(subjectControllerProvider.notifier)
                        .filterStatus(value),
                  ),
                ],
              ),
            ),
            Expanded(
              child: subjects.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _SubjectError(
                  message: _errorMessage(error),
                  onRetry: () =>
                      ref.read(subjectControllerProvider.notifier).refresh(),
                ),
                data: (page) => _SubjectResults(
                  page: page,
                  loadingMore: _loadingMore,
                  mutating: _mutating,
                  onRefresh: () =>
                      ref.read(subjectControllerProvider.notifier).refresh(),
                  onLoadMore: _loadMore,
                  onEdit: page.canManage
                      ? (item) => _openForm(
                          existing: item,
                          initialYearId: page.academicYearId,
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
      if (mounted) ref.read(subjectControllerProvider.notifier).search(value);
    });
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(subjectControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openForm({
    Subject? existing,
    required int initialYearId,
  }) async {
    setState(() => _mutating = true);
    late SubjectReference reference;
    try {
      reference = await ref.read(subjectReferenceProvider.future);
    } catch (error) {
      if (mounted) _showError(error);
      return;
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
    if (!mounted) return;

    final value = await showModalBottomSheet<SubjectFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _SubjectFormSheet(
        reference: reference,
        initialYearId: initialYearId,
        existing: existing,
      ),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? 'Mata pelajaran berhasil ditambahkan.'
          : 'Mata pelajaran berhasil diperbarui.',
      operation: existing == null
          ? () => ref.read(subjectActionsProvider).create(value)
          : () => ref
                .read(subjectActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(subjectControllerProvider.notifier).refresh();
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

class _SubjectSummary extends StatelessWidget {
  const _SubjectSummary({required this.counts});

  final SubjectCounts counts;

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

class _SubjectStatusFilter extends StatelessWidget {
  const _SubjectStatusFilter({
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

class _SubjectResults extends StatelessWidget {
  const _SubjectResults({
    required this.page,
    required this.loadingMore,
    required this.mutating,
    required this.onRefresh,
    required this.onLoadMore,
    this.onEdit,
  });

  final SubjectPage page;
  final bool loadingMore;
  final bool mutating;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<Subject>? onEdit;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(48),
          children: const [
            Icon(Icons.menu_book_rounded, size: 52, color: NusaColors.primary),
            SizedBox(height: 12),
            Text(
              'Belum ada mata pelajaran pada filter ini.',
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
        key: const PageStorageKey<String>('subject-list'),
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
                    '${page.pagination.total} mata pelajaran ditampilkan',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  );
          }

          final subject = page.items[index];
          return _SubjectCard(
            subject: subject,
            onTap: onEdit == null || mutating ? null : () => onEdit!(subject),
          );
        },
      ),
    );
  }
}

class _SubjectCard extends StatelessWidget {
  const _SubjectCard({required this.subject, this.onTap});

  final Subject subject;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final activeSettings = subject.settings
        .where((setting) => setting.active)
        .toList(growable: false);

    return Material(
      key: Key('subject-${subject.id}'),
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
                      subject.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${subject.group ?? 'Kelompok belum diisi'} • ${subject.assessmentTypeLabel}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                    const SizedBox(height: 7),
                    if (activeSettings.isEmpty)
                      const _SubjectTag(
                        icon: Icons.info_outline_rounded,
                        label: 'Belum diatur',
                      )
                    else
                      Wrap(
                        spacing: 6,
                        runSpacing: 5,
                        children: [
                          for (final setting in activeSettings)
                            _SubjectTag(
                              icon: Icons.stairs_rounded,
                              label:
                                  '${_roman(setting.level)} • ${setting.code}${subject.usesPredicate ? ' • Predikat' : ' • KKM ${setting.minimumScore ?? '-'}'}',
                            ),
                        ],
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 7),
              Column(
                children: [
                  _SubjectStatus(active: subject.active),
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
}

class _SubjectTag extends StatelessWidget {
  const _SubjectTag({required this.icon, required this.label});

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
        Flexible(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

class _SubjectStatus extends StatelessWidget {
  const _SubjectStatus({required this.active});

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

class _SubjectFormSheet extends StatefulWidget {
  const _SubjectFormSheet({
    required this.reference,
    required this.initialYearId,
    this.existing,
  });

  final SubjectReference reference;
  final int initialYearId;
  final Subject? existing;

  @override
  State<_SubjectFormSheet> createState() => _SubjectFormSheetState();
}

class _SubjectFormSheetState extends State<_SubjectFormSheet> {
  late int _yearId;
  late String? _group;
  late bool _active;
  late final TextEditingController _nameController;
  late final TextEditingController _orderController;
  late final TextEditingController _notesController;
  late final Map<int, _LevelFormState> _levels;
  String? _error;

  bool get _editing => widget.existing != null;

  bool get _usesPredicate => widget.reference.groups
      .where((group) => group.name == _group)
      .any((group) => group.usesPredicate);

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _yearId = widget.initialYearId;
    _group = existing?.group;
    _active = existing?.active ?? true;
    _nameController = TextEditingController(text: existing?.name);
    _orderController = TextEditingController(text: '${existing?.order ?? 0}');
    _notesController = TextEditingController(text: existing?.notes);
    _levels = {
      for (final level in widget.reference.levels)
        level.value: _LevelFormState(
          level: level,
          setting: existing?.settingFor(level.value),
          activeByDefault: existing == null,
        ),
    };
  }

  @override
  void dispose() {
    _nameController.dispose();
    _orderController.dispose();
    _notesController.dispose();
    for (final level in _levels.values) {
      level.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.94,
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
                    _editing ? 'Ubah Mata Pelajaran' : 'Tambah Mata Pelajaran',
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
              key: const Key('subject-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                NusaDropdownField<int>(
                  fieldKey: const Key('subject-form-year'),
                  value: _yearId,
                  enabled: !_editing,
                  decoration: InputDecoration(
                    labelText: 'Tahun pengaturan',
                    prefixIcon: const Icon(Icons.calendar_month_rounded),
                    helperText: _editing
                        ? 'Ubah filter daftar untuk mengedit tahun lainnya.'
                        : 'Kode dan KKM disimpan per tahun pelajaran.',
                  ),
                  options: [
                    for (final year in widget.reference.academicYears)
                      NusaDropdownOption(
                        value: year.id,
                        label: year.active ? '${year.name} • Aktif' : year.name,
                      ),
                  ],
                  onChanged: (value) {
                    if (value != null) setState(() => _yearId = value);
                  },
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('subject-form-name'),
                  controller: _nameController,
                  textCapitalization: TextCapitalization.words,
                  maxLength: 255,
                  decoration: const InputDecoration(
                    labelText: 'Nama mata pelajaran',
                    hintText: 'Contoh: Matematika',
                    prefixIcon: Icon(Icons.menu_book_rounded),
                    counterText: '',
                  ),
                ),
                const SizedBox(height: 12),
                NusaDropdownField<String?>(
                  fieldKey: const Key('subject-form-group'),
                  value: _group,
                  decoration: const InputDecoration(
                    labelText: 'Jenis / kelompok',
                    prefixIcon: Icon(Icons.category_outlined),
                  ),
                  options: [
                    const NusaDropdownOption<String?>(
                      value: null,
                      label: 'Belum dipilih',
                    ),
                    for (final group in widget.reference.groups)
                      NusaDropdownOption<String?>(
                        value: group.name,
                        label: group.name,
                      ),
                  ],
                  onChanged: (value) => setState(() => _group = value),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color:
                        (_usesPredicate
                                ? NusaColors.accent
                                : NusaColors.primary)
                            .withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        _usesPredicate
                            ? Icons.workspace_premium_outlined
                            : Icons.calculate_outlined,
                        color: NusaColors.primary,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          _usesPredicate
                              ? 'Penilaian predikat SB / B / C / K tanpa KKM.'
                              : 'Penilaian angka 0–100; KKM/KKTP wajib diisi.',
                          style: const TextStyle(fontSize: 11.5),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('subject-form-order'),
                  controller: _orderController,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'Urutan tampil',
                    prefixIcon: Icon(Icons.format_list_numbered_rounded),
                  ),
                ),
                const SizedBox(height: 5),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Mata pelajaran aktif'),
                  subtitle: const Text('Tersedia pada proses akademik.'),
                  value: _active,
                  onChanged: (value) => setState(() => _active = value),
                ),
                const SizedBox(height: 5),
                const Text(
                  'Pengaturan per Tingkat',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 3),
                Text(
                  _usesPredicate
                      ? 'Aktifkan tingkat yang mengikuti kegiatan dan isi kodenya.'
                      : 'Aktifkan tingkat, lalu isi kode dan KKM/KKTP.',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
                const SizedBox(height: 9),
                for (final level in _levels.values) ...[
                  _LevelFormCard(
                    state: level,
                    usesPredicate: _usesPredicate,
                    onChanged: () => setState(() {}),
                  ),
                  const SizedBox(height: 9),
                ],
                TextField(
                  key: const Key('subject-form-notes'),
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
                key: const Key('save-subject'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(_editing ? 'Simpan Perubahan' : 'Simpan Mapel'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  void _submit() {
    final name = _nameController.text.trim();
    final order = int.tryParse(_orderController.text.trim());
    final activeLevels = _levels.values.where((level) => level.active).toList();

    if (name.isEmpty) {
      setState(() => _error = 'Nama mata pelajaran wajib diisi.');
      return;
    }
    if (order == null || order < 0 || order > 999) {
      setState(
        () => _error = 'Urutan tampil harus berada antara 0 sampai 999.',
      );
      return;
    }
    if (activeLevels.isEmpty) {
      setState(() => _error = 'Aktifkan minimal satu tingkat.');
      return;
    }
    for (final level in activeLevels) {
      if (level.codeController.text.trim().isEmpty) {
        setState(() => _error = 'Kode kelas ${level.level.label} wajib diisi.');
        return;
      }
      if (!_usesPredicate) {
        final score = int.tryParse(level.scoreController.text.trim());
        if (score == null || score < 0 || score > 100) {
          setState(
            () => _error =
                'KKM/KKTP kelas ${level.level.label} harus 0 sampai 100.',
          );
          return;
        }
      }
    }

    Navigator.pop(
      context,
      SubjectFormValue(
        academicYearId: _yearId,
        name: name,
        group: _group,
        order: order,
        active: _active,
        notes: _notesController.text.trim().isEmpty
            ? null
            : _notesController.text.trim(),
        settings: [
          for (final level in _levels.values)
            SubjectLevelSetting(
              level: level.level.value,
              code: level.codeController.text.trim(),
              minimumScore: _usesPredicate
                  ? null
                  : int.tryParse(level.scoreController.text.trim()),
              active: level.active,
            ),
        ],
      ),
    );
  }
}

class _LevelFormState {
  _LevelFormState({
    required this.level,
    required SubjectLevelSetting? setting,
    required bool activeByDefault,
  }) : active = setting?.active ?? activeByDefault,
       codeController = TextEditingController(text: setting?.code),
       scoreController = TextEditingController(
         text: setting?.minimumScore?.toString(),
       );

  final SubjectLevel level;
  bool active;
  final TextEditingController codeController;
  final TextEditingController scoreController;

  void dispose() {
    codeController.dispose();
    scoreController.dispose();
  }
}

class _LevelFormCard extends StatelessWidget {
  const _LevelFormCard({
    required this.state,
    required this.usesPredicate,
    required this.onChanged,
  });

  final _LevelFormState state;
  final bool usesPredicate;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: state.active ? Colors.white : NusaColors.background,
      border: Border.all(
        color: state.active ? NusaColors.primaryLight : NusaColors.outline,
      ),
      borderRadius: BorderRadius.circular(15),
    ),
    child: Column(
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Kelas ${state.level.label}',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            Switch.adaptive(
              key: Key('subject-level-${state.level.value}-active'),
              value: state.active,
              onChanged: (value) {
                state.active = value;
                onChanged();
              },
            ),
          ],
        ),
        if (state.active) ...[
          const SizedBox(height: 5),
          Row(
            children: [
              Expanded(
                flex: 3,
                child: TextField(
                  key: Key('subject-level-${state.level.value}-code'),
                  controller: state.codeController,
                  textCapitalization: TextCapitalization.characters,
                  maxLength: 30,
                  decoration: const InputDecoration(
                    labelText: 'Kode',
                    counterText: '',
                  ),
                ),
              ),
              if (!usesPredicate) ...[
                const SizedBox(width: 8),
                Expanded(
                  flex: 2,
                  child: TextField(
                    key: Key('subject-level-${state.level.value}-score'),
                    controller: state.scoreController,
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    decoration: const InputDecoration(labelText: 'KKM/KKTP'),
                  ),
                ),
              ],
            ],
          ),
        ],
      ],
    ),
  );
}

class _SubjectError extends StatelessWidget {
  const _SubjectError({required this.message, required this.onRetry});

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

String _roman(int level) => switch (level) {
  7 => 'VII',
  8 => 'VIII',
  9 => 'IX',
  _ => '$level',
};

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Data mata pelajaran belum dapat diproses.';
