import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_sanction/application/student_sanction_controller.dart';
import 'package:nusa/features/student_sanction/data/student_sanction_file_services.dart';
import 'package:nusa/features/student_sanction/domain/student_sanction.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentSanctionDetailView extends ConsumerStatefulWidget {
  const StudentSanctionDetailView({required this.sanctionId, super.key});
  final int sanctionId;

  @override
  ConsumerState<StudentSanctionDetailView> createState() =>
      _StudentSanctionDetailViewState();
}

class _StudentSanctionDetailViewState
    extends ConsumerState<StudentSanctionDetailView> {
  final _noteController = TextEditingController();
  final _resultController = TextEditingController();
  int? _loadedId;
  String _status = 'menunggu';
  int? _officerId;
  String? _deadline;
  bool _submitting = false;
  int? _busyEvidenceId;
  bool _uploading = false;

  @override
  void dispose() {
    _noteController.dispose();
    _resultController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(studentSanctionDetailProvider(widget.sanctionId));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Pelaksanaan Sanksi'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading ? null : _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) =>
              _Error(message: _message(error), onRetry: _refresh),
          data: (detail) {
            _initialize(detail);
            return SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _StudentHeader(item: detail.item),
                  const SizedBox(height: 11),
                  _ProgressCard(item: detail.item),
                  const SizedBox(height: 11),
                  _RuleCard(item: detail.item),
                  const SizedBox(height: 11),
                  if (detail.access.canManage && !detail.access.finalStatus)
                    _ManagementCard(
                      detail: detail,
                      status: _status,
                      officerId: _officerId,
                      deadline: _deadline,
                      noteController: _noteController,
                      resultController: _resultController,
                      loading: _submitting,
                      onStatusChanged: (value) =>
                          setState(() => _status = value),
                      onOfficerChanged: (value) =>
                          setState(() => _officerId = value),
                      onPickDeadline: _pickDeadline,
                      onSubmit: () => _confirmSubmit(detail),
                    )
                  else
                    _ExecutionCard(item: detail.item, access: detail.access),
                  const SizedBox(height: 11),
                  _EvidenceCard(
                    detail: detail,
                    uploading: _uploading,
                    busyEvidenceId: _busyEvidenceId,
                    onUpload:
                        detail.access.canManage &&
                            !detail.access.finalStatus &&
                            detail.evidence.length < 5
                        ? () => _uploadEvidence(detail)
                        : null,
                    onDownload: _downloadEvidence,
                    onDelete:
                        detail.access.canManage && !detail.access.finalStatus
                        ? _deleteEvidence
                        : null,
                  ),
                  const SizedBox(height: 11),
                  _PeopleCard(item: detail.item),
                  const SizedBox(height: 11),
                  _HistoryCard(history: detail.history),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  void _initialize(StudentSanctionDetail detail) {
    if (_loadedId == detail.item.id) return;
    _loadedId = detail.item.id;
    _status = detail.item.status;
    _officerId = detail.item.officer?.id;
    _deadline = detail.item.deadline;
    _noteController.text = detail.item.note ?? '';
    _resultController.text = detail.item.result ?? '';
  }

  void _refresh() {
    _loadedId = null;
    ref.invalidate(studentSanctionDetailProvider(widget.sanctionId));
  }

  Future<void> _pickDeadline() async {
    final triggered =
        ref
            .read(studentSanctionDetailProvider(widget.sanctionId))
            .value
            ?.item
            .triggeredAt ??
        DateTime.now();
    final initial = DateTime.tryParse(_deadline ?? '') ?? DateTime.now();
    final first = DateTime(triggered.year, triggered.month, triggered.day);
    final result = await showDatePicker(
      context: context,
      initialDate: initial.isBefore(first) ? first : initial,
      firstDate: first,
      lastDate: DateTime(first.year + 3),
    );
    if (result != null) setState(() => _deadline = _isoDate(result));
  }

  Future<void> _confirmSubmit(StudentSanctionDetail detail) async {
    if (['diproses', 'selesai'].contains(_status) && _officerId == null) {
      _snack('Petugas penanggung jawab wajib dipilih.');
      return;
    }
    if (_status == 'diproses' && !_filled(_deadline)) {
      _snack('Batas pelaksanaan wajib diisi saat sanksi mulai diproses.');
      return;
    }
    if (_status == 'selesai' && _resultController.text.trim().isEmpty) {
      _snack('Hasil pelaksanaan wajib diisi sebelum sanksi diselesaikan.');
      return;
    }
    if (_status == 'dibatalkan' && _noteController.text.trim().isEmpty) {
      _snack('Alasan pembatalan wajib ditulis pada catatan.');
      return;
    }
    final destructive = ['selesai', 'dibatalkan'].contains(_status);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          _status == 'selesai'
              ? 'Selesaikan sanksi?'
              : _status == 'dibatalkan'
              ? 'Batalkan sanksi?'
              : 'Simpan pelaksanaan?',
        ),
        content: Text(
          destructive
              ? 'Status ini bersifat final. Pastikan catatan dan hasil pelaksanaan sudah benar.'
              : 'Petugas, tenggat, dan catatan pelaksanaan akan diperbarui.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Kembali'),
          ),
          FilledButton(
            key: const Key('student-sanction-confirm-submit'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
    if (confirmed == true) await _submit(detail);
  }

  Future<void> _submit(StudentSanctionDetail detail) async {
    setState(() => _submitting = true);
    try {
      final next = await ref
          .read(studentSanctionActionsProvider)
          .update(
            detail.item.id,
            StudentSanctionPayload(
              status: _status,
              officerId: _officerId,
              deadline: _deadline,
              note: _emptyToNull(_noteController.text),
              result: _emptyToNull(_resultController.text),
            ),
          );
      _replace(next);
      if (mounted) _snack('Pelaksanaan sanksi berhasil diperbarui.');
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _uploadEvidence(StudentSanctionDetail detail) async {
    try {
      final files = await ref.read(studentSanctionFilePickerProvider).pick();
      if (!mounted || files.isEmpty) return;
      if (files.any((file) => file.bytes.length > 10 * 1024 * 1024)) {
        _snack('Ukuran setiap bukti maksimal 10 MB.');
        return;
      }
      if (detail.evidence.length + files.length > 5) {
        _snack('Maksimal lima bukti untuk satu pelaksanaan sanksi.');
        return;
      }
      final request = await _askEvidenceDescription(files);
      if (!mounted || request == null) return;
      setState(() => _uploading = true);
      final next = await ref
          .read(studentSanctionActionsProvider)
          .uploadEvidence(
            id: detail.item.id,
            files: files,
            description: _emptyToNull(request),
          );
      _replace(next);
      if (mounted) _snack('${files.length} bukti berhasil diunggah.');
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  Future<String?> _askEvidenceDescription(
    List<SanctionPickedFile> files,
  ) async {
    var description = '';
    final result = await showDialog<({bool upload, String text})>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Unggah Bukti Privat'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              '${files.length} file dipilih. Bukti hanya dapat dibuka oleh pengguna yang berhak melihat sanksi ini.',
              style: const TextStyle(fontSize: 11, height: 1.4),
            ),
            const SizedBox(height: 12),
            TextField(
              key: const Key('student-sanction-evidence-description'),
              onChanged: (value) => description = value,
              maxLength: 500,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Keterangan (opsional)',
                hintText: 'Contoh: dokumentasi pelaksanaan',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, (upload: false, text: '')),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('student-sanction-confirm-upload'),
            onPressed: () => Navigator.pop(context, (
              upload: true,
              text: description.trim(),
            )),
            child: const Text('Unggah'),
          ),
        ],
      ),
    );
    return result?.upload == true ? result!.text : null;
  }

  Future<void> _downloadEvidence(SanctionEvidence evidence) async {
    setState(() => _busyEvidenceId = evidence.id);
    try {
      final download = await ref
          .read(studentSanctionActionsProvider)
          .downloadEvidence(evidence);
      final saved = await ref
          .read(studentSanctionFileSaverProvider)
          .save(download);
      if (mounted) {
        _snack(saved ? 'Bukti berhasil disimpan.' : 'Penyimpanan dibatalkan.');
      }
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _busyEvidenceId = null);
    }
  }

  Future<void> _deleteEvidence(SanctionEvidence evidence) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Hapus bukti?'),
        content: Text(
          '${evidence.fileName} akan dihapus permanen dari bukti pelaksanaan sanksi.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('student-sanction-confirm-delete-evidence'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Hapus'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _busyEvidenceId = evidence.id);
    try {
      final next = await ref
          .read(studentSanctionActionsProvider)
          .deleteEvidence(evidence.id);
      _replace(next);
      if (mounted) _snack('Bukti pelaksanaan berhasil dihapus.');
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _busyEvidenceId = null);
    }
  }

  void _replace(StudentSanctionDetail detail) {
    _status = detail.item.status;
    _officerId = detail.item.officer?.id;
    _deadline = detail.item.deadline;
    _noteController.text = detail.item.note ?? '';
    _resultController.text = detail.item.result ?? '';
    ref.invalidate(studentSanctionControllerProvider);
    _loadedId = null;
    ref.invalidate(studentSanctionDetailProvider(widget.sanctionId));
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _StudentHeader extends StatelessWidget {
  const _StudentHeader({required this.item});
  final StudentSanctionItem item;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(item.status, item.overdue);
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
              color: color,
              borderRadius: BorderRadius.circular(11),
            ),
            child: Text(
              item.overdue ? 'Terlambat' : item.statusLabel,
              style: const TextStyle(
                color: Colors.white,
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

class _ProgressCard extends StatelessWidget {
  const _ProgressCard({required this.item});
  final StudentSanctionItem item;

  @override
  Widget build(BuildContext context) {
    final activeStep = switch (item.status) {
      'selesai' => 3,
      'diproses' => 2,
      'dibatalkan' => 0,
      _ => 1,
    };
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
        child: Row(
          children: [
            _Step(label: 'Terpicu', number: 1, activeStep: activeStep),
            _Line(active: activeStep >= 2),
            _Step(label: 'Diproses', number: 2, activeStep: activeStep),
            _Line(active: activeStep >= 3),
            _Step(label: 'Selesai', number: 3, activeStep: activeStep),
          ],
        ),
      ),
    );
  }
}

class _Step extends StatelessWidget {
  const _Step({
    required this.label,
    required this.number,
    required this.activeStep,
  });
  final String label;
  final int number;
  final int activeStep;

  @override
  Widget build(BuildContext context) {
    final completed = activeStep >= number;
    final current = activeStep == number;
    return Expanded(
      child: Column(
        children: [
          CircleAvatar(
            radius: 12,
            backgroundColor: completed
                ? NusaColors.primary
                : NusaColors.surfaceBlue,
            child: completed && !current
                ? const Icon(Icons.check_rounded, size: 15, color: Colors.white)
                : Text(
                    '$number',
                    style: TextStyle(
                      color: completed
                          ? Colors.white
                          : NusaColors.textSecondary,
                      fontSize: 9,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            maxLines: 1,
            style: TextStyle(
              color: completed ? NusaColors.primary : NusaColors.textSecondary,
              fontSize: 8.5,
              fontWeight: completed ? FontWeight.w800 : FontWeight.normal,
            ),
          ),
        ],
      ),
    );
  }
}

class _Line extends StatelessWidget {
  const _Line({required this.active});
  final bool active;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      height: 2,
      margin: const EdgeInsets.only(bottom: 18),
      color: active ? NusaColors.primary : NusaColors.surfaceBlue,
    ),
  );
}

class _RuleCard extends StatelessWidget {
  const _RuleCard({required this.item});
  final StudentSanctionItem item;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionTitle(icon: Icons.gavel_rounded, title: 'Sanksi'),
          const SizedBox(height: 10),
          Text(
            item.rule.name,
            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w900),
          ),
          if (_filled(item.rule.description)) ...[
            const SizedBox(height: 5),
            Text(
              item.rule.description!,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10.5,
                height: 1.4,
              ),
            ),
          ],
          const SizedBox(height: 10),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              _Chip(label: 'Batas ${item.rule.pointThreshold} poin'),
              _Chip(label: 'Terpicu ${item.triggerPoints} poin'),
              _Chip(label: _dateTimeLabel(item.triggeredAt)),
            ],
          ),
        ],
      ),
    ),
  );
}

class _ManagementCard extends StatelessWidget {
  const _ManagementCard({
    required this.detail,
    required this.status,
    required this.officerId,
    required this.deadline,
    required this.noteController,
    required this.resultController,
    required this.loading,
    required this.onStatusChanged,
    required this.onOfficerChanged,
    required this.onPickDeadline,
    required this.onSubmit,
  });
  final StudentSanctionDetail detail;
  final String status;
  final int? officerId;
  final String? deadline;
  final TextEditingController noteController;
  final TextEditingController resultController;
  final bool loading;
  final ValueChanged<String> onStatusChanged;
  final ValueChanged<int?> onOfficerChanged;
  final VoidCallback onPickDeadline;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _SectionTitle(
            icon: Icons.assignment_turned_in_rounded,
            title: 'Kelola Pelaksanaan',
          ),
          const SizedBox(height: 13),
          NusaDropdownField<String>(
            fieldKey: const Key('student-sanction-status'),
            value: status,
            enabled: !loading,
            decoration: const InputDecoration(labelText: 'Status'),
            options: [
              for (final item in detail.statusOptions)
                NusaDropdownOption(value: item.code, label: item.label),
            ],
            onChanged: (value) {
              if (value != null) onStatusChanged(value);
            },
          ),
          const SizedBox(height: 11),
          NusaDropdownField<int?>(
            fieldKey: const Key('student-sanction-officer'),
            value: officerId,
            enabled: !loading,
            decoration: const InputDecoration(
              labelText: 'Petugas penanggung jawab',
            ),
            options: [
              const NusaDropdownOption(value: null, label: 'Pilih petugas'),
              for (final item in detail.officers)
                NusaDropdownOption(value: item.id, label: item.name),
            ],
            onChanged: onOfficerChanged,
          ),
          const SizedBox(height: 11),
          InkWell(
            key: const Key('student-sanction-deadline'),
            onTap: loading ? null : onPickDeadline,
            borderRadius: BorderRadius.circular(14),
            child: InputDecorator(
              decoration: const InputDecoration(
                labelText: 'Batas pelaksanaan',
                prefixIcon: Icon(Icons.event_rounded),
              ),
              child: Text(
                _filled(deadline) ? _dateLabel(deadline!) : 'Pilih tanggal',
                style: TextStyle(
                  color: _filled(deadline)
                      ? NusaColors.textPrimary
                      : NusaColors.textSecondary,
                ),
              ),
            ),
          ),
          const SizedBox(height: 11),
          TextField(
            key: const Key('student-sanction-note'),
            controller: noteController,
            enabled: !loading,
            maxLength: 2000,
            minLines: 3,
            maxLines: 5,
            decoration: InputDecoration(
              labelText: status == 'dibatalkan'
                  ? 'Alasan pembatalan'
                  : 'Catatan pelaksanaan',
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 4),
          TextField(
            key: const Key('student-sanction-result'),
            controller: resultController,
            enabled: !loading,
            maxLength: 3000,
            minLines: 3,
            maxLines: 6,
            decoration: const InputDecoration(
              labelText: 'Hasil pelaksanaan',
              hintText: 'Wajib diisi saat sanksi diselesaikan',
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 8),
          NusaPrimaryButton(
            key: const Key('student-sanction-submit'),
            label: status == 'selesai'
                ? 'Simpan dan Tandai Selesai'
                : status == 'dibatalkan'
                ? 'Batalkan Sanksi'
                : status == 'diproses'
                ? 'Mulai / Perbarui Proses'
                : 'Simpan Penugasan',
            loading: loading,
            onPressed: onSubmit,
          ),
        ],
      ),
    ),
  );
}

class _ExecutionCard extends StatelessWidget {
  const _ExecutionCard({required this.item, required this.access});
  final StudentSanctionItem item;
  final StudentSanctionDetailAccess access;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionTitle(
            icon: Icons.assignment_turned_in_rounded,
            title: 'Pelaksanaan',
          ),
          const SizedBox(height: 11),
          _InfoRow(label: 'Status', value: item.statusLabel),
          _InfoRow(label: 'Petugas', value: item.officer?.name ?? '-'),
          _InfoRow(
            label: 'Batas',
            value: _filled(item.deadline) ? _dateLabel(item.deadline!) : '-',
          ),
          _InfoRow(label: 'Mulai', value: _dateTimeLabel(item.startedAt)),
          _InfoRow(label: 'Selesai', value: _dateTimeLabel(item.completedAt)),
          _InfoRow(label: 'Catatan', value: item.note ?? '-'),
          _InfoRow(label: 'Hasil', value: item.result ?? '-'),
          const SizedBox(height: 5),
          Text(
            access.finalStatus
                ? 'Pelaksanaan sudah berstatus final dan tidak dapat diubah.'
                : 'Anda dapat memantau pelaksanaan ini, tetapi tidak memiliki hak untuk mengubahnya.',
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10,
            ),
          ),
        ],
      ),
    ),
  );
}

class _EvidenceCard extends StatelessWidget {
  const _EvidenceCard({
    required this.detail,
    required this.uploading,
    required this.busyEvidenceId,
    required this.onUpload,
    required this.onDownload,
    required this.onDelete,
  });
  final StudentSanctionDetail detail;
  final bool uploading;
  final int? busyEvidenceId;
  final VoidCallback? onUpload;
  final ValueChanged<SanctionEvidence> onDownload;
  final ValueChanged<SanctionEvidence>? onDelete;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Expanded(
                child: _SectionTitle(
                  icon: Icons.verified_user_rounded,
                  title: 'Bukti Privat',
                ),
              ),
              if (onUpload != null)
                TextButton.icon(
                  key: const Key('student-sanction-upload-evidence'),
                  onPressed: uploading ? null : onUpload,
                  icon: uploading
                      ? const SizedBox.square(
                          dimension: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.upload_file_rounded, size: 18),
                  label: const Text('Tambah'),
                ),
            ],
          ),
          const SizedBox(height: 5),
          const Text(
            'Foto/PDF, maksimal 5 file dan 10 MB per file. Bukti tidak tersedia sebagai tautan publik.',
            style: TextStyle(color: NusaColors.textSecondary, fontSize: 9.5),
          ),
          if (detail.evidence.isEmpty) ...[
            const SizedBox(height: 13),
            Container(
              padding: const EdgeInsets.all(13),
              decoration: BoxDecoration(
                color: NusaColors.surfaceBlue,
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Text(
                'Belum ada bukti pelaksanaan.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 10.5),
              ),
            ),
          ] else
            for (final evidence in detail.evidence) ...[
              const Divider(height: 18),
              _EvidenceRow(
                evidence: evidence,
                busy: busyEvidenceId == evidence.id,
                canDownload: detail.access.canDownloadEvidence,
                canDelete: onDelete != null,
                onDownload: () => onDownload(evidence),
                onDelete: () => onDelete?.call(evidence),
              ),
            ],
        ],
      ),
    ),
  );
}

class _EvidenceRow extends StatelessWidget {
  const _EvidenceRow({
    required this.evidence,
    required this.busy,
    required this.canDownload,
    required this.canDelete,
    required this.onDownload,
    required this.onDelete,
  });
  final SanctionEvidence evidence;
  final bool busy;
  final bool canDownload;
  final bool canDelete;
  final VoidCallback onDownload;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: NusaColors.surfaceBlue,
          borderRadius: BorderRadius.circular(11),
        ),
        child: Icon(
          evidence.mimeType == 'application/pdf'
              ? Icons.picture_as_pdf_rounded
              : Icons.image_rounded,
          color: NusaColors.primary,
          size: 21,
        ),
      ),
      const SizedBox(width: 9),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              evidence.fileName,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            Text(
              '${evidence.fileSize} · ${evidence.uploadedBy ?? 'Sistem NUSA'}',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 9,
              ),
            ),
            if (_filled(evidence.description))
              Text(
                evidence.description!,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 9),
              ),
          ],
        ),
      ),
      if (busy)
        const Padding(
          padding: EdgeInsets.all(10),
          child: SizedBox.square(
            dimension: 18,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        )
      else ...[
        if (canDownload)
          IconButton(
            key: Key('student-sanction-download-${evidence.id}'),
            tooltip: 'Unduh bukti',
            onPressed: onDownload,
            icon: const Icon(Icons.download_rounded, size: 20),
          ),
        if (canDelete)
          IconButton(
            key: Key('student-sanction-delete-${evidence.id}'),
            tooltip: 'Hapus bukti',
            onPressed: onDelete,
            icon: const Icon(Icons.delete_outline_rounded, size: 20),
          ),
      ],
    ],
  );
}

class _PeopleCard extends StatelessWidget {
  const _PeopleCard({required this.item});
  final StudentSanctionItem item;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionTitle(
            icon: Icons.groups_rounded,
            title: 'Pendamping Siswa',
          ),
          const SizedBox(height: 10),
          _InfoRow(label: 'Petugas', value: item.officer?.name ?? '-'),
          _InfoRow(
            label: 'Wali kelas',
            value: item.homeroomTeacher?.name ?? '-',
          ),
          _InfoRow(label: 'Guru wali', value: item.studentMentor?.name ?? '-'),
          if (_filled(item.updatedBy))
            _InfoRow(label: 'Diperbarui', value: item.updatedBy!),
        ],
      ),
    ),
  );
}

class _HistoryCard extends StatelessWidget {
  const _HistoryCard({required this.history});
  final List<SanctionHistory> history;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionTitle(icon: Icons.history_rounded, title: 'Riwayat'),
          const SizedBox(height: 10),
          if (history.isEmpty)
            const Text(
              'Belum ada riwayat perubahan.',
              style: TextStyle(color: NusaColors.textSecondary, fontSize: 10),
            )
          else
            for (var index = 0; index < history.length; index++)
              _HistoryRow(
                item: history[index],
                last: index == history.length - 1,
              ),
        ],
      ),
    ),
  );
}

class _HistoryRow extends StatelessWidget {
  const _HistoryRow({required this.item, required this.last});
  final SanctionHistory item;
  final bool last;

  @override
  Widget build(BuildContext context) => IntrinsicHeight(
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 24,
          child: Column(
            children: [
              Container(
                width: 10,
                height: 10,
                decoration: const BoxDecoration(
                  color: NusaColors.primary,
                  shape: BoxShape.circle,
                ),
              ),
              if (!last)
                Expanded(
                  child: Container(width: 2, color: NusaColors.surfaceBlue),
                ),
            ],
          ),
        ),
        const SizedBox(width: 5),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(bottom: 13),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title,
                  style: const TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (_filled(item.previousStatusLabel) ||
                    _filled(item.nextStatusLabel))
                  Text(
                    '${item.previousStatusLabel ?? '-'} → ${item.nextStatusLabel ?? '-'}',
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontSize: 9,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                if (_filled(item.note))
                  Text(
                    item.note!,
                    style: const TextStyle(fontSize: 9.5, height: 1.35),
                  ),
                Text(
                  '${item.user} · ${_dateTimeLabel(item.occurredAt)}',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 8.5,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.icon, required this.title});
  final IconData icon;
  final String title;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon, size: 19, color: NusaColors.primary),
      const SizedBox(width: 7),
      Expanded(
        child: Text(
          title,
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w900),
        ),
      ),
    ],
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 76,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 9.5,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 10.5, height: 1.35),
          ),
        ),
      ],
    ),
  );
}

class _Chip extends StatelessWidget {
  const _Chip({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(10),
    ),
    child: Text(
      label,
      style: const TextStyle(
        color: NusaColors.primary,
        fontSize: 9,
        fontWeight: FontWeight.w700,
      ),
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

Color _statusColor(String status, bool overdue) {
  if (overdue) return Colors.red;
  return switch (status) {
    'selesai' => NusaColors.success,
    'diproses' => NusaColors.primary,
    'dibatalkan' => NusaColors.textSecondary,
    _ => const Color(0xFFC58F00),
  };
}

bool _filled(String? value) => value != null && value.trim().isNotEmpty;
String? _emptyToNull(String value) =>
    value.trim().isEmpty ? null : value.trim();
String _isoDate(DateTime date) =>
    '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  return date == null
      ? value
      : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String _dateTimeLabel(DateTime? value) {
  if (value == null) return '-';
  return '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year} ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')} WIB';
}

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Pelaksanaan sanksi belum dapat diproses.',
};
