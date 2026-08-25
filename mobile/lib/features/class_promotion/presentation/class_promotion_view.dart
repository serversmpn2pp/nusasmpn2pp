import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/class_promotion/application/class_promotion_controller.dart';
import 'package:nusa/features/class_promotion/domain/class_promotion.dart';
import 'package:nusa/features/class_promotion/presentation/widgets/class_promotion_components.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/shared/widgets/nusa_section_title.dart';

class ClassPromotionView extends ConsumerWidget {
  const ClassPromotionView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final promotion = ref.watch(classPromotionControllerProvider);

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Kenaikan Kelas'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: promotion.isLoading
                ? null
                : ref.read(classPromotionControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: promotion.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _PromotionError(
            message: _errorMessage(error),
            onRetry: ref
                .read(classPromotionControllerProvider.notifier)
                .refresh,
          ),
          data: (page) => _PromotionContent(page: page),
        ),
      ),
    );
  }
}

class _PromotionContent extends ConsumerStatefulWidget {
  const _PromotionContent({required this.page});

  final ClassPromotionPage page;

  @override
  ConsumerState<_PromotionContent> createState() => _PromotionContentState();
}

class _PromotionContentState extends ConsumerState<_PromotionContent> {
  final Map<int, int?> _targets = {};
  bool _processing = false;
  late String _dataSignature;

  @override
  void initState() {
    super.initState();
    _syncTargets();
  }

  @override
  void didUpdateWidget(covariant _PromotionContent oldWidget) {
    super.didUpdateWidget(oldWidget);
    final signature = _signature(widget.page);
    if (signature != _dataSignature) _syncTargets();
  }

  void _syncTargets() {
    _dataSignature = _signature(widget.page);
    _targets
      ..clear()
      ..addEntries(
        widget.page.members.map(
          (member) => MapEntry(member.id, member.suggestedDestinationClassId),
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final page = widget.page;
    final notifier = ref.read(classPromotionControllerProvider.notifier);
    final sourceYearId = page.filter.sourceYearId;
    final destinationYears = page.academicYears
        .where((year) => year.id != sourceYearId)
        .toList(growable: false);

    return RefreshIndicator(
      onRefresh: notifier.refresh,
      child: ListView(
        key: const PageStorageKey<String>('class-promotion-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
        children: [
          PromotionSummaryCard(summary: page.summary),
          const SizedBox(height: 14),
          const NusaSectionTitle(title: 'Periode dan Kelas Asal'),
          const SizedBox(height: 9),
          NusaDropdownField<int>(
            fieldKey: const Key('promotion-source-year'),
            value: page.filter.sourceYearId,
            enabled: !_processing,
            decoration: const InputDecoration(
              labelText: 'Tahun pelajaran asal',
              prefixIcon: Icon(Icons.history_rounded),
            ),
            options: [
              for (final year in page.academicYears)
                NusaDropdownOption<int>(
                  value: year.id,
                  label: '${year.name}${year.active ? ' · Aktif' : ''}',
                ),
            ],
            onChanged: (value) {
              if (value != null) notifier.selectSourceYear(value);
            },
          ),
          const SizedBox(height: 10),
          NusaDropdownField<int>(
            fieldKey: const Key('promotion-destination-year'),
            value: page.filter.destinationYearId,
            enabled: !_processing,
            decoration: const InputDecoration(
              labelText: 'Tahun pelajaran tujuan',
              hintText: 'Pilih tahun tujuan',
              prefixIcon: Icon(Icons.event_available_rounded),
            ),
            options: [
              for (final year in destinationYears)
                NusaDropdownOption<int>(
                  value: year.id,
                  label: '${year.name}${year.active ? ' · Aktif' : ''}',
                ),
            ],
            onChanged: _processing ? null : notifier.selectDestinationYear,
          ),
          const SizedBox(height: 10),
          NusaDropdownField<int>(
            fieldKey: const Key('promotion-source-class'),
            value: page.filter.sourceClassId,
            enabled: !_processing,
            decoration: const InputDecoration(
              labelText: 'Kelas asal',
              hintText: 'Pilih kelas asal',
              prefixIcon: Icon(Icons.meeting_room_outlined),
            ),
            options: [
              for (final item in page.sourceClasses)
                NusaDropdownOption<int>(
                  value: item.id,
                  label: '${item.name} · ${item.studentCount} siswa',
                ),
            ],
            onChanged: _processing ? null : notifier.selectSourceClass,
          ),
          const SizedBox(height: 12),
          PromotionNoticeCard(messages: page.warnings),
          if (page.destinationClasses.isNotEmpty) ...[
            const SizedBox(height: 18),
            const NusaSectionTitle(title: 'Kapasitas Kelas Tujuan'),
            const SizedBox(height: 9),
            DestinationCapacityList(classes: page.destinationClasses),
          ],
          if (page.selectedSourceClass != null) ...[
            const SizedBox(height: 20),
            NusaSectionTitle(
              key: const Key('apply-promotion-suggestion'),
              title: 'Siswa ${page.selectedSourceClass!.name}',
              actionLabel: 'Terapkan Saran',
              onAction: _processing || page.members.isEmpty
                  ? null
                  : _applySuggestions,
            ),
            const SizedBox(height: 9),
            if (page.members.isEmpty)
              const _PromotionEmpty(
                icon: Icons.group_off_outlined,
                message: 'Kelas asal belum memiliki siswa.',
              )
            else
              for (final member in page.members) ...[
                PromotionMemberCard(
                  member: member,
                  destinationClasses: page.destinationClasses,
                  value: _targets[member.id],
                  enabled: !_processing,
                  onChanged: (value) =>
                      setState(() => _targets[member.id] = value),
                ),
                const SizedBox(height: 10),
              ],
          ],
          if (page.ready) ...[
            const SizedBox(height: 8),
            NusaPrimaryButton(
              key: const Key('process-class-promotion'),
              label: 'Proses Kenaikan Kelas',
              loading: _processing,
              onPressed: _processing ? null : _confirmAndProcess,
            ),
          ],
        ],
      ),
    );
  }

  void _applySuggestions() {
    setState(() {
      for (final member in widget.page.members) {
        _targets[member.id] = member.suggestedDestinationClassId;
      }
    });
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(content: Text('Saran kelas tujuan telah diterapkan.')),
      );
  }

  Future<void> _confirmAndProcess() async {
    final selected = _targets.values.whereType<int>().length;
    final skipped = widget.page.members.length - selected;
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.trending_up_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Proses kenaikan kelas?'),
            content: Text(
              '$selected siswa akan ditempatkan dan $skipped siswa dilewati. '
              'Penempatan yang sudah ada pada tahun tujuan akan diperbarui.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-class-promotion'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Ya, Proses'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    setState(() => _processing = true);
    try {
      final result = await ref
          .read(classPromotionControllerProvider.notifier)
          .process([
            for (final member in widget.page.members)
              PromotionAssignment(
                memberId: member.id,
                destinationClassId: _targets[member.id],
                note: member.initialNote,
              ),
          ]);
      if (!mounted) return;
      setState(() => _processing = false);
      await _showResult(result);
    } catch (error) {
      if (mounted) _showError(error);
    } finally {
      if (mounted && _processing) setState(() => _processing = false);
    }
  }

  Future<void> _showResult(PromotionResult result) => showDialog<void>(
    context: context,
    builder: (context) => AlertDialog(
      icon: const Icon(Icons.task_alt_rounded, color: NusaColors.success),
      title: const Text('Ringkasan Kenaikan Kelas'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${result.placed} dari ${result.processed} siswa berhasil '
              'ditempatkan. ${result.skipped} siswa dilewati.',
            ),
            if (result.notes.isNotEmpty) ...[
              const SizedBox(height: 12),
              const Text(
                'Catatan',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 5),
              for (final note in result.notes)
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text('• $note', style: const TextStyle(fontSize: 12)),
                ),
            ],
          ],
        ),
      ),
      actions: [
        FilledButton(
          key: const Key('close-promotion-result'),
          onPressed: () => Navigator.pop(context),
          child: const Text('Selesai'),
        ),
      ],
    ),
  );

  void _showError(Object error) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
  }

  String _signature(ClassPromotionPage page) => [
    page.filter.sourceYearId,
    page.filter.destinationYearId,
    page.filter.sourceClassId,
    for (final member in page.members)
      '${member.id}:${member.currentPlacement?.schoolClass.id}:${member.suggestedDestinationClassId}',
  ].join('|');
}

class _PromotionEmpty extends StatelessWidget {
  const _PromotionEmpty({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(24),
    decoration: BoxDecoration(
      color: Colors.white,
      border: Border.all(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Column(
      children: [
        Icon(icon, color: NusaColors.textSecondary, size: 36),
        const SizedBox(height: 9),
        Text(message, textAlign: TextAlign.center),
      ],
    ),
  );
}

class _PromotionError extends StatelessWidget {
  const _PromotionError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 48,
            color: NusaColors.primary,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
          ),
        ],
      ),
    ),
  );
}

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Data kenaikan kelas belum dapat diproses.';
}
