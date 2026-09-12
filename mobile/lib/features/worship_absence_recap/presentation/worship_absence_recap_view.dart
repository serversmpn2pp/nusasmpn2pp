import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_absence_recap/application/worship_absence_recap_controller.dart';
import 'package:nusa/features/worship_absence_recap/application/worship_absence_recap_document_service.dart';
import 'package:nusa/features/worship_absence_recap/domain/worship_absence_recap.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class WorshipAbsenceRecapView extends ConsumerStatefulWidget {
  const WorshipAbsenceRecapView({super.key});

  @override
  ConsumerState<WorshipAbsenceRecapView> createState() =>
      _WorshipAbsenceRecapViewState();
}

class _WorshipAbsenceRecapViewState
    extends ConsumerState<WorshipAbsenceRecapView> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  bool _loadingMore = false;
  bool _exporting = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final asyncPage = ref.watch(worshipAbsenceRecapControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Rekap Berhalangan'),
        actions: [
          if (_exporting)
            const Padding(
              padding: EdgeInsets.all(14),
              child: SizedBox.square(
                dimension: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            )
          else
            PopupMenuButton<_DocumentAction>(
              tooltip: 'Dokumen rekap',
              onSelected: _export,
              itemBuilder: (context) => const [
                PopupMenuItem(
                  value: _DocumentAction.print,
                  child: ListTile(
                    leading: Icon(Icons.print_outlined),
                    title: Text('Cetak PDF'),
                    contentPadding: EdgeInsets.zero,
                  ),
                ),
                PopupMenuItem(
                  value: _DocumentAction.share,
                  child: ListTile(
                    leading: Icon(Icons.share_outlined),
                    title: Text('Bagikan PDF'),
                    contentPadding: EdgeInsets.zero,
                  ),
                ),
              ],
              icon: const Icon(Icons.picture_as_pdf_outlined),
            ),
          IconButton(
            tooltip: 'Perbarui',
            onPressed: asyncPage.isLoading
                ? null
                : () => ref
                      .read(worshipAbsenceRecapControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: asyncPage.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ErrorState(
            message: _errorMessage(error),
            onRetry: () => ref
                .read(worshipAbsenceRecapControllerProvider.notifier)
                .refresh(),
          ),
          data: (page) {
            if (_searchController.text.isEmpty &&
                page.filter.query.isNotEmpty) {
              _searchController.text = page.filter.query;
            }
            return RefreshIndicator(
              onRefresh: ref
                  .read(worshipAbsenceRecapControllerProvider.notifier)
                  .refresh,
              child: ListView(
                key: const PageStorageKey<String>('worship-absence-recap'),
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
                children: [
                  _PrivateHeader(page: page),
                  const SizedBox(height: 12),
                  _PrivacyNotice(message: page.privacyMessage),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: NusaTextField(
                          fieldKey: const Key('worship-absence-recap-search'),
                          controller: _searchController,
                          hintText: 'Cari nama siswi atau NISN',
                          prefixIcon: Icons.search_rounded,
                          textInputAction: TextInputAction.search,
                          onChanged: _search,
                          onFieldSubmitted: (value) {
                            _debounce?.cancel();
                            ref
                                .read(
                                  worshipAbsenceRecapControllerProvider
                                      .notifier,
                                )
                                .search(value);
                          },
                          suffixIcon: _searchController.text.isEmpty
                              ? null
                              : IconButton(
                                  tooltip: 'Hapus pencarian',
                                  onPressed: () {
                                    _debounce?.cancel();
                                    _searchController.clear();
                                    setState(() {});
                                    ref
                                        .read(
                                          worshipAbsenceRecapControllerProvider
                                              .notifier,
                                        )
                                        .search('');
                                  },
                                  icon: const Icon(Icons.close_rounded),
                                ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      SizedBox.square(
                        dimension: 56,
                        child: FilledButton.tonal(
                          key: const Key('worship-absence-recap-filter'),
                          style: FilledButton.styleFrom(
                            padding: EdgeInsets.zero,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(15),
                            ),
                          ),
                          onPressed: () => _showFilters(page),
                          child: Badge(
                            isLabelVisible:
                                page.filter.classId != null ||
                                page.filter.status != 'semua',
                            smallSize: 7,
                            backgroundColor: NusaColors.accent,
                            child: const Icon(Icons.tune_rounded),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _SummaryGrid(summary: page.summary),
                  const SizedBox(height: 18),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Daftar Periode',
                              style: TextStyle(
                                color: NusaColors.textPrimary,
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              _filterCaption(page),
                              style: const TextStyle(
                                color: NusaColors.textSecondary,
                                fontSize: 10.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Text(
                        '${page.pagination.total} periode',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  if (page.items.isEmpty)
                    const _EmptyState()
                  else
                    ...page.items.map(
                      (item) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _PeriodCard(
                          item: item,
                          onTap: () => context.push(
                            '/konfirmasi-berhalangan-ibadah/${item.id}',
                          ),
                        ),
                      ),
                    ),
                  if (page.pagination.hasNextPage) ...[
                    const SizedBox(height: 2),
                    OutlinedButton.icon(
                      key: const Key('worship-absence-recap-load-more'),
                      onPressed: _loadingMore ? null : _loadMore,
                      icon: _loadingMore
                          ? const SizedBox.square(
                              dimension: 17,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.expand_more_rounded),
                      label: Text(
                        _loadingMore ? 'Memuat...' : 'Muat data berikutnya',
                      ),
                    ),
                  ],
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  void _search(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 700), () {
      ref.read(worshipAbsenceRecapControllerProvider.notifier).search(value);
    });
  }

  Future<void> _showFilters(WorshipAbsenceRecapPage page) async {
    final result = await showModalBottomSheet<_FilterSelection>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (context) => _FilterSheet(page: page),
    );
    if (result == null) return;
    await ref
        .read(worshipAbsenceRecapControllerProvider.notifier)
        .applyFilters(
          month: result.month,
          classId: result.classId,
          status: result.status,
        );
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(worshipAbsenceRecapControllerProvider.notifier).loadMore();
    } catch (error) {
      _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _export(_DocumentAction action) async {
    if (_exporting) return;
    setState(() => _exporting = true);
    try {
      final page = await ref
          .read(worshipAbsenceRecapControllerProvider.notifier)
          .fetchForDocument();
      final service = ref.read(worshipAbsenceRecapDocumentServiceProvider);
      if (action == _DocumentAction.print) {
        await service.printReport(page);
      } else {
        await service.shareReport(page);
      }
    } catch (error) {
      _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

enum _DocumentAction { print, share }

class _PrivateHeader extends StatelessWidget {
  const _PrivateHeader({required this.page});
  final WorshipAbsenceRecapPage page;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primaryDark, NusaColors.primary],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: .17),
          blurRadius: 18,
          offset: const Offset(0, 8),
        ),
      ],
    ),
    child: Row(
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: .13),
            borderRadius: BorderRadius.circular(15),
          ),
          child: const Icon(
            Icons.shield_rounded,
            color: NusaColors.accent,
            size: 27,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'REKAP PRIVAT PENDAMPING',
                style: TextStyle(
                  color: NusaColors.accent,
                  fontSize: 10,
                  fontWeight: FontWeight.w900,
                  letterSpacing: .8,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                page.monthLabel,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 19,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                'Tahun Pelajaran ${page.academicYear?.name ?? '-'}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Color(0xFFDCEBFA), fontSize: 11),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _PrivacyNotice extends StatelessWidget {
  const _PrivacyNotice({required this.message});
  final String message;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF7DA),
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.accent.withValues(alpha: .45)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.lock_outline_rounded, color: Color(0xFF8A6500)),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(
              color: Color(0xFF725600),
              fontSize: 10.5,
              height: 1.4,
            ),
          ),
        ),
      ],
    ),
  );
}

class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.summary});
  final WorshipAbsenceSummary summary;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final columns = constraints.maxWidth >= 600 ? 5 : 3;
      return GridView.count(
        crossAxisCount: columns,
        mainAxisSpacing: 8,
        crossAxisSpacing: 8,
        childAspectRatio: constraints.maxWidth >= 600 ? 1.75 : 1.18,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        children: [
          _SummaryTile(label: 'Periode', value: summary.periods),
          _SummaryTile(label: 'Siswi', value: summary.students),
          _SummaryTile(label: 'Dipantau', value: summary.active),
          _SummaryTile(
            label: 'Perlu konfirmasi',
            value: summary.needsConfirmation,
            warning: summary.needsConfirmation > 0,
          ),
          _SummaryTile(label: 'Selesai', value: summary.completed),
        ],
      );
    },
  );
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile({
    required this.label,
    required this.value,
    this.warning = false,
  });
  final String label;
  final int value;
  final bool warning;

  @override
  Widget build(BuildContext context) {
    final color = warning ? const Color(0xFFB57900) : NusaColors.primary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            '$value',
            style: TextStyle(
              color: color,
              fontSize: 19,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 9,
              height: 1.2,
            ),
          ),
        ],
      ),
    );
  }
}

class _PeriodCard extends StatelessWidget {
  const _PeriodCard({required this.item, required this.onTap});
  final WorshipAbsenceRecapItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final statusColor = switch (item.status) {
      'perlu_konfirmasi' => const Color(0xFFB57900),
      'selesai' => NusaColors.success,
      _ => NusaColors.primary,
    };
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      child: InkWell(
        key: Key('worship-absence-period-${item.id}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(17),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(17),
            border: Border.all(color: NusaColors.outline),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CircleAvatar(
                    radius: 22,
                    backgroundColor: NusaColors.surfaceBlue,
                    child: Text(
                      item.student.initials,
                      style: const TextStyle(
                        color: NusaColors.primary,
                        fontWeight: FontWeight.w900,
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
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          '${item.schoolClass.name}  |  NISN ${item.student.nisn ?? '-'}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 10.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: statusColor.withValues(alpha: .1),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      item.statusLabel,
                      style: TextStyle(
                        color: statusColor,
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: NusaColors.background,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    const Icon(
                      Icons.date_range_outlined,
                      color: NusaColors.primary,
                      size: 18,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        '${item.startDateLabel} - ${item.endDateLabel}',
                        style: const TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    Text(
                      '${item.durationDays} hari',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: _CountFact(
                      icon: Icons.qr_code_scanner_rounded,
                      label: 'Scan bulan ini',
                      value: item.monthlyScans,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _CountFact(
                      icon: Icons.verified_user_outlined,
                      label: 'Konfirmasi',
                      value: item.monthlyConfirmations,
                    ),
                  ),
                ],
              ),
              if (item.lastConfirmation != null ||
                  item.completionMethod != null) ...[
                const SizedBox(height: 9),
                Text(
                  item.lastConfirmation == null
                      ? 'Diselesaikan melalui ${item.completionMethod}'
                      : 'Terakhir: ${item.lastConfirmation!.resultLabel} | ${item.lastConfirmation!.dateLabel}',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10,
                  ),
                ),
              ],
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: const [
                  Text(
                    'Buka riwayat privat',
                    style: TextStyle(
                      color: NusaColors.primary,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  SizedBox(width: 3),
                  Icon(
                    Icons.chevron_right_rounded,
                    color: NusaColors.primary,
                    size: 18,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CountFact extends StatelessWidget {
  const _CountFact({
    required this.icon,
    required this.label,
    required this.value,
  });
  final IconData icon;
  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 8),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(11),
    ),
    child: Row(
      children: [
        Icon(icon, size: 17, color: NusaColors.primary),
        const SizedBox(width: 7),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '$value',
                style: const TextStyle(
                  color: NusaColors.primary,
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 8.5,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _FilterSelection {
  const _FilterSelection({
    required this.month,
    required this.classId,
    required this.status,
  });
  final String month;
  final int? classId;
  final String status;
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.page});
  final WorshipAbsenceRecapPage page;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late String _month = widget.page.month;
  late int? _classId = widget.page.filter.classId;
  late String _status = widget.page.filter.status;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(top: 36),
    padding: EdgeInsets.fromLTRB(
      16,
      10,
      16,
      16 + MediaQuery.viewInsetsOf(context).bottom,
    ),
    decoration: const BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    child: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 42,
              height: 4,
              decoration: BoxDecoration(
                color: NusaColors.outline,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            'Filter Rekap',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 4),
          const Text(
            'Pilih periode dan cakupan data privat yang ingin ditampilkan.',
            style: TextStyle(color: NusaColors.textSecondary, fontSize: 11),
          ),
          const SizedBox(height: 16),
          InkWell(
            key: const Key('worship-absence-recap-month-filter'),
            onTap: _selectMonth,
            borderRadius: BorderRadius.circular(14),
            child: InputDecorator(
              decoration: const InputDecoration(
                labelText: 'Bulan rekap',
                prefixIcon: Icon(Icons.calendar_month_outlined),
                suffixIcon: Icon(Icons.chevron_right_rounded),
              ),
              child: Text(_monthLabel(_month)),
            ),
          ),
          const SizedBox(height: 10),
          NusaDropdownField<int?>(
            fieldKey: const Key('worship-absence-recap-class-filter'),
            value: _classId,
            decoration: const InputDecoration(
              labelText: 'Cakupan kelas',
              prefixIcon: Icon(Icons.class_outlined),
            ),
            options: [
              const NusaDropdownOption<int?>(
                value: null,
                label: 'Semua kelas yang ditugaskan',
              ),
              ...widget.page.classes.map(
                (item) =>
                    NusaDropdownOption<int?>(value: item.id, label: item.name),
              ),
            ],
            onChanged: (value) => setState(() => _classId = value),
          ),
          const SizedBox(height: 10),
          NusaDropdownField<String>(
            fieldKey: const Key('worship-absence-recap-status-filter'),
            value: _status,
            decoration: const InputDecoration(
              labelText: 'Status periode',
              prefixIcon: Icon(Icons.fact_check_outlined),
            ),
            options: widget.page.statuses
                .map(
                  (item) => NusaDropdownOption<String>(
                    value: item.code,
                    label: item.label,
                  ),
                )
                .toList(),
            onChanged: (value) {
              if (value != null) setState(() => _status = value);
            },
          ),
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              key: const Key('worship-absence-recap-apply-filter'),
              onPressed: () => Navigator.pop(
                context,
                _FilterSelection(
                  month: _month,
                  classId: _classId,
                  status: _status,
                ),
              ),
              icon: const Icon(Icons.check_rounded),
              label: const Text('Terapkan Filter'),
            ),
          ),
        ],
      ),
    ),
  );

  Future<void> _selectMonth() async {
    final selected = _parseMonth(_month) ?? DateTime.now();
    final minimum = _parseMonth(widget.page.minimumMonth) ?? selected;
    final maximum = _parseMonth(widget.page.maximumMonth) ?? selected;
    final result = await showDialog<DateTime>(
      context: context,
      builder: (context) => _MonthPickerDialog(
        selected: selected,
        minimum: minimum,
        maximum: maximum,
      ),
    );
    if (result == null) return;
    setState(() {
      _month =
          '${result.year.toString().padLeft(4, '0')}-${result.month.toString().padLeft(2, '0')}';
    });
  }
}

class _MonthPickerDialog extends StatefulWidget {
  const _MonthPickerDialog({
    required this.selected,
    required this.minimum,
    required this.maximum,
  });
  final DateTime selected;
  final DateTime minimum;
  final DateTime maximum;

  @override
  State<_MonthPickerDialog> createState() => _MonthPickerDialogState();
}

class _MonthPickerDialogState extends State<_MonthPickerDialog> {
  late int _year = widget.selected.year;
  static const _months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agt',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];

  @override
  Widget build(BuildContext context) => AlertDialog(
    insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 24),
    title: Row(
      children: [
        const Expanded(
          child: Text(
            'Pilih Bulan',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
        ),
        IconButton(
          tooltip: 'Tutup',
          onPressed: () => Navigator.pop(context),
          icon: const Icon(Icons.close_rounded),
        ),
      ],
    ),
    content: SizedBox(
      width: 330,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconButton(
                onPressed: _year > widget.minimum.year
                    ? () => setState(() => _year--)
                    : null,
                icon: const Icon(Icons.chevron_left_rounded),
              ),
              SizedBox(
                width: 82,
                child: Text(
                  '$_year',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              IconButton(
                onPressed: _year < widget.maximum.year
                    ? () => setState(() => _year++)
                    : null,
                icon: const Icon(Icons.chevron_right_rounded),
              ),
            ],
          ),
          const SizedBox(height: 8),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 3,
              mainAxisExtent: 47,
              crossAxisSpacing: 8,
              mainAxisSpacing: 8,
            ),
            itemCount: 12,
            itemBuilder: (context, index) {
              final month = DateTime(_year, index + 1);
              final enabled =
                  !_beforeMonth(month, widget.minimum) &&
                  !_afterMonth(month, widget.maximum);
              final selected =
                  month.year == widget.selected.year &&
                  month.month == widget.selected.month;
              return Material(
                color: selected
                    ? NusaColors.primary
                    : enabled
                    ? NusaColors.background
                    : NusaColors.outline.withValues(alpha: .45),
                borderRadius: BorderRadius.circular(11),
                child: InkWell(
                  onTap: enabled ? () => Navigator.pop(context, month) : null,
                  borderRadius: BorderRadius.circular(11),
                  child: Center(
                    child: Text(
                      _months[index],
                      style: TextStyle(
                        color: selected
                            ? Colors.white
                            : enabled
                            ? NusaColors.textPrimary
                            : NusaColors.textSecondary.withValues(alpha: .5),
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ],
      ),
    ),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 28),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: const Column(
      children: [
        Icon(
          Icons.event_available_outlined,
          size: 42,
          color: NusaColors.primary,
        ),
        SizedBox(height: 10),
        Text(
          'Tidak ada periode berhalangan',
          textAlign: TextAlign.center,
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        SizedBox(height: 5),
        Text(
          'Ubah bulan, kelas, status, atau kata pencarian untuk melihat data lain.',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 11.5,
            height: 1.4,
          ),
        ),
      ],
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.shield_outlined,
            size: 50,
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

String _filterCaption(WorshipAbsenceRecapPage page) {
  final selectedClass = page.classes
      .where((item) => item.id == page.filter.classId)
      .firstOrNull;
  final selectedStatus = page.statuses
      .where((item) => item.code == page.filter.status)
      .firstOrNull;
  return '${page.monthLabel} | ${selectedClass?.name ?? 'Semua kelas'} | ${selectedStatus?.label ?? 'Semua status'}';
}

DateTime? _parseMonth(String value) =>
    value.isEmpty ? null : DateTime.tryParse('$value-01');

bool _beforeMonth(DateTime value, DateTime other) =>
    value.year < other.year ||
    (value.year == other.year && value.month < other.month);

bool _afterMonth(DateTime value, DateTime other) =>
    value.year > other.year ||
    (value.year == other.year && value.month > other.month);

String _monthLabel(String value) {
  final month = _parseMonth(value);
  if (month == null) return '-';
  const names = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
  ];
  return '${names[month.month - 1]} ${month.year}';
}

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Rekap berhalangan belum dapat dimuat. Silakan coba lagi.';
