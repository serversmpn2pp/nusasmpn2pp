import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_assistance/application/student_assistance_controller.dart';
import 'package:nusa/features/student_assistance/domain/student_assistance.dart';
import 'package:nusa/features/student_assistance/presentation/widgets/student_assistance_form.dart';

class StudentAssistanceDetailView extends ConsumerStatefulWidget {
  const StudentAssistanceDetailView({required this.assistanceId, super.key});
  final int assistanceId;

  @override
  ConsumerState<StudentAssistanceDetailView> createState() =>
      _StudentAssistanceDetailViewState();
}

class _StudentAssistanceDetailViewState
    extends ConsumerState<StudentAssistanceDetailView> {
  final _noteController = TextEditingController();
  final _resultController = TextEditingController();
  int? _loadedId;
  String _type = '';
  int? _officerId;
  String _date = '';
  String _status = 'dalam_proses';
  bool _submitting = false;

  @override
  void dispose() {
    _noteController.dispose();
    _resultController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(
      studentAssistanceDetailProvider(widget.assistanceId),
    );
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Pendampingan'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : () => ref.invalidate(
                    studentAssistanceDetailProvider(widget.assistanceId),
                  ),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _Error(
            message: _message(error),
            onRetry: () => ref.invalidate(
              studentAssistanceDetailProvider(widget.assistanceId),
            ),
          ),
          data: (detail) {
            _initialize(detail);
            return SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _StudentHeader(item: detail.item),
                  if (detail.item.warning != null) ...[
                    const SizedBox(height: 10),
                    _SourceCard(warning: detail.item.warning!),
                  ],
                  const SizedBox(height: 12),
                  Card(
                    margin: EdgeInsets.zero,
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: detail.access.canManage
                          ? StudentAssistanceForm(
                              types: detail.types,
                              officers: detail.officers,
                              statuses: detail.statuses,
                              type: _type,
                              officerId: _officerId,
                              date: _date,
                              status: _status,
                              noteController: _noteController,
                              resultController: _resultController,
                              loading: _submitting,
                              buttonLabel: _status == 'selesai'
                                  ? 'Simpan dan Tandai Selesai'
                                  : 'Simpan Perubahan',
                              onTypeChanged: (value) =>
                                  setState(() => _type = value),
                              onOfficerChanged: (value) =>
                                  setState(() => _officerId = value),
                              onStatusChanged: (value) =>
                                  setState(() => _status = value),
                              onPickDate: _pickDate,
                              onSubmit: () => _confirmSubmit(detail),
                            )
                          : _ReadOnly(item: detail.item),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  void _initialize(StudentAssistanceDetail detail) {
    if (_loadedId == detail.item.id) return;
    _loadedId = detail.item.id;
    _type = detail.item.type;
    _officerId = detail.item.officer?.id;
    _date = detail.item.date;
    _status = detail.item.status;
    _noteController.text = detail.item.note;
    _resultController.text = detail.item.result ?? '';
  }

  Future<void> _pickDate() async {
    final current = DateTime.tryParse(_date) ?? DateTime.now();
    final result = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime(current.year - 2),
      lastDate: DateTime(current.year + 2),
    );
    if (result != null) setState(() => _date = _isoDate(result));
  }

  Future<void> _confirmSubmit(StudentAssistanceDetail detail) async {
    if (_officerId == null || _noteController.text.trim().isEmpty) {
      _snack('Petugas dan catatan pendampingan wajib diisi.');
      return;
    }
    if (_status == 'selesai' && _resultController.text.trim().isEmpty) {
      _snack('Hasil penanganan wajib diisi sebelum pendampingan diselesaikan.');
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          _status == 'selesai'
              ? 'Selesaikan pendampingan?'
              : 'Simpan perubahan?',
        ),
        content: Text(
          _status == 'selesai'
              ? 'Pastikan hasil penanganan telah menggambarkan kesepakatan atau perubahan yang dicapai.'
              : 'Catatan terbaru akan tersimpan sebagai perkembangan pendampingan siswa.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('student-assistance-confirm-submit'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
    if (confirmed == true) await _submit(detail);
  }

  Future<void> _submit(StudentAssistanceDetail detail) async {
    setState(() => _submitting = true);
    try {
      final next = await ref
          .read(studentAssistanceActionsProvider)
          .update(
            detail.item.id,
            StudentAssistancePayload(
              type: _type,
              officerId: _officerId!,
              date: _date,
              note: _noteController.text.trim(),
              status: _status,
              result: _resultController.text.trim().isEmpty
                  ? null
                  : _resultController.text.trim(),
            ),
          );
      ref.invalidate(studentAssistanceControllerProvider);
      ref.invalidate(studentAssistanceDetailProvider(widget.assistanceId));
      _loadedId = null;
      if (mounted) {
        _snack(
          next.item.status == 'selesai'
              ? 'Pendampingan siswa telah ditandai selesai.'
              : 'Pendampingan siswa berhasil diperbarui.',
        );
      }
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

class _StudentHeader extends StatelessWidget {
  const _StudentHeader({required this.item});
  final StudentAssistanceItem item;

  @override
  Widget build(BuildContext context) {
    final completed = item.status == 'selesai';
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 25,
            backgroundColor: Colors.white.withValues(alpha: 0.14),
            child: const Icon(Icons.person_rounded, color: Colors.white),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.student.name,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '${item.schoolClass?.name ?? 'Tanpa kelas'} · NISN ${item.student.nisn ?? '-'}\n${item.academicYear?.name ?? '-'}',
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 10,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
            decoration: BoxDecoration(
              color: completed
                  ? NusaColors.success.withValues(alpha: 0.9)
                  : NusaColors.accent,
              borderRadius: BorderRadius.circular(11),
            ),
            child: Text(
              item.statusLabel,
              style: TextStyle(
                color: completed ? Colors.white : NusaColors.primaryDark,
                fontSize: 9,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SourceCard extends StatelessWidget {
  const _SourceCard({required this.warning});
  final AssistanceWarning warning;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF7DA),
      borderRadius: BorderRadius.circular(13),
      border: Border.all(color: NusaColors.accent.withValues(alpha: 0.4)),
    ),
    child: Row(
      children: [
        const Icon(Icons.warning_amber_rounded, color: Color(0xFFC58F00)),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            'Sumber: ${warning.typeLabel}\n${warning.title}',
            style: const TextStyle(fontSize: 10.5, height: 1.4),
          ),
        ),
      ],
    ),
  );
}

class _ReadOnly extends StatelessWidget {
  const _ReadOnly({required this.item});
  final StudentAssistanceItem item;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      const Text(
        'Rincian Pendampingan',
        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
      ),
      const SizedBox(height: 12),
      _Row(label: 'Jenis', value: item.typeLabel),
      _Row(label: 'Tanggal', value: item.date),
      _Row(label: 'Petugas', value: item.officer?.name ?? '-'),
      _Row(label: 'Catatan', value: item.note),
      _Row(label: 'Hasil', value: _filled(item.result) ? item.result! : '-'),
      const SizedBox(height: 8),
      const Text(
        'Anda dapat memantau catatan ini, tetapi tidak memiliki hak untuk mengubahnya.',
        style: TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
      ),
    ],
  );
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 70,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10,
            ),
          ),
        ),
        Expanded(child: Text(value, style: const TextStyle(fontSize: 10.5))),
      ],
    ),
  );
}

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

bool _filled(String? value) => value != null && value.trim().isNotEmpty;
String _isoDate(DateTime date) =>
    '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Detail pendampingan belum dapat diproses.',
};
