import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/question_bank/application/question_bank_controller.dart';
import 'package:nusa/features/question_bank/domain/question_bank.dart';

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
          if (question.media.image case final image?) ...[
            const SizedBox(height: 13),
            ClipRRect(
              borderRadius: BorderRadius.circular(13),
              child: Image.network(
                image.url,
                width: double.infinity,
                fit: BoxFit.contain,
                errorBuilder: (context, error, stackTrace) => Container(
                  height: 120,
                  color: NusaColors.surfaceBlue,
                  alignment: Alignment.center,
                  child: const Text('Gambar tidak dapat dimuat'),
                ),
              ),
            ),
            if (_hasText(image.caption)) ...[
              const SizedBox(height: 5),
              Text(
                image.caption!,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10,
                ),
              ),
            ],
          ],
          if (question.media.table case final table?) ...[
            const SizedBox(height: 13),
            if (_hasText(table.title)) ...[
              Text(
                table.title!,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
            ],
            _QuestionTableView(table: table),
          ],
          if (question.media.formula case final formula?) ...[
            const SizedBox(height: 13),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: NusaColors.surfaceBlue,
                borderRadius: BorderRadius.circular(12),
              ),
              child: SelectableText(
                formula.latex,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: NusaColors.primary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
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
              ),
            )
          else if (question.answer.statements.isNotEmpty)
            ...question.answer.statements.map(
              (item) => _AnswerRow(
                leading: '${item.number}',
                text: item.text,
                correct: item.answer,
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
  });
  final String leading;
  final String text;
  final bool correct;
  final String? answerLabel;

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
        Expanded(child: Text(text, style: const TextStyle(fontSize: 12))),
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
        Expanded(child: Text(pair.left, style: const TextStyle(fontSize: 12))),
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 8),
          child: Icon(
            Icons.arrow_forward_rounded,
            size: 17,
            color: NusaColors.primary,
          ),
        ),
        Expanded(
          child: Text(
            pair.right,
            textAlign: TextAlign.end,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

class _QuestionTableView extends StatelessWidget {
  const _QuestionTableView({required this.table});
  final QuestionTable table;

  @override
  Widget build(BuildContext context) {
    if (table.rows.isEmpty) return const SizedBox.shrink();
    final columns = table.rows
        .map((row) => row.length)
        .fold<int>(0, (a, b) => a > b ? a : b);
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Table(
        defaultColumnWidth: const IntrinsicColumnWidth(),
        border: TableBorder.all(color: NusaColors.outline),
        children: [
          for (var rowIndex = 0; rowIndex < table.rows.length; rowIndex++)
            TableRow(
              decoration: rowIndex == 0
                  ? const BoxDecoration(color: NusaColors.surfaceBlue)
                  : null,
              children: [
                for (var column = 0; column < columns; column++)
                  Padding(
                    padding: const EdgeInsets.all(9),
                    child: Text(
                      column < table.rows[rowIndex].length
                          ? table.rows[rowIndex][column]
                          : '',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: rowIndex == 0
                            ? FontWeight.w800
                            : FontWeight.normal,
                      ),
                    ),
                  ),
              ],
            ),
        ],
      ),
    );
  }
}

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
      Text(
        title,
        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
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
