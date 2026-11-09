import 'package:flutter/material.dart';

Future<String?> showExamUnlockDialog(
  BuildContext context, {
  required String participantName,
}) => showDialog<String>(
  context: context,
  builder: (_) => _ExamUnlockDialog(participantName: participantName),
);

class _ExamUnlockDialog extends StatefulWidget {
  const _ExamUnlockDialog({required this.participantName});

  final String participantName;

  @override
  State<_ExamUnlockDialog> createState() => _ExamUnlockDialogState();
}

class _ExamUnlockDialogState extends State<_ExamUnlockDialog> {
  final _formKey = GlobalKey<FormState>();
  final _reasonController = TextEditingController();

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  void _submit() {
    if (_formKey.currentState?.validate() != true) return;
    Navigator.pop(context, _reasonController.text.trim());
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Buka ujian siswa?'),
    scrollable: true,
    content: Form(
      key: _formKey,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            '${widget.participantName} dapat melanjutkan ujian setelah pemeriksaan petugas. Jawaban dan riwayat tetap disimpan; waktu ujian tidak ditambah.',
          ),
          const SizedBox(height: 16),
          TextFormField(
            key: const Key('exam-unlock-reason'),
            controller: _reasonController,
            minLines: 3,
            maxLines: 5,
            maxLength: 500,
            textCapitalization: TextCapitalization.sentences,
            autovalidateMode: AutovalidateMode.onUserInteraction,
            decoration: const InputDecoration(
              labelText: 'Alasan pembukaan',
              hintText: 'Jelaskan hasil pemeriksaan dan alasan siswa diizinkan melanjutkan.',
              helperText:
                  'Wajib 10–500 karakter. Dicatat bersama identitas petugas.',
              helperMaxLines: 3,
            ),
            validator: (value) {
              final reason = value?.trim() ?? '';
              if (reason.isEmpty) return 'Alasan pembukaan wajib diisi.';
              if (reason.runes.length < 10) {
                return 'Alasan minimal 10 karakter.';
              }
              if (reason.runes.length > 500) {
                return 'Alasan maksimal 500 karakter.';
              }
              return null;
            },
          ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Batal'),
      ),
      FilledButton(
        key: const Key('exam-unlock-submit'),
        onPressed: _submit,
        child: const Text('Buka Ujian'),
      ),
    ],
  );
}
