import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_point_recap/application/student_point_recap_controller.dart';
import 'package:nusa/features/student_point_recap/domain/student_point_recap.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentPointRecapDetailView extends ConsumerWidget {
  const StudentPointRecapDetailView({
    required this.studentId,
    this.academicYearId,
    super.key,
  });
  final int studentId;
  final int? academicYearId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final query = (studentId: studentId, academicYearId: academicYearId);
    final state = ref.watch(studentPointRecapDetailProvider(query));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Profil Disiplin Siswa'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : () => ref.invalidate(studentPointRecapDetailProvider(query)),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _Error(
            message: _message(error),
            onRetry: () =>
                ref.invalidate(studentPointRecapDetailProvider(query)),
          ),
          data: (detail) => _Content(detail: detail),
        ),
      ),
    );
  }
}

class _Content extends StatelessWidget {
  const _Content({required this.detail});
  final StudentPointRecapDetail detail;

  @override
  Widget build(BuildContext context) {
    final student = detail.student;
    final activeAssistance = detail.assistances
        .where((item) => item.status == 'dalam_proses')
        .firstOrNull;
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (detail.academicYears.isNotEmpty) ...[
            NusaDropdownField<int>(
              fieldKey: const Key('point-recap-detail-year'),
              value: detail.academicYear?.id,
              decoration: const InputDecoration(
                labelText: 'Tahun pelajaran',
                prefixIcon: Icon(Icons.calendar_month_rounded),
              ),
              options: [
                for (final item in detail.academicYears)
                  NusaDropdownOption(
                    value: item.id,
                    label: '${item.name}${item.active ? ' · Aktif' : ''}',
                  ),
              ],
              onChanged: (value) {
                if (value == null || value == detail.academicYear?.id) return;
                context.go(
                  Uri(
                    path: '/rekap-poin-siswa/${student.student.id}',
                    queryParameters: {'tahun': '$value'},
                  ).toString(),
                );
              },
            ),
            const SizedBox(height: 10),
          ],
          _StudentHeader(detail: detail),
          const SizedBox(height: 10),
          _IndicatorCard(summary: detail.summary),
          const SizedBox(height: 10),
          _Metrics(summary: detail.summary),
          const SizedBox(height: 10),
          if (detail.monthlyProgress.isNotEmpty) ...[
            _MonthlyProgress(items: detail.monthlyProgress),
            const SizedBox(height: 10),
          ],
          _AttentionCard(detail: detail, activeAssistance: activeAssistance),
          const SizedBox(height: 10),
          _WarningSection(items: detail.warnings),
          const SizedBox(height: 10),
          _AssistanceSection(items: detail.assistances),
          const SizedBox(height: 10),
          _TransactionSection(items: detail.transactions),
          const SizedBox(height: 10),
          _ReportSection(items: detail.reports),
          const SizedBox(height: 10),
          _SanctionSection(items: detail.sanctions),
          const SizedBox(height: 10),
          _ReductionSection(items: detail.reductions),
          const SizedBox(height: 10),
          _LateSection(items: detail.lateArrivals),
        ],
      ),
    );
  }
}

class _StudentHeader extends StatelessWidget {
  const _StudentHeader({required this.detail});
  final StudentPointRecapDetail detail;

  @override
  Widget build(BuildContext context) {
    final item = detail.student;
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.13),
              borderRadius: BorderRadius.circular(15),
            ),
            child: const Icon(Icons.person_rounded, color: NusaColors.accent),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.student.name,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '${item.schoolClass?.name ?? 'Tanpa kelas'} · ${detail.academicYear?.name ?? '-'}',
                  style: const TextStyle(color: Colors.white70, fontSize: 10.5),
                ),
                Text(
                  'NIS ${item.student.nis ?? '-'} · NISN ${item.student.nisn ?? '-'}',
                  style: const TextStyle(color: Colors.white60, fontSize: 9.5),
                ),
                if (item.homeroomTeacher != null)
                  Text(
                    'Guru wali: ${item.homeroomTeacher!.name}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white60,
                      fontSize: 9.5,
                    ),
                  ),
              ],
            ),
          ),
          Column(
            children: [
              Text(
                '${detail.summary.totalPoints}',
                style: const TextStyle(
                  color: NusaColors.accent,
                  fontSize: 27,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const Text(
                'poin resmi',
                style: TextStyle(color: Colors.white70, fontSize: 8.5),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _IndicatorCard extends StatelessWidget {
  const _IndicatorCard({required this.summary});
  final StudentPointDetailSummary summary;

  @override
  Widget build(BuildContext context) {
    final indicator = summary.indicator;
    final color = _indicatorColor(indicator.code);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.speed_rounded, color: color),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    indicator.label,
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
                Text(
                  '${indicator.percentage}%',
                  style: TextStyle(color: color, fontWeight: FontWeight.w900),
                ),
              ],
            ),
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(5),
              child: LinearProgressIndicator(
                minHeight: 8,
                value: (indicator.percentage / 100).clamp(0, 1),
                backgroundColor: NusaColors.outline,
                color: color,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              indicator.nextThreshold == null
                  ? 'Tidak ada ambang sanksi berikutnya.'
                  : '${indicator.distance ?? 0} poin menuju ${indicator.nextThreshold!.name} (${indicator.nextThreshold!.points} poin).',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Metrics extends StatelessWidget {
  const _Metrics({required this.summary});
  final StudentPointDetailSummary summary;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final width = (constraints.maxWidth - 9) / 2;
      return Wrap(
        spacing: 9,
        runSpacing: 9,
        children: [
          _Metric(
            width: width,
            label: 'Peringatan aktif',
            value: '${summary.activeWarnings}',
            note: '${summary.importantWarnings} penting',
            icon: Icons.warning_amber_rounded,
          ),
          _Metric(
            width: width,
            label: 'Laporan menunggu',
            value: '${summary.pendingReports}',
            note: '${summary.pendingPoints} potensi poin',
            icon: Icons.pending_actions_rounded,
          ),
          _Metric(
            width: width,
            label: 'Sanksi aktif',
            value: '${summary.activeSanctions}',
            note: 'Perlu tindak lanjut',
            icon: Icons.gavel_rounded,
          ),
          _Metric(
            width: width,
            label: 'Keterlambatan',
            value: '${summary.lateCount}×',
            note: '${summary.lateMinutes} menit',
            icon: Icons.more_time_rounded,
          ),
        ],
      );
    },
  );
}

class _Metric extends StatelessWidget {
  const _Metric({
    required this.width,
    required this.label,
    required this.value,
    required this.note,
    required this.icon,
  });
  final double width;
  final String label;
  final String value;
  final String note;
  final IconData icon;
  @override
  Widget build(BuildContext context) => SizedBox(
    width: width,
    child: Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 20, color: NusaColors.primary),
            const SizedBox(height: 7),
            Text(
              value,
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
            ),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 9.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            Text(
              note,
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
    ),
  );
}

class _MonthlyProgress extends StatelessWidget {
  const _MonthlyProgress({required this.items});
  final List<PointMonthlyProgress> items;
  @override
  Widget build(BuildContext context) {
    final visible = items.length > 8 ? items.sublist(items.length - 8) : items;
    final maximum = visible.fold<int>(
      1,
      (max, item) => item.balance > max ? item.balance : max,
    );
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _SectionTitle(
              icon: Icons.trending_up_rounded,
              title: 'Perkembangan Poin Bulanan',
            ),
            const SizedBox(height: 11),
            for (final item in visible) ...[
              Row(
                children: [
                  SizedBox(
                    width: 58,
                    child: Text(
                      item.label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 9),
                    ),
                  ),
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        minHeight: 6,
                        value: item.balance / maximum,
                        backgroundColor: NusaColors.outline,
                        color: NusaColors.primaryLight,
                      ),
                    ),
                  ),
                  SizedBox(
                    width: 38,
                    child: Text(
                      '${item.balance}',
                      textAlign: TextAlign.right,
                      style: const TextStyle(
                        fontSize: 9.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 7),
            ],
          ],
        ),
      ),
    );
  }
}

class _AttentionCard extends StatelessWidget {
  const _AttentionCard({required this.detail, required this.activeAssistance});
  final StudentPointRecapDetail detail;
  final PointAssistance? activeAssistance;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _SectionTitle(
            icon: Icons.support_agent_rounded,
            title: 'Tindak Lanjut Utama',
          ),
          const SizedBox(height: 9),
          Text(
            activeAssistance == null
                ? 'Belum ada pendampingan aktif pada periode ini.'
                : '${activeAssistance!.typeLabel} sedang ditangani oleh ${activeAssistance!.officer ?? 'petugas yang ditunjuk'}.',
            style: const TextStyle(fontSize: 10.5, height: 1.45),
          ),
          const SizedBox(height: 11),
          if (activeAssistance != null)
            OutlinedButton.icon(
              key: const Key('point-recap-open-active-assistance'),
              onPressed: () =>
                  context.push('/pendampingan-siswa/${activeAssistance!.id}'),
              icon: const Icon(Icons.open_in_new_rounded),
              label: const Text('Buka Pendampingan'),
            )
          else if (detail.access.canManageAssistance)
            FilledButton.icon(
              key: const Key('point-recap-start-assistance'),
              onPressed: () => context.push(
                Uri(
                  path: '/pendampingan-siswa/tambah',
                  queryParameters: {
                    'tahun': '${detail.academicYear?.id ?? ''}',
                    'kelas': '${detail.student.schoolClass?.id ?? ''}',
                    'siswa': '${detail.student.student.id}',
                    'q': detail.student.student.name,
                  }..removeWhere((key, value) => value.isEmpty),
                ).toString(),
              ),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Mulai Pendampingan'),
            ),
        ],
      ),
    ),
  );
}

class _WarningSection extends StatelessWidget {
  const _WarningSection({required this.items});
  final List<PointWarning> items;
  @override
  Widget build(BuildContext context) => _ExpandableSection(
    key: const Key('point-recap-warnings'),
    icon: Icons.warning_amber_rounded,
    title: 'Peringatan Aktif',
    count: items.length,
    initiallyExpanded: true,
    emptyText: 'Tidak ada peringatan dini aktif.',
    children: [
      for (final item in items)
        _ActionRow(
          title: item.typeLabel,
          subtitle:
              '${item.levelLabel} · Siklus ${item.cycle}\n${item.message}',
          color: item.level == 'penting'
              ? const Color(0xFFD84A3A)
              : const Color(0xFFC58F00),
          onTap: () => context.push('/peringatan-dini-siswa/${item.id}'),
        ),
    ],
  );
}

class _AssistanceSection extends StatelessWidget {
  const _AssistanceSection({required this.items});
  final List<PointAssistance> items;
  @override
  Widget build(BuildContext context) => _ExpandableSection(
    icon: Icons.handshake_outlined,
    title: 'Riwayat Pendampingan',
    count: items.length,
    emptyText: 'Belum ada riwayat pendampingan.',
    children: [
      for (final item in items)
        _ActionRow(
          title: item.typeLabel,
          subtitle:
              '${_dateLabel(item.date)} · ${item.statusLabel}\n${item.summary}',
          color: item.status == 'selesai'
              ? NusaColors.success
              : const Color(0xFFC58F00),
          onTap: () => context.push('/pendampingan-siswa/${item.id}'),
        ),
    ],
  );
}

class _TransactionSection extends StatelessWidget {
  const _TransactionSection({required this.items});
  final List<PointTransaction> items;
  @override
  Widget build(BuildContext context) => _ExpandableSection(
    key: const Key('point-recap-transactions'),
    icon: Icons.receipt_long_rounded,
    title: 'Transaksi Poin Resmi',
    count: items.length,
    initiallyExpanded: true,
    emptyText: 'Belum ada transaksi poin resmi.',
    children: [
      for (final item in items)
        _ActionRow(
          title:
              '${item.points > 0 ? '+' : ''}${item.points} · ${item.typeLabel}',
          subtitle: '${_dateTimeLabel(item.recordedAt)}\n${item.description}',
          color: item.points < 0 ? NusaColors.success : const Color(0xFFC58F00),
          onTap: item.source?.type == 'laporan'
              ? () => context.push('/daftar-laporan-siswa/${item.source!.id}')
              : null,
        ),
    ],
  );
}

class _ReportSection extends StatelessWidget {
  const _ReportSection({required this.items});
  final List<PointReport> items;
  @override
  Widget build(BuildContext context) => _ExpandableSection(
    icon: Icons.assignment_outlined,
    title: 'Riwayat Laporan',
    count: items.length,
    emptyText: 'Belum ada riwayat laporan.',
    children: [
      for (final item in items)
        _ActionRow(
          title: '${item.number} · ${item.points} poin',
          subtitle:
              '${_dateLabel(item.date)} · ${item.typeLabel}\n${item.category ?? '-'} · ${item.statusLabel}',
          color: item.status == 'disahkan'
              ? const Color(0xFFD84A3A)
              : const Color(0xFFC58F00),
          onTap: () => context.push('/daftar-laporan-siswa/${item.id}'),
        ),
    ],
  );
}

class _SanctionSection extends StatelessWidget {
  const _SanctionSection({required this.items});
  final List<PointSanction> items;
  @override
  Widget build(BuildContext context) => _ExpandableSection(
    icon: Icons.gavel_rounded,
    title: 'Riwayat Sanksi',
    count: items.length,
    emptyText: 'Belum ada riwayat sanksi.',
    children: [
      for (final item in items)
        _ActionRow(
          title: item.name,
          subtitle:
              '${_dateTimeLabel(item.triggeredAt)} · ${item.statusLabel}\nTerpicu pada ${item.triggerPoints} poin${item.officer == null ? '' : ' · ${item.officer}'}',
          color: item.overdue
              ? const Color(0xFFD84A3A)
              : item.status == 'selesai'
              ? NusaColors.success
              : const Color(0xFFC58F00),
          onTap: () => context.push('/pelaksanaan-sanksi-siswa/${item.id}'),
        ),
    ],
  );
}

class _ReductionSection extends StatelessWidget {
  const _ReductionSection({required this.items});
  final List<PointReduction> items;
  @override
  Widget build(BuildContext context) => _ExpandableSection(
    icon: Icons.volunteer_activism_outlined,
    title: 'Pengurangan Poin',
    count: items.length,
    emptyText: 'Belum ada pengajuan pengurangan poin.',
    children: [
      for (final item in items)
        _ActionRow(
          title: '${item.activity} · ${item.points} poin',
          subtitle:
              '${_dateLabel(item.date)} · ${item.statusLabel}\n${item.description ?? '-'}',
          color: item.status == 'disetujui'
              ? NusaColors.success
              : const Color(0xFFC58F00),
        ),
    ],
  );
}

class _LateSection extends StatelessWidget {
  const _LateSection({required this.items});
  final List<PointLateArrival> items;
  @override
  Widget build(BuildContext context) => _ExpandableSection(
    icon: Icons.more_time_rounded,
    title: 'Keterlambatan',
    count: items.length,
    emptyText: 'Tidak ada catatan keterlambatan.',
    children: [
      for (final item in items)
        _ActionRow(
          title: '${item.minutes} menit terlambat',
          subtitle:
              '${_dateLabel(item.date)} · ${item.schoolClass ?? '-'}${item.points > 0 ? ' · ${item.points} poin' : ''}',
          color: const Color(0xFFC58F00),
        ),
    ],
  );
}

class _ExpandableSection extends StatelessWidget {
  const _ExpandableSection({
    required this.icon,
    required this.title,
    required this.count,
    required this.emptyText,
    required this.children,
    this.initiallyExpanded = false,
    super.key,
  });
  final IconData icon;
  final String title;
  final int count;
  final String emptyText;
  final List<Widget> children;
  final bool initiallyExpanded;

  @override
  Widget build(BuildContext context) => Card(
    child: Theme(
      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
      child: ExpansionTile(
        initiallyExpanded: initiallyExpanded,
        leading: Icon(icon, color: NusaColors.primary),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
        subtitle: Text('$count data', style: const TextStyle(fontSize: 9.5)),
        childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
        children: children.isEmpty
            ? [
                Padding(
                  padding: const EdgeInsets.fromLTRB(8, 4, 8, 10),
                  child: Text(
                    emptyText,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                ),
              ]
            : children,
      ),
    ),
  );
}

class _ActionRow extends StatelessWidget {
  const _ActionRow({
    required this.title,
    required this.subtitle,
    required this.color,
    this.onTap,
  });
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(13),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 9),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 8,
            height: 36,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(5),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  maxLines: 4,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 9.5,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
          if (onTap != null) const Icon(Icons.chevron_right_rounded, size: 20),
        ],
      ),
    ),
  );
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.icon, required this.title});
  final IconData icon;
  final String title;
  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon, color: NusaColors.primary, size: 20),
      const SizedBox(width: 8),
      Expanded(
        child: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
    ],
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

Color _indicatorColor(String code) => switch (code) {
  'sanksi_aktif' || 'ambang_tertinggi' => const Color(0xFFD84A3A),
  'mendekati_sanksi' || 'menunggu_verifikasi' => const Color(0xFFC58F00),
  'terpantau' => NusaColors.primaryLight,
  _ => NusaColors.success,
};

String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  return date == null
      ? value
      : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String _dateTimeLabel(DateTime? date) {
  if (date == null) return '-';
  return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')} WIB';
}

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Profil disiplin siswa belum dapat dimuat.',
};
