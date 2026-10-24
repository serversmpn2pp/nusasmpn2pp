import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/question_bank/application/question_bank_controller.dart';
import 'package:nusa/features/question_bank/domain/question_bank.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';
import 'package:nusa/features/student_exam/presentation/student_exam_question_view.dart';

class QuestionBankDetailView extends ConsumerWidget {
  const QuestionBankDetailView({required this.questionId, super.key});
  final int questionId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(questionBankDetailProvider(questionId));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Soal CBT'),
        actions: [
          if (state.value?.access.canManage == true)
            IconButton(
              tooltip: 'Edit',
              onPressed: () => context.push('/bank-soal/$questionId/ubah'),
              icon: const Icon(Icons.edit_rounded),
            ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _DetailError(
            message: error is AppException
                ? error.message
                : 'Detail soal belum dapat dimuat.',
            onRetry: () =>
                ref.invalidate(questionBankDetailProvider(questionId)),
          ),
          data: (question) => RefreshIndicator(
            onRefresh: () async =>
                ref.refresh(questionBankDetailProvider(questionId).future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
              children: [
                _IdentityCard(question: question),
                const SizedBox(height: 11),
                FilledButton.tonalIcon(
                  key: const Key('question-bank-preview'),
                  onPressed: () =>
                      context.push('/bank-soal/$questionId/pratinjau'),
                  icon: const Icon(Icons.preview_outlined),
                  label: const Text('Pratinjau Tampilan Siswa'),
                ),
                const SizedBox(height: 11),
                _ContentCard(question: question),
                const SizedBox(height: 11),
                _AnswerCard(question: question),
                if (_hasText(question.explanation)) ...[
                  const SizedBox(height: 11),
                  _TextCard(
                    title: 'Pembahasan',
                    icon: Icons.lightbulb_outline_rounded,
                    text: question.explanation!,
                  ),
                ],
                if (question.access.canArchive) ...[
                  const SizedBox(height: 16),
                  OutlinedButton.icon(
                    key: const Key('question-bank-archive'),
                    onPressed: () => _archive(context, ref, question),
                    icon: const Icon(Icons.archive_outlined),
                    label: const Text('Arsipkan Soal'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.red.shade700,
                      minimumSize: const Size.fromHeight(48),
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

  Future<void> _archive(
    BuildContext context,
    WidgetRef ref,
    BankQuestionDetail question,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Arsipkan soal?'),
        content: Text(
          '${question.code} tidak lagi dapat dipilih untuk paket baru. Riwayat pemakaian tetap tersimpan.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Arsipkan'),
          ),
        ],
      ),
    );
    if (confirmed != true || !context.mounted) return;

    try {
      await ref
          .read(questionBankControllerProvider.notifier)
          .archive(question.id);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Soal berhasil diarsipkan.')),
        );
        context.pop();
      }
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error is AppException
                  ? error.message
                  : 'Soal belum dapat diarsipkan.',
            ),
          ),
        );
      }
    }
  }
}

class _IdentityCard extends StatelessWidget {
  const _IdentityCard({required this.question});
  final BankQuestionDetail question;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
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
            Container(
              width: 43,
              height: 43,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(13),
              ),
              child: const Icon(Icons.quiz_rounded, color: NusaColors.accent),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    question.code,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  Text(
                    question.typeLabel,
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 10.5,
                    ),
                  ),
                ],
              ),
            ),
            _HeaderStatus(question: question),
          ],
        ),
        const SizedBox(height: 14),
        Wrap(
          spacing: 14,
          runSpacing: 8,
          children: [
            _HeaderFact(
              icon: Icons.menu_book_rounded,
              text: question.subject?.name ?? '-',
            ),
            _HeaderFact(
              icon: Icons.school_rounded,
              text: 'Kelas ${question.grade}',
            ),
            _HeaderFact(
              icon: Icons.stars_rounded,
              text: '${_number(question.maximumScore)} poin',
            ),
            _HeaderFact(
              icon: Icons.recycling_rounded,
              text: '${question.usageCount} pemakaian',
            ),
          ],
        ),
      ],
    ),
  );
}

class _HeaderStatus extends StatelessWidget {
  const _HeaderStatus({required this.question});
  final BankQuestionDetail question;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: question.status == 'siap'
          ? NusaColors.accent
          : Colors.white.withValues(alpha: 0.14),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      question.statusLabel,
      style: TextStyle(
        color: question.status == 'siap'
            ? NusaColors.primaryDark
            : Colors.white,
        fontSize: 9.5,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _HeaderFact extends StatelessWidget {
  const _HeaderFact({required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, color: Colors.white70, size: 15),
      const SizedBox(width: 5),
      Text(text, style: const TextStyle(color: Colors.white70, fontSize: 10.5)),
    ],
  );
}

class _ContentCard extends StatelessWidget {
  const _ContentCard({required this.question});
  final BankQuestionDetail question;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _CardTitle(icon: Icons.article_outlined, title: 'Isi Soal'),
          if (_hasText(question.stimulus)) ...[
            const SizedBox(height: 13),
            const _Label('Stimulus'),
            const SizedBox(height: 5),
            Text(question.stimulus!, style: const TextStyle(height: 1.5)),
          ],
          if (_hasMedia(question.stimulusMedia)) ...[
            const SizedBox(height: 13),
            _BankMedia(media: question.stimulusMedia),
          ],
          if (_hasMedia(question.media)) ...[
            const SizedBox(height: 13),
            _BankMedia(media: question.media),
          ],
          const SizedBox(height: 14),
          const _Label('Pertanyaan'),
          const SizedBox(height: 5),
          SelectableText(
            question.question,
            style: const TextStyle(fontSize: 14, height: 1.55),
          ),
          const Divider(height: 26),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _MetaChip(question.categoryLabel),
              _MetaChip(question.difficultyLabel),
              if (_hasText(question.topic)) _MetaChip(question.topic!),
              if (_hasText(question.academicYear?.name))
                _MetaChip(question.academicYear!.name),
            ],
          ),
          if (_hasText(question.learningObjective)) ...[
            const SizedBox(height: 14),
            const _Label('Tujuan pembelajaran'),
            const SizedBox(height: 5),
            Text(
              question.learningObjective!,
              style: const TextStyle(height: 1.45),
            ),
          ],
        ],
      ),
    ),
  );
}

class _AnswerCard extends StatelessWidget {
  const _AnswerCard({required this.question});
  final BankQuestionDetail question;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _CardTitle(
            icon: Icons.task_alt_rounded,
            title: 'Jawaban & Kunci',
          ),
          const SizedBox(height: 12),
          if (question.answer.options.isNotEmpty)
            ...question.answer.options.map(
              (item) => _AnswerRow(
                leading: item.code,
                text: item.text,
                correct: item.correct,
                media: item.media,
              ),
            )
          else if (question.answer.statements.isNotEmpty)
            ...question.answer.statements.map(
              (item) => _AnswerRow(
                leading: '${item.number}',
                text: item.text,
                correct: item.answer,
                media: item.media,
                answerLabel: item.answer ? 'Benar' : 'Salah',
              ),
            )
          else if (question.answer.pairs.isNotEmpty)
            ...question.answer.pairs.map((item) => _PairRow(pair: item))
          else ...[
            const _Label('Kunci jawaban'),
            const SizedBox(height: 5),
            Text(
              _hasText(question.answer.textKey)
                  ? question.answer.textKey!
                  : 'Diperiksa manual',
              style: const TextStyle(height: 1.45),
            ),
          ],
          if (_hasText(question.answer.rubric)) ...[
            const Divider(height: 24),
            const _Label('Rubrik pemeriksaan'),
            const SizedBox(height: 5),
            Text(question.answer.rubric!, style: const TextStyle(height: 1.45)),
          ],
        ],
      ),
    ),
  );
}

class _AnswerRow extends StatelessWidget {
  const _AnswerRow({
    required this.leading,
    required this.text,
    required this.correct,
    this.answerLabel,
    this.media = const QuestionMedia(),
  });
  final String leading;
  final String text;
  final bool correct;
  final String? answerLabel;
  final QuestionMedia media;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 8),
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: correct ? NusaColors.successSurface : NusaColors.background,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(
        color: correct
            ? NusaColors.success.withValues(alpha: 0.35)
            : NusaColors.outline,
      ),
    ),
    child: Row(
      children: [
        CircleAvatar(
          radius: 15,
          backgroundColor: correct ? NusaColors.success : NusaColors.outline,
          foregroundColor: correct ? Colors.white : NusaColors.textPrimary,
          child: Text(
            leading,
            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(text, style: const TextStyle(fontSize: 12)),
              if (_hasMedia(media)) ...[
                const SizedBox(height: 8),
                _BankMedia(media: media),
              ],
            ],
          ),
        ),
        if (answerLabel != null)
          Text(
            answerLabel!,
            style: TextStyle(
              color: correct ? NusaColors.success : NusaColors.textSecondary,
              fontSize: 10,
              fontWeight: FontWeight.w800,
            ),
          )
        else if (correct)
          const Icon(Icons.check_circle_rounded, color: NusaColors.success),
      ],
    ),
  );
}

class _PairRow extends StatelessWidget {
  const _PairRow({required this.pair});
  final QuestionPair pair;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 8),
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(pair.left, style: const TextStyle(fontSize: 12)),
              if (_hasMedia(pair.leftMedia)) _BankMedia(media: pair.leftMedia),
            ],
          ),
        ),
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 8),
          child: Icon(
            Icons.arrow_forward_rounded,
            size: 17,
            color: NusaColors.primary,
          ),
        ),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                pair.right,
                textAlign: TextAlign.end,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (_hasMedia(pair.rightMedia))
                _BankMedia(media: pair.rightMedia),
            ],
          ),
        ),
      ],
    ),
  );
}

class _BankMedia extends StatelessWidget {
  const _BankMedia({required this.media});
  final QuestionMedia media;
  @override
  Widget build(BuildContext context) => StudentExamQuestionMedia(
    media: StudentExamMedia.fromJson(media.toJson()),
  );
}

bool _hasMedia(QuestionMedia media) =>
    media.image?.url.isNotEmpty == true ||
    media.table?.rows.isNotEmpty == true ||
    media.formula?.latex.isNotEmpty == true;

class _TextCard extends StatelessWidget {
  const _TextCard({
    required this.title,
    required this.icon,
    required this.text,
  });
  final String title;
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _CardTitle(icon: icon, title: title),
          const SizedBox(height: 11),
          Text(text, style: const TextStyle(height: 1.5)),
        ],
      ),
    ),
  );
}

class _CardTitle extends StatelessWidget {
  const _CardTitle({required this.icon, required this.title});
  final IconData icon;
  final String title;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon, size: 20, color: NusaColors.primary),
      const SizedBox(width: 8),
      Expanded(
        child: Text(
          title,
          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
        ),
      ),
    ],
  );
}

class _Label extends StatelessWidget {
  const _Label(this.text);
  final String text;
  @override
  Widget build(BuildContext context) => Text(
    text,
    style: const TextStyle(
      color: NusaColors.textSecondary,
      fontSize: 10,
      fontWeight: FontWeight.w700,
    ),
  );
}

class _MetaChip extends StatelessWidget {
  const _MetaChip(this.label);
  final String label;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: const TextStyle(
        color: NusaColors.primary,
        fontSize: 9.5,
        fontWeight: FontWeight.w700,
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
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.error_outline_rounded, size: 48),
          const SizedBox(height: 12),
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

bool _hasText(String? value) => value?.trim().isNotEmpty == true;
String _number(double value) =>
    value == value.roundToDouble() ? '${value.toInt()}' : '$value';
