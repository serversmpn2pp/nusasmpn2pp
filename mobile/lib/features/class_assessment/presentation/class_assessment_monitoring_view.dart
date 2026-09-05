import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/class_assessment/application/class_assessment_monitoring_controller.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment_monitoring.dart';
import 'package:nusa/features/class_assessment/presentation/widgets/class_assessment_operation_widgets.dart';

class ClassAssessmentMonitoringView extends ConsumerStatefulWidget {
  const ClassAssessmentMonitoringView({required this.assessmentId, super.key});

  final int assessmentId;

  @override
  ConsumerState<ClassAssessmentMonitoringView> createState() =>
      _ClassAssessmentMonitoringViewState();
}

class _ClassAssessmentMonitoringViewState
    extends ConsumerState<ClassAssessmentMonitoringView> {
  int? _classId;
  String _status = 'semua';
  bool _autoRefresh = true;
  Timer? _timer;
  final Set<int> _unlocking = {};

  AssessmentOperationRequest get _request =>
      (assessmentId: widget.assessmentId, classId: _classId, status: _status);

  @override
  void initState() {
    super.initState();
    _startTimer();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _startTimer() {
    _timer?.cancel();
    if (!_autoRefresh) return;
    _timer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted) ref.invalidate(classAssessmentMonitoringProvider(_request));
    });
  }

  Future<void> _refresh() async {
    final request = _request;
    ref.invalidate(classAssessmentMonitoringProvider(request));
    await ref.read(classAssessmentMonitoringProvider(request).future);
  }

  @override
  Widget build(BuildContext context) {
    final request = _request;
    final state = ref.watch(classAssessmentMonitoringProvider(request));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Monitoring Asesmen'),
        actions: [
          IconButton(
            tooltip: 'Hasil asesmen',
            onPressed: () =>
                context.push('/asesmen-kelas/${widget.assessmentId}/hasil'),
            icon: const Icon(Icons.assessment_outlined),
          ),
          IconButton(
            key: const Key('assessment-monitoring-auto-refresh'),
            tooltip: _autoRefresh
                ? 'Matikan pembaruan otomatis'
                : 'Aktifkan pembaruan otomatis',
            onPressed: () {
              setState(() => _autoRefresh = !_autoRefresh);
              _startTimer();
            },
            icon: Icon(
              _autoRefresh ? Icons.sync_rounded : Icons.sync_disabled_rounded,
            ),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => AssessmentOperationError(
            message: _message(error, 'Monitoring asesmen belum dapat dimuat.'),
            onRetry: _refresh,
          ),
          data: (data) => RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              key: const PageStorageKey<String>('class-assessment-monitoring'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                AssessmentOperationHero(
                  assessment: data.assessment,
                  eyebrow: 'MONITORING LANGSUNG',
                ),
                const SizedBox(height: 11),
                _LiveStatus(data: data, autoRefresh: _autoRefresh),
                const SizedBox(height: 11),
                _ReadinessCard(readiness: data.readiness),
                const SizedBox(height: 11),
                AssessmentMetricsGrid(
                  items: [
                    AssessmentMetricData(
                      label: 'Total peserta',
                      value: '${data.summary.total}',
                      icon: Icons.groups_rounded,
                      color: NusaColors.primary,
                    ),
                    AssessmentMetricData(
                      label: 'Belum hadir',
                      value: '${data.summary.notPresent}',
                      icon: Icons.person_off_outlined,
                      color: NusaColors.textSecondary,
                    ),
                    AssessmentMetricData(
                      label: 'Hadir, belum mulai',
                      value: '${data.summary.presentNotStarted}',
                      icon: Icons.hourglass_top_rounded,
                      color: const Color(0xFF9A7000),
                    ),
                    AssessmentMetricData(
                      label: 'Sedang mengerjakan',
                      value: '${data.summary.working}',
                      icon: Icons.edit_note_rounded,
                      color: NusaColors.primaryLight,
                    ),
                    AssessmentMetricData(
                      label: 'Selesai (${data.summary.finishedPercent}%)',
                      value: '${data.summary.finished}',
                      icon: Icons.task_alt_rounded,
                      color: NusaColors.success,
                    ),
                    AssessmentMetricData(
                      label: 'Tidak hadir',
                      value: '${data.summary.absent}',
                      icon: Icons.event_busy_rounded,
                      color: const Color(0xFFC62828),
                    ),
                  ],
                ),
                const SizedBox(height: 11),
                AssessmentFilterCard(
                  classes: data.classes,
                  statuses: data.statuses,
                  selectedClassId: _classId,
                  selectedStatus: _status,
                  classKey: const Key('assessment-monitoring-class-filter'),
                  statusKey: const Key('assessment-monitoring-status-filter'),
                  onClassChanged: (value) => setState(() => _classId = value),
                  onStatusChanged: (value) => setState(() => _status = value),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Peserta Asesmen',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    Text(
                      '${data.items.length} siswa',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 9),
                if (data.items.isEmpty)
                  const AssessmentOperationEmpty(
                    message: 'Belum ada peserta yang sesuai dengan filter.',
                  )
                else
                  for (final item in data.items) ...[
                    _ParticipantCard(
                      key: Key('assessment-monitoring-participant-${item.id}'),
                      item: item,
                      questionCount: data.assessment.displayQuestionCount,
                      unlocking: _unlocking.contains(item.id),
                      onUnlock: item.status == 'terblokir'
                          ? () => _unlockParticipant(item)
                          : null,
                    ),
                    const SizedBox(height: 9),
                  ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _unlockParticipant(AssessmentMonitoringParticipant item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Buka ujian siswa?'),
        content: Text(
          '${item.student.name} dapat melanjutkan ujian. Catatan keluar aplikasi tetap disimpan.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Buka Ujian'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _unlocking.add(item.id));
    try {
      await ref
          .read(classAssessmentMonitoringActionsProvider)
          .unlockParticipant(item.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Ujian ${item.student.name} sudah dibuka.')),
      );
      await _refresh();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_message(error, 'Ujian belum dapat dibuka.'))),
        );
      }
    } finally {
      if (mounted) setState(() => _unlocking.remove(item.id));
    }
  }
}

class _LiveStatus extends StatelessWidget {
  const _LiveStatus({required this.data, required this.autoRefresh});

  final AssessmentMonitoringData data;
  final bool autoRefresh;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
    decoration: BoxDecoration(
      color: autoRefresh ? NusaColors.successSurface : Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(
        color: autoRefresh
            ? NusaColors.success.withValues(alpha: 0.24)
            : NusaColors.outline,
      ),
    ),
    child: Row(
      children: [
        Icon(
          autoRefresh ? Icons.sensors_rounded : Icons.pause_circle_outline,
          color: autoRefresh ? NusaColors.success : NusaColors.textSecondary,
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            autoRefresh
                ? 'Data server diperbarui otomatis setiap ${data.refreshSeconds} detik.'
                : 'Pembaruan otomatis sedang dimatikan.',
            style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700),
          ),
        ),
        Text(
          assessmentTime(data.generatedAt),
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10),
        ),
      ],
    ),
  );
}

class _ReadinessCard extends StatelessWidget {
  const _ReadinessCard({required this.readiness});

  final AssessmentReadiness readiness;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          _ReadinessItem(
            label: 'Paket',
            ready: readiness.packageOpen,
            readyText: 'Dibuka',
            waitingText: 'Belum dibuka',
          ),
          _ReadinessItem(
            label: 'Soal',
            ready: readiness.questionsReady,
            readyText: 'Siap',
            waitingText: 'Belum siap',
          ),
          _ReadinessItem(
            label: 'Peserta',
            ready: readiness.participantsReady,
            readyText: 'Terdaftar',
            waitingText: 'Belum ada',
          ),
        ],
      ),
    ),
  );
}

class _ReadinessItem extends StatelessWidget {
  const _ReadinessItem({
    required this.label,
    required this.ready,
    required this.readyText,
    required this.waitingText,
  });

  final String label;
  final bool ready;
  final String readyText;
  final String waitingText;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Icon(
          ready ? Icons.check_circle_rounded : Icons.warning_amber_rounded,
          size: 19,
          color: ready ? NusaColors.success : const Color(0xFF9A7000),
        ),
        const SizedBox(height: 3),
        Text(label, style: const TextStyle(fontSize: 9)),
        Text(
          ready ? readyText : waitingText,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.w800),
        ),
      ],
    ),
  );
}

class _ParticipantCard extends StatelessWidget {
  const _ParticipantCard({
    required this.item,
    required this.questionCount,
    required this.unlocking,
    this.onUnlock,
    super.key,
  });

  final AssessmentMonitoringParticipant item;
  final int questionCount;
  final bool unlocking;
  final VoidCallback? onUnlock;

  @override
  Widget build(BuildContext context) {
    final color = assessmentToneColor(item.statusTone);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(Icons.person_rounded, color: color),
                ),
                const SizedBox(width: 9),
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
                      Text(
                        '${item.className} · Absen ${item.student.rollNumber ?? '-'} · NISN ${item.student.nationalStudentNumber ?? '-'}',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 9.5,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 6),
                AssessmentToneBadge(
                  label: item.statusLabel,
                  tone: item.statusTone,
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: LinearProgressIndicator(
                      value: item.answerPercent / 100,
                      minHeight: 8,
                      backgroundColor: NusaColors.outline,
                      color: NusaColors.primaryLight,
                    ),
                  ),
                ),
                const SizedBox(width: 9),
                Text(
                  '${item.savedAnswers}/$questionCount (${item.answerPercent}%)',
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 9),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                AssessmentToneBadge(
                  label: item.attendanceLabel,
                  tone: item.attendanceTone,
                ),
                if (item.doubtfulAnswers > 0)
                  AssessmentToneBadge(
                    label: '${item.doubtfulAnswers} ragu',
                    tone: 'peringatan',
                  ),
                if (item.appSwitchCount > 0)
                  AssessmentToneBadge(
                    label: '${item.appSwitchCount} keluar aplikasi',
                    tone: item.status == 'terblokir' ? 'bahaya' : 'peringatan',
                  ),
                if (item.remainingMinutes != null)
                  AssessmentInlineInfo(
                    icon: Icons.timer_outlined,
                    label: 'Sisa ${item.remainingMinutes} menit',
                  ),
                if (item.startedAt != null)
                  AssessmentInlineInfo(
                    icon: Icons.play_circle_outline,
                    label: 'Mulai ${assessmentTime(item.startedAt)}',
                  ),
                if (item.finishedAt != null)
                  AssessmentInlineInfo(
                    icon: Icons.task_alt_rounded,
                    label: 'Selesai ${assessmentTime(item.finishedAt)}',
                  ),
              ],
            ),
            if (onUnlock != null) ...[
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: FilledButton.tonalIcon(
                  key: Key('assessment-monitoring-unlock-${item.id}'),
                  onPressed: unlocking ? null : onUnlock,
                  icon: unlocking
                      ? const SizedBox.square(
                          dimension: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.lock_open_rounded),
                  label: const Text('Buka Ujian'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

String _message(Object error, String fallback) =>
    error is AppException ? error.message : fallback;
