import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/parent_child_exams/application/parent_child_exams_controller.dart';
import 'package:nusa/features/parent_child_exams/domain/parent_child_exams.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class ParentChildExamsView extends ConsumerWidget {
  const ParentChildExamsView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final result = ref.watch(parentChildExamsControllerProvider);
    final controller = ref.read(parentChildExamsControllerProvider.notifier);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Ujian Anak Saya'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: result.isLoading ? null : controller.refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: result.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => _ErrorState(
            message: error is AppException
                ? error.message
                : 'Ujian anak belum dapat dimuat.',
            onRetry: controller.refresh,
          ),
          data: (page) => _Content(page: page),
        ),
      ),
    );
  }
}

class _Content extends ConsumerWidget {
  const _Content({required this.page});

  final ParentChildExamsPage page;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(parentChildExamsControllerProvider.notifier);
    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        key: const Key('parent-child-exams-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          _Hero(student: page.student),
          if (page.children.length > 1) ...[
            const SizedBox(height: 12),
            NusaDropdownField<int>(
              fieldKey: const Key('parent-exams-child-filter'),
              value: page.selectedStudentId,
              options: page.children
                  .map(
                    (child) => NusaDropdownOption<int>(
                      value: child.id,
                      label: child.name,
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
          const SizedBox(height: 12),
          if (page.student == null)
            const _EmptyCard(
              icon: Icons.link_off_rounded,
              title: 'Data anak belum terhubung',
              message:
                  'Hubungi administrator sekolah agar akun orang tua '
                  'dihubungkan dengan data anak yang benar.',
            )
          else ...[
            _Summary(summary: page.summary),
            const SizedBox(height: 16),
            const _SectionTitle(
              title: 'Daftar Ujian',
              subtitle: 'Jadwal, kemajuan, dan hasil yang dipublikasikan',
            ),
            const SizedBox(height: 9),
            if (page.exams.isEmpty)
              const _EmptyCard(
                icon: Icons.quiz_outlined,
                title: 'Belum ada ujian',
                message:
                    'Ujian yang diberikan kepada anak akan tampil di sini.',
              )
            else
              ...page.exams.map(
                (exam) => Padding(
                  padding: const EdgeInsets.only(bottom: 9),
                  child: _ExamCard(exam: exam),
                ),
              ),
            const SizedBox(height: 3),
            _PrivacyNotice(message: page.notice),
          ],
        ],
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.student});

  final ExamChild? student;

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
          blurRadius: 18,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: Stack(
      children: [
        Positioned(
          right: -12,
          bottom: -26,
          child: Icon(
            Icons.fact_check_rounded,
            size: 105,
            color: Colors.white.withValues(alpha: .06),
          ),
        ),
        Row(
          children: [
            Container(
              width: 50,
              height: 50,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: .12),
                borderRadius: BorderRadius.circular(15),
                border: Border.all(
                  color: NusaColors.accent.withValues(alpha: .7),
                ),
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
                    'Pemantauan Ujian Anak',
                    style: TextStyle(color: Colors.white70, fontSize: 10.5),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    student?.name ?? 'Data anak belum tersedia',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  if (student != null) ...[
                    const SizedBox(height: 5),
                    Text(
                      'NISN ${student!.nisn ?? '-'}',
                      style: const TextStyle(
                        color: NusaColors.accent,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ],
    ),
  );
}

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});

  final ParentExamSummary summary;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final gap = constraints.maxWidth < 340 ? 6.0 : 8.0;
      return Row(
        children: [
          Expanded(
            child: _Metric(
              label: 'Aktif',
              value: summary.active,
              color: NusaColors.success,
            ),
          ),
          SizedBox(width: gap),
          Expanded(
            child: _Metric(
              label: 'Akan datang',
              value: summary.upcoming,
              color: const Color(0xFFE59A00),
            ),
          ),
          SizedBox(width: gap),
          Expanded(
            child: _Metric(
              label: 'Selesai',
              value: summary.completed,
              color: NusaColors.primary,
            ),
          ),
        ],
      );
    },
  );
}

class _Metric extends StatelessWidget {
  const _Metric({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 12),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .08),
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: color.withValues(alpha: .16)),
    ),
    child: Column(
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
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class _ExamCard extends StatelessWidget {
  const _ExamCard({required this.exam});

  final ParentExam exam;

  @override
  Widget build(BuildContext context) {
    final color = _toneColor(exam.statusTone);
    final date = _examDate(exam);
    final time = _examTime(exam);
    return Card(
      child: InkWell(
        key: Key('parent-exam-${exam.id}'),
        onTap: () => _showDetails(context, exam),
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 43,
                    height: 43,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: .1),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Icon(Icons.quiz_rounded, color: color),
                  ),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          exam.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          exam.subject,
                          style: const TextStyle(
                            color: NusaColors.primary,
                            fontSize: 11.5,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  _StatusPill(label: exam.statusLabel, color: color),
                ],
              ),
              const SizedBox(height: 11),
              Wrap(
                spacing: 12,
                runSpacing: 7,
                children: [
                  if (date != null)
                    _Info(
                      icon: Icons.calendar_today_rounded,
                      text: _dateLabel(date),
                    ),
                  if (time != null)
                    _Info(icon: Icons.schedule_rounded, text: time),
                  _Info(
                    icon: Icons.timer_outlined,
                    text: '${exam.durationMinutes} menit',
                  ),
                ],
              ),
              const Divider(height: 21),
              Row(
                children: [
                  Expanded(child: _ResultLabel(exam: exam)),
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: NusaColors.textSecondary,
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

class _ResultLabel extends StatelessWidget {
  const _ResultLabel({required this.exam});

  final ParentExam exam;

  @override
  Widget build(BuildContext context) {
    if (exam.result.visible) {
      final passed = exam.result.passed;
      final color = passed == false ? NusaColors.danger : NusaColors.success;
      return Row(
        children: [
          Icon(Icons.workspace_premium_rounded, size: 18, color: color),
          const SizedBox(width: 6),
          Text(
            'Nilai ${_score(exam.result.score)}',
            style: TextStyle(
              color: color,
              fontSize: 11.5,
              fontWeight: FontWeight.w800,
            ),
          ),
          if (passed != null) ...[
            const SizedBox(width: 6),
            Text(
              passed ? '· Tuntas' : '· Belum tuntas',
              style: TextStyle(color: color, fontSize: 10.5),
            ),
          ],
        ],
      );
    }

    final label = exam.result.waitingForCorrection
        ? 'Menunggu koreksi guru'
        : exam.group == 'selesai'
        ? 'Hasil belum dipublikasikan'
        : '${exam.progress.answered}/${exam.progress.questionCount} soal terjawab';
    return Text(
      label,
      style: const TextStyle(
        color: NusaColors.textSecondary,
        fontSize: 10.5,
        fontWeight: FontWeight.w600,
      ),
    );
  }
}

void _showDetails(BuildContext context, ParentExam exam) {
  final date = _examDate(exam);
  final time = _examTime(exam);
  showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    backgroundColor: Colors.white,
    builder: (context) => SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              exam.name,
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Text(
              exam.subject,
              style: const TextStyle(
                color: NusaColors.primary,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 16),
            _DetailRow(label: 'Status', value: exam.statusLabel),
            _DetailRow(
              label: 'Jadwal',
              value: [if (date != null) _dateLabel(date), ?time].join(' · '),
            ),
            _DetailRow(label: 'Kelas', value: exam.schoolClass ?? '-'),
            _DetailRow(
              label: 'Nomor peserta',
              value: exam.participantNumber ?? '-',
            ),
            _DetailRow(
              label: 'Kemajuan',
              value:
                  '${exam.progress.answered} dari ${exam.progress.questionCount} soal terjawab',
            ),
            const Divider(height: 25),
            if (exam.result.visible) ...[
              const Text(
                'Hasil dipublikasikan',
                style: TextStyle(color: NusaColors.textSecondary, fontSize: 11),
              ),
              const SizedBox(height: 5),
              Text(
                _score(exam.result.score),
                style: TextStyle(
                  color: exam.result.passed == false
                      ? NusaColors.danger
                      : NusaColors.success,
                  fontSize: 34,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (exam.result.minimumScore != null)
                Text(
                  'KKM ${_score(exam.result.minimumScore)} · '
                  '${exam.result.passed == true ? 'Tuntas' : 'Belum tuntas'}',
                  style: const TextStyle(fontSize: 12),
                ),
            ] else
              _PrivacyNotice(
                message: exam.result.waitingForCorrection
                    ? 'Nilai akan tampil setelah koreksi guru selesai dan hasil dipublikasikan.'
                    : 'Nilai belum dipublikasikan oleh sekolah.',
              ),
          ],
        ),
      ),
    ),
  );
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
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
            value.isEmpty ? '-' : value,
            style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(maxWidth: 105),
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .09),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      maxLines: 2,
      textAlign: TextAlign.center,
      overflow: TextOverflow.ellipsis,
      style: TextStyle(
        color: color,
        fontSize: 9.5,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _Info extends StatelessWidget {
  const _Info({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 14, color: NusaColors.textSecondary),
      const SizedBox(width: 4),
      Text(
        text,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10),
      ),
    ],
  );
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
      ),
      const SizedBox(height: 2),
      Text(
        subtitle,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
      ),
    ],
  );
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
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.shield_outlined, color: NusaColors.primary, size: 19),
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

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({
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
          Icon(icon, color: NusaColors.primary, size: 34),
          const SizedBox(height: 9),
          Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
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
          const Icon(Icons.cloud_off_rounded, size: 48),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
          ),
        ],
      ),
    ),
  );
}

Color _toneColor(String tone) => switch (tone) {
  'aktif' => NusaColors.success,
  'bahaya' => NusaColors.danger,
  'selesai' => NusaColors.primary,
  _ => const Color(0xFFE59A00),
};

String _score(double? value) {
  if (value == null) return '-';
  return value == value.roundToDouble()
      ? value.toInt().toString()
      : value
            .toStringAsFixed(2)
            .replaceFirst(RegExp(r'0+$'), '')
            .replaceFirst(RegExp(r'\.$'), '');
}

String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  if (date == null) return value;
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

String? _examDate(ParentExam exam) {
  if (exam.date case final value?) return value;
  final value = exam.startAt;
  if (value == null) return null;
  return '${value.year.toString().padLeft(4, '0')}-'
      '${value.month.toString().padLeft(2, '0')}-'
      '${value.day.toString().padLeft(2, '0')}';
}

String? _examTime(ParentExam exam) {
  if (exam.time case final value?) return value;
  final start = exam.startAt;
  if (start == null) return null;
  final startLabel =
      '${start.hour.toString().padLeft(2, '0')}:'
      '${start.minute.toString().padLeft(2, '0')}';
  final end = exam.endAt;
  if (end == null) return startLabel;
  final endLabel =
      '${end.hour.toString().padLeft(2, '0')}:'
      '${end.minute.toString().padLeft(2, '0')}';
  return '$startLabel - $endLabel';
}
