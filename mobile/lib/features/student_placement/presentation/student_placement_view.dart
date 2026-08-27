import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_placement/application/student_placement_controller.dart';
import 'package:nusa/features/student_placement/domain/student_placement.dart';
import 'package:nusa/features/student_placement/presentation/widgets/student_placement_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentPlacementView extends ConsumerStatefulWidget {
  const StudentPlacementView({super.key});

  @override
  ConsumerState<StudentPlacementView> createState() =>
      _StudentPlacementViewState();
}

class _StudentPlacementViewState extends ConsumerState<StudentPlacementView> {
  final _searchController = TextEditingController();
  final Set<int> _selectedStudentIds = {};
  Timer? _debounce;
  bool _showMembers = false;
  bool _mutating = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final placement = ref.watch(studentPlacementControllerProvider);
    final current = placement.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Penempatan Siswa'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: placement.isLoading || _mutating ? null : _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton:
          current?.canManage == true &&
              current?.selectedClass != null &&
              _selectedStudentIds.isNotEmpty
          ? FloatingActionButton.extended(
              key: const Key('place-selected-students'),
              onPressed: _mutating
                  ? null
                  : () => _openPlacement(current!.selectedClass!),
              icon: const Icon(Icons.group_add_outlined),
              label: Text('Tempatkan (${_selectedStudentIds.length})'),
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
                    _PlacementSummary(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaDropdownField<int>(
                      fieldKey: const Key('student-placement-academic-year'),
                      value: current.selectedAcademicYearId,
                      options: current.academicYears
                          .map(
                            (item) => NusaDropdownOption(
                              value: item.id,
                              label:
                                  '${item.name}${item.active ? ' · Aktif' : ''} (${item.classCount} kelas)',
                            ),
                          )
                          .toList(growable: false),
                      decoration: const InputDecoration(
                        labelText: 'Tahun pelajaran',
                        prefixIcon: Icon(Icons.event_note_outlined),
                      ),
                      enabled: !placement.isLoading && !_mutating,
                      onChanged: _selectAcademicYear,
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<int>(
                      fieldKey: const Key('student-placement-class'),
                      value: current.selectedClassId,
                      options: current.classes
                          .map(
                            (item) => NusaDropdownOption(
                              value: item.id,
                              label:
                                  '${item.name} · ${item.memberCount}${item.capacity == null ? '' : '/${item.capacity}'} siswa',
                            ),
                          )
                          .toList(growable: false),
                      decoration: const InputDecoration(
                        labelText: 'Kelas tujuan',
                        prefixIcon: Icon(Icons.meeting_room_outlined),
                      ),
                      enabled:
                          !placement.isLoading &&
                          !_mutating &&
                          current.classes.isNotEmpty,
                      onChanged: _selectClass,
                    ),
                    if (current.selectedClass != null) ...[
                      const SizedBox(height: 9),
                      _ClassStatusCard(item: current.selectedClass!),
                      const SizedBox(height: 9),
                      _PlacementTabs(
                        showMembers: _showMembers,
                        availableCount: current.availableStudents.length,
                        memberCount: current.members.length,
                        onChanged: (value) => setState(() {
                          _showMembers = value;
                          if (value) _selectedStudentIds.clear();
                        }),
                      ),
                      if (!_showMembers) ...[
                        const SizedBox(height: 8),
                        NusaTextField(
                          fieldKey: const Key('student-placement-search'),
                          controller: _searchController,
                          hintText: 'Cari nama, NIS, atau NISN',
                          prefixIcon: Icons.search_rounded,
                          enabled: !placement.isLoading && !_mutating,
                          onChanged: _search,
                          suffixIcon: _searchController.text.isEmpty
                              ? null
                              : IconButton(
                                  onPressed: _clearSearch,
                                  icon: const Icon(Icons.close_rounded),
                                ),
                        ),
                      ],
                    ],
                  ],
                ),
              ),
            Expanded(
              child: placement.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _PlacementError(
                  message: _errorMessage(error),
                  onRetry: _refresh,
                ),
                data: (page) => page.selectedClass == null
                    ? const _EmptyPlacement(
                        icon: Icons.meeting_room_outlined,
                        message: 'Belum ada kelas yang dapat dipilih pada tahun pelajaran ini.',
                      )
                    : _showMembers
                    ? _MemberResults(page: page, onRefresh: _refresh)
                    : _AvailableStudentResults(
                        page: page,
                        selectedIds: _selectedStudentIds,
                        mutating: _mutating,
                        onRefresh: _refresh,
                        onToggle: _toggleStudent,
                        onSelectVisible: () => _selectVisible(page),
                        onClearSelection: () =>
                            setState(_selectedStudentIds.clear),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _selectAcademicYear(int? value) async {
    if (value == null) return;
    _resetInteraction();
    await ref
        .read(studentPlacementControllerProvider.notifier)
        .selectAcademicYear(value);
  }

  Future<void> _selectClass(int? value) async {
    if (value == null) return;
    _resetInteraction();
    await ref
        .read(studentPlacementControllerProvider.notifier)
        .selectClass(value);
  }

  void _resetInteraction() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {
      _selectedStudentIds.clear();
      _showMembers = false;
    });
  }

  void _search(String value) {
    setState(_selectedStudentIds.clear);
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) {
        ref.read(studentPlacementControllerProvider.notifier).search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(_selectedStudentIds.clear);
    ref.read(studentPlacementControllerProvider.notifier).search('');
  }

  void _toggleStudent(StudentPlacementPage page, int id, bool selected) {
    if (!selected) {
      setState(() => _selectedStudentIds.remove(id));
      return;
    }
    final remaining = page.selectedClass?.remainingSeats;
    if (remaining != null && _selectedStudentIds.length >= remaining) {
      _showMessage('Pilihan sudah mencapai sisa kapasitas kelas.');
      return;
    }
    setState(() => _selectedStudentIds.add(id));
  }

  void _selectVisible(StudentPlacementPage page) {
    final remaining = page.selectedClass?.remainingSeats;
    final limit = remaining ?? page.availableStudents.length;
    setState(() {
      _selectedStudentIds
        ..clear()
        ..addAll(page.availableStudents.take(limit).map((item) => item.id));
    });
    if (remaining != null && page.availableStudents.length > remaining) {
      _showMessage('Dipilih $remaining siswa sesuai sisa kapasitas kelas.');
    }
  }

  Future<void> _openPlacement(StudentPlacementClass selectedClass) async {
    final value = await showModalBottomSheet<StudentPlacementFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => StudentPlacementFormSheet(
        selectedClass: selectedClass,
        studentIds: _selectedStudentIds.toList(growable: false),
      ),
    );
    if (value == null || !mounted) return;

    setState(() => _mutating = true);
    try {
      final count = await ref
          .read(studentPlacementActionsProvider)
          .place(value);
      _selectedStudentIds.clear();
      await ref.read(studentPlacementControllerProvider.notifier).refresh();
      if (!mounted) return;
      _showMessage(
        '$count siswa berhasil ditempatkan ke ${selectedClass.name}.',
      );
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _refresh() async {
    _selectedStudentIds.clear();
    await ref.read(studentPlacementControllerProvider.notifier).refresh();
    if (mounted) setState(() {});
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _PlacementSummary extends StatelessWidget {
  const _PlacementSummary({required this.summary});

  final StudentPlacementSummary summary;

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
        _SummaryItem(label: 'Siswa aktif', value: summary.activeStudents),
        _SummaryItem(label: 'Ditempatkan', value: summary.placed),
        _SummaryItem(label: 'Belum', value: summary.unplaced),
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
          maxLines: 1,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.72),
            fontSize: 10,
          ),
        ),
      ],
    ),
  );
}

class _ClassStatusCard extends StatelessWidget {
  const _ClassStatusCard({required this.item});

  final StudentPlacementClass item;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
    decoration: BoxDecoration(
      color: item.remainingSeats == 0
          ? NusaColors.accent.withValues(alpha: 0.11)
          : NusaColors.successSurface,
      borderRadius: BorderRadius.circular(15),
      border: Border.all(
        color:
            (item.remainingSeats == 0 ? NusaColors.accent : NusaColors.success)
                .withValues(alpha: 0.24),
      ),
    ),
    child: Row(
      children: [
        Icon(
          item.remainingSeats == 0
              ? Icons.event_seat_rounded
              : Icons.groups_2_outlined,
          color: NusaColors.primary,
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                _capacityText(item),
                style: const TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                'Wali kelas: ${item.homeroomTeacher ?? 'Belum ditetapkan'}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10,
                ),
              ),
            ],
          ),
        ),
        _StatusBadge(active: item.active),
      ],
    ),
  );
}

class _PlacementTabs extends StatelessWidget {
  const _PlacementTabs({
    required this.showMembers,
    required this.availableCount,
    required this.memberCount,
    required this.onChanged,
  });

  final bool showMembers;
  final int availableCount;
  final int memberCount;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: FilterChip(
          key: const Key('student-placement-available-tab'),
          label: SizedBox(
            width: double.infinity,
            child: Text('Belum ($availableCount)', textAlign: TextAlign.center),
          ),
          selected: !showMembers,
          showCheckmark: false,
          onSelected: (_) => onChanged(false),
        ),
      ),
      const SizedBox(width: 8),
      Expanded(
        child: FilterChip(
          key: const Key('student-placement-member-tab'),
          label: SizedBox(
            width: double.infinity,
            child: Text('Anggota ($memberCount)', textAlign: TextAlign.center),
          ),
          selected: showMembers,
          showCheckmark: false,
          onSelected: (_) => onChanged(true),
        ),
      ),
    ],
  );
}

class _AvailableStudentResults extends StatelessWidget {
  const _AvailableStudentResults({
    required this.page,
    required this.selectedIds,
    required this.mutating,
    required this.onRefresh,
    required this.onToggle,
    required this.onSelectVisible,
    required this.onClearSelection,
  });

  final StudentPlacementPage page;
  final Set<int> selectedIds;
  final bool mutating;
  final Future<void> Function() onRefresh;
  final void Function(StudentPlacementPage, int, bool) onToggle;
  final VoidCallback onSelectVisible;
  final VoidCallback onClearSelection;

  @override
  Widget build(BuildContext context) {
    final full = page.selectedClass?.remainingSeats == 0;
    if (!page.canManage) {
      return const _EmptyPlacement(
        icon: Icons.lock_outline_rounded,
        message: 'Akun ini hanya dapat melihat anggota kelas.',
      );
    }
    if (full) {
      return const _EmptyPlacement(
        icon: Icons.event_seat_rounded,
        message:
            'Kapasitas kelas sudah penuh. Pilih kelas lain untuk penempatan.',
      );
    }
    if (page.availableStudents.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(36),
          children: const [
            Icon(Icons.how_to_reg_rounded, size: 50, color: NusaColors.success),
            SizedBox(height: 12),
            Text(
              'Tidak ada siswa aktif yang cocok dan belum ditempatkan.',
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
        key: const PageStorageKey<String>('student-placement-available-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 2, 16, 92),
        itemCount: page.availableStudents.length + 1,
        separatorBuilder: (context, index) => const SizedBox(height: 7),
        itemBuilder: (context, index) {
          if (index == 0) {
            return Row(
              children: [
                Expanded(
                  child: Text(
                    selectedIds.isEmpty
                        ? '${page.availableStudents.length} siswa tersedia'
                        : '${selectedIds.length} siswa dipilih',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                TextButton(
                  key: const Key('select-visible-students'),
                  onPressed: mutating
                      ? null
                      : selectedIds.isEmpty
                      ? onSelectVisible
                      : onClearSelection,
                  child: Text(
                    selectedIds.isEmpty ? 'Pilih semua' : 'Hapus pilihan',
                  ),
                ),
              ],
            );
          }

          final student = page.availableStudents[index - 1];
          return _StudentTile(
            key: Key('available-student-${student.id}'),
            student: student,
            selected: selectedIds.contains(student.id),
            enabled: !mutating,
            onChanged: (selected) => onToggle(page, student.id, selected),
          );
        },
      ),
    );
  }
}

class _MemberResults extends StatelessWidget {
  const _MemberResults({required this.page, required this.onRefresh});

  final StudentPlacementPage page;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    if (page.members.isEmpty) {
      return const _EmptyPlacement(
        icon: Icons.group_off_outlined,
        message: 'Belum ada siswa yang ditempatkan di kelas ini.',
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        key: const PageStorageKey<String>('student-placement-member-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 2, 16, 30),
        itemCount: page.members.length,
        separatorBuilder: (context, index) => const SizedBox(height: 7),
        itemBuilder: (context, index) {
          final member = page.members[index];
          return Card(
            key: Key('placed-student-${member.student.id}'),
            child: ListTile(
              leading: _StudentAvatar(student: member.student),
              title: Text(
                member.student.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
              subtitle: Text(
                'NISN ${member.student.nisn ?? '-'} · Masuk ${member.entryDate ?? '-'}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 10),
              ),
              trailing: member.rollNumber == null
                  ? _StatusBadge(active: member.status == 'aktif')
                  : CircleAvatar(
                      radius: 15,
                      backgroundColor: NusaColors.surfaceBlue,
                      child: Text(
                        '${member.rollNumber}',
                        style: const TextStyle(
                          color: NusaColors.primary,
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
            ),
          );
        },
      ),
    );
  }
}

class _StudentTile extends StatelessWidget {
  const _StudentTile({
    required this.student,
    required this.selected,
    required this.enabled,
    required this.onChanged,
    super.key,
  });

  final StudentPlacementStudent student;
  final bool selected;
  final bool enabled;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) => Card(
    color: selected ? NusaColors.surfaceBlue : null,
    child: CheckboxListTile(
      value: selected,
      onChanged: enabled ? (value) => onChanged(value ?? false) : null,
      secondary: _StudentAvatar(student: student),
      title: Text(
        student.name,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
      ),
      subtitle: Text(
        'NIS ${student.nis ?? '-'} · NISN ${student.nisn ?? '-'}',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontSize: 10),
      ),
      controlAffinity: ListTileControlAffinity.trailing,
      contentPadding: const EdgeInsets.only(left: 12, right: 4),
    ),
  );
}

class _StudentAvatar extends StatelessWidget {
  const _StudentAvatar({required this.student});

  final StudentPlacementStudent student;

  @override
  Widget build(BuildContext context) => CircleAvatar(
    radius: 20,
    backgroundColor: NusaColors.primary.withValues(alpha: 0.1),
    child: Text(
      _initials(student.name),
      style: const TextStyle(
        color: NusaColors.primary,
        fontSize: 11,
        fontWeight: FontWeight.w800,
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

class _EmptyPlacement extends StatelessWidget {
  const _EmptyPlacement({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(36),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 50, color: NusaColors.primary),
          const SizedBox(height: 12),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: NusaColors.textSecondary),
          ),
        ],
      ),
    ),
  );
}

class _PlacementError extends StatelessWidget {
  const _PlacementError({required this.message, required this.onRetry});

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

String _capacityText(StudentPlacementClass item) {
  if (item.capacity == null) {
    return '${item.memberCount} anggota · Tanpa batas kapasitas';
  }
  return '${item.memberCount}/${item.capacity} anggota · ${item.remainingSeats ?? 0} kursi tersedia';
}

String _initials(String name) {
  final parts = name
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty);
  return parts.take(2).map((part) => part[0].toUpperCase()).join();
}

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Penempatan siswa belum dapat diproses.';
}
