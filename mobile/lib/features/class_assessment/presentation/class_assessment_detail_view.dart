import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/class_assessment/application/class_assessment_controller.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment.dart';

class ClassAssessmentDetailView extends ConsumerWidget {
  const ClassAssessmentDetailView({required this.assessmentId, super.key});
  final int assessmentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(classAssessmentDetailProvider(assessmentId));
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Asesmen'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : () => ref.invalidate(
                    classAssessmentDetailProvider(assessmentId),
                  ),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => current == null
              ? const Center(child: CircularProgressIndicator())
              : _Content(detail: current),
          error: (error, stackTrace) => _ErrorState(
            message: _message(error),
            onRetry: () =>
                ref.invalidate(classAssessmentDetailProvider(assessmentId)),
          ),
          data: (detail) => _Content(detail: detail),
        ),
      ),
    );
  }
}

class _Content extends ConsumerWidget {
  const _Content({required this.detail});
  final ClassAssessmentDetail detail;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final item = detail.assessment;
    return RefreshIndicator(
      onRefresh: () =>
          ref.refresh(classAssessmentDetailProvider(item.id).future),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
        children: [
          _Hero(detail: detail),
          const SizedBox(height: 11),
          _Progress(detail: detail),
          const SizedBox(height: 11),
          Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  key: const Key('class-assessment-questions'),
                  onPressed: item.status == 'nonaktif'
                      ? null
                      : () => context.push('/asesmen-kelas/${item.id}/soal'),
                  icon: const Icon(Icons.quiz_rounded),
                  label: Text(
                    item.questionCount == 0 ? 'Pilih Soal' : 'Ubah Soal',
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton.icon(
                  key: const Key('class-assessment-edit'),
                  onPressed: item.status == 'nonaktif'
                      ? null
                      : () => context.push('/asesmen-kelas/${item.id}/ubah'),
                  icon: const Icon(Icons.edit_outlined),
                  label: const Text('Pengaturan'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 11),
          _Section(
            title: 'Pelaksanaan',
            child: Column(
              children: [
                _DetailRow(
                  icon: Icons.event_rounded,
                  label: 'Dibuka',
                  value: _dateTime(item.startsAt),
                ),
                _DetailRow(
                  icon: Icons.event_available_rounded,
                  label: 'Ditutup',
                  value: _dateTime(item.endsAt),
                ),
                _DetailRow(
                  icon: Icons.timer_outlined,
                  label: 'Durasi',
                  value: '${item.durationMinutes} menit',
                ),
                _DetailRow(
                  icon: Icons.workspace_premium_outlined,
                  label: 'KKM',
                  value: '${detail.minimumScore}',
                  divider: false,
                ),
              ],
            ),
          ),
          const SizedBox(height: 11),
          _Section(
            title: 'Kelas dan tujuan nilai',
            child: Column(
              children: [
                for (var index = 0; index < detail.classes.length; index++)
                  _ClassRow(
                    item: detail.classes[index],
                    divider: index < detail.classes.length - 1,
                  ),
              ],
            ),
          ),
          const SizedBox(height: 11),
          _Section(
            title: 'Pengaturan pengerjaan',
            child: Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [
                _SettingChip(
                  label: 'Acak soal',
                  enabled: detail.shuffleQuestions,
                ),
                _SettingChip(
                  label: 'Acak jawaban',
                  enabled: detail.shuffleAnswers,
                ),
                _SettingChip(
                  label: 'Satu perangkat',
                  enabled: detail.singleDevice,
                ),
                _SettingChip(
                  label: 'Catat pindah aplikasi',
                  enabled: detail.detectTabChange,
                ),
                _SettingChip(
                  label: 'Tampilkan hasil',
                  enabled: detail.showResult,
                ),
              ],
            ),
          ),
          if (detail.instructions?.trim().isNotEmpty == true) ...[
            const SizedBox(height: 11),
            _Section(
              title: 'Petunjuk untuk siswa',
              child: Text(
                detail.instructions!,
                style: const TextStyle(fontSize: 12, height: 1.5),
              ),
            ),
          ],
          const SizedBox(height: 11),
          Container(
            padding: const EdgeInsets.all(13),
            decoration: BoxDecoration(
              color: NusaColors.surfaceBlue,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: NusaColors.outline),
            ),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.info_outline_rounded, color: NusaColors.primary),
                SizedBox(width: 9),
                Expanded(
                  child: Text(
                    'Monitoring pengerjaan, koreksi uraian, dan penerapan hasil ke nilai akan dibangun pada modul operasional CBT berikutnya.',
                    style: TextStyle(fontSize: 10.5, height: 1.4),
                  ),
                ),
              ],
            ),
          ),
          if (detail.access.canDeactivate) ...[
            const SizedBox(height: 18),
            OutlinedButton.icon(
              key: const Key('class-assessment-deactivate'),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFFC62828),
              ),
              onPressed: () => _deactivate(context, ref),
              icon: const Icon(Icons.block_rounded),
              label: const Text('Nonaktifkan Asesmen'),
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _deactivate(BuildContext context, WidgetRef ref) async {
    final accepted = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Nonaktifkan asesmen?'),
        content: const Text(
          'Asesmen tidak akan dapat digunakan lagi oleh siswa. Data dan riwayatnya tetap tersimpan.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('class-assessment-confirm-deactivate'),
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFFC62828),
            ),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Nonaktifkan'),
          ),
        ],
      ),
    );
    if (accepted != true || !context.mounted) return;
    try {
      await ref
          .read(classAssessmentControllerProvider.notifier)
          .deactivate(detail.assessment.id);
      if (context.mounted) context.pop(true);
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_message(error))));
      }
    }
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.detail});
  final ClassAssessmentDetail detail;
  @override
  Widget build(BuildContext context) {
    final item = detail.assessment;
    return Container(
      padding: const EdgeInsets.all(17),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(19),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  detail.code,
                  style: const TextStyle(
                    color: NusaColors.accent,
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  item.statusLabel,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 7),
          Text(
            item.name,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 19,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            '${item.subject} · Kelas ${item.grade} · ${_capitalize(item.semester)}',
            style: const TextStyle(color: Colors.white70, fontSize: 11),
          ),
        ],
      ),
    );
  }
}

class _Progress extends StatelessWidget {
  const _Progress({required this.detail});
  final ClassAssessmentDetail detail;
  @override
  Widget build(BuildContext context) {
    final item = detail.assessment;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            Row(
              children: [
                _Metric(
                  label: 'Soal',
                  value: '${item.questionCount}/${item.targetQuestions}',
                ),
                _Metric(label: 'Peserta', value: '${item.participantCount}'),
                _Metric(label: 'Kelas', value: '${detail.classes.length}'),
                _Metric(label: 'Durasi', value: '${item.durationMinutes} mnt'),
              ],
            ),
            if (!item.questionsReady) ...[
              const SizedBox(height: 11),
              const Row(
                children: [
                  Icon(
                    Icons.warning_amber_rounded,
                    size: 17,
                    color: Color(0xFF9A7000),
                  ),
                  SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      'Pilih soal sampai memenuhi target sebelum pelaksanaan.',
                      style: TextStyle(
                        color: Color(0xFF7B5900),
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 12),
        ),
        Text(
          label,
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 9),
        ),
      ],
    ),
  );
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.child});
  final String title;
  final Widget child;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          child,
        ],
      ),
    ),
  );
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.icon,
    required this.label,
    required this.value,
    this.divider = true,
  });
  final IconData icon;
  final String label;
  final String value;
  final bool divider;
  @override
  Widget build(BuildContext context) => Column(
    children: [
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 7),
        child: Row(
          children: [
            Icon(icon, size: 18, color: NusaColors.primary),
            const SizedBox(width: 9),
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 11,
                ),
              ),
            ),
            Flexible(
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

class _ClassRow extends StatelessWidget {
  const _ClassRow({required this.item, required this.divider});
  final AssessmentTargetClass item;
  final bool divider;
  @override
  Widget build(BuildContext context) => Column(
    children: [
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 7),
        child: Row(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: NusaColors.surfaceBlue,
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(
                Icons.class_rounded,
                color: NusaColors.primary,
                size: 19,
              ),
            ),
            const SizedBox(width: 9),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.name,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  Text(
                    item.component ?? 'Belum ada tujuan nilai',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10,
                    ),
                  ),
                ],
              ),
            ),
            Text(
              '${item.participantCount} siswa',
              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
      if (divider) const Divider(height: 1),
    ],
  );
}

class _SettingChip extends StatelessWidget {
  const _SettingChip({required this.label, required this.enabled});
  final String label;
  final bool enabled;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
    decoration: BoxDecoration(
      color: enabled ? NusaColors.surfaceBlue : const Color(0xFFF3F4F6),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(
          enabled ? Icons.check_circle_rounded : Icons.remove_circle_outline,
          size: 14,
          color: enabled ? NusaColors.primary : NusaColors.textSecondary,
        ),
        const SizedBox(width: 4),
        Text(
          label,
          style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.w700),
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
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

String _dateTime(DateTime? value) {
  if (value == null) return '-';
  return '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year} ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
}

String _capitalize(String value) => value.isEmpty
    ? value
    : '${value.substring(0, 1).toUpperCase()}${value.substring(1)}';
String _message(Object error) => error is AppException
    ? error.message
    : 'Detail asesmen belum dapat dimuat.';
