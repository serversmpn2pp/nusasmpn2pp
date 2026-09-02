import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/point_reduction/application/point_reduction_controller.dart';
import 'package:nusa/features/point_reduction/data/point_reduction_file_services.dart';
import 'package:nusa/features/point_reduction/domain/point_reduction.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class PointReductionView extends ConsumerStatefulWidget {
  const PointReductionView({super.key});

  @override
  ConsumerState<PointReductionView> createState() => _PointReductionViewState();
}

class _PointReductionViewState extends ConsumerState<PointReductionView> {
  final _searchController = TextEditingController();
  bool _loadingMore = false;
  int? _downloadingId;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(pointReductionControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Penghargaan & Pengurangan Poin'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading ? null : _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.access.canSubmit == true
          ? FloatingActionButton.extended(
              key: const Key('point-reduction-create'),
              onPressed: state.isLoading ? null : () => _showCreate(current!),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Ajukan'),
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
                      key: const Key('point-reduction-search'),
                      controller: _searchController,
                      enabled: !state.isLoading,
                      onChanged: ref
                          .read(pointReductionControllerProvider.notifier)
                          .search,
                      decoration: const InputDecoration(
                        hintText: 'Cari siswa atau kegiatan',
                        prefixIcon: Icon(Icons.search_rounded),
                      ),
                    ),
                    const SizedBox(height: 8),
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final status = NusaDropdownField<String>(
                          fieldKey: const Key('point-reduction-status'),
                          value: current.filter.status,
                          enabled: !state.isLoading,
                          decoration: const InputDecoration(
                            labelText: 'Status',
                            prefixIcon: Icon(Icons.filter_alt_rounded),
                          ),
                          options: [
                            for (final item in current.options.statuses)
                              NusaDropdownOption(
                                value: item.code,
                                label: item.label,
                              ),
                          ],
                          onChanged: (value) {
                            if (value != null) {
                              ref
                                  .read(
                                    pointReductionControllerProvider.notifier,
                                  )
                                  .filterStatus(value);
                            }
                          },
                        );
                        final filter = OutlinedButton.icon(
                          key: const Key('point-reduction-open-filter'),
                          onPressed: state.isLoading
                              ? null
                              : () => _showFilters(current),
                          icon: const Icon(Icons.tune_rounded),
                          label: const Text('Tahun/Kelas'),
                        );
                        if (constraints.maxWidth < 330) {
                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              status,
                              const SizedBox(height: 8),
                              SizedBox(height: 48, child: filter),
                            ],
                          );
                        }
                        return Row(
                          children: [
                            Expanded(child: status),
                            const SizedBox(width: 8),
                            SizedBox(height: 56, child: filter),
                          ],
                        );
                      },
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
                        downloadingId: _downloadingId,
                        onRefresh: _refresh,
                        onLoadMore: _loadMore,
                        onOpen: _showDetail,
                        onDownload: _download,
                        onDecide: _decide,
                      ),
                error: (error, stackTrace) =>
                    _Error(message: _message(error), onRetry: _refresh),
                data: (page) => _Results(
                  page: page,
                  loadingMore: _loadingMore,
                  downloadingId: _downloadingId,
                  onRefresh: _refresh,
                  onLoadMore: _loadMore,
                  onOpen: _showDetail,
                  onDownload: _download,
                  onDecide: _decide,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _refresh() =>
      ref.read(pointReductionControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(pointReductionControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _showFilters(PointReductionPage page) async {
    final result = await showModalBottomSheet<({int? yearId, int? classId})>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (context) => _FilterSheet(page: page),
    );
    if (result == null) return;
    await ref
        .read(pointReductionControllerProvider.notifier)
        .applyFilters(academicYearId: result.yearId, classId: result.classId);
  }

  Future<void> _showCreate(PointReductionPage page) async {
    final result = await showModalBottomSheet<PointReductionCreatePayload>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _CreateSheet(
        page: page,
        onPickEvidence: () => ref.read(pointReductionFilePickerProvider).pick(),
      ),
    );
    if (result == null || !mounted) return;
    try {
      final mutation = await ref
          .read(pointReductionControllerProvider.notifier)
          .create(result);
      if (mounted) _snack(mutation.message);
    } catch (error) {
      if (mounted) _snack(_message(error));
    }
  }

  void _showDetail(PointReductionItem item) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => _DetailSheet(
        item: item,
        downloading: _downloadingId == item.id,
        onDownload: item.evidence == null
            ? null
            : () {
                Navigator.pop(sheetContext);
                _download(item);
              },
        onDecide: item.canDecide
            ? () {
                Navigator.pop(sheetContext);
                _decide(item);
              }
            : null,
      ),
    );
  }

  Future<void> _decide(PointReductionItem item) async {
    final result = await showDialog<({String decision, String? note})>(
      context: context,
      builder: (context) => _DecisionDialog(item: item),
    );
    if (result == null || !mounted) return;
    try {
      final mutation = await ref
          .read(pointReductionControllerProvider.notifier)
          .decide(id: item.id, decision: result.decision, note: result.note);
      if (mounted) _snack(mutation.message);
    } catch (error) {
      if (mounted) _snack(_message(error));
    }
  }

  Future<void> _download(PointReductionItem item) async {
    if (_downloadingId != null) return;
    setState(() => _downloadingId = item.id);
    try {
      final download = await ref
          .read(pointReductionControllerProvider.notifier)
          .download(item);
      final saved = await ref
          .read(pointReductionFileSaverProvider)
          .save(download);
      if (mounted) {
        _snack(saved ? 'Bukti berhasil disimpan.' : 'Penyimpanan dibatalkan.');
      }
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _downloadingId = null);
    }
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _DecisionDialog extends StatefulWidget {
  const _DecisionDialog({required this.item});
  final PointReductionItem item;

  @override
  State<_DecisionDialog> createState() => _DecisionDialogState();
}

class _DecisionDialogState extends State<_DecisionDialog> {
  final _note = TextEditingController();

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Putusan Penghargaan'),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('${widget.item.student.name} · -${widget.item.points} poin'),
          const SizedBox(height: 12),
          TextField(
            key: const Key('point-reduction-decision-note'),
            controller: _note,
            minLines: 3,
            maxLines: 5,
            maxLength: 2000,
            decoration: const InputDecoration(
              labelText: 'Catatan keputusan',
              hintText: 'Tuliskan hasil verifikasi bila diperlukan',
              alignLabelWithHint: true,
            ),
          ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Batal'),
      ),
      OutlinedButton(
        key: const Key('point-reduction-reject'),
        onPressed: () => Navigator.pop(context, (
          decision: 'ditolak',
          note: _optional(_note.text),
        )),
        child: const Text('Tolak'),
      ),
      FilledButton(
        key: const Key('point-reduction-approve'),
        onPressed: () => Navigator.pop(context, (
          decision: 'disetujui',
          note: _optional(_note.text),
        )),
        child: const Text('Setujui'),
      ),
    ],
  );
}

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final PointReductionSummary summary;

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
        _Stat(label: 'Pengajuan', value: summary.all, accent: true),
        _Stat(label: 'Menunggu', value: summary.pending),
        _Stat(label: 'Disetujui', value: summary.approved),
        _Stat(label: 'Poin Turun', value: summary.approvedPoints),
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
    required this.downloadingId,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
    required this.onDownload,
    required this.onDecide,
  });
  final PointReductionPage page;
  final bool loadingMore;
  final int? downloadingId;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onLoadMore;
  final ValueChanged<PointReductionItem> onOpen;
  final ValueChanged<PointReductionItem> onDownload;
  final ValueChanged<PointReductionItem> onDecide;

  @override
  Widget build(BuildContext context) => page.items.isEmpty
      ? RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(28, 44, 28, 110),
            children: const [
              Icon(
                Icons.emoji_events_outlined,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              SizedBox(height: 12),
              Text(
                'Belum ada pengajuan pada filter ini.',
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
                child: _ReductionCard(
                  item: item,
                  downloading: downloadingId == item.id,
                  onOpen: () => onOpen(item),
                  onDownload: item.evidence == null
                      ? null
                      : () => onDownload(item),
                  onDecide: item.canDecide ? () => onDecide(item) : null,
                ),
              );
            },
          ),
        );
}

class _ReductionCard extends StatelessWidget {
  const _ReductionCard({
    required this.item,
    required this.downloading,
    required this.onOpen,
    required this.onDownload,
    required this.onDecide,
  });
  final PointReductionItem item;
  final bool downloading;
  final VoidCallback onOpen;
  final VoidCallback? onDownload;
  final VoidCallback? onDecide;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(item.status);
    return Card(
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(17),
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Center(
                      child: Text(
                        '-${item.points}',
                        style: TextStyle(
                          color: color,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
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
                          style: const TextStyle(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          '${item.schoolClass?.name ?? 'Tanpa kelas'} · ${_dateLabel(item.activityDate)}',
                          style: const TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 10,
                          ),
                        ),
                        const SizedBox(height: 7),
                        Text(
                          item.activity,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 12.5),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 7),
                  _Badge(label: item.statusLabel, color: color),
                ],
              ),
              if (item.evidence != null || item.canDecide) ...[
                const SizedBox(height: 9),
                Wrap(
                  spacing: 8,
                  runSpacing: 7,
                  children: [
                    if (item.evidence != null)
                      OutlinedButton.icon(
                        onPressed: downloading ? null : onDownload,
                        icon: downloading
                            ? const SizedBox.square(
                                dimension: 14,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Icon(Icons.download_rounded, size: 17),
                        label: const Text('Bukti'),
                      ),
                    if (item.canDecide)
                      FilledButton.icon(
                        key: Key('point-reduction-decide-${item.id}'),
                        onPressed: onDecide,
                        icon: const Icon(Icons.fact_check_rounded, size: 17),
                        label: const Text('Putuskan'),
                      ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.page});
  final PointReductionPage page;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  int? _yearId;
  int? _classId;

  @override
  void initState() {
    super.initState();
    _yearId = widget.page.filter.academicYearId;
    _classId = widget.page.filter.classId;
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Filter Riwayat',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 14),
        NusaDropdownField<int?>(
          fieldKey: const Key('point-reduction-year-filter'),
          value: _yearId,
          decoration: const InputDecoration(labelText: 'Tahun pelajaran'),
          options: [
            const NusaDropdownOption(value: null, label: 'Tahun aktif'),
            for (final item in widget.page.options.academicYears)
              NusaDropdownOption(value: item.id, label: item.name),
          ],
          onChanged: (value) => setState(() {
            _yearId = value;
            _classId = null;
          }),
        ),
        const SizedBox(height: 11),
        NusaDropdownField<int?>(
          fieldKey: const Key('point-reduction-class-filter'),
          value: _classId,
          decoration: const InputDecoration(labelText: 'Kelas'),
          options: [
            const NusaDropdownOption(value: null, label: 'Semua kelas'),
            for (final item in widget.page.options.classes)
              NusaDropdownOption(value: item.id, label: item.name),
          ],
          onChanged: (value) => setState(() => _classId = value),
        ),
        const SizedBox(height: 16),
        NusaPrimaryButton(
          label: 'Terapkan Filter',
          onPressed: () =>
              Navigator.pop(context, (yearId: _yearId, classId: _classId)),
        ),
      ],
    ),
  );
}

class _CreateSheet extends StatefulWidget {
  const _CreateSheet({required this.page, required this.onPickEvidence});
  final PointReductionPage page;
  final Future<ReductionPickedFile?> Function() onPickEvidence;

  @override
  State<_CreateSheet> createState() => _CreateSheetState();
}

class _CreateSheetState extends State<_CreateSheet> {
  final _formKey = GlobalKey<FormState>();
  final _description = TextEditingController();
  int? _studentId;
  late String _date;
  late String _activity;
  late int _points;
  ReductionPickedFile? _evidence;
  bool _picking = false;

  @override
  void initState() {
    super.initState();
    _date = _isoDate(DateTime.now());
    _activity = widget.page.options.activities.firstOrNull ?? '';
    _points = widget.page.options.points.firstOrNull ?? 10;
  }

  @override
  void dispose() {
    _description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 28),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Ajukan Penghargaan',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900),
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            Text(
              widget.page.activeAcademicYear == null
                  ? 'Tahun pelajaran aktif belum tersedia.'
                  : 'Tahun aktif ${widget.page.activeAcademicYear!.name}. Poin baru berkurang setelah disetujui.',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 11,
              ),
            ),
            const SizedBox(height: 14),
            NusaDropdownField<int>(
              fieldKey: const Key('point-reduction-student'),
              value: _studentId,
              enabled: widget.page.options.students.isNotEmpty,
              decoration: const InputDecoration(
                labelText: 'Siswa',
                hintText: 'Pilih siswa bersaldo poin',
                prefixIcon: Icon(Icons.person_search_rounded),
              ),
              options: [
                for (final item in widget.page.options.students)
                  NusaDropdownOption(
                    value: item.id,
                    label:
                        '${item.name} · ${item.schoolClass?.name ?? '-'} · ${item.balance} poin',
                  ),
              ],
              onChanged: (value) => setState(() => _studentId = value),
            ),
            if (widget.page.options.students.isEmpty) ...[
              const SizedBox(height: 6),
              const Text(
                'Tidak ada siswa dengan saldo poin pada tahun aktif.',
                style: TextStyle(color: NusaColors.textSecondary, fontSize: 10),
              ),
            ],
            const SizedBox(height: 11),
            InkWell(
              key: const Key('point-reduction-date'),
              onTap: _pickDate,
              borderRadius: BorderRadius.circular(14),
              child: InputDecorator(
                decoration: const InputDecoration(
                  labelText: 'Tanggal kegiatan',
                  prefixIcon: Icon(Icons.event_rounded),
                ),
                child: Text(_dateLabel(_date)),
              ),
            ),
            const SizedBox(height: 11),
            NusaDropdownField<String>(
              fieldKey: const Key('point-reduction-activity'),
              value: _activity,
              decoration: const InputDecoration(
                labelText: 'Kegiatan positif',
                prefixIcon: Icon(Icons.emoji_events_rounded),
              ),
              options: [
                for (final item in widget.page.options.activities)
                  NusaDropdownOption(value: item, label: item),
              ],
              onChanged: (value) {
                if (value != null) setState(() => _activity = value);
              },
            ),
            const SizedBox(height: 11),
            NusaDropdownField<int>(
              fieldKey: const Key('point-reduction-points'),
              value: _points,
              decoration: const InputDecoration(
                labelText: 'Pengurangan poin',
                prefixIcon: Icon(Icons.remove_circle_outline_rounded),
              ),
              options: [
                for (final item in widget.page.options.points)
                  NusaDropdownOption(value: item, label: '$item poin'),
              ],
              onChanged: (value) {
                if (value != null) setState(() => _points = value);
              },
            ),
            const SizedBox(height: 11),
            TextFormField(
              key: const Key('point-reduction-description'),
              controller: _description,
              minLines: 3,
              maxLines: 5,
              maxLength: 3000,
              decoration: const InputDecoration(
                labelText: 'Keterangan',
                hintText: 'Jelaskan kegiatan atau prestasi siswa',
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 2),
            OutlinedButton.icon(
              key: const Key('point-reduction-evidence'),
              onPressed: _picking ? null : _pickEvidence,
              icon: _picking
                  ? const SizedBox.square(
                      dimension: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.attach_file_rounded),
              label: Text(
                _evidence?.name ?? 'Pilih bukti (opsional, maks. 4 MB)',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
            const SizedBox(height: 14),
            NusaPrimaryButton(
              key: const Key('point-reduction-submit'),
              label: 'Kirim Pengajuan',
              onPressed:
                  widget.page.activeAcademicYear == null ||
                      widget.page.options.students.isEmpty
                  ? null
                  : _submit,
            ),
          ],
        ),
      ),
    ),
  );

  Future<void> _pickDate() async {
    final current = DateTime.tryParse(_date) ?? DateTime.now();
    final result = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (result != null && mounted) setState(() => _date = _isoDate(result));
  }

  Future<void> _pickEvidence() async {
    setState(() => _picking = true);
    try {
      final result = await widget.onPickEvidence();
      if (!mounted || result == null) return;
      if (result.bytes.length > 4 * 1024 * 1024) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Ukuran bukti maksimal 4 MB.')),
        );
        return;
      }
      setState(() => _evidence = result);
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (_studentId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih siswa terlebih dahulu.')),
      );
      return;
    }
    Navigator.pop(
      context,
      PointReductionCreatePayload(
        studentId: _studentId!,
        activityDate: _date,
        activity: _activity,
        points: _points,
        description: _optional(_description.text),
        evidence: _evidence,
      ),
    );
  }
}

class _DetailSheet extends StatelessWidget {
  const _DetailSheet({
    required this.item,
    required this.downloading,
    required this.onDownload,
    required this.onDecide,
  });
  final PointReductionItem item;
  final bool downloading;
  final VoidCallback? onDownload;
  final VoidCallback? onDecide;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(item.status);
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.student.name,
                      style: const TextStyle(
                        fontSize: 19,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      '${item.schoolClass?.name ?? 'Tanpa kelas'} · ${item.academicYear?.name ?? '-'}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ),
              _Badge(label: item.statusLabel, color: color),
            ],
          ),
          const SizedBox(height: 15),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _InfoRow(label: 'Kegiatan', value: item.activity),
                  _InfoRow(
                    label: 'Tanggal',
                    value: _dateLabel(item.activityDate),
                  ),
                  _InfoRow(label: 'Pengurangan', value: '${item.points} poin'),
                  _InfoRow(
                    label: 'Diajukan oleh',
                    value: item.submittedBy ?? '-',
                  ),
                  _InfoRow(label: 'Keterangan', value: item.description ?? '-'),
                ],
              ),
            ),
          ),
          if (item.evidence != null) ...[
            const SizedBox(height: 10),
            Card(
              child: ListTile(
                leading: const Icon(
                  Icons.description_rounded,
                  color: NusaColors.primary,
                ),
                title: Text(item.evidence!.fileName),
                subtitle: Text(item.evidence!.sizeLabel),
                trailing: downloading
                    ? const CircularProgressIndicator()
                    : const Icon(Icons.download_rounded),
                onTap: downloading ? null : onDownload,
              ),
            ),
          ],
          if (item.status != 'diajukan') ...[
            const SizedBox(height: 10),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Keputusan',
                      style: TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 8),
                    _InfoRow(label: 'Status', value: item.statusLabel),
                    _InfoRow(
                      label: 'Diputuskan oleh',
                      value: item.approvedBy ?? '-',
                    ),
                    _InfoRow(
                      label: 'Waktu',
                      value: _dateTimeLabel(item.decidedAt),
                    ),
                    _InfoRow(label: 'Catatan', value: item.decisionNote ?? '-'),
                  ],
                ),
              ),
            ),
          ],
          if (onDecide != null) ...[
            const SizedBox(height: 14),
            NusaPrimaryButton(
              label: 'Periksa dan Putuskan',
              onPressed: onDecide,
            ),
          ],
        ],
      ),
    );
  }
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
          width: 112,
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

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(99),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w800),
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

Color _statusColor(String status) => switch (status) {
  'disetujui' => NusaColors.success,
  'ditolak' => const Color(0xFFD84A3A),
  _ => const Color(0xFFC58F00),
};

String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  if (date == null) return value.isEmpty ? '-' : value;
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

String _dateTimeLabel(DateTime? value) {
  if (value == null) return '-';
  final minute = value.minute.toString().padLeft(2, '0');
  return '${_dateLabel(_isoDate(value))} ${value.hour.toString().padLeft(2, '0')}:$minute';
}

String _isoDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

String? _optional(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Penghargaan dan pengurangan poin belum dapat diproses.',
};
