import 'package:flutter/material.dart';
import 'package:nusa/features/central_exam_execution/domain/central_exam_execution.dart';

class CentralExamRetakeDialog extends StatefulWidget {
  const CentralExamRetakeDialog({required this.participant, super.key});
  final CentralExamParticipant participant;

  @override
  State<CentralExamRetakeDialog> createState() =>
      _CentralExamRetakeDialogState();
}

class _CentralExamRetakeDialogState extends State<CentralExamRetakeDialog> {
  final _form = GlobalKey<FormState>();
  final _reason = TextEditingController();

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Buka untuk Susulan?'),
    scrollable: true,
    content: Form(
      key: _form,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${widget.participant.name} · ${widget.participant.className}\n'
            'Nilai diterapkan: ${widget.participant.appliedScore ?? '-'}',
          ),
          const SizedBox(height: 12),
          const Text(
            'Penerapan nilai siswa ini dibatalkan dan nilai lama disimpan dalam riwayat. '
            'Jawaban tersimpan tetap dipertahankan. Nilai siswa lain tidak diubah. '
            'Publikasi nilai akademik mapel kembali menjadi draf.',
          ),
          const SizedBox(height: 12),
          TextFormField(
            key: const Key('central-exam-retake-reason'),
            controller: _reason,
            minLines: 2,
            maxLines: 4,
            maxLength: 1000,
            decoration: const InputDecoration(
              labelText: 'Alasan pembukaan',
              hintText: 'Jelaskan gangguan perangkat atau alasan yang disetujui panitia.',
              alignLabelWithHint: true,
            ),
            validator: (value) => (value?.trim().runes.length ?? 0) < 10
                ? 'Tuliskan alasan minimal 10 karakter.'
                : null,
          ),
          const SizedBox(height: 8),
          const Text(
            'Setelah dibuka, jadwalkan susulan melalui Pelaksanaan Ujian Terpusat di web. '
            'Hasil susulan perlu dikoreksi dan diterapkan kembali.',
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
        key: const Key('confirm-central-exam-retake'),
        onPressed: () {
          if (_form.currentState!.validate()) {
            Navigator.pop(context, _reason.text.trim());
          }
        },
        child: const Text('Buka untuk Susulan'),
      ),
    ],
  );
}
