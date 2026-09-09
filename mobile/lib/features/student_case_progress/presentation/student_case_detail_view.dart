import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_case_progress/application/student_case_progress_controller.dart';
import 'package:nusa/features/student_case_progress/domain/student_case_progress.dart';
import 'package:nusa/features/student_case_progress/presentation/student_case_style.dart';

class StudentCaseDetailView extends ConsumerWidget {
  const StudentCaseDetailView({required this.reportId, super.key});

  final int reportId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(studentCaseDetailProvider(reportId));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Progress Kasus'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : () => ref.invalidate(studentCaseDetailProvider(reportId)),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ErrorState(
            message: _message(error),
            onRetry: () => ref.invalidate(studentCaseDetailProvider(reportId)),
          ),
          data: (detail) => _DetailContent(
            detail: detail,
            onRefresh: () async {
              ref.invalidate(studentCaseDetailProvider(reportId));
              await ref.read(studentCaseDetailProvider(reportId).future);
            },
          ),
        ),
      ),
    );
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({required this.detail, required this.onRefresh});
  final StudentCaseDetail detail;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final report = detail.report;
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        key: const Key('student-case-detail-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          _DetailHero(detail: detail),
          const SizedBox(height: 12),
          _ProgressStages(stages: detail.stages),
          const SizedBox(height: 12),
          _Panel(
            icon: Icons.info_outline_rounded,
            title: 'Ringkasan Kejadian',
            child: Column(
              children: [
                _FactRow(
                  label: 'Tanggal',
                  value: studentCaseDate(report.item.incidentDate),
                ),
                _FactRow(
                  label: 'Waktu',
                  value: report.incidentTime == null
                      ? '-'
                      : '${report.incidentTime} WIB',
                ),
                _FactRow(label: 'Tempat', value: report.item.place ?? '-'),
                _FactRow(
                  label: 'Kelas saat kejadian',
                  value: report.item.schoolClass?.name ?? '-',
                ),
                _FactRow(
                  label: 'Tahun pelajaran',
                  value: report.item.academicYear?.name ?? '-',
                ),
                _FactRow(label: 'Sumber', value: report.source, divider: false),
              ],
            ),
          ),
          const SizedBox(height: 12),
          _Panel(
            icon: Icons.description_outlined,
            title: 'Kronologi',
            child: Text(
              report.chronology,
              style: const TextStyle(fontSize: 12, height: 1.55),
            ),
          ),
          const SizedBox(height: 12),
          _DecisionPanel(
            decision: detail.decision,
            colorKey: report.item.status.color,
          ),
          const SizedBox(height: 12),
          _TimelinePanel(items: detail.timeline),
          if (detail.followUps.isNotEmpty) ...[
            const SizedBox(height: 12),
            _FollowUpPanel(items: detail.followUps),
          ],
          const SizedBox(height: 12),
          _PrivacyNotice(message: detail.privacy),
        ],
      ),
    );
  }
}

class _DetailHero extends StatelessWidget {
  const _DetailHero({required this.detail});
  final StudentCaseDetail detail;

  @override
  Widget build(BuildContext context) {
    final status = detail.report.item.status;
    return Container(
      padding: const EdgeInsets.all(17),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .13),
                  borderRadius: BorderRadius.circular(99),
                ),
                child: Text(
                  status.label,
                  style: const TextStyle(
                    color: NusaColors.accent,
                    fontSize: 10,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              Text(
                detail.report.item.number,
                style: const TextStyle(color: Colors.white70, fontSize: 10),
              ),
            ],
          ),
          const SizedBox(height: 11),
          Text(
            detail.student.name,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 19,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            status.description,
            style: const TextStyle(
              color: Colors.white70,
              fontSize: 11.5,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 11),
          Text(
            'Status penanganan: ${status.handlingStatus}',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 10.5,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _ProgressStages extends StatelessWidget {
  const _ProgressStages({required this.stages});
  final List<StudentCaseStage> stages;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 15),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (final stage in stages)
            Expanded(
              child: Column(
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    width: 30,
                    height: 30,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: stage.complete
                          ? NusaColors.primary
                          : NusaColors.background,
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: stage.complete
                            ? NusaColors.primary
                            : NusaColors.outline,
                      ),
                    ),
                    child: stage.complete
                        ? const Icon(
                            Icons.check_rounded,
                            size: 17,
                            color: Colors.white,
                          )
                        : Text(
                            '${stage.number}',
                            style: const TextStyle(
                              color: NusaColors.textSecondary,
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    stage.label,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: stage.complete
                          ? NusaColors.primary
                          : NusaColors.textSecondary,
                      fontSize: 8.5,
                      height: 1.2,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    ),
  );
}

class _Panel extends StatelessWidget {
  const _Panel({required this.icon, required this.title, required this.child});
  final IconData icon;
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 20, color: NusaColors.primary),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 13),
          child,
        ],
      ),
    ),
  );
}

class _FactRow extends StatelessWidget {
  const _FactRow({
    required this.label,
    required this.value,
    this.divider = true,
  });
  final String label;
  final String value;
  final bool divider;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 112,
              child: Text(
                label,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10.5,
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                value,
                textAlign: TextAlign.right,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      ),
      if (divider) const Divider(height: 1),
    ],
  );
}

class _DecisionPanel extends StatelessWidget {
  const _DecisionPanel({required this.decision, required this.colorKey});
  final StudentCaseDecision decision;
  final String colorKey;

  @override
  Widget build(BuildContext context) {
    final color = studentCaseColor(colorKey);
    return _Panel(
      icon: Icons.verified_outlined,
      title: 'Keputusan Sekolah',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
            decoration: BoxDecoration(
              color: studentCaseSurface(colorKey),
              borderRadius: BorderRadius.circular(99),
            ),
            child: Text(
              decision.status,
              style: TextStyle(
                color: color,
                fontSize: 10,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(height: 9),
          Text(
            decision.description,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11.5,
              height: 1.4,
            ),
          ),
          if (decision.violations.isNotEmpty) ...[
            const SizedBox(height: 12),
            for (
              var index = 0;
              index < decision.violations.length;
              index++
            ) ...[
              _ViolationRow(item: decision.violations[index]),
              if (index < decision.violations.length - 1)
                const Divider(height: 12),
            ],
          ],
          if (decision.officialPoints != null) ...[
            const Divider(height: 22),
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Total poin resmi',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
                Text(
                  '${decision.officialPoints}',
                  style: const TextStyle(
                    color: Color(0xFFC53A3A),
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
          ],
          if (decision.provisional) ...[
            const SizedBox(height: 12),
            const _InlineNotice(
              icon: Icons.hourglass_top_rounded,
              message: 'Rekomendasi yang masih diperiksa belum menjadi keputusan resmi sekolah.',
            ),
          ],
        ],
      ),
    );
  }
}

class _ViolationRow extends StatelessWidget {
  const _ViolationRow({required this.item});
  final StudentCaseViolation item;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      const Icon(Icons.gavel_rounded, size: 18, color: NusaColors.primary),
      const SizedBox(width: 8),
      Expanded(
        child: Text(
          item.name,
          style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700),
        ),
      ),
      const SizedBox(width: 8),
      Text(
        '${item.points} poin',
        style: const TextStyle(
          color: Color(0xFFC53A3A),
          fontSize: 11,
          fontWeight: FontWeight.w900,
        ),
      ),
    ],
  );
}

class _TimelinePanel extends StatelessWidget {
  const _TimelinePanel({required this.items});
  final List<StudentCaseTimeline> items;

  @override
  Widget build(BuildContext context) => _Panel(
    icon: Icons.timeline_rounded,
    title: 'Riwayat Proses',
    child: Column(
      children: [
        for (var index = 0; index < items.length; index++)
          _TimelineRow(item: items[index], last: index == items.length - 1),
      ],
    ),
  );
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({required this.item, required this.last});
  final StudentCaseTimeline item;
  final bool last;

  @override
  Widget build(BuildContext context) => IntrinsicHeight(
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 20,
          child: Column(
            children: [
              Container(
                width: 10,
                height: 10,
                decoration: const BoxDecoration(
                  color: NusaColors.primary,
                  shape: BoxShape.circle,
                ),
              ),
              if (!last)
                Expanded(child: Container(width: 2, color: NusaColors.outline)),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(bottom: last ? 0 : 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title,
                  style: const TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  item.description,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  studentCaseDate(item.date, includeTime: true),
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 9.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}

class _FollowUpPanel extends StatelessWidget {
  const _FollowUpPanel({required this.items});
  final List<StudentCaseFollowUp> items;

  @override
  Widget build(BuildContext context) => _Panel(
    icon: Icons.follow_the_signs_rounded,
    title: 'Tindak Lanjut',
    child: Column(
      children: [
        for (var index = 0; index < items.length; index++) ...[
          Row(
            children: [
              const CircleAvatar(
                radius: 17,
                backgroundColor: NusaColors.successSurface,
                child: Icon(
                  Icons.task_alt_rounded,
                  size: 19,
                  color: NusaColors.success,
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      items[index].type,
                      style: const TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      '${studentCaseDate(items[index].date)} · ${items[index].status}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (index < items.length - 1) const Divider(height: 18),
        ],
      ],
    ),
  );
}

class _InlineNotice extends StatelessWidget {
  const _InlineNotice({required this.icon, required this.message});
  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF8D8),
      borderRadius: BorderRadius.circular(13),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 19, color: const Color(0xFF9A7100)),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(
              color: Color(0xFF725600),
              fontSize: 10.5,
              height: 1.35,
            ),
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
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(
          Icons.privacy_tip_outlined,
          color: NusaColors.primary,
          size: 21,
        ),
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

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 54,
            color: NusaColors.textSecondary,
          ),
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
  _ => 'Detail progress kasus belum dapat dimuat.',
};
