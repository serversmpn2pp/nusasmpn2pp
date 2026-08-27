import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_document/data/teaching_document_file_picker.dart';
import 'package:nusa/features/teaching_document/domain/teaching_document.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class TeachingDocumentFormSheet extends ConsumerStatefulWidget {
  const TeachingDocumentFormSheet.create({
    required this.page,
    required this.assignment,
    required this.type,
    super.key,
  }) : detail = null;

  const TeachingDocumentFormSheet.edit({required this.detail, super.key})
    : page = null,
      assignment = null,
      type = null;

  final TeachingDocumentPage? page;
  final TeachingDocumentAssignment? assignment;
  final TeachingDocumentType? type;
  final TeachingDocumentDetail? detail;

  @override
  ConsumerState<TeachingDocumentFormSheet> createState() =>
      _TeachingDocumentFormSheetState();
}

class _TeachingDocumentFormSheetState
    extends ConsumerState<TeachingDocumentFormSheet> {
  late int _grade;
  late final TextEditingController _titleController;
  late final TextEditingController _noteController;
  TeachingDocumentPickedFile? _file;
  String? _error;
  bool _picking = false;

  bool get _editing => widget.detail != null;

  TeachingDocumentUploadLimit get _uploadLimit =>
      widget.detail?.uploadLimit ?? widget.page!.uploadLimit;

  @override
  void initState() {
    super.initState();
    final document = widget.detail?.document;
    _grade = document?.grade ?? widget.assignment!.grade;
    _titleController = TextEditingController(
      text:
          document?.title ??
          '${widget.type!.name} ${widget.assignment!.subject.name} Tingkat ${widget.assignment!.gradeLabel}',
    );
    _noteController = TextEditingController(text: document?.teacherNote);
  }

  @override
  void dispose() {
    _titleController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.9,
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
                        ? 'Revisi Perangkat Ajar'
                        : 'Unggah Perangkat Ajar',
                    style: const TextStyle(
                      color: NusaColors.textPrimary,
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-teaching-document-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('teaching-document-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                _DocumentContext(
                  subject:
                      widget.detail?.document.subject?.name ??
                      widget.assignment!.subject.name,
                  type: widget.detail?.document.type?.name ?? widget.type!.name,
                  academicYear:
                      widget.detail?.document.academicYear?.name ??
                      widget.page!.academicYears
                          .where(
                            (year) =>
                                year.id == widget.page!.filter.academicYearId,
                          )
                          .firstOrNull
                          ?.name ??
                      '-',
                  semester: widget.page?.filter.semester,
                ),
                const SizedBox(height: 14),
                if (_editing && widget.detail!.availableGrades.length > 1)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 11),
                    child: NusaDropdownField<int>(
                      fieldKey: const Key('teaching-document-grade'),
                      value: _grade,
                      decoration: const InputDecoration(
                        labelText: 'Tingkat',
                        prefixIcon: Icon(Icons.stairs_rounded),
                      ),
                      options: [
                        for (final grade in widget.detail!.availableGrades)
                          NusaDropdownOption(
                            value: grade,
                            label: 'Tingkat ${_romanGrade(grade)}',
                          ),
                      ],
                      onChanged: (value) {
                        if (value != null) setState(() => _grade = value);
                      },
                    ),
                  ),
                TextField(
                  key: const Key('teaching-document-title'),
                  controller: _titleController,
                  textCapitalization: TextCapitalization.sentences,
                  maxLength: 180,
                  decoration: const InputDecoration(
                    labelText: 'Judul dokumen',
                    prefixIcon: Icon(Icons.title_rounded),
                  ),
                ),
                const SizedBox(height: 5),
                TextField(
                  key: const Key('teaching-document-note'),
                  controller: _noteController,
                  minLines: 2,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    labelText: 'Catatan guru (opsional)',
                    prefixIcon: Icon(Icons.notes_rounded),
                    alignLabelWithHint: true,
                  ),
                ),
                const SizedBox(height: 12),
                _FileSelector(
                  file: _file,
                  currentFileName: widget.detail?.document.fileName,
                  limitLabel: _uploadLimit.label,
                  picking: _picking,
                  onPick: _pickFile,
                  onRemove: () => setState(() => _file = null),
                ),
                if (_editing) ...[
                  const SizedBox(height: 8),
                  const Text(
                    'PDF baru bersifat opsional. Pilih PDF hanya jika isi berkas direvisi; revisi berkas akan kembali menunggu pemeriksaan.',
                    style: TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                      height: 1.35,
                    ),
                  ),
                ],
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    _error!,
                    key: const Key('teaching-document-form-error'),
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontSize: 12,
                    ),
                  ),
                ],
                const SizedBox(height: 18),
                FilledButton.icon(
                  key: const Key('save-teaching-document'),
                  onPressed: _picking ? null : _submit,
                  icon: Icon(
                    _editing
                        ? Icons.system_update_alt_rounded
                        : Icons.cloud_upload_outlined,
                  ),
                  label: Text(_editing ? 'Simpan Revisi' : 'Unggah PDF'),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );

  Future<void> _pickFile() async {
    setState(() {
      _picking = true;
      _error = null;
    });
    try {
      final file = await ref.read(teachingDocumentFilePickerProvider).pickPdf();
      if (!mounted || file == null) return;
      final isPdf = file.name.toLowerCase().endsWith('.pdf');
      if (!isPdf) {
        setState(() => _error = 'Berkas harus menggunakan format PDF.');
        return;
      }
      if (file.size > _uploadLimit.bytes) {
        setState(
          () => _error = 'Ukuran PDF melebihi batas ${_uploadLimit.label}.',
        );
        return;
      }
      setState(() => _file = file);
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Berkas tidak dapat dibaca. Silakan pilih ulang.',
        );
      }
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  void _submit() {
    final title = _titleController.text.trim();
    if (title.isEmpty) {
      setState(() => _error = 'Judul dokumen wajib diisi.');
      return;
    }
    if (!_editing && _file == null) {
      setState(() => _error = 'Pilih file PDF yang akan diunggah.');
      return;
    }

    Navigator.pop(
      context,
      TeachingDocumentFormValue(
        academicYearId: widget.page?.filter.academicYearId,
        semester: widget.page?.filter.semester,
        subjectId: widget.assignment?.subject.id,
        grade: _grade,
        typeId: widget.type?.id,
        title: title,
        teacherNote: _noteController.text,
        file: _file,
      ),
    );
  }
}

class _DocumentContext extends StatelessWidget {
  const _DocumentContext({
    required this.subject,
    required this.type,
    required this.academicYear,
    this.semester,
  });

  final String subject;
  final String type;
  final String academicYear;
  final int? semester;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(15),
    ),
    child: Row(
      children: [
        const Icon(Icons.menu_book_rounded, color: NusaColors.primary),
        const SizedBox(width: 11),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(type, style: const TextStyle(fontWeight: FontWeight.w800)),
              Text(
                '$subject · $academicYear${semester == null ? '' : ' · Semester $semester'}',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10.5,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _FileSelector extends StatelessWidget {
  const _FileSelector({
    required this.file,
    required this.currentFileName,
    required this.limitLabel,
    required this.picking,
    required this.onPick,
    required this.onRemove,
  });

  final TeachingDocumentPickedFile? file;
  final String? currentFileName;
  final String limitLabel;
  final bool picking;
  final VoidCallback onPick;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: NusaColors.surface,
      border: Border.all(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(15),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                'Berkas PDF',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            Text(
              'Maks. $limitLabel',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10,
              ),
            ),
          ],
        ),
        if (currentFileName != null && file == null) ...[
          const SizedBox(height: 6),
          Text(
            'Berkas saat ini: $currentFileName',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
            ),
          ),
        ],
        if (file != null) ...[
          const SizedBox(height: 9),
          Row(
            children: [
              const Icon(Icons.picture_as_pdf_rounded, color: Colors.redAccent),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  file!.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 12),
                ),
              ),
              IconButton(
                tooltip: 'Hapus pilihan',
                onPressed: onRemove,
                icon: const Icon(Icons.close_rounded),
              ),
            ],
          ),
        ],
        const SizedBox(height: 9),
        OutlinedButton.icon(
          key: const Key('pick-teaching-document-pdf'),
          onPressed: picking ? null : onPick,
          icon: picking
              ? const SizedBox.square(
                  dimension: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.folder_open_rounded),
          label: Text(file == null ? 'Pilih PDF' : 'Ganti PDF'),
        ),
      ],
    ),
  );
}

String _romanGrade(int grade) => switch (grade) {
  7 => 'VII',
  8 => 'VIII',
  9 => 'IX',
  _ => '$grade',
};
