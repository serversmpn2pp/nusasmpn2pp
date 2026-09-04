import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_recap/application/worship_recap_controller.dart';
import 'package:nusa/features/worship_recap/domain/worship_recap.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class WorshipRecapView extends ConsumerStatefulWidget {
  const WorshipRecapView({super.key});

  @override
  ConsumerState<WorshipRecapView> createState() => _WorshipRecapViewState();
}

class _WorshipRecapViewState extends ConsumerState<WorshipRecapView> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  bool _loadingMore = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final asyncPage = ref.watch(worshipRecapControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Rekap Ibadah Siswa'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: asyncPage.isLoading
                ? null
                : () => ref
                      .read(worshipRecapControllerProvider.notifier)
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
            onRetry: () =>
                ref.read(worshipRecapControllerProvider.notifier).refresh(),
          ),
          data: (page) => RefreshIndicator(
            onRefresh: ref
                .read(worshipRecapControllerProvider.notifier)
                .refresh,
            child: ListView(
              key: const PageStorageKey<String>('worship-recap-scroll'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
              children: [
                _HeaderCard(page: page),
                const SizedBox(height: 12),
                _FilterCard(
                  page: page,
                  onSelectDate: () => _selectDate(page),
                  onActivityChanged: (value) {
                    if (value != null) {
                      ref
                          .read(worshipRecapControllerProvider.notifier)
                          .selectActivity(value);
                    }
                  },
                  onClassChanged: (value) {
                    _searchController.clear();
                    ref
                        .read(worshipRecapControllerProvider.notifier)
                        .selectClass(value);
                  },
                ),
                const SizedBox(height: 12),
                _ScheduleCard(page: page),
                if (page.selectedActivity?.maleOnly ?? false) ...[
                  const SizedBox(height: 12),
                  const _FridayPrayerNotice(),
                ],
                const SizedBox(height: 12),
                _SummaryStrip(summary: page.summary),
                const SizedBox(height: 17),
                const Text(
                  'Ringkasan per Kelas',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Tekan kelas untuk membuka daftar siswanya.',
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 11.5,
                  ),
                ),
                const SizedBox(height: 10),
                _ClassGrid(
                  items: page.classSummaries,
                  selectedClassId: page.selectedClassId,
                  onSelect: (value) {
                    _searchController.clear();
                    ref
                        .read(worshipRecapControllerProvider.notifier)
                        .selectClass(value);
                  },
                ),
                const SizedBox(height: 15),
                _PrivacyNotice(message: page.privacyMessage),
                const SizedBox(height: 16),
                if (page.selectedClassId == null)
                  const _SelectClassState()
                else ...[
                  _StudentFilters(
                    searchController: _searchController,
                    status: page.filter.status,
                    onSearch: _search,
                    onSubmit: (value) {
                      _debounce?.cancel();
                      ref
                          .read(worshipRecapControllerProvider.notifier)
                          .search(value);
                    },
                    onClear: () {
                      _searchController.clear();
                      setState(() {});
                      ref
                          .read(worshipRecapControllerProvider.notifier)
                          .search('');
                    },
                    onStatusChanged: (value) {
                      if (value != null) {
                        ref
                            .read(worshipRecapControllerProvider.notifier)
                            .filterStatus(value);
                      }
                    },
                  ),
                  const SizedBox(height: 13),
                  _StudentSectionHeader(page: page),
                  const SizedBox(height: 9),
                  if (page.records.isEmpty)
                    const _EmptyStudents()
                  else
                    ...page.records.map(
                      (record) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _StudentCard(
                          record: record,
                          canCorrect:
                              page.access.canCorrect && record.canBeCorrected,
                          onCorrect: () => _openCorrection(page, record),
                        ),
                      ),
                    ),
                  if (page.pagination.hasNextPage) ...[
                    const SizedBox(height: 2),
                    OutlinedButton.icon(
                      key: const Key('worship-recap-load-more'),
                      onPressed: _loadingMore ? null : _loadMore,
                      icon: _loadingMore
                          ? const SizedBox.square(
                              dimension: 17,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.expand_more_rounded),
                      label: Text(
                        _loadingMore ? 'Memuat...' : 'Muat siswa berikutnya',
                      ),
                    ),
                  ],
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _selectDate(WorshipRecapPage page) async {
    final current = DateTime.tryParse(page.date) ?? DateTime.now();
    final now = DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: current.isAfter(now) ? now : current,
      firstDate: DateTime(now.year - 3),
      lastDate: DateTime(now.year, now.month, now.day),
      helpText: 'Pilih tanggal rekap ibadah',
    );
    if (selected == null) return;
    final value =
        '${selected.year.toString().padLeft(4, '0')}-'
        '${selected.month.toString().padLeft(2, '0')}-'
        '${selected.day.toString().padLeft(2, '0')}';
    await ref.read(worshipRecapControllerProvider.notifier).selectDate(value);
  }

  void _search(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      ref.read(worshipRecapControllerProvider.notifier).search(value);
    });
  }

  Future<void> _openCorrection(
    WorshipRecapPage page,
    WorshipRecapRecord record,
  ) async {
    final activityId = page.selectedActivity?.id;
    if (activityId == null) return;
    final changed = await context.push<bool>(
      '/rekap-kegiatan-ibadah/koreksi/${record.memberId}'
      '?tanggal=${page.date}&kegiatan=$activityId',
    );
    if (changed == true) {
      await ref.read(worshipRecapControllerProvider.notifier).refresh();
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(worshipRecapControllerProvider.notifier).loadMore();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

class _HeaderCard extends StatelessWidget {
  const _HeaderCard({required this.page});

  final WorshipRecapPage page;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.17),
          blurRadius: 18,
          offset: const Offset(0, 8),
        ),
      ],
    ),
    child: Row(
      children: [
        Container(
          width: 49,
          height: 49,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.13),
            borderRadius: BorderRadius.circular(15),
          ),
          child: const Icon(
            Icons.fact_check_rounded,
            color: NusaColors.accent,
            size: 27,
          ),
        ),
        const SizedBox(width: 13),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'REKAP HARIAN IBADAH',
                style: TextStyle(
                  color: NusaColors.accent,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 1,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                page.selectedActivity?.name ?? 'Kegiatan belum tersedia',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                page.academicYear == null
                    ? 'Tahun pelajaran belum aktif'
                    : 'Tahun Pelajaran ${page.academicYear!.name}',
                style: const TextStyle(color: Colors.white70, fontSize: 11),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _FilterCard extends StatelessWidget {
  const _FilterCard({
    required this.page,
    required this.onSelectDate,
    required this.onActivityChanged,
    required this.onClassChanged,
  });

  final WorshipRecapPage page;
  final VoidCallback onSelectDate;
  final ValueChanged<int?> onActivityChanged;
  final ValueChanged<int?> onClassChanged;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      children: [
        Material(
          color: NusaColors.surfaceBlue,
          borderRadius: BorderRadius.circular(14),
          child: InkWell(
            key: const Key('worship-recap-date'),
            onTap: onSelectDate,
            borderRadius: BorderRadius.circular(14),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
              child: Row(
                children: [
                  const Icon(
                    Icons.calendar_month_rounded,
                    color: NusaColors.primary,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      page.dateLabel,
                      style: const TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const Icon(
                    Icons.edit_calendar_outlined,
                    size: 19,
                    color: NusaColors.textSecondary,
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 10),
        NusaDropdownField<int>(
          fieldKey: const Key('worship-recap-activity'),
          value: page.selectedActivity?.id,
          options: page.activities
              .map(
                (item) => NusaDropdownOption<int>(
                  value: item.id,
                  label: item.active ? item.name : '${item.name} · nonaktif',
                ),
              )
              .toList(),
          decoration: const InputDecoration(
            labelText: 'Kegiatan ibadah',
            prefixIcon: Icon(Icons.self_improvement_rounded),
          ),
          onChanged: onActivityChanged,
        ),
        const SizedBox(height: 10),
        NusaDropdownField<int?>(
          fieldKey: const Key('worship-recap-class'),
          value: page.selectedClassId,
          options: [
            const NusaDropdownOption<int?>(value: null, label: 'Semua kelas'),
            ...page.classes.map(
              (item) => NusaDropdownOption<int?>(
                value: item.id,
                label: '${item.name} · ${item.studentCount} siswa',
              ),
            ),
          ],
          decoration: const InputDecoration(
            labelText: 'Kelas',
            prefixIcon: Icon(Icons.class_outlined),
          ),
          onChanged: onClassChanged,
        ),
      ],
    ),
  );
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({required this.page});

  final WorshipRecapPage page;

  @override
  Widget build(BuildContext context) {
    final schedule = page.schedule;
    final available = schedule != null;
    final color = available ? NusaColors.primary : const Color(0xFFB57900);
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: available ? NusaColors.surfaceBlue : const Color(0xFFFFF7DA),
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: color.withValues(alpha: 0.22)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            available ? Icons.schedule_rounded : Icons.event_busy_rounded,
            color: color,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              available
                  ? 'Pelaksanaan ${schedule.eventTime} WIB · Scan ${schedule.scanRange}'
                  : 'Tidak ada jadwal pada tanggal ini. Rekap tetap menampilkan catatan yang pernah tersimpan.',
              style: const TextStyle(fontSize: 11.5, height: 1.4),
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryStrip extends StatelessWidget {
  const _SummaryStrip({required this.summary});

  final WorshipRecapSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 13),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      children: [
        Row(
          children: [
            _SummaryItem(
              value: summary.total,
              label: 'Total siswa',
              color: NusaColors.primary,
            ),
            _SummaryItem(
              value: summary.atSchool,
              label: 'Hadir sekolah',
              color: NusaColors.primaryLight,
            ),
            _SummaryItem(
              value: summary.notAtSchool,
              label: 'Tidak hadir',
              color: NusaColors.textSecondary,
            ),
          ],
        ),
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 7, vertical: 10),
          child: Divider(height: 1, color: NusaColors.outline),
        ),
        Row(
          children: [
            _SummaryItem(
              value: summary.excused,
              label: 'Berhalangan',
              color: const Color(0xFF7657A6),
            ),
            _SummaryItem(
              value: summary.notRequired,
              label: 'Tidak wajib',
              color: NusaColors.primaryLight,
            ),
            _SummaryItem(
              value: summary.requiredToPray,
              label: 'Wajib salat',
              color: NusaColors.primary,
            ),
          ],
        ),
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 7, vertical: 10),
          child: Divider(height: 1, color: NusaColors.outline),
        ),
        Row(
          children: [
            _SummaryItem(
              value: summary.present,
              label: 'Sudah salat',
              color: NusaColors.success,
            ),
            _SummaryItem(
              value: summary.notPresent,
              label: 'Belum salat',
              color: const Color(0xFFB57900),
            ),
            _SummaryItem(
              value: summary.percentage,
              label: 'Capaian %',
              color: NusaColors.primaryLight,
            ),
          ],
        ),
      ],
    ),
  );
}

class _FridayPrayerNotice extends StatelessWidget {
  const _FridayPrayerNotice();

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('worship-recap-friday-notice'),
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF7DA),
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: NusaColors.accent.withValues(alpha: 0.5)),
    ),
    child: const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.mosque_rounded, color: NusaColors.primary, size: 21),
        SizedBox(width: 9),
        Expanded(
          child: Text(
            'Sholat Jumat khusus siswa laki-laki. Siswi yang hadir dicatat Tidak wajib (pulang) dan tidak masuk perhitungan capaian.',
            style: TextStyle(fontSize: 11, height: 1.4),
          ),
        ),
      ],
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({
    required this.value,
    required this.label,
    required this.color,
  });

  final int value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: color,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
          ),
        ),
      ],
    ),
  );
}

class _ClassGrid extends StatelessWidget {
  const _ClassGrid({
    required this.items,
    required this.selectedClassId,
    required this.onSelect,
  });

  final List<WorshipRecapClassSummary> items;
  final int? selectedClassId;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final width = (constraints.maxWidth - 9) / 2;
      return Wrap(
        spacing: 9,
        runSpacing: 9,
        children: items
            .map(
              (item) => SizedBox(
                width: width,
                child: _ClassCard(
                  item: item,
                  selected: selectedClassId == item.schoolClass.id,
                  onTap: () => onSelect(item.schoolClass.id),
                ),
              ),
            )
            .toList(),
      );
    },
  );
}

class _ClassCard extends StatelessWidget {
  const _ClassCard({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  final WorshipRecapClassSummary item;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final complete = item.requiredToPray > 0 && item.notPresent == 0;
    final color = complete ? NusaColors.success : NusaColors.primary;
    return Material(
      color: selected ? NusaColors.surfaceBlue : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(15),
        side: BorderSide(
          color: selected ? NusaColors.primary : NusaColors.outline,
          width: selected ? 1.5 : 1,
        ),
      ),
      child: InkWell(
        key: Key('worship-recap-class-${item.schoolClass.id}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(15),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      item.schoolClass.name,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                  Text(
                    '${item.percentage}%',
                    style: TextStyle(
                      color: color,
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                '${item.present} dari ${item.requiredToPray} siswa wajib salat',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10.5,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                '${item.notAtSchool} tidak hadir · ${item.excused} berhalangan · ${item.notRequired} tidak wajib',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9.5,
                ),
              ),
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: LinearProgressIndicator(
                  value: item.percentage.clamp(0, 100) / 100,
                  minHeight: 5,
                  color: color,
                  backgroundColor: NusaColors.outline,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PrivacyNotice extends StatelessWidget {
  const _PrivacyNotice({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.shield_outlined, size: 20, color: NusaColors.primary),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
              height: 1.4,
            ),
          ),
        ),
      ],
    ),
  );
}

class _StudentFilters extends StatelessWidget {
  const _StudentFilters({
    required this.searchController,
    required this.status,
    required this.onSearch,
    required this.onSubmit,
    required this.onClear,
    required this.onStatusChanged,
  });

  final TextEditingController searchController;
  final String status;
  final ValueChanged<String> onSearch;
  final ValueChanged<String> onSubmit;
  final VoidCallback onClear;
  final ValueChanged<String?> onStatusChanged;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      NusaTextField(
        fieldKey: const Key('worship-recap-search'),
        controller: searchController,
        hintText: 'Cari nama, NIS, atau NISN',
        prefixIcon: Icons.search_rounded,
        textInputAction: TextInputAction.search,
        onChanged: onSearch,
        onFieldSubmitted: onSubmit,
        suffixIcon: searchController.text.isEmpty
            ? null
            : IconButton(
                onPressed: onClear,
                icon: const Icon(Icons.close_rounded),
              ),
      ),
      const SizedBox(height: 9),
      NusaDropdownField<String>(
        fieldKey: const Key('worship-recap-status'),
        value: status,
        options: const [
          NusaDropdownOption(value: 'semua', label: 'Semua status'),
          NusaDropdownOption(value: 'sudah', label: 'Sudah salat'),
          NusaDropdownOption(value: 'belum', label: 'Belum salat'),
          NusaDropdownOption(value: 'berhalangan', label: 'Berhalangan'),
          NusaDropdownOption(
            value: 'tidak_wajib',
            label: 'Tidak wajib (pulang)',
          ),
          NusaDropdownOption(
            value: 'tidak_hadir',
            label: 'Tidak hadir sekolah',
          ),
        ],
        decoration: const InputDecoration(
          labelText: 'Status ibadah',
          prefixIcon: Icon(Icons.filter_alt_outlined),
        ),
        onChanged: onStatusChanged,
      ),
    ],
  );
}

class _StudentSectionHeader extends StatelessWidget {
  const _StudentSectionHeader({required this.page});

  final WorshipRecapPage page;

  @override
  Widget build(BuildContext context) {
    final name =
        page.classes
            .where((item) => item.id == page.selectedClassId)
            .firstOrNull
            ?.name ??
        '-';
    return Row(
      children: [
        Expanded(
          child: Text(
            'Siswa Kelas $name',
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
          ),
        ),
        Text(
          '${page.pagination.total} siswa',
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 11),
        ),
      ],
    );
  }
}

class _StudentCard extends StatelessWidget {
  const _StudentCard({
    required this.record,
    required this.canCorrect,
    required this.onCorrect,
  });

  final WorshipRecapRecord record;
  final bool canCorrect;
  final VoidCallback onCorrect;

  @override
  Widget build(BuildContext context) {
    final color = switch (record.status) {
      'sudah' => NusaColors.success,
      'belum' => const Color(0xFFB57900),
      'berhalangan' => const Color(0xFF7657A6),
      'tidak_wajib' => NusaColors.primaryLight,
      'tidak_hadir' => NusaColors.textSecondary,
      _ => NusaColors.primary,
    };
    final icon = switch (record.status) {
      'sudah' => Icons.check_circle_rounded,
      'belum' => Icons.schedule_rounded,
      'berhalangan' => Icons.privacy_tip_outlined,
      'tidak_wajib' => Icons.home_outlined,
      'tidak_hadir' => Icons.event_busy_rounded,
      _ => Icons.info_outline_rounded,
    };
    return Container(
      key: Key('worship-recap-student-${record.memberId}'),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 22,
                backgroundColor: color.withValues(alpha: 0.1),
                child: Text(
                  record.student.initials,
                  style: TextStyle(color: color, fontWeight: FontWeight.w900),
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${record.rollNumber ?? '-'}. ${record.student.name}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      'NISN ${record.student.nisn ?? '-'}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
              ),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 120),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(icon, color: color, size: 13),
                      const SizedBox(width: 4),
                      Flexible(
                        child: Text(
                          record.statusLabel,
                          maxLines: 2,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: color,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 11),
          _Fact(
            label: 'Kehadiran sekolah',
            value: record.schoolAttendanceLabel,
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: _Fact(
                  label: 'Waktu ibadah',
                  value: record.attendance?.time ?? '-',
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _Fact(
                  label: 'Sumber',
                  value: record.attendance?.sourceLabel ?? '-',
                ),
              ),
            ],
          ),
          if (record.attendance?.recordedBy != null) ...[
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Dicatat oleh ${record.attendance!.recordedBy}',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10.5,
                ),
              ),
            ),
          ],
          if (canCorrect) ...[
            const SizedBox(height: 11),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                key: Key('worship-recap-correct-${record.memberId}'),
                onPressed: onCorrect,
                icon: Icon(
                  record.present
                      ? Icons.edit_note_rounded
                      : Icons.add_task_rounded,
                  size: 18,
                ),
                label: Text(
                  record.present ? 'Koreksi presensi' : 'Input manual',
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _Fact extends StatelessWidget {
  const _Fact({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(9),
    decoration: BoxDecoration(
      color: NusaColors.background,
      borderRadius: BorderRadius.circular(10),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700),
        ),
      ],
    ),
  );
}

class _SelectClassState extends StatelessWidget {
  const _SelectClassState();

  @override
  Widget build(BuildContext context) => _StateCard(
    icon: Icons.touch_app_rounded,
    title: 'Pilih kelas untuk melihat siswa',
    message: 'Ringkasan seluruh kelas sudah tampil. Tekan salah satu kelas untuk membuka daftar presensinya.',
  );
}

class _EmptyStudents extends StatelessWidget {
  const _EmptyStudents();

  @override
  Widget build(BuildContext context) => _StateCard(
    icon: Icons.person_search_rounded,
    title: 'Siswa tidak ditemukan',
    message: 'Tidak ada siswa yang sesuai dengan pencarian atau status yang dipilih.',
  );
}

class _StateCard extends StatelessWidget {
  const _StateCard({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 27),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      children: [
        Icon(icon, color: NusaColors.primary, size: 40),
        const SizedBox(height: 10),
        Text(
          title,
          textAlign: TextAlign.center,
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 5),
        Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(
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
            Icons.fact_check_outlined,
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

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Rekap ibadah belum dapat dimuat. Silakan coba lagi.';
