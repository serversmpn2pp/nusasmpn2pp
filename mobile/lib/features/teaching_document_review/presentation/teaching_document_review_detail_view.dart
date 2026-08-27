import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_document/domain/teaching_document.dart';
import 'package:nusa/features/teaching_document_review/application/teaching_document_review_controller.dart';
import 'package:nusa/features/teaching_document_review/data/teaching_document_download_saver.dart';
import 'package:nusa/features/teaching_document_review/domain/teaching_document_review.dart';
import 'package:nusa/features/teaching_document_review/presentation/widgets/teaching_document_review_form_sheet.dart';

class TeachingDocumentReviewDetailView extends ConsumerStatefulWidget {
  const TeachingDocumentReviewDetailView({required this.documentId, super.key});

  final int documentId;

  @override
  ConsumerState<TeachingDocumentReviewDetailView> createState() =>
      _TeachingDocumentReviewDetailViewState();
}

class _TeachingDocumentReviewDetailViewState
    extends ConsumerState<TeachingDocumentReviewDetailView> {
  bool _downloading = false;
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final detail = ref.watch(
      teachingDocumentReviewDetailProvider(widget.documentId),
    );
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Periksa Dokumen'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: detail.isLoading || _mutating
                ? null
                : () => ref.invalidate(
                    teachingDocumentReviewDetailProvider(widget.documentId),
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
              teachingDocumentReviewDetailProvider(widget.documentId),
            ),
          ),
          data: (data) => RefreshIndicator(
            onRefresh: () => ref.refresh(
              teachingDocumentReviewDetailProvider(widget.documentId).future,
            ),
            child: ListView(
              key: const PageStorageKey<String>(
                'teaching-document-review-detail',
              ),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
              children: [
                _DocumentHeader(detail: data),
                const SizedBox(height: 11),
                _DocumentInformation(detail: data),
                if (data.document.teacherNote case final note?) ...[
                  const SizedBox(height: 10),
                  _NoteCard(
                    icon: Icons.sticky_note_2_outlined,
                    title: 'Catatan Guru',
                    note: note,
                    color: NusaColors.primary,
                  ),
                ],
                if (data.document.reviewerNote case final note?) ...[
                  const SizedBox(height: 10),
                  _NoteCard(
                    icon: Icons.rate_review_outlined,
                    title: 'Catatan Pemeriksa Sebelumnya',
                    note: note,
                    color: Colors.deepOrange,
                  ),
                ],
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  key: const Key('download-review-document'),
                  onPressed: _downloading ? null : () => _download(data),
                  icon: _downloading
                      ? const SizedBox.square(
                          dimension: 17,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.download_rounded),
                  label: Text(
                    _downloading
                        ? 'Mengunduh PDF...'
                        : 'Simpan PDF untuk Dibaca',
                  ),
                ),
                if (data.canReview) ...[
                  const SizedBox(height: 9),
                  FilledButton.icon(
                    key: const Key('open-document-review-form'),
                    onPressed: _mutating ? null : () => _openReview(data),
                    icon: const Icon(Icons.fact_check_outlined),
                    label: const Text('Beri Hasil Pemeriksaan'),
                  ),
                ],
                const SizedBox(height: 20),
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
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(15),
                      child: Text('Belum ada riwayat berkas.'),
                    ),
                  )
                else
                  for (var index = 0; index < data.histories.length; index++)
                    _HistoryCard(
                      history: data.histories[index],
                      latest: index == 0,
                    ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _download(TeachingDocumentReviewDetail detail) async {
    setState(() => _downloading = true);
    try {
      final download = await ref
          .read(teachingDocumentReviewActionsProvider)
          .download(id: widget.documentId, fileName: detail.document.fileName);
      final saved = await ref
          .read(teachingDocumentDownloadSaverProvider)
          .save(download);
      if (!mounted || !saved) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(content: Text('PDF berhasil disimpan di perangkat.')),
        );
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  Future<void> _openReview(TeachingDocumentReviewDetail detail) async {
    final value = await showModalBottomSheet<TeachingDocumentReviewValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          TeachingDocumentReviewFormSheet(document: detail.document),
    );
    if (value == null || !mounted) return;

    setState(() => _mutating = true);
    try {
      await ref
          .read(teachingDocumentReviewActionsProvider)
          .review(id: widget.documentId, value: value);
      ref.invalidate(teachingDocumentReviewControllerProvider);
      ref.invalidate(teachingDocumentReviewDetailProvider(widget.documentId));
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(content: Text('Hasil pemeriksaan berhasil disimpan.')),
        );
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  void _showError(Object error) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
  }
}

class _DocumentHeader extends StatelessWidget {
  const _DocumentHeader({required this.detail});

  final TeachingDocumentReviewDetail detail;

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
                color: Colors.white.withValues(alpha: 0.13),
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
                    detail.document.title,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 17,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    detail.employee.name,
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
        const SizedBox(height: 13),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
          decoration: BoxDecoration(
            color: _statusColor(detail.document.status).withValues(alpha: 0.25),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            detail.document.statusLabel,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 10.5,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    ),
  );
}

class _DocumentInformation extends StatelessWidget {
  const _DocumentInformation({required this.detail});

  final TeachingDocumentReviewDetail detail;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: NusaColors.surface,
      border: Border.all(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(16),
    ),
    child: Column(
      children: [
        _InfoRow(
          label: 'Mata pelajaran',
          value: detail.document.subject?.name ?? '-',
        ),
        _InfoRow(label: 'Jenis', value: detail.document.type?.name ?? '-'),
        _InfoRow(label: 'Tingkat', value: detail.document.gradeLabel),
        _InfoRow(
          label: 'Tahun pelajaran',
          value: detail.document.academicYear?.name ?? '-',
        ),
        _InfoRow(label: 'Nama berkas', value: detail.document.fileName),
        _InfoRow(
          label: 'Ukuran',
          value: _formatBytes(detail.document.fileSize),
          last: true,
        ),
      ],
    ),
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value, this.last = false});

  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: last ? 0 : 10),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 105,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

class _NoteCard extends StatelessWidget {
  const _NoteCard({
    required this.icon,
    required this.title,
    required this.note,
    required this.color,
  });

  final IconData icon;
  final String title;
  final String note;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: color, size: 21),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 11,
                ),
              ),
              const SizedBox(height: 3),
              Text(note, style: const TextStyle(fontSize: 11, height: 1.4)),
            ],
          ),
        ),
      ],
    ),
  );
}

class _HistoryCard extends StatelessWidget {
  const _HistoryCard({required this.history, required this.latest});

  final TeachingDocumentHistory history;
  final bool latest;

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 8),
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          const Icon(Icons.history_rounded, color: NusaColors.primary),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  history.fileName,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Text(
                  _formatBytes(history.fileSize),
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10,
                  ),
                ),
              ],
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
    ),
  );
}

class _DetailError extends StatelessWidget {
  const _DetailError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(message, textAlign: TextAlign.center),
        const SizedBox(height: 10),
        FilledButton(onPressed: onRetry, child: const Text('Coba Lagi')),
      ],
    ),
  );
}

Color _statusColor(String status) => switch (status) {
  'sudah_diperiksa' => NusaColors.success,
  'perlu_perbaikan' => Colors.deepOrange,
  _ => NusaColors.primaryLight,
};

String _formatBytes(int bytes) {
  if (bytes >= 1024 * 1024) {
    return '${(bytes / 1024 / 1024).toStringAsFixed(1)} MB';
  }
  return '${(bytes / 1024).toStringAsFixed(0)} KB';
}

String _errorMessage(Object error) => switch (error) {
  AppException exception => exception.message,
  _ => 'Terjadi gangguan saat memuat dokumen.',
};
