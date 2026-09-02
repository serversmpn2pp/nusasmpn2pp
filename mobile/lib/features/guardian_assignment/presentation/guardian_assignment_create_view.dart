import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/guardian_assignment/application/guardian_assignment_controller.dart';
import 'package:nusa/features/guardian_assignment/domain/guardian_assignment.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GuardianAssignmentCreateView extends ConsumerStatefulWidget {
  const GuardianAssignmentCreateView({super.key});

  @override
  ConsumerState<GuardianAssignmentCreateView> createState() =>
      _GuardianAssignmentCreateViewState();
}

class _GuardianAssignmentCreateViewState
    extends ConsumerState<GuardianAssignmentCreateView> {
  final _studentSearch = TextEditingController();
  final _decree = TextEditingController();
  final _note = TextEditingController();
  final Set<int> _selected = {};
  int? _guardianId;
  int? _classId;
  bool _unassignedOnly = false;
  bool _submitting = false;
  String _startDate = _isoDate(DateTime.now());

  @override
  void dispose() {
    _studentSearch.dispose();
    _decree.dispose();
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(guardianAssignmentControllerProvider);
    final page = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(title: const Text('Atur Siswa Guru Wali')),
      body: page == null
          ? state.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, stackTrace) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(_message(error), textAlign: TextAlign.center),
                ),
              ),
              data: (_) => const SizedBox.shrink(),
            )
          : _buildContent(page),
      bottomNavigationBar: page == null
          ? null
          : SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  border: Border(top: BorderSide(color: NusaColors.outline)),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${_selected.length} siswa dipilih',
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ),
                    SizedBox(
                      width: 155,
                      child: FilledButton(
                        key: const Key('guardian-assignment-submit'),
                        onPressed: _submitting ? null : () => _submit(page),
                        child: _submitting
                            ? const SizedBox.square(
                                dimension: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Text('Simpan'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildContent(GuardianAssignmentPage page) {
    final students = _filteredStudents(page);
    final transferCount = _transferCount(page);
    return CustomScrollView(
      slivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
          sliver: SliverList.list(
            children: [
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: NusaColors.outline),
                ),
                child: const Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.info_outline_rounded, color: NusaColors.primary),
                    SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Satu siswa hanya memiliki satu Guru Wali aktif. Pemindahan akan menutup penugasan lama tanpa menghapus riwayat.',
                        style: TextStyle(fontSize: 11, height: 1.4),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              NusaDropdownField<int>(
                fieldKey: const Key('guardian-assignment-guardian'),
                value: _guardianId,
                decoration: const InputDecoration(
                  labelText: 'Guru Wali',
                  hintText: 'Pilih pegawai',
                  prefixIcon: Icon(Icons.supervisor_account_rounded),
                ),
                options: [
                  for (final employee in page.options.employees)
                    NusaDropdownOption(
                      value: employee.id,
                      label:
                          '${employee.name} (${employee.activeStudentCount} siswa)',
                    ),
                ],
                onChanged: (value) => setState(() => _guardianId = value),
              ),
              const SizedBox(height: 10),
              InkWell(
                key: const Key('guardian-assignment-start-date'),
                onTap: _pickDate,
                borderRadius: BorderRadius.circular(14),
                child: InputDecorator(
                  decoration: const InputDecoration(
                    labelText: 'Mulai bertugas',
                    prefixIcon: Icon(Icons.event_rounded),
                  ),
                  child: Text(_dateLabel(_startDate)),
                ),
              ),
              const SizedBox(height: 10),
              TextField(
                key: const Key('guardian-assignment-decree'),
                controller: _decree,
                maxLength: 100,
                decoration: const InputDecoration(
                  labelText: 'Nomor SK (opsional)',
                  prefixIcon: Icon(Icons.description_outlined),
                ),
              ),
              TextField(
                key: const Key('guardian-assignment-note'),
                controller: _note,
                minLines: 2,
                maxLines: 4,
                maxLength: 2000,
                decoration: const InputDecoration(
                  labelText: 'Catatan (opsional)',
                  alignLabelWithHint: true,
                ),
              ),
              const SizedBox(height: 5),
              const Text(
                'Pilih Siswa',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              TextField(
                key: const Key('guardian-assignment-student-search'),
                controller: _studentSearch,
                onChanged: (_) => setState(() {}),
                decoration: const InputDecoration(
                  hintText: 'Cari nama, NIS, atau NISN',
                  prefixIcon: Icon(Icons.search_rounded),
                ),
              ),
              const SizedBox(height: 8),
              NusaDropdownField<int?>(
                fieldKey: const Key('guardian-assignment-class-filter'),
                value: _classId,
                decoration: const InputDecoration(labelText: 'Kelas'),
                options: [
                  const NusaDropdownOption(value: null, label: 'Semua kelas'),
                  for (final schoolClass in page.options.classes)
                    NusaDropdownOption(
                      value: schoolClass.id,
                      label: schoolClass.name,
                    ),
                ],
                onChanged: (value) => setState(() => _classId = value),
              ),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                value: _unassignedOnly,
                onChanged: (value) => setState(() => _unassignedOnly = value),
                title: const Text(
                  'Hanya siswa yang belum memiliki Guru Wali',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
                ),
              ),
              Wrap(
                spacing: 4,
                runSpacing: 2,
                children: [
                  TextButton.icon(
                    onPressed: students.isEmpty
                        ? null
                        : () => _selectVisible(students),
                    icon: const Icon(Icons.select_all_rounded),
                    label: const Text('Pilih tampil'),
                  ),
                  TextButton(
                    onPressed: _selected.isEmpty
                        ? null
                        : () => setState(_selected.clear),
                    child: const Text('Kosongkan'),
                  ),
                ],
              ),
              if (transferCount > 0)
                Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.all(11),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF8E1),
                    borderRadius: BorderRadius.circular(13),
                    border: Border.all(color: NusaColors.accent),
                  ),
                  child: Text(
                    '$transferCount siswa akan dipindahkan dari Guru Wali sebelumnya.',
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              if (students.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 28),
                  child: Text(
                    'Tidak ada siswa sesuai filter.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: NusaColors.textSecondary),
                  ),
                ),
            ],
          ),
        ),
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 100),
          sliver: SliverList.builder(
            itemCount: students.length,
            itemBuilder: (context, index) {
              final student = students[index];
              final checked = _selected.contains(student.id);
              return Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Card(
                  clipBehavior: Clip.antiAlias,
                  child: InkWell(
                    key: Key('guardian-assignment-student-${student.id}'),
                    onTap: () => _toggleStudent(student.id, !checked),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(8, 8, 12, 8),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Checkbox(
                            value: checked,
                            onChanged: (value) =>
                                _toggleStudent(student.id, value ?? false),
                          ),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  student.name,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  '${student.schoolClass?.name ?? 'Belum ditempatkan'} · NISN ${student.nisn ?? '-'}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(fontSize: 11),
                                ),
                                if (student.activeAssignment != null)
                                  Text(
                                    'Saat ini: ${student.activeAssignment!.guardian.name}',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: NusaColors.primary,
                                      fontSize: 10,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  void _toggleStudent(int studentId, bool selected) {
    setState(() {
      if (selected) {
        if (_selected.length < 200) _selected.add(studentId);
      } else {
        _selected.remove(studentId);
      }
    });
  }

  List<GuardianStudent> _filteredStudents(GuardianAssignmentPage page) {
    final query = _studentSearch.text.trim().toLowerCase();
    return page.options.students.where((student) {
      if (_classId != null && student.schoolClass?.id != _classId) return false;
      if (_unassignedOnly && student.activeAssignment != null) return false;
      if (query.isEmpty) return true;
      return '${student.name} ${student.nis ?? ''} ${student.nisn ?? ''}'
          .toLowerCase()
          .contains(query);
    }).toList();
  }

  int _transferCount(GuardianAssignmentPage page) => page.options.students
      .where(
        (student) =>
            _selected.contains(student.id) &&
            student.activeAssignment != null &&
            student.activeAssignment!.guardian.id != _guardianId,
      )
      .length;

  void _selectVisible(List<GuardianStudent> students) {
    setState(() {
      for (final student in students) {
        if (_selected.length >= 200) break;
        _selected.add(student.id);
      }
    });
  }

  Future<void> _pickDate() async {
    final selected = await showDatePicker(
      context: context,
      initialDate: DateTime.tryParse(_startDate) ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (selected != null && mounted) {
      setState(() => _startDate = _isoDate(selected));
    }
  }

  Future<void> _submit(GuardianAssignmentPage page) async {
    if (_guardianId == null) {
      _snack('Pilih Guru Wali terlebih dahulu.');
      return;
    }
    if (_selected.isEmpty) {
      _snack('Pilih minimal satu siswa.');
      return;
    }
    final transfers = _transferCount(page);
    if (transfers > 0) {
      final accepted = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Pindahkan Guru Wali?'),
          content: Text(
            '$transfers siswa sudah memiliki Guru Wali lain. Penugasan lama akan diakhiri dan riwayatnya tetap disimpan.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Batal'),
            ),
            FilledButton(
              key: const Key('guardian-assignment-confirm-transfer'),
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Pindahkan'),
            ),
          ],
        ),
      );
      if (accepted != true || !mounted) return;
    }
    setState(() => _submitting = true);
    try {
      final result = await ref
          .read(guardianAssignmentControllerProvider.notifier)
          .create(
            GuardianAssignmentPayload(
              guardianId: _guardianId!,
              studentIds: _selected.toList(),
              startDate: _startDate,
              decreeNumber: _optional(_decree.text),
              note: _optional(_note.text),
            ),
          );
      if (mounted) context.pop(result.message);
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

String _isoDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  if (date == null) return value;
  return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String? _optional(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Penugasan Guru Wali belum dapat diproses.',
};
