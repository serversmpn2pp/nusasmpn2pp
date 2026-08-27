import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_document/application/teaching_document_controller.dart';
import 'package:nusa/features/teaching_document/domain/teaching_document.dart';
import 'package:nusa/features/teaching_document/presentation/widgets/teaching_document_form_sheet.dart';

class TeachingDocumentDetailView extends ConsumerStatefulWidget {
  const TeachingDocumentDetailView({required this.documentId, super.key});

  final int documentId;

  @override
  ConsumerState<TeachingDocumentDetailView> createState() =>
      _TeachingDocumentDetailViewState();
}

class _TeachingDocumentDetailViewState
    extends ConsumerState<TeachingDocumentDetailView> {
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final detail = ref.watch(teachingDocumentDetailProvider(widget.documentId));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Rincian Perangkat Ajar'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: detail.isLoading || _mutating
                ? null
                : () => ref.invalidate(
                    teachingDocumentDetailProvider(widget.documentId),
                  ),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: detail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _DetailError(
            message: _errorMessage(error),
            onRetry: () => ref.invalidate(
              teachingDocumentDetailProvider(widget.documentId),
            ),
          ),
          data: (data) => RefreshIndicator(
            onRefresh: () => ref.refresh(
              teachingDocumentDetailProvider(widget.documentId).future,
            ),
            child: ListView(
              key: const PageStorageKey<String>('teaching-document-detail'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
              children: [
                _DetailHeader(document: data.document),
                if (data.document.reviewerNote case final note?) ...[
                  const SizedBox(height: 10),
                  _ReviewerNote(
                    note: note,
                    reviewer: data.document.reviewer,
                    reviewedAt: data.document.reviewedAt,
                  ),
                ],
                const SizedBox(height: 12),
                _InformationCard(document: data.document),
                const SizedBox(height: 12),
                FilledButton.icon(
                  key: const Key('revise-teaching-document'),
                  onPressed: _mutating ? null : () => _openEdit(data),
                  icon: const Icon(Icons.edit_document),
                  label: const Text('Ubah Informasi / Unggah Revisi'),
                ),
                const SizedBox(height: 19),
                Text(
                  'Riwayat Berkas (${data.histories.length})',
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                if (data.histories.isEmpty)
                  const _EmptyHistory()
                else
                  for (var index = 0; index < data.histories.length; index++)
                    _HistoryTile(
                      history: data.histories[index],
                      latest: index == 0,
                      last: index == data.histories.length - 1,
                    ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _openEdit(TeachingDocumentDetail detail) async {
    final value = await showModalBottomSheet<TeachingDocumentFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => TeachingDocumentFormSheet.edit(detail: detail),
    );
    if (value == null || !mounted) return;

    setState(() => _mutating = true);
    try {
      await ref
          .read(teachingDocumentActionsProvider)
          .update(id: widget.documentId, value: value);
      ref.invalidate(teachingDocumentControllerProvider);
      ref.invalidate(teachingDocumentDetailProvider(widget.documentId));
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            content: Text(
              value.file == null
                  ? 'Informasi perangkat ajar berhasil diperbarui.'
                  : 'Revisi PDF berhasil diunggah dan menunggu pemeriksaan.',
            ),
          ),
        );
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }
}

class _DetailHeader extends StatelessWidget {
  const _DetailHeader({required this.document});

  final TeachingDocument document;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.14),
                borderRadius: BorderRadius.circular(13),
              ),
              child: const Icon(
                Icons.picture_as_pdf_rounded,
                color: Colors.white,
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    document.title,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${document.subject?.name ?? '-'} · Tingkat ${document.gradeLabel}',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.74),
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
          decoration: BoxDecoration(
            color: _statusColor(document.status).withValues(alpha: 0.22),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(_statusIcon(document.status), size: 16, color: Colors.white),
              const SizedBox(width: 6),
              Text(
                document.statusLabel,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _ReviewerNote extends StatelessWidget {
  const _ReviewerNote({required this.note, this.reviewer, this.reviewedAt});

  final String note;
  final String? reviewer;
  final DateTime? reviewedAt;

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('teaching-document-reviewer-note'),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Colors.orange.withValues(alpha: 0.11),
      border: Border.all(color: Colors.orange.withValues(alpha: 0.35)),
      borderRadius: BorderRadius.circular(15),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.rate_review_outlined, color: Colors.deepOrange),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Catatan Pemeriksa',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 4),
              Text(note, style: const TextStyle(fontSize: 12, height: 1.4)),
              if (reviewer != null) ...[
                const SizedBox(height: 5),
                Text(
                  '$reviewer${reviewedAt == null ? '' : ' · ${_formatDate(reviewedAt!)}'}',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10,
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    ),
  );
}

class _InformationCard extends StatelessWidget {
  const _InformationCard({required this.document});

  final TeachingDocument document;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(15),
    decoration: BoxDecoration(
      color: NusaColors.surface,
      border: Border.all(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Column(
      children: [
        _InformationRow(
          icon: Icons.category_outlined,
          label: 'Jenis',
          value: document.type?.name ?? '-',
        ),
        _InformationRow(
          icon: Icons.calendar_month_outlined,
          label: 'Tahun Pelajaran',
          value: document.academicYear?.name ?? '-',
        ),
        _InformationRow(
          icon: Icons.insert_drive_file_outlined,
          label: 'Nama Berkas',
          value: document.fileName,
        ),
        _InformationRow(
          icon: Icons.data_usage_rounded,
          label: 'Ukuran',
          value: _formatBytes(document.fileSize),
        ),
        _InformationRow(
          icon: Icons.schedule_rounded,
          label: 'Diunggah',
          value: document.uploadedAt == null
              ? '-'
              : _formatDate(document.uploadedAt!),
          last: document.teacherNote == null,
        ),
        if (document.teacherNote case final note?)
          _InformationRow(
            icon: Icons.notes_rounded,
            label: 'Catatan Guru',
            value: note,
            last: true,
          ),
      ],
    ),
  );
}

class _InformationRow extends StatelessWidget {
  const _InformationRow({
    required this.icon,
    required this.label,
    required this.value,
    this.last = false,
  });

  final IconData icon;
  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: last ? 0 : 12),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: NusaColors.primary, size: 19),
        const SizedBox(width: 10),
        SizedBox(
          width: 92,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

class _HistoryTile extends StatelessWidget {
  const _HistoryTile({
    required this.history,
    required this.latest,
    required this.last,
  });

  final TeachingDocumentHistory history;
  final bool latest;
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
                width: 12,
                height: 12,
                decoration: BoxDecoration(
                  color: latest ? NusaColors.accent : NusaColors.primaryLight,
                  shape: BoxShape.circle,
                  border: Border.all(color: NusaColors.primary, width: 2),
                ),
              ),
              if (!last)
                Expanded(child: Container(width: 2, color: NusaColors.outline)),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Container(
            margin: EdgeInsets.only(bottom: last ? 0 : 9),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: NusaColors.surface,
              border: Border.all(color: NusaColors.outline),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        history.fileName,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    if (latest)
                      const Text(
                        'TERBARU',
                        style: TextStyle(
                          color: NusaColors.primary,
                          fontSize: 8,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  '${_formatBytes(history.fileSize)} · ${history.uploadedAt == null ? '-' : _formatDate(history.uploadedAt!)}',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10,
                  ),
                ),
                if (history.note case final note?) ...[
                  const SizedBox(height: 5),
                  Text(note, style: const TextStyle(fontSize: 10.5)),
                ],
              ],
            ),
          ),
        ),
      ],
    ),
  );
}

class _EmptyHistory extends StatelessWidget {
  const _EmptyHistory();

  @override
  Widget build(BuildContext context) => const Card(
    child: Padding(
      padding: EdgeInsets.all(16),
      child: Text('Belum ada riwayat berkas.'),
    ),
  );
}

class _DetailError extends StatelessWidget {
  const _DetailError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 42),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton(onPressed: onRetry, child: const Text('Coba Lagi')),
        ],
      ),
    ),
  );
}

Color _statusColor(String status) => switch (status) {
  'sudah_diperiksa' => NusaColors.success,
  'perlu_perbaikan' => Colors.deepOrange,
  _ => NusaColors.primaryLight,
};

IconData _statusIcon(String status) => switch (status) {
  'sudah_diperiksa' => Icons.verified_rounded,
  'perlu_perbaikan' => Icons.build_circle_outlined,
  _ => Icons.hourglass_top_rounded,
};

String _formatBytes(int bytes) {
  if (bytes >= 1024 * 1024) {
    return '${(bytes / 1024 / 1024).toStringAsFixed(1)} MB';
  }
  return '${(bytes / 1024).toStringAsFixed(0)} KB';
}

String _formatDate(DateTime value) {
  final local = value.toLocal();
  String two(int number) => number.toString().padLeft(2, '0');
  return '${two(local.day)}-${two(local.month)}-${local.year} ${two(local.hour)}:${two(local.minute)} WIB';
}

String _errorMessage(Object error) => switch (error) {
  AppException exception => exception.message,
  _ => 'Terjadi gangguan saat memuat perangkat ajar.',
};
