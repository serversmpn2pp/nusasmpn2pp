import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_document/domain/teaching_document.dart';
import 'package:nusa/features/teaching_document_review/domain/teaching_document_review.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class TeachingDocumentReviewFormSheet extends StatefulWidget {
  const TeachingDocumentReviewFormSheet({required this.document, super.key});

  final TeachingDocument document;

  @override
  State<TeachingDocumentReviewFormSheet> createState() =>
      _TeachingDocumentReviewFormSheetState();
}

class _TeachingDocumentReviewFormSheetState
    extends State<TeachingDocumentReviewFormSheet> {
  late String _status;
  late final TextEditingController _noteController;
  String? _error;

  @override
  void initState() {
    super.initState();
    _status = widget.document.status == 'sudah_diperiksa'
        ? 'sudah_diperiksa'
        : 'perlu_perbaikan';
    _noteController = TextEditingController(text: widget.document.reviewerNote);
  }

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SafeArea(
      top: false,
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: NusaColors.outline,
                  borderRadius: BorderRadius.circular(4),
                ),
              ),
            ),
            const SizedBox(height: 15),
            const Text(
              'Hasil Pemeriksaan',
              style: TextStyle(
                color: NusaColors.textPrimary,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              widget.document.title,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 11,
              ),
            ),
            const SizedBox(height: 15),
            NusaDropdownField<String>(
              fieldKey: const Key('review-document-decision'),
              value: _status,
              decoration: const InputDecoration(
                labelText: 'Keputusan',
                prefixIcon: Icon(Icons.fact_check_outlined),
              ),
              options: const [
                NusaDropdownOption(
                  value: 'sudah_diperiksa',
                  label: 'Sudah diperiksa',
                ),
                NusaDropdownOption(
                  value: 'perlu_perbaikan',
                  label: 'Perlu perbaikan',
                ),
              ],
              onChanged: (value) {
                if (value != null) {
                  setState(() {
                    _status = value;
                    _error = null;
                  });
                }
              },
            ),
            const SizedBox(height: 11),
            TextField(
              key: const Key('review-document-note'),
              controller: _noteController,
              minLines: 4,
              maxLines: 7,
              textCapitalization: TextCapitalization.sentences,
              decoration: InputDecoration(
                labelText: _status == 'perlu_perbaikan'
                    ? 'Catatan pemeriksa *'
                    : 'Catatan pemeriksa (opsional)',
                hintText:
                    'Tuliskan bagian yang sudah baik atau perlu diperbaiki.',
                prefixIcon: const Icon(Icons.rate_review_outlined),
                alignLabelWithHint: true,
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(
                _error!,
                key: const Key('review-document-error'),
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontSize: 11,
                ),
              ),
            ],
            const SizedBox(height: 17),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Batal'),
                  ),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: FilledButton(
                    key: const Key('save-document-review'),
                    onPressed: _submit,
                    child: const Text('Simpan'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );

  void _submit() {
    final note = _noteController.text.trim();
    if (_status == 'perlu_perbaikan' && note.isEmpty) {
      setState(
        () => _error = 'Catatan wajib diisi agar guru mengetahui bagian yang harus diperbaiki.',
      );
      return;
    }
    Navigator.pop(
      context,
      TeachingDocumentReviewValue(
        status: _status,
        reviewerNote: note.isEmpty ? null : note,
      ),
    );
  }
}
