import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_absence_settings/application/worship_absence_settings_controller.dart';
import 'package:nusa/features/worship_absence_settings/domain/worship_absence_settings.dart';
import 'package:nusa/features/worship_absence_settings/presentation/widgets/worship_absence_settings_sheets.dart';

class WorshipAbsenceSettingsView extends ConsumerStatefulWidget {
  const WorshipAbsenceSettingsView({super.key});

  @override
  ConsumerState<WorshipAbsenceSettingsView> createState() =>
      _WorshipAbsenceSettingsViewState();
}

class _WorshipAbsenceSettingsViewState
    extends ConsumerState<WorshipAbsenceSettingsView> {
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final settings = ref.watch(worshipAbsenceSettingsControllerProvider);

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text(
          'Berhalangan Ibadah',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: settings.isLoading || _mutating
                ? null
                : ref
                      .read(worshipAbsenceSettingsControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: settings.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _SettingsError(
            message: _errorMessage(error),
            onRetry: ref
                .read(worshipAbsenceSettingsControllerProvider.notifier)
                .refresh,
          ),
          data: (page) => page.available
              ? _SettingsContent(
                  page: page,
                  mutating: _mutating,
                  onRefresh: ref
                      .read(worshipAbsenceSettingsControllerProvider.notifier)
                      .refresh,
                  onEditSettings: () => _openSettings(page),
                  onAddCompanion: () => _openCompanion(page),
                  onEditCompanion: (item) =>
                      _openCompanion(page, existing: item),
                  onDeactivateCompanion: _confirmDeactivate,
                )
              : _NoAcademicYear(
                  onRefresh: ref
                      .read(worshipAbsenceSettingsControllerProvider.notifier)
                      .refresh,
                ),
        ),
      ),
    );
  }

  Future<void> _openSettings(WorshipAbsenceSettingsPage page) async {
    final current = page.settings;
    if (current == null) return;
    final value = await showModalBottomSheet<WorshipAbsenceSettingsValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => WorshipAbsenceLimitSheet(settings: current),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: 'Pengaturan berhalangan berhasil disimpan.',
      operation: () =>
          ref.read(worshipAbsenceSettingsActionsProvider).updateSettings(value),
    );
  }

  Future<void> _openCompanion(
    WorshipAbsenceSettingsPage page, {
    WorshipCompanionAssignment? existing,
  }) async {
    if (existing == null && page.employees.isEmpty) {
      _showError(
        'Belum ada guru perempuan aktif yang dapat menjadi pendamping.',
      );
      return;
    }
    final value = await showModalBottomSheet<WorshipCompanionAssignmentValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          WorshipCompanionFormSheet(page: page, existing: existing),
    );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: 'Pendamping ibadah siswi berhasil disimpan.',
      operation: () =>
          ref.read(worshipAbsenceSettingsActionsProvider).saveCompanion(value),
    );
  }

  Future<void> _confirmDeactivate(WorshipCompanionAssignment item) async {
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.person_off_outlined,
              color: NusaColors.primary,
            ),
            title: const Text('Nonaktifkan pendamping?'),
            content: Text(
              '${item.employeeName} tidak lagi dapat menangani konfirmasi privat. '
              'Riwayat cakupan kelas tetap tersimpan.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-worship-companion-deactivate'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    await _runMutation(
      successMessage: 'Pendamping berhasil dinonaktifkan.',
      operation: () => ref
          .read(worshipAbsenceSettingsActionsProvider)
          .deactivateCompanion(item.id),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref
          .read(worshipAbsenceSettingsControllerProvider.notifier)
          .refresh();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(successMessage)));
    } catch (error) {
      if (mounted) _showError(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  void _showError(Object message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message.toString())));
  }
}

class _SettingsContent extends StatelessWidget {
  const _SettingsContent({
    required this.page,
    required this.mutating,
    required this.onRefresh,
    required this.onEditSettings,
    required this.onAddCompanion,
    required this.onEditCompanion,
    required this.onDeactivateCompanion,
  });

  final WorshipAbsenceSettingsPage page;
  final bool mutating;
  final Future<void> Function() onRefresh;
  final VoidCallback onEditSettings;
  final VoidCallback onAddCompanion;
  final ValueChanged<WorshipCompanionAssignment> onEditCompanion;
  final ValueChanged<WorshipCompanionAssignment> onDeactivateCompanion;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
      children: [
        _SummaryCard(page: page),
        const SizedBox(height: 10),
        _LimitCard(
          settings: page.settings!,
          enabled: !mutating,
          onEdit: onEditSettings,
        ),
        const SizedBox(height: 10),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: NusaColors.accent.withValues(alpha: 0.13),
            borderRadius: BorderRadius.circular(14),
          ),
          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.lock_outline_rounded,
                size: 20,
                color: NusaColors.primary,
              ),
              SizedBox(width: 9),
              Expanded(
                child: Text(
                  'Informasi berhalangan bersifat privat. Tidak ada pemeriksaan fisik dan hasil konfirmasi tidak ditampilkan pada rekap umum.',
                  style: TextStyle(fontSize: 11.5, height: 1.4),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        Row(
          children: [
            const Expanded(
              child: Text(
                'Pendamping Aktif',
                style: TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            IconButton.filledTonal(
              key: const Key('add-worship-companion'),
              tooltip: 'Tambah pendamping',
              onPressed: mutating ? null : onAddCompanion,
              icon: const Icon(Icons.person_add_alt_1_rounded),
            ),
          ],
        ),
        const SizedBox(height: 9),
        if (page.assignments.isEmpty)
          const _EmptyCompanion()
        else
          ...page.assignments.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: _CompanionCard(
                item: item,
                onEdit: mutating ? null : () => onEditCompanion(item),
                onDeactivate: mutating
                    ? null
                    : () => onDeactivateCompanion(item),
              ),
            ),
          ),
      ],
    ),
  );
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.page});

  final WorshipAbsenceSettingsPage page;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(13),
          ),
          child: const Icon(Icons.shield_outlined, color: NusaColors.accent),
        ),
        const SizedBox(width: 11),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                page.academicYear?.name ?? '-',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                '${page.summary.activeCompanions} pendamping · '
                '${page.summary.coveredClasses}/${page.summary.classCount} kelas tercakup',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.73),
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

class _LimitCard extends StatelessWidget {
  const _LimitCard({
    required this.settings,
    required this.enabled,
    required this.onEdit,
  });

  final WorshipAbsenceSettings settings;
  final bool enabled;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: NusaColors.primary.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Text(
              '${settings.confirmationDayLimit}',
              style: const TextStyle(
                color: NusaColors.primary,
                fontSize: 19,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Batas konfirmasi',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 2),
                Text(
                  '${settings.confirmationDayLimit} hari kalender · '
                  '${settings.active ? 'Pengingat aktif' : 'Pengingat nonaktif'}',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            key: const Key('edit-worship-absence-limit'),
            tooltip: 'Atur batas konfirmasi',
            onPressed: enabled ? onEdit : null,
            icon: const Icon(Icons.edit_outlined),
          ),
        ],
      ),
    ),
  );
}

class _CompanionCard extends StatelessWidget {
  const _CompanionCard({required this.item, this.onEdit, this.onDeactivate});

  final WorshipCompanionAssignment item;
  final VoidCallback? onEdit;
  final VoidCallback? onDeactivate;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('worship-companion-${item.id}'),
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: NusaColors.primary.withValues(alpha: 0.09),
              borderRadius: BorderRadius.circular(13),
            ),
            child: const Icon(
              Icons.supervisor_account_outlined,
              color: NusaColors.primary,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.employeeName,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (item.employeeNip?.isNotEmpty == true) ...[
                  const SizedBox(height: 2),
                  Text(
                    item.employeeNip!,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10,
                    ),
                  ),
                ],
                const SizedBox(height: 7),
                Wrap(
                  spacing: 5,
                  runSpacing: 5,
                  children: item.allClasses
                      ? const [_ScopeBadge(label: 'Seluruh kelas')]
                      : item.classes
                            .map((kelas) => _ScopeBadge(label: kelas.name))
                            .toList(growable: false),
                ),
              ],
            ),
          ),
          PopupMenuButton<String>(
            key: Key('worship-companion-menu-${item.id}'),
            tooltip: 'Aksi pendamping',
            onSelected: (value) {
              if (value == 'edit') onEdit?.call();
              if (value == 'deactivate') onDeactivate?.call();
            },
            itemBuilder: (context) => [
              PopupMenuItem(
                value: 'edit',
                enabled: onEdit != null,
                child: const Text('Atur'),
              ),
              PopupMenuItem(
                value: 'deactivate',
                enabled: onDeactivate != null,
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

class _ScopeBadge extends StatelessWidget {
  const _ScopeBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: NusaColors.success.withValues(alpha: 0.09),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: const TextStyle(
        color: NusaColors.success,
        fontSize: 9,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _EmptyCompanion extends StatelessWidget {
  const _EmptyCompanion();

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: const [
          Icon(
            Icons.person_search_outlined,
            size: 42,
            color: NusaColors.primary,
          ),
          SizedBox(height: 9),
          Text(
            'Belum ada pendamping ibadah siswi yang ditugaskan.',
            textAlign: TextAlign.center,
            style: TextStyle(color: NusaColors.textSecondary),
          ),
        ],
      ),
    ),
  );
}

class _NoAcademicYear extends StatelessWidget {
  const _NoAcademicYear({required this.onRefresh});

  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(38),
      children: const [
        Icon(Icons.event_busy_outlined, size: 52, color: NusaColors.primary),
        SizedBox(height: 12),
        Text(
          'Aktifkan tahun pelajaran terlebih dahulu sebelum mengatur batas dan pendamping ibadah siswi.',
          textAlign: TextAlign.center,
          style: TextStyle(color: NusaColors.textSecondary, height: 1.4),
        ),
      ],
    ),
  );
}

class _SettingsError extends StatelessWidget {
  const _SettingsError({required this.message, required this.onRetry});

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
      : 'Pengaturan berhalangan belum dapat diproses.';
}
