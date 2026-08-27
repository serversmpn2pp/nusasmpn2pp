import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/survey_monitoring/application/survey_monitoring_controller.dart';
import 'package:nusa/features/survey_monitoring/domain/survey_monitoring.dart';

class SurveyMonitoringDetailView extends ConsumerWidget {
  const SurveyMonitoringDetailView({
    required this.assignmentId,
    required this.semester,
    super.key,
  });

  final int assignmentId;
  final String semester;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final query = (assignmentId: assignmentId, semester: semester);
    final detail = ref.watch(surveyMonitoringDetailProvider(query));

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Rincian Survei'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: detail.isLoading
                ? null
                : () => ref.invalidate(surveyMonitoringDetailProvider(query)),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: detail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _DetailError(
            message: _errorMessage(error),
            onRetry: () =>
                ref.invalidate(surveyMonitoringDetailProvider(query)),
          ),
          data: (data) => RefreshIndicator(
            onRefresh: () =>
                ref.refresh(surveyMonitoringDetailProvider(query).future),
            child: ListView(
              key: const PageStorageKey<String>(
                'survey-monitoring-detail-scroll',
              ),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
              children: [
                _AssignmentContext(detail: data),
                const SizedBox(height: 10),
                _DetailSummary(assignment: data.assignment),
                const SizedBox(height: 12),
                if (!data.assignment.resultsOpen)
                  _LockedResults(detail: data)
                else ...[
                  const _PrivacyNotice(),
                  const SizedBox(height: 18),
                  const _SectionTitle(
                    title: 'Rincian Pernyataan',
                    subtitle:
                        'Skala 1 sangat tidak sesuai sampai 5 sangat sesuai.',
                  ),
                  const SizedBox(height: 9),
                  for (
                    var index = 0;
                    index < data.questions.length;
                    index++
                  ) ...[
                    _QuestionCard(
                      index: index + 1,
                      question: data.questions[index],
                    ),
                    if (index < data.questions.length - 1)
                      const SizedBox(height: 9),
                  ],
                  const SizedBox(height: 18),
                  _SectionTitle(
                    title: 'Saran Siswa',
                    subtitle:
                        '${data.suggestions.length} saran tertulis anonim',
                  ),
                  const SizedBox(height: 9),
                  if (data.suggestions.isEmpty)
                    const _EmptySuggestions()
                  else
                    for (
                      var index = 0;
                      index < data.suggestions.length;
                      index++
                    )
                      Padding(
                        padding: EdgeInsets.only(
                          bottom: index == data.suggestions.length - 1 ? 0 : 8,
                        ),
                        child: _SuggestionCard(
                          suggestion: data.suggestions[index],
                        ),
                      ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _AssignmentContext extends StatelessWidget {
  const _AssignmentContext({required this.detail});

  final SurveyMonitoringDetail detail;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                detail.assignment.teacherName,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
              decoration: BoxDecoration(
                color: NusaColors.accent,
                borderRadius: BorderRadius.circular(20),
              ),
              child: const Text(
                'Anonim',
                style: TextStyle(
                  color: NusaColors.primaryDark,
                  fontSize: 9,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 5),
        Text(
          '${detail.assignment.subjectName} · ${detail.assignment.className}',
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.88),
            fontSize: 12,
          ),
        ),
        Text(
          '${detail.assignment.academicYearName} · Semester ${_capitalize(detail.semester)}',
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.68),
            fontSize: 10.5,
          ),
        ),
      ],
    ),
  );
}

class _DetailSummary extends StatelessWidget {
  const _DetailSummary({required this.assignment});

  final SurveyMonitoringAssignment assignment;

  @override
  Widget build(BuildContext context) => GridView.count(
    crossAxisCount: 2,
    shrinkWrap: true,
    physics: const NeverScrollableScrollPhysics(),
    mainAxisSpacing: 8,
    crossAxisSpacing: 8,
    childAspectRatio: 2.25,
    children: [
      _MetricCard(label: 'Siswa Kelas', value: '${assignment.studentCount}'),
      _MetricCard(
        label: 'Sudah Mengisi',
        value: '${assignment.respondentCount}',
        color: NusaColors.success,
      ),
      _MetricCard(
        label: 'Pengisian',
        value: '${assignment.responsePercentage.toStringAsFixed(1)}%',
      ),
      _MetricCard(
        label: 'Rata-rata',
        value: assignment.resultsOpen
            ? assignment.average?.toStringAsFixed(2) ?? '-'
            : '-',
        color: NusaColors.primaryLight,
      ),
    ],
  );
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.label, required this.value, this.color});

  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            maxLines: 1,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 9.5,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              color: color ?? NusaColors.textPrimary,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    ),
  );
}

class _LockedResults extends StatelessWidget {
  const _LockedResults({required this.detail});

  final SurveyMonitoringDetail detail;

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('survey-monitoring-results-locked'),
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: NusaColors.accent.withValues(alpha: 0.13),
      border: Border.all(color: NusaColors.accent),
      borderRadius: BorderRadius.circular(16),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.lock_outline_rounded, color: NusaColors.primary),
        const SizedBox(width: 11),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Hasil rinci belum ditampilkan',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 3),
              Text(
                'Minimal ${detail.minimumRespondents} siswa harus mengisi '
                'agar kerahasiaan jawaban tetap terlindungi.',
                style: const TextStyle(fontSize: 11.5, height: 1.4),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Text(
          '${detail.assignment.respondentCount}/${detail.minimumRespondents}',
          style: const TextStyle(
            color: NusaColors.primary,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    ),
  );
}

class _PrivacyNotice extends StatelessWidget {
  const _PrivacyNotice();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: NusaColors.successSurface,
      borderRadius: BorderRadius.circular(14),
    ),
    child: const Row(
      children: [
        Icon(Icons.shield_outlined, color: NusaColors.success, size: 20),
        SizedBox(width: 9),
        Expanded(
          child: Text(
            'Rincian ditampilkan sebagai agregat tanpa nama atau identitas siswa.',
            style: TextStyle(fontSize: 11, height: 1.35),
          ),
        ),
      ],
    ),
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
        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
      ),
      Text(
        subtitle,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
      ),
    ],
  );
}

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({required this.index, required this.question});

  final int index;
  final SurveyMonitoringQuestion question;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('survey-monitoring-question-$index'),
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 28,
                height: 28,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: NusaColors.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(9),
                ),
                child: Text(
                  '$index',
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      question.statement,
                      style: const TextStyle(
                        fontSize: 12.5,
                        height: 1.4,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      '${question.answerCount} jawaban',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 9.5,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Column(
                  children: [
                    Text(
                      question.average?.toStringAsFixed(2) ?? '-',
                      style: const TextStyle(
                        color: NusaColors.primary,
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const Text(
                      'Rata-rata',
                      style: TextStyle(
                        fontSize: 8,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (final distribution in question.distribution)
            Padding(
              padding: const EdgeInsets.only(bottom: 7),
              child: Row(
                children: [
                  SizedBox(
                    width: 16,
                    child: Text(
                      '${distribution.value}',
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        minHeight: 7,
                        value: (distribution.percentage / 100)
                            .clamp(0.0, 1.0)
                            .toDouble(),
                        color: _scoreColor(distribution.value),
                        backgroundColor: NusaColors.outline,
                      ),
                    ),
                  ),
                  const SizedBox(width: 7),
                  SizedBox(
                    width: 62,
                    child: Text(
                      '${distribution.count} · ${distribution.percentage.toStringAsFixed(1)}%',
                      textAlign: TextAlign.right,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 9,
                        fontWeight: FontWeight.w700,
                      ),
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

class _SuggestionCard extends StatelessWidget {
  const _SuggestionCard({required this.suggestion});

  final SurveyMonitoringSuggestion suggestion;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.format_quote_rounded,
            color: NusaColors.primaryLight,
            size: 23,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  suggestion.text,
                  style: const TextStyle(fontSize: 12, height: 1.45),
                ),
                if (suggestion.filledAt != null) ...[
                  const SizedBox(height: 5),
                  Text(
                    _dateLabel(suggestion.filledAt!),
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 9.5,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _EmptySuggestions extends StatelessWidget {
  const _EmptySuggestions();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: NusaColors.surface,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: NusaColors.outline),
    ),
    child: const Center(
      child: Text(
        'Belum ada saran tertulis dari siswa.',
        style: TextStyle(color: NusaColors.textSecondary, fontSize: 11),
      ),
    ),
  );
}

class _DetailError extends StatelessWidget {
  const _DetailError({required this.message, required this.onRetry});

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
            Icons.cloud_off_rounded,
            size: 48,
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

Color _scoreColor(int value) => switch (value) {
  1 => const Color(0xFFC2413B),
  2 => const Color(0xFFD97706),
  3 => NusaColors.accent,
  4 => NusaColors.primaryLight,
  _ => NusaColors.success,
};

String _capitalize(String value) =>
    value.isEmpty ? value : '${value[0].toUpperCase()}${value.substring(1)}';

String _dateLabel(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}/'
    '${value.month.toString().padLeft(2, '0')}/${value.year}';

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Rincian survei belum dapat dimuat.';
