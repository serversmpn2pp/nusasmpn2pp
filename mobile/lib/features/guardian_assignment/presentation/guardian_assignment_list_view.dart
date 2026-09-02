import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/guardian_assignment/application/guardian_assignment_controller.dart';
import 'package:nusa/features/guardian_assignment/domain/guardian_assignment.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GuardianAssignmentListView extends ConsumerStatefulWidget {
  const GuardianAssignmentListView({super.key});

  @override
  ConsumerState<GuardianAssignmentListView> createState() =>
      _GuardianAssignmentListViewState();
}

class _GuardianAssignmentListViewState
    extends ConsumerState<GuardianAssignmentListView> {
  final _search = TextEditingController();
  bool _loadingMore = false;
  int? _endingId;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(guardianAssignmentControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Penugasan Guru Wali'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading ? null : _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('guardian-assignment-create'),
              onPressed: state.isLoading ? null : _openCreate,
              icon: const Icon(Icons.group_add_rounded),
              label: const Text('Atur Siswa'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (current != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 7, 16, 8),
                child: Column(
                  children: [
                    _Summary(summary: current.summary),
                    const SizedBox(height: 9),
                    TextField(
                      key: const Key('guardian-assignment-search'),
                      controller: _search,
                      enabled: !state.isLoading,
                      onChanged: ref
                          .read(guardianAssignmentControllerProvider.notifier)
                          .search,
                      decoration: const InputDecoration(
                        hintText: 'Cari siswa, NISN, atau Guru Wali',
                        prefixIcon: Icon(Icons.search_rounded),
                      ),
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<int?>(
                      fieldKey: const Key('guardian-assignment-filter'),
                      value: current.filter.guardianId,
                      enabled: !state.isLoading,
                      decoration: const InputDecoration(
                        labelText: 'Guru Wali',
                        prefixIcon: Icon(Icons.supervisor_account_rounded),
                      ),
                      options: [
                        const NusaDropdownOption(
                          value: null,
                          label: 'Semua Guru Wali',
                        ),
                        for (final employee in current.options.employees)
                          NusaDropdownOption(
                            value: employee.id,
                            label:
                                '${employee.name} (${employee.activeStudentCount} siswa)',
                          ),
                      ],
                      onChanged: ref
                          .read(guardianAssignmentControllerProvider.notifier)
                          .filterGuardian,
                    ),
                  ],
                ),
              ),
            Expanded(
              child: state.when(
                loading: () => current == null
                    ? const Center(child: CircularProgressIndicator())
                    : _Results(
                        page: current,
                        loadingMore: _loadingMore,
                        endingId: _endingId,
                        onRefresh: _refresh,
                        onLoadMore: _loadMore,
                        onOpen: _showDetail,
                        onEnd: _confirmEnd,
                      ),
                error: (error, stackTrace) =>
                    _Error(message: _message(error), onRetry: _refresh),
                data: (page) => _Results(
                  page: page,
                  loadingMore: _loadingMore,
                  endingId: _endingId,
                  onRefresh: _refresh,
                  onLoadMore: _loadMore,
                  onOpen: _showDetail,
                  onEnd: _confirmEnd,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _refresh() =>
      ref.read(guardianAssignmentControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(guardianAssignmentControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openCreate() async {
    final message = await context.push<String>('/penugasan-guru-wali/tambah');
    if (!mounted) return;
    await _refresh();
    if (message != null && mounted) _snack(message);
  }

  void _showDetail(GuardianAssignmentItem item) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (context) => _DetailSheet(item: item),
    );
  }

  Future<void> _confirmEnd(GuardianAssignmentItem item) async {
    final accepted = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Akhiri penugasan?'),
        content: Text(
          '${item.student.name} tidak lagi menjadi siswa wali ${item.guardian.name}. Riwayat penugasan tetap tersimpan.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('guardian-assignment-confirm-end'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Akhiri'),
          ),
        ],
      ),
    );
    if (accepted != true || !mounted) return;
    setState(() => _endingId = item.id);
    try {
      final result = await ref
          .read(guardianAssignmentControllerProvider.notifier)
          .end(item.id);
      if (mounted) _snack(result.message);
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _endingId = null);
    }
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final GuardianAssignmentSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        _Stat(
          label: 'Siswa Aktif',
          value: summary.activeStudents,
          accent: true,
        ),
        _Stat(label: 'Ditugaskan', value: summary.assignedStudents),
        _Stat(label: 'Belum', value: summary.unassignedStudents),
        _Stat(label: 'Guru Wali', value: summary.activeGuardians),
      ],
    ),
  );
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value, this.accent = false});
  final String label;
  final int value;
  final bool accent;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: accent ? NusaColors.accent : Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Colors.white70, fontSize: 8.5),
        ),
      ],
    ),
  );
}

class _Results extends StatelessWidget {
  const _Results({
    required this.page,
    required this.loadingMore,
    required this.endingId,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
    required this.onEnd,
  });
  final GuardianAssignmentPage page;
  final bool loadingMore;
  final int? endingId;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onLoadMore;
  final ValueChanged<GuardianAssignmentItem> onOpen;
  final ValueChanged<GuardianAssignmentItem> onEnd;

  @override
  Widget build(BuildContext context) => page.items.isEmpty
      ? RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(28, 44, 28, 110),
            children: const [
              Icon(
                Icons.supervisor_account_outlined,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Belum ada penugasan Guru Wali pada filter ini.',
                textAlign: TextAlign.center,
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
            ],
          ),
        )
      : RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 3, 16, 110),
            itemCount:
                page.items.length +
                (page.pagination.hasNextPage || loadingMore ? 1 : 0),
            itemBuilder: (context, index) {
              if (index >= page.items.length) {
                if (!loadingMore) {
                  WidgetsBinding.instance.addPostFrameCallback(
                    (_) => onLoadMore(),
                  );
                }
                return const Padding(
                  padding: EdgeInsets.all(16),
                  child: Center(child: CircularProgressIndicator()),
                );
              }
              final item = page.items[index];
              return Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: Card(
                  child: InkWell(
                    onTap: () => onOpen(item),
                    borderRadius: BorderRadius.circular(17),
                    child: Padding(
                      padding: const EdgeInsets.all(13),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 46,
                            height: 46,
                            decoration: BoxDecoration(
                              color: NusaColors.surfaceBlue,
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: const Icon(
                              Icons.school_rounded,
                              color: NusaColors.primary,
                            ),
                          ),
                          const SizedBox(width: 11),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item.student.name,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  '${item.schoolClass?.name ?? 'Belum ditempatkan'} · NISN ${item.student.nisn ?? '-'}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: NusaColors.textSecondary,
                                    fontSize: 10,
                                  ),
                                ),
                                const SizedBox(height: 7),
                                Text(
                                  item.guardian.name,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: NusaColors.primary,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                Text(
                                  'Mulai ${_dateLabel(item.startDate)}',
                                  style: const TextStyle(
                                    color: NusaColors.textSecondary,
                                    fontSize: 9.5,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            key: Key('guardian-assignment-end-${item.id}'),
                            tooltip: 'Akhiri penugasan',
                            onPressed: endingId == item.id
                                ? null
                                : () => onEnd(item),
                            icon: endingId == item.id
                                ? const SizedBox.square(
                                    dimension: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.person_remove_outlined),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        );
}

class _DetailSheet extends StatelessWidget {
  const _DetailSheet({required this.item});
  final GuardianAssignmentItem item;

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          item.student.name,
          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900),
        ),
        Text(
          '${item.schoolClass?.name ?? 'Belum ditempatkan'} · NISN ${item.student.nisn ?? '-'}',
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 11),
        ),
        const SizedBox(height: 14),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              children: [
                _InfoRow(label: 'Guru Wali', value: item.guardian.name),
                _InfoRow(label: 'NIP', value: item.guardian.nip ?? '-'),
                _InfoRow(
                  label: 'Mulai tugas',
                  value: _dateLabel(item.startDate),
                ),
                _InfoRow(label: 'Nomor SK', value: item.decreeNumber ?? '-'),
                _InfoRow(label: 'Catatan', value: item.note ?? '-'),
                _InfoRow(label: 'Dibuat oleh', value: item.createdBy ?? '-'),
              ],
            ),
          ),
        ),
      ],
    ),
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
          width: 105,
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
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
          ),
        ),
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

String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  if (date == null) return value.isEmpty ? '-' : value;
  return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Penugasan Guru Wali belum dapat diproses.',
};
