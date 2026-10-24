import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/question_bank/application/question_bank_controller.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';
import 'package:nusa/features/student_exam/presentation/student_exam_question_view.dart';

/// Pure presentation sandbox: no participant, autosave, security events or timer.
class QuestionBankPreviewView extends ConsumerStatefulWidget {
  const QuestionBankPreviewView({required this.questionId, super.key});
  final int questionId;
  @override
  ConsumerState<QuestionBankPreviewView> createState() =>
      _QuestionBankPreviewViewState();
}

class _QuestionBankPreviewViewState
    extends ConsumerState<QuestionBankPreviewView> {
  StudentExamQuestion? _question;
  final _controllers = <String, TextEditingController>{};
  TextEditingController _controllerFor(StudentExamQuestion q, String key) =>
      _controllers.putIfAbsent(
        key,
        () => TextEditingController(text: q.answer.values.firstOrNull ?? ''),
      );
  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  void _reset() {
    for (final controller in _controllers.values) {
      controller.clear();
    }
    setState(() => _question = null);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(questionBankPreviewProvider(widget.questionId));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pratinjau Siswa'),
        actions: [
          IconButton(
            tooltip: 'Ulangi Pratinjau',
            onPressed: _reset,
            icon: const Icon(Icons.restart_alt),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stack) => Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    error is AppException
                        ? error.message
                        : 'Pratinjau belum dapat dimuat.',
                    textAlign: TextAlign.center,
                  ),
                  TextButton(
                    onPressed: () => ref.invalidate(
                      questionBankPreviewProvider(widget.questionId),
                    ),
                    child: const Text('Coba Lagi'),
                  ),
                ],
              ),
            ),
          ),
          data: (serverQuestion) {
            final question = _question ?? serverQuestion;
            return Column(
              children: [
                const Padding(
                  padding: EdgeInsets.fromLTRB(16, 8, 16, 0),
                  child: Text(
                    'Tampilan dan masukan sama dengan siswa. Jawaban percobaan tidak disimpan; urutan pilihan ujian dapat berbeda sesuai pengacakan.',
                    style: TextStyle(
                      fontSize: 11,
                      color: NusaColors.textSecondary,
                    ),
                  ),
                ),
                Expanded(
                  child: StudentExamQuestionView(
                    question: question,
                    statusIndicator: const Text(
                      'Pratinjau',
                      style: TextStyle(fontSize: 10, color: NusaColors.primary),
                    ),
                    controllerFor: _controllerFor,
                    uploadingFile: false,
                    onAnswerChanged: (answer) => setState(
                      () => _question = question.copyWith(answer: answer),
                    ),
                    onDoubtChanged: (value) => setState(
                      () => _question = question.copyWith(doubtful: value),
                    ),
                    onUploadFile: () => ScaffoldMessenger.of(context)
                        .showSnackBar(
                          const SnackBar(
                            content: Text(
                              'Pratinjau saja. Tidak membuka atau mengunggah berkas jawaban.',
                            ),
                          ),
                        ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
