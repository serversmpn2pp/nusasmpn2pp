import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/learning_survey/application/learning_survey_controller.dart';
import 'package:nusa/features/learning_survey/domain/learning_survey.dart';

class LearningSurveyView extends ConsumerStatefulWidget {
  const LearningSurveyView({
    required this.assignmentId,
    required this.semester,
    super.key,
  });

  final int assignmentId;
  final String semester;

  @override
  ConsumerState<LearningSurveyView> createState() => _LearningSurveyViewState();
}

class _LearningSurveyViewState extends ConsumerState<LearningSurveyView> {
  final _suggestionController = TextEditingController();
  final Map<String, int> _answers = {};
  bool _showMissing = false;
  bool _submitting = false;

  LearningSurveyArgs get _args => LearningSurveyArgs(
    assignmentId: widget.assignmentId,
    semester: widget.semester,
  );

  @override
  void dispose() {
    _suggestionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final result = ref.watch(learningSurveyProvider(_args));
    final page = result.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(title: const Text('Survei Pembelajaran')),
      bottomNavigationBar: page == null
          ? null
          : _SurveyBottomBar(
              completed: page.alreadyCompleted,
              answered: _answers.length,
              total: page.questions.length,
              submitting: _submitting,
              onPressed: _submitting
                  ? null
                  : page.alreadyCompleted
                  ? () => Navigator.pop(context, true)
                  : () => _submit(page),
            ),
      body: SafeArea(
        top: false,
        child: result.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _SurveyError(
            message: _errorMessage(error),
            onRetry: () async => ref.invalidate(learningSurveyProvider(_args)),
          ),
          data: (data) => data.alreadyCompleted
              ? const _AlreadyCompleted()
              : _buildForm(data),
        ),
      ),
    );
  }

  Widget _buildForm(LearningSurveyPage page) => ListView(
    key: const PageStorageKey<String>('learning-survey-list'),
    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
    children: [
      const _SurveyHero(),
      const SizedBox(height: 10),
      _LearningContextCard(page: page),
      const SizedBox(height: 10),
      _ScaleGuide(options: page.options),
      const SizedBox(height: 16),
      Row(
        children: [
          const Expanded(
            child: Text(
              'Pernyataan Survei',
              style: TextStyle(
                color: NusaColors.textPrimary,
                fontSize: 16,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          Text(
            '${_answers.length}/${page.questions.length} terjawab',
            style: TextStyle(
              color: _answers.length == page.questions.length
                  ? NusaColors.success
                  : NusaColors.textSecondary,
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
      const SizedBox(height: 9),
      for (var index = 0; index < page.questions.length; index++) ...[
        _SurveyQuestionCard(
          index: index + 1,
          question: page.questions[index],
          options: page.options,
          value: _answers[page.questions[index].code],
          showError:
              _showMissing && _answers[page.questions[index].code] == null,
          onChanged: (value) => setState(() {
            _answers[page.questions[index].code] = value;
            _showMissing = false;
          }),
        ),
        const SizedBox(height: 9),
      ],
      Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Saran untuk pembelajaran',
                style: TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              const Text(
                'Opsional · maksimal 1.000 karakter',
                style: TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9.5,
                ),
              ),
              const SizedBox(height: 10),
              TextField(
                key: const Key('survey-suggestion'),
                controller: _suggestionController,
                minLines: 3,
                maxLines: 5,
                maxLength: 1000,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  hintText:
                      'Tuliskan hal yang sudah baik atau dapat ditingkatkan.',
                  alignLabelWithHint: true,
                ),
              ),
            ],
          ),
        ),
      ),
      const SizedBox(height: 10),
      Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: NusaColors.surfaceBlue,
          borderRadius: BorderRadius.circular(13),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(
              Icons.shield_outlined,
              size: 19,
              color: NusaColors.primary,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                page.note,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10,
                  height: 1.4,
                ),
              ),
            ),
          ],
        ),
      ),
    ],
  );

  Future<void> _submit(LearningSurveyPage page) async {
    if (_answers.length != page.questions.length) {
      setState(() => _showMissing = true);
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(content: Text('Semua pernyataan wajib dijawab.')),
        );
      return;
    }

    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.rate_review_rounded,
              color: NusaColors.primary,
            ),
            title: const Text('Kirim survei?'),
            content: const Text(
              'Pastikan jawaban sudah sesuai. Survei yang telah dikirim tidak '
              'dapat diubah kembali.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Periksa Lagi'),
              ),
              FilledButton(
                key: const Key('confirm-submit-survey'),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Kirim Survei'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;

    setState(() => _submitting = true);
    try {
      await ref
          .read(learningSurveyActionsProvider)
          .submit(
            _args,
            LearningSurveySubmission(
              answers: Map.unmodifiable(_answers),
              suggestion: _suggestionController.text,
            ),
          );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}

class _SurveyHero extends StatelessWidget {
  const _SurveyHero();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: const Row(
      children: [
        Icon(Icons.forum_rounded, size: 36, color: NusaColors.accent),
        SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Umpan Balik Siswa',
                style: TextStyle(
                  color: NusaColors.accent,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                ),
              ),
              SizedBox(height: 3),
              Text(
                'Jawab sesuai pengalaman belajar Anda pada semester ini.',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 13,
                  height: 1.35,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _LearningContextCard extends StatelessWidget {
  const _LearningContextCard({required this.page});

  final LearningSurveyPage page;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final width = constraints.maxWidth < 280
              ? constraints.maxWidth
              : (constraints.maxWidth - 9) / 2;
          return Wrap(
            spacing: 9,
            runSpacing: 11,
            children: [
              SizedBox(
                width: width,
                child: _ContextItem(
                  label: 'Mata pelajaran',
                  value: page.context.subjectName,
                ),
              ),
              SizedBox(
                width: width,
                child: _ContextItem(
                  label: 'Guru',
                  value: page.context.teacherName,
                ),
              ),
              SizedBox(
                width: width,
                child: _ContextItem(
                  label: 'Kelas',
                  value: page.context.className,
                ),
              ),
              SizedBox(
                width: width,
                child: _ContextItem(
                  label: 'Periode',
                  value:
                      '${page.context.academicYearName} · ${_semesterLabel(page.semester)}',
                ),
              ),
            ],
          );
        },
      ),
    ),
  );
}

class _ContextItem extends StatelessWidget {
  const _ContextItem({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        label,
        style: const TextStyle(
          color: NusaColors.textSecondary,
          fontSize: 8.5,
          fontWeight: FontWeight.w700,
        ),
      ),
      const SizedBox(height: 2),
      Text(
        value,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          color: NusaColors.textPrimary,
          fontSize: 10.5,
          fontWeight: FontWeight.w800,
        ),
      ),
    ],
  );
}

class _ScaleGuide extends StatelessWidget {
  const _ScaleGuide({required this.options});

  final List<LearningSurveyOption> options;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF9E5),
      borderRadius: BorderRadius.circular(13),
      border: Border.all(color: NusaColors.accent.withValues(alpha: 0.6)),
    ),
    child: Row(
      children: [
        const Icon(Icons.tune_rounded, size: 19, color: Color(0xFFA67C00)),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            options.isEmpty
                ? 'Gunakan skala jawaban 1 sampai 5.'
                : '${options.first.value} = ${options.first.label}  ·  '
                      '${options.last.value} = ${options.last.label}',
            style: const TextStyle(
              color: Color(0xFF765B00),
              fontSize: 9.5,
              height: 1.35,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    ),
  );
}

class _SurveyQuestionCard extends StatelessWidget {
  const _SurveyQuestionCard({
    required this.index,
    required this.question,
    required this.options,
    required this.value,
    required this.showError,
    required this.onChanged,
  });

  final int index;
  final LearningSurveyQuestion question;
  final List<LearningSurveyOption> options;
  final int? value;
  final bool showError;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final selected = options.where((item) => item.value == value).firstOrNull;
    return Card(
      key: Key('survey-question-${question.code}'),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(
          color: showError
              ? Theme.of(context).colorScheme.error
              : NusaColors.outline,
          width: showError ? 1.5 : 1,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 27,
                  height: 27,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: NusaColors.primary,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    '$index',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 10,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    question.statement,
                    style: const TextStyle(
                      color: NusaColors.textPrimary,
                      fontSize: 12,
                      height: 1.4,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 13),
            Row(
              children: [
                for (
                  var optionIndex = 0;
                  optionIndex < options.length;
                  optionIndex++
                ) ...[
                  Expanded(
                    child: _ScaleOption(
                      questionCode: question.code,
                      option: options[optionIndex],
                      selected: options[optionIndex].value == value,
                      onTap: () => onChanged(options[optionIndex].value),
                    ),
                  ),
                  if (optionIndex != options.length - 1)
                    const SizedBox(width: 6),
                ],
              ],
            ),
            const SizedBox(height: 8),
            Text(
              showError
                  ? 'Pernyataan ini wajib dijawab.'
                  : selected?.label ?? 'Pilih jawaban 1 sampai 5',
              style: TextStyle(
                color: showError
                    ? Theme.of(context).colorScheme.error
                    : selected != null
                    ? NusaColors.primary
                    : NusaColors.textSecondary,
                fontSize: 9.5,
                fontWeight: selected != null || showError
                    ? FontWeight.w700
                    : FontWeight.w400,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ScaleOption extends StatelessWidget {
  const _ScaleOption({
    required this.questionCode,
    required this.option,
    required this.selected,
    required this.onTap,
  });

  final String questionCode;
  final LearningSurveyOption option;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: selected ? NusaColors.primary : NusaColors.background,
    borderRadius: BorderRadius.circular(11),
    child: InkWell(
      key: Key('survey-answer-$questionCode-${option.value}'),
      onTap: onTap,
      borderRadius: BorderRadius.circular(11),
      child: Container(
        height: 43,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(11),
          border: Border.all(
            color: selected ? NusaColors.primary : NusaColors.outline,
          ),
        ),
        child: Text(
          '${option.value}',
          style: TextStyle(
            color: selected ? Colors.white : NusaColors.primary,
            fontSize: 13,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    ),
  );
}

class _SurveyBottomBar extends StatelessWidget {
  const _SurveyBottomBar({
    required this.completed,
    required this.answered,
    required this.total,
    required this.submitting,
    required this.onPressed,
  });

  final bool completed;
  final int answered;
  final int total;
  final bool submitting;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    elevation: 12,
    child: SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
        child: FilledButton.icon(
          key: const Key('submit-learning-survey'),
          onPressed: onPressed,
          icon: submitting
              ? const SizedBox.square(
                  dimension: 17,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : Icon(
                  completed ? Icons.check_circle_rounded : Icons.send_rounded,
                ),
          label: Text(
            submitting
                ? 'Mengirim...'
                : completed
                ? 'Kembali ke Nilai Saya'
                : 'Kirim dan Buka Nilai ($answered/$total)',
          ),
        ),
      ),
    ),
  );
}

class _AlreadyCompleted extends StatelessWidget {
  const _AlreadyCompleted();

  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(
      padding: EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.verified_rounded, size: 52, color: NusaColors.success),
          SizedBox(height: 12),
          Text(
            'Survei Sudah Diisi',
            style: TextStyle(
              color: NusaColors.textPrimary,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          SizedBox(height: 5),
          Text(
            'Nilai mata pelajaran sudah dapat dilihat.',
            textAlign: TextAlign.center,
            style: TextStyle(color: NusaColors.textSecondary),
          ),
        ],
      ),
    ),
  );
}

class _SurveyError extends StatelessWidget {
  const _SurveyError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

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

String _semesterLabel(String semester) =>
    semester == 'genap' ? 'Genap' : 'Ganjil';

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Survei pembelajaran belum dapat diproses.';
}
