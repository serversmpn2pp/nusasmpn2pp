import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_document/application/teaching_document_controller.dart';
import 'package:nusa/features/teaching_document/domain/teaching_document.dart';
import 'package:nusa/features/teaching_document/presentation/widgets/teaching_document_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class TeachingDocumentView extends ConsumerStatefulWidget {
  const TeachingDocumentView({super.key});

  @override
  ConsumerState<TeachingDocumentView> createState() =>
      _TeachingDocumentViewState();
}

class _TeachingDocumentViewState extends ConsumerState<TeachingDocumentView> {
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final documents = ref.watch(teachingDocumentControllerProvider);
    final current = documents.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Perangkat Ajar Saya'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: documents.isLoading || _mutating
                ? null
                : ref.read(teachingDocumentControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: documents.when(
          loading: () => current == null
              ? const Center(child: CircularProgressIndicator())
              : _DocumentContent(
                  page: current,
                  disabled: true,
                  onUpload: _openCreate,
                  onOpen: _openDetail,
                  onRefresh: ref
                      .read(teachingDocumentControllerProvider.notifier)
                      .refresh,
                ),
          error: (error, stackTrace) => _DocumentError(
            message: _errorMessage(error),
            onRetry: ref
                .read(teachingDocumentControllerProvider.notifier)
                .refresh,
          ),
          data: (page) => _DocumentContent(
            page: page,
            disabled: _mutating,
            onUpload: _openCreate,
            onOpen: _openDetail,
            onRefresh: ref
                .read(teachingDocumentControllerProvider.notifier)
                .refresh,
          ),
        ),
      ),
    );
  }

  Future<void> _openCreate(
    TeachingDocumentPage page,
    TeachingDocumentAssignment assignment,
    TeachingDocumentType type,
  ) async {
    final value = await showModalBottomSheet<TeachingDocumentFormValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => TeachingDocumentFormSheet.create(
        page: page,
        assignment: assignment,
        type: type,
      ),
    );
    if (value == null || !mounted) return;
    await _runMutation(
      operation: () => ref.read(teachingDocumentActionsProvider).create(value),
      successMessage:
          'Perangkat ajar berhasil diunggah dan menunggu pemeriksaan.',
    );
  }

  void _openDetail(int id) => context.push('/perangkat-ajar-saya/$id');

  Future<void> _runMutation({
    required Future<void> Function() operation,
    required String successMessage,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(teachingDocumentControllerProvider.notifier).refresh();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(successMessage)));
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

class _DocumentContent extends ConsumerWidget {
  const _DocumentContent({
    required this.page,
    required this.disabled,
    required this.onUpload,
    required this.onOpen,
    required this.onRefresh,
  });

  final TeachingDocumentPage page;
  final bool disabled;
  final void Function(
    TeachingDocumentPage,
    TeachingDocumentAssignment,
    TeachingDocumentType,
  )
  onUpload;
  final ValueChanged<int> onOpen;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (page.employee == null) {
      return const _MissingEmployee();
    }
    final notifier = ref.read(teachingDocumentControllerProvider.notifier);
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        key: const PageStorageKey<String>('teaching-document-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
        children: [
          _DocumentSummary(page: page),
          const SizedBox(height: 12),
          NusaDropdownField<int?>(
            fieldKey: const Key('teaching-document-year-filter'),
            value: page.filter.academicYearId,
            enabled: !disabled,
            decoration: const InputDecoration(
              labelText: 'Tahun pelajaran',
              prefixIcon: Icon(Icons.calendar_month_rounded),
            ),
            options: [
              for (final year in page.academicYears)
                NusaDropdownOption<int?>(
                  value: year.id,
                  label: '${year.name}${year.active ? ' · Aktif' : ''}',
                ),
            ],
            onChanged: notifier.filterAcademicYear,
          ),
          const SizedBox(height: 9),
          Row(
            children: [
              for (final semester in [1, 2])
                Expanded(
                  child: Padding(
                    padding: EdgeInsets.only(right: semester == 1 ? 8 : 0),
                    child: ChoiceChip(
                      key: Key('teaching-document-semester-$semester'),
                      label: SizedBox(
                        width: double.infinity,
                        child: Text(
                          'Semester $semester',
                          textAlign: TextAlign.center,
                        ),
                      ),
                      selected: page.filter.semester == semester,
                      showCheckmark: false,
                      onSelected: disabled
                          ? null
                          : (_) => notifier.filterSemester(semester),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 18),
          const _SectionHeading(
            title: 'Dokumen per Penugasan',
            subtitle: 'Unggah satu dokumen untuk setiap jenis, mata pelajaran, dan tingkat.',
          ),
          const SizedBox(height: 10),
          if (page.assignments.isEmpty)
            const _EmptyAssignments()
          else
            for (var index = 0; index < page.assignments.length; index++) ...[
              _AssignmentCard(
                page: page,
                assignment: page.assignments[index],
                disabled: disabled,
                onUpload: onUpload,
                onOpen: onOpen,
              ),
              if (index < page.assignments.length - 1)
                const SizedBox(height: 10),
            ],
          if (page.legacyDocuments.isNotEmpty) ...[
            const SizedBox(height: 18),
            const _SectionHeading(
              title: 'Dokumen Lama',
              subtitle: 'Dokumen berikut belum memiliki informasi tingkat.',
            ),
            const SizedBox(height: 9),
            for (final document in page.legacyDocuments)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: _LegacyDocumentCard(
                  document: document,
                  onOpen: () => onOpen(document.id),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

class _DocumentSummary extends StatelessWidget {
  const _DocumentSummary({required this.page});

  final TeachingDocumentPage page;

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
        Text(
          page.employee!.name,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 17,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          'Kelengkapan perangkat ajar semester ${page.filter.semester}',
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.72),
            fontSize: 11,
          ),
        ),
        const SizedBox(height: 13),
        ClipRRect(
          borderRadius: BorderRadius.circular(9),
          child: LinearProgressIndicator(
            minHeight: 8,
            value: page.summary.completeness / 100,
            backgroundColor: Colors.white.withValues(alpha: 0.18),
            valueColor: const AlwaysStoppedAnimation(NusaColors.accent),
          ),
        ),
        const SizedBox(height: 7),
        Row(
          children: [
            Text(
              '${page.summary.uploadedCount}/${page.summary.requiredCount} wajib terunggah',
              style: const TextStyle(color: Colors.white, fontSize: 11),
            ),
            const Spacer(),
            Text(
              '${page.summary.completeness}%',
              style: const TextStyle(
                color: NusaColors.accent,
                fontSize: 15,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            _SummaryPill(
              icon: Icons.hourglass_top_rounded,
              label: '${page.summary.waitingCount} menunggu',
            ),
            const SizedBox(width: 8),
            _SummaryPill(
              icon: Icons.build_circle_outlined,
              label: '${page.summary.revisionCount} perbaikan',
              warning: page.summary.revisionCount > 0,
            ),
          ],
        ),
      ],
    ),
  );
}

class _SummaryPill extends StatelessWidget {
  const _SummaryPill({
    required this.icon,
    required this.label,
    this.warning = false,
  });

  final IconData icon;
  final String label;
  final bool warning;

  @override
  Widget build(BuildContext context) => Flexible(
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: warning
            ? NusaColors.accent.withValues(alpha: 0.22)
            : Colors.white.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: Colors.white, size: 14),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              label,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.white, fontSize: 10),
            ),
          ),
        ],
      ),
    ),
  );
}

class _AssignmentCard extends StatelessWidget {
  const _AssignmentCard({
    required this.page,
    required this.assignment,
    required this.disabled,
    required this.onUpload,
    required this.onOpen,
  });

  final TeachingDocumentPage page;
  final TeachingDocumentAssignment assignment;
  final bool disabled;
  final void Function(
    TeachingDocumentPage,
    TeachingDocumentAssignment,
    TeachingDocumentType,
  )
  onUpload;
  final ValueChanged<int> onOpen;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: NusaColors.surface,
      border: Border.all(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Column(
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 10),
          decoration: const BoxDecoration(
            color: NusaColors.surfaceBlue,
            borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
          ),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: NusaColors.primary,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.menu_book_rounded,
                  color: Colors.white,
                  size: 21,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      assignment.subject.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    Text(
                      'Tingkat ${assignment.gradeLabel}',
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
        ),
        for (var index = 0; index < assignment.slots.length; index++) ...[
          _DocumentSlotTile(
            slot: assignment.slots[index],
            disabled: disabled,
            onPressed: assignment.slots[index].document == null
                ? () => onUpload(page, assignment, assignment.slots[index].type)
                : () => onOpen(assignment.slots[index].document!.id),
          ),
          if (index < assignment.slots.length - 1)
            const Divider(height: 1, indent: 14, endIndent: 14),
        ],
      ],
    ),
  );
}

class _DocumentSlotTile extends StatelessWidget {
  const _DocumentSlotTile({
    required this.slot,
    required this.disabled,
    required this.onPressed,
  });

  final TeachingDocumentSlot slot;
  final bool disabled;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final document = slot.document;
    return InkWell(
      key: Key('teaching-document-slot-${slot.type.id}'),
      onTap: disabled ? null : onPressed,
      borderRadius: BorderRadius.circular(15),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
        child: Row(
          children: [
            Icon(
              document == null
                  ? Icons.upload_file_outlined
                  : Icons.picture_as_pdf_rounded,
              color: document == null
                  ? NusaColors.textSecondary
                  : _statusColor(document.status),
              size: 23,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          slot.type.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      if (slot.type.required)
                        const Text(
                          'WAJIB',
                          style: TextStyle(
                            color: NusaColors.primary,
                            fontSize: 8,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    document == null
                        ? 'Belum diunggah · Ketuk untuk memilih PDF'
                        : document.statusLabel,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: document == null
                          ? NusaColors.textSecondary
                          : _statusColor(document.status),
                      fontSize: 10.5,
                      fontWeight: document == null
                          ? FontWeight.w400
                          : FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 7),
            Icon(
              document == null
                  ? Icons.add_circle_outline_rounded
                  : Icons.chevron_right_rounded,
              color: NusaColors.primary,
              size: 21,
            ),
          ],
        ),
      ),
    );
  }
}

class _LegacyDocumentCard extends StatelessWidget {
  const _LegacyDocumentCard({required this.document, required this.onOpen});

  final TeachingDocument document;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      onTap: onOpen,
      leading: const Icon(Icons.history_rounded, color: NusaColors.primary),
      title: Text(document.title, maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: Text(document.statusLabel),
      trailing: const Icon(Icons.chevron_right_rounded),
    ),
  );
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: const TextStyle(
          color: NusaColors.textPrimary,
          fontSize: 16,
          fontWeight: FontWeight.w800,
        ),
      ),
      const SizedBox(height: 2),
      Text(
        subtitle,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
      ),
    ],
  );
}

class _EmptyAssignments extends StatelessWidget {
  const _EmptyAssignments();

  @override
  Widget build(BuildContext context) => const _MessageCard(
    icon: Icons.assignment_ind_outlined,
    title: 'Belum ada penugasan mengajar',
    message: 'Hubungi administrator jika mata pelajaran dan tingkat yang diajar belum muncul.',
  );
}

class _MissingEmployee extends StatelessWidget {
  const _MissingEmployee();

  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(
      padding: EdgeInsets.all(20),
      child: _MessageCard(
        icon: Icons.badge_outlined,
        title: 'Akun belum terhubung ke pegawai',
        message: 'Minta administrator menghubungkan akun ini dengan data pegawai terlebih dahulu.',
      ),
    ),
  );
}

class _MessageCard extends StatelessWidget {
  const _MessageCard({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: NusaColors.surface,
      border: Border.all(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Column(
      children: [
        Icon(icon, size: 34, color: NusaColors.primary),
        const SizedBox(height: 9),
        Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
        const SizedBox(height: 3),
        Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 11),
        ),
      ],
    ),
  );
}

class _DocumentError extends StatelessWidget {
  const _DocumentError({required this.message, required this.onRetry});

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

String _errorMessage(Object error) => switch (error) {
  AppException exception => exception.message,
  _ => 'Terjadi gangguan saat memuat perangkat ajar.',
};
