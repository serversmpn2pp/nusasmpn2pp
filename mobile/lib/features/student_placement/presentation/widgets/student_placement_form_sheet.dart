import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_placement/domain/student_placement.dart';

class StudentPlacementFormSheet extends StatefulWidget {
  const StudentPlacementFormSheet({
    required this.selectedClass,
    required this.studentIds,
    super.key,
  });

  final StudentPlacementClass selectedClass;
  final List<int> studentIds;

  @override
  State<StudentPlacementFormSheet> createState() =>
      _StudentPlacementFormSheetState();
}

class _StudentPlacementFormSheetState extends State<StudentPlacementFormSheet> {
  final _notesController = TextEditingController();
  DateTime? _entryDate;

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
      height: (MediaQuery.sizeOf(context).height * 0.62).clamp(390.0, 560.0),
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
                const Expanded(
                  child: Text(
                    'Konfirmasi Penempatan',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
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
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: NusaColors.surfaceBlue,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: NusaColors.primary.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(13),
                        ),
                        child: const Icon(
                          Icons.group_add_outlined,
                          color: NusaColors.primary,
                        ),
                      ),
                      const SizedBox(width: 11),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${widget.studentIds.length} siswa → ${widget.selectedClass.name}',
                              style: const TextStyle(
                                color: NusaColors.textPrimary,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              _capacityText(widget.selectedClass),
                              style: const TextStyle(
                                color: NusaColors.textSecondary,
                                fontSize: 11,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                InkWell(
                  key: const Key('student-placement-entry-date'),
                  borderRadius: BorderRadius.circular(14),
                  onTap: _pickDate,
                  child: InputDecorator(
                    decoration: const InputDecoration(
                      labelText: 'Tanggal masuk (opsional)',
                      prefixIcon: Icon(Icons.event_outlined),
                      suffixIcon: Icon(Icons.calendar_month_rounded),
                    ),
                    child: Text(
                      _entryDate == null
                          ? 'Mengikuti awal tahun pelajaran'
                          : _formatDate(_entryDate!),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('student-placement-notes'),
                  controller: _notesController,
                  minLines: 2,
                  maxLines: 4,
                  maxLength: 1000,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Keterangan (opsional)',
                    hintText: 'Contoh: Penempatan siswa baru',
                    alignLabelWithHint: true,
                    prefixIcon: Icon(Icons.notes_rounded),
                  ),
                ),
                const SizedBox(height: 4),
                const Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      Icons.info_outline_rounded,
                      size: 18,
                      color: NusaColors.primary,
                    ),
                    SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Siswa yang sudah berada di kelas lain pada tahun yang sama tidak dapat ditempatkan kembali.',
                        style: TextStyle(fontSize: 11, height: 1.35),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('confirm-student-placement'),
                onPressed: _submit,
                icon: const Icon(Icons.check_circle_outline_rounded),
                label: Text('Tempatkan ${widget.studentIds.length} Siswa'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _entryDate ?? now,
      firstDate: DateTime(now.year - 3),
      lastDate: DateTime(now.year + 3, 12, 31),
    );
    if (picked != null) setState(() => _entryDate = picked);
  }

  void _submit() => Navigator.pop(
    context,
    StudentPlacementFormValue(
      classId: widget.selectedClass.id,
      studentIds: widget.studentIds,
      entryDate: _entryDate,
      notes: _notesController.text.trim(),
    ),
  );
}

String _capacityText(StudentPlacementClass item) {
  if (item.capacity == null) {
    return '${item.memberCount} anggota · Tanpa batas kapasitas';
  }
  return '${item.memberCount}/${item.capacity} anggota · ${item.remainingSeats ?? 0} kursi tersedia';
}

String _formatDate(DateTime value) {
  final day = value.day.toString().padLeft(2, '0');
  final month = value.month.toString().padLeft(2, '0');
  return '$day/$month/${value.year}';
}
