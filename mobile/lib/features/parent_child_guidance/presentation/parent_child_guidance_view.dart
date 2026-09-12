import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/parent_child_guidance/application/parent_child_guidance_controller.dart';
import 'package:nusa/features/parent_child_guidance/domain/parent_child_guidance.dart';
import 'package:nusa/features/student_case_progress/domain/student_case_progress.dart';
import 'package:nusa/features/student_case_progress/presentation/student_case_style.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class ParentChildGuidanceView extends ConsumerStatefulWidget {
  const ParentChildGuidanceView({
    this.initialTab = ParentChildGuidanceTab.reports,
    super.key,
  });

  final ParentChildGuidanceTab initialTab;

  @override
  ConsumerState<ParentChildGuidanceView> createState() =>
      _ParentChildGuidanceViewState();
}

class _ParentChildGuidanceViewState
    extends ConsumerState<ParentChildGuidanceView> {
  @override
  void initState() {
    super.initState();
    if (widget.initialTab != ParentChildGuidanceTab.reports) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          ref
              .read(parentChildGuidanceControllerProvider.notifier)
              .selectTab(widget.initialTab);
        }
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(parentChildGuidanceControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pembinaan & Poin Anak Saya'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref
                      .read(parentChildGuidanceControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => _ErrorState(
            message: _message(error),
            onRetry: ref
                .read(parentChildGuidanceControllerProvider.notifier)
                .refresh,
          ),
          data: (page) => _Content(page: page),
        ),
      ),
    );
  }
}

class _Content extends ConsumerStatefulWidget {
  const _Content({required this.page});
  final ParentChildGuidancePage page;

  @override
  ConsumerState<_Content> createState() => _ContentState();
}

class _ContentState extends ConsumerState<_Content> {
  bool _loadingMore = false;

  @override
  Widget build(BuildContext context) {
    final page = widget.page;
    final controller = ref.read(parentChildGuidanceControllerProvider.notifier);
    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        key: const Key('parent-child-guidance-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          _Hero(page: page),
          if (page.student == null) ...[
            const SizedBox(height: 12),
            const _EmptyState(
              icon: Icons.link_off_rounded,
              title: 'Akun belum terhubung ke data anak',
              message: 'Hubungi administrator sekolah agar akun ini dihubungkan dengan data anak yang benar.',
            ),
          ] else ...[
            if (page.children.length > 1) ...[
              const SizedBox(height: 12),
              NusaDropdownField<int>(
                fieldKey: const Key('parent-guidance-child-filter'),
                value: page.filter.studentId,
                options: page.children
                    .map(
                      (item) => NusaDropdownOption<int>(
                        value: item.id,
                        label: item.name,
                      ),
                    )
                    .toList(growable: false),
                decoration: const InputDecoration(
                  labelText: 'Pilih anak',
                  prefixIcon: Icon(Icons.family_restroom_rounded),
                ),
                onChanged: (value) {
                  if (value != null) controller.selectStudent(value);
                },
              ),
            ],
            if (page.academicYears.isNotEmpty) ...[
              const SizedBox(height: 10),
              NusaDropdownField<int>(
                fieldKey: const Key('parent-guidance-year-filter'),
                value: page.filter.academicYearId,
                options: page.academicYears
                    .map(
                      (item) => NusaDropdownOption<int>(
                        value: item.id,
                        label: item.label,
                      ),
                    )
                    .toList(growable: false),
                decoration: const InputDecoration(
                  labelText: 'Tahun pelajaran',
                  prefixIcon: Icon(Icons.school_outlined),
                ),
                onChanged: (value) {
                  if (value != null) controller.selectAcademicYear(value);
                },
              ),
            ],
            const SizedBox(height: 12),
            _Summary(summary: page.summary),
            const SizedBox(height: 14),
            _Tabs(selected: page.filter.tab, onChanged: controller.selectTab),
            const SizedBox(height: 12),
            page.filter.tab == ParentChildGuidanceTab.reports
                ? _Reports(items: page.reports)
                : _Points(items: page.points),
            if (page.pagination.hasNextPage || _loadingMore) ...[
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _loadingMore ? null : _loadMore,
                icon: _loadingMore
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.expand_more_rounded),
                label: Text(_loadingMore ? 'Memuat...' : 'Muat lebih banyak'),
              ),
            ],
          ],
          const SizedBox(height: 14),
          _Notice(message: page.privacy),
        ],
      ),
    );
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(parentChildGuidanceControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_message(error))));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.page});
  final ParentChildGuidancePage page;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: .16),
          blurRadius: 16,
          offset: const Offset(0, 7),
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
            Icons.family_restroom_rounded,
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
                'Pembinaan & Poin Anak Saya',
                style: TextStyle(color: Colors.white70, fontSize: 10.5),
              ),
              const SizedBox(height: 3),
              Text(
                page.student?.name ?? 'Data anak belum tersedia',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                [
                  page.schoolClass?.name,
                  page.selectedAcademicYear?.name,
                ].whereType<String>().join(' · '),
                style: const TextStyle(
                  color: NusaColors.accent,
                  fontSize: 10.5,
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

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final ParentChildGuidanceSummary summary;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(6),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final width = (constraints.maxWidth - 6) / 2;
          return Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              _Stat(
                width: width,
                label: 'Semua laporan',
                value: summary.reports,
              ),
              _Stat(
                width: width,
                label: 'Sedang diproses',
                value: summary.inProgress,
                color: const Color(0xFF9A7100),
              ),
              _Stat(
                width: width,
                label: 'Poin pelanggaran',
                value: summary.violationPoints,
                color: const Color(0xFFC53A3A),
              ),
              _Stat(
                width: width,
                label: 'Saldo poin',
                value: summary.balance,
                color: summary.balance > 0
                    ? const Color(0xFFC53A3A)
                    : NusaColors.success,
                caption: 'Pengurangan ${summary.reductions}',
              ),
            ],
          );
        },
      ),
    ),
  );
}

class _Stat extends StatelessWidget {
  const _Stat({
    required this.width,
    required this.label,
    required this.value,
    this.color = NusaColors.primary,
    this.caption,
  });
  final double width;
  final String label;
  final int value;
  final Color color;
  final String? caption;

  @override
  Widget build(BuildContext context) => Container(
    width: width,
    padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 10),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .07),
      borderRadius: BorderRadius.circular(13),
    ),
    child: Row(
      children: [
        Icon(Icons.score_rounded, color: color, size: 20),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '$value',
                style: TextStyle(
                  color: color,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9,
                ),
              ),
              if (caption != null)
                Text(caption!, style: TextStyle(color: color, fontSize: 8.5)),
            ],
          ),
        ),
      ],
    ),
  );
}

class _Tabs extends StatelessWidget {
  const _Tabs({required this.selected, required this.onChanged});
  final ParentChildGuidanceTab selected;
  final ValueChanged<ParentChildGuidanceTab> onChanged;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(4),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      children: [
        Expanded(
          child: _TabButton(
            key: const Key('parent-guidance-reports-tab'),
            label: 'Perkembangan',
            selected: selected == ParentChildGuidanceTab.reports,
            onTap: () => onChanged(ParentChildGuidanceTab.reports),
          ),
        ),
        Expanded(
          child: _TabButton(
            key: const Key('parent-guidance-points-tab'),
            label: 'Riwayat Poin',
            selected: selected == ParentChildGuidanceTab.points,
            onTap: () => onChanged(ParentChildGuidanceTab.points),
          ),
        ),
      ],
    ),
  );
}

class _TabButton extends StatelessWidget {
  const _TabButton({
    required this.label,
    required this.selected,
    required this.onTap,
    super.key,
  });
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: selected ? NusaColors.primary : Colors.transparent,
    borderRadius: BorderRadius.circular(11),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(11),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 10),
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: selected ? Colors.white : NusaColors.textSecondary,
            fontSize: 10.5,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    ),
  );
}

class _Reports extends StatelessWidget {
  const _Reports({required this.items});
  final List<StudentCaseItem> items;

  @override
  Widget build(BuildContext context) => items.isEmpty
      ? const _EmptyState(
          icon: Icons.verified_user_outlined,
          title: 'Belum ada laporan pembinaan',
          message: 'Perkembangan laporan anak akan ditampilkan di sini.',
        )
      : Column(
          children: [
            for (var index = 0; index < items.length; index++) ...[
              _ReportCard(item: items[index]),
              if (index < items.length - 1) const SizedBox(height: 9),
            ],
          ],
        );
}

class _ReportCard extends StatelessWidget {
  const _ReportCard({required this.item});
  final StudentCaseItem item;

  @override
  Widget build(BuildContext context) {
    final color = studentCaseColor(item.status.color);
    return Card(
      child: InkWell(
        key: Key('parent-guidance-report-${item.id}'),
        onTap: () => context.push('/pembinaan-poin-anak/${item.id}'),
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: 7,
                runSpacing: 7,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  Text(
                    item.number,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: studentCaseSurface(item.status.color),
                      borderRadius: BorderRadius.circular(99),
                    ),
                    child: Text(
                      item.status.label,
                      style: TextStyle(
                        color: color,
                        fontSize: 9.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 9),
              Text(
                item.title,
                style: const TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                item.status.description,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10.5,
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 9),
              Row(
                children: [
                  const Icon(
                    Icons.calendar_today_outlined,
                    size: 14,
                    color: NusaColors.textSecondary,
                  ),
                  const SizedBox(width: 5),
                  Expanded(
                    child: Text(
                      studentCaseDate(item.incidentDate),
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                  ),
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: NusaColors.primary,
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

class _Points extends StatelessWidget {
  const _Points({required this.items});
  final List<ParentChildPoint> items;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      const _Notice(
        message: 'Poin pelanggaran menambah saldo. Reward atau kegiatan positif mengurangi saldo setelah disetujui sekolah.',
        blue: true,
      ),
      const SizedBox(height: 10),
      if (items.isEmpty)
        const _EmptyState(
          icon: Icons.score_outlined,
          title: 'Belum ada transaksi poin',
          message: 'Poin resmi dan pengurangan poin akan ditampilkan di sini.',
        )
      else
        for (var index = 0; index < items.length; index++) ...[
          _PointCard(item: items[index]),
          if (index < items.length - 1) const SizedBox(height: 9),
        ],
    ],
  );
}

class _PointCard extends StatelessWidget {
  const _PointCard({required this.item});
  final ParentChildPoint item;

  @override
  Widget build(BuildContext context) {
    final color = item.isReduction
        ? NusaColors.success
        : const Color(0xFFC53A3A);
    final child = Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            item.isReduction
                ? Icons.emoji_events_outlined
                : Icons.gavel_rounded,
            color: color,
            size: 23,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.description,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  [
                    item.typeLabel,
                    if ((item.source ?? '').isNotEmpty) item.source!,
                  ].join(' · '),
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 9.5,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  studentCaseDate(item.recordedAt),
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 9.5,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Text(
            '${item.points > 0 ? '+' : ''}${item.points} poin',
            style: TextStyle(
              color: color,
              fontSize: 13,
              fontWeight: FontWeight.w900,
            ),
          ),
          if (item.reportId != null)
            const Icon(Icons.chevron_right_rounded, color: NusaColors.primary),
        ],
      ),
    );
    return Card(
      child: item.reportId == null
          ? child
          : InkWell(
              key: Key('parent-guidance-point-${item.id}'),
              onTap: () =>
                  context.push('/pembinaan-poin-anak/${item.reportId}'),
              borderRadius: BorderRadius.circular(18),
              child: child,
            ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.message, this.blue = false});
  final String message;
  final bool blue;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: blue ? NusaColors.surfaceBlue : const Color(0xFFFFF8D8),
      borderRadius: BorderRadius.circular(14),
      border: blue ? Border.all(color: NusaColors.outline) : null,
    ),
    child: Text(
      message,
      style: TextStyle(
        color: blue ? NusaColors.textSecondary : const Color(0xFF725600),
        fontSize: 10.5,
        height: 1.4,
      ),
    ),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.message,
  });
  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(22),
      child: Column(
        children: [
          Icon(icon, size: 44, color: NusaColors.textSecondary),
          const SizedBox(height: 10),
          Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
              height: 1.4,
            ),
          ),
        ],
      ),
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const Icon(Icons.cloud_off_rounded, size: 54),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

String _message(Object error) => switch (error) {
  AppException exception => exception.message,
  _ => 'Pembinaan dan poin anak belum dapat dimuat.',
};
