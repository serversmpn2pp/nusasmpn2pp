import 'package:flutter/material.dart';
import 'package:flutter_math_fork/flutter_math.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';
import 'package:nusa/features/student_exam/presentation/exam_question_image.dart';

class StudentExamQuestionView extends StatelessWidget {
  const StudentExamQuestionView({
    required this.question,
    required this.statusIndicator,
    required this.controllerFor,
    required this.onAnswerChanged,
    required this.onDoubtChanged,
    required this.uploadingFile,
    required this.onUploadFile,
    super.key,
  });

  final StudentExamQuestion question;
  final Widget statusIndicator;
  final TextEditingController Function(StudentExamQuestion, String)
  controllerFor;
  final ValueChanged<Map<String, String>> onAnswerChanged;
  final ValueChanged<bool> onDoubtChanged;
  final bool uploadingFile;
  final VoidCallback onUploadFile;

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    key: Key('student-exam-question-${question.id}'),
    keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
    child: Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: NusaColors.primary,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    '${question.number}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    question.typeLabel,
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                statusIndicator,
              ],
            ),
            if (question.stimulus?.trim().isNotEmpty == true ||
                hasStudentExamMedia(question.stimulusMedia)) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(13),
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (question.stimulus?.trim().isNotEmpty == true)
                      Text(question.stimulus!),
                    if (hasStudentExamMedia(question.stimulusMedia)) ...[
                      if (question.stimulus?.trim().isNotEmpty == true)
                        const SizedBox(height: 10),
                      StudentExamQuestionMedia(media: question.stimulusMedia),
                    ],
                  ],
                ),
              ),
            ],
            if (hasStudentExamMedia(question.media)) ...[
              const SizedBox(height: 14),
              StudentExamQuestionMedia(media: question.media),
            ],
            const SizedBox(height: 18),
            Text(
              question.question,
              style: const TextStyle(
                fontSize: 16,
                height: 1.45,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 16),
            _answerInput(),
            const SizedBox(height: 12),
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              value: question.doubtful,
              activeTrackColor: NusaColors.accent,
              title: const Text(
                'Tandai ragu-ragu',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              subtitle: const Text('Kembali periksa sebelum dikumpulkan.'),
              onChanged: onDoubtChanged,
            ),
          ],
        ),
      ),
    ),
  );

  Widget _answerInput() => switch (question.type) {
    'pilihan_ganda' => _choiceOptions(multiple: false),
    'pilihan_ganda_kompleks' => _choiceOptions(multiple: true),
    'benar_salah' => _trueFalse(),
    'menjodohkan' => _matching(),
    'isian_singkat' => _textAnswer(lines: 1),
    'numerik' => _textAnswer(lines: 1, numeric: true),
    'upload_file' => _fileUpload(),
    _ => _textAnswer(lines: 6),
  };

  Widget _fileUpload() {
    final fileName = question.answer['berkas'];
    final uploaded = fileName?.trim().isNotEmpty == true;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: NusaColors.surfaceBlue,
        border: Border.all(color: NusaColors.outline),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                uploaded
                    ? Icons.insert_drive_file_rounded
                    : Icons.upload_file_rounded,
                color: NusaColors.primary,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      uploaded ? fileName! : 'Belum ada berkas jawaban',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const Text(
                      'PDF, gambar, atau dokumen Office · Maksimal 10 MB',
                      style: TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          FilledButton.icon(
            key: Key('student-exam-upload-${question.id}'),
            onPressed: uploadingFile ? null : onUploadFile,
            icon: uploadingFile
                ? const SizedBox.square(
                    dimension: 17,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.folder_open_rounded),
            label: Text(
              uploadingFile
                  ? 'Mengunggah...'
                  : uploaded
                  ? 'Ganti Berkas'
                  : 'Pilih dan Unggah Berkas',
            ),
          ),
        ],
      ),
    );
  }

  Widget _choiceOptions({required bool multiple}) {
    final selected = question.answer.values.toSet();
    return Column(
      children: question.options
          .map((option) {
            final checked = selected.contains(option.code);
            return Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: InkWell(
                onTap: () {
                  final next = <String>{...selected};
                  if (multiple) {
                    checked ? next.remove(option.code) : next.add(option.code);
                  } else {
                    next
                      ..clear()
                      ..add(option.code);
                  }
                  final sorted = next.toList()..sort();
                  onAnswerChanged({
                    for (var index = 0; index < sorted.length; index++)
                      '$index': sorted[index],
                  });
                },
                borderRadius: BorderRadius.circular(14),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 160),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: checked
                        ? NusaColors.surfaceBlue
                        : NusaColors.surface,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: checked ? NusaColors.primary : NusaColors.outline,
                      width: checked ? 1.5 : 1,
                    ),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 28,
                        height: 28,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: checked
                              ? NusaColors.primary
                              : NusaColors.background,
                          shape: multiple
                              ? BoxShape.rectangle
                              : BoxShape.circle,
                          borderRadius: multiple
                              ? BorderRadius.circular(7)
                              : null,
                          border: checked
                              ? null
                              : Border.all(color: NusaColors.outline),
                        ),
                        child: checked
                            ? Icon(
                                multiple ? Icons.check_rounded : Icons.circle,
                                color: Colors.white,
                                size: multiple ? 19 : 10,
                              )
                            : Text(
                                option.label,
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                      ),
                      const SizedBox(width: 11),
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text(option.text),
                              if (hasStudentExamMedia(option.media)) ...[
                                const SizedBox(height: 8),
                                StudentExamQuestionMedia(media: option.media),
                              ],
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          })
          .toList(growable: false),
    );
  }

  Widget _trueFalse() => Column(
    children: question.statements
        .map((statement) {
          final current = question.answer[statement.number];
          return Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              border: Border.all(color: NusaColors.outline),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${statement.number}. ${statement.text}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                if (hasStudentExamMedia(statement.media)) ...[
                  const SizedBox(height: 8),
                  StudentExamQuestionMedia(media: statement.media),
                ],
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  children: ['benar', 'salah']
                      .map((value) {
                        return ChoiceChip(
                          label: Text(value == 'benar' ? 'Benar' : 'Salah'),
                          selected: current == value,
                          onSelected: (_) {
                            final answer = {...question.answer};
                            answer[statement.number] = value;
                            onAnswerChanged(answer);
                          },
                        );
                      })
                      .toList(growable: false),
                ),
              ],
            ),
          );
        })
        .toList(growable: false),
  );

  Widget _matching() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          margin: const EdgeInsets.only(bottom: 14),
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: const Color(0xFFE8F0F8),
            border: Border.all(color: const Color(0xFFB9CDE2)),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Pilihan pasangan',
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              for (final option in question.options)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        '${option.label}. ${option.text}',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      if (hasStudentExamMedia(option.media)) ...[
                        const SizedBox(height: 6),
                        StudentExamQuestionMedia(media: option.media),
                      ],
                    ],
                  ),
                ),
            ],
          ),
        ),
        for (final pair in question.pairs)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  '${pair.number}. ${pair.left}',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                if (hasStudentExamMedia(pair.media)) ...[
                  const SizedBox(height: 8),
                  StudentExamQuestionMedia(media: pair.media),
                ],
                const SizedBox(height: 8),
                DropdownButtonFormField<String>(
                  key: Key('student-exam-match-${question.id}-${pair.number}'),
                  initialValue:
                      question.options.any(
                        (option) => option.text == question.answer[pair.number],
                      )
                      ? question.answer[pair.number]
                      : null,
                  isExpanded: true,
                  items: question.options
                      .map(
                        (option) => DropdownMenuItem<String>(
                          value: option.text,
                          child: Text(
                            '${option.label}. ${option.text}',
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      )
                      .toList(growable: false),
                  onChanged: (value) {
                    final answer = {...question.answer};
                    if (value == null || value.isEmpty) {
                      answer.remove(pair.number);
                    } else {
                      answer[pair.number] = value;
                    }
                    onAnswerChanged(answer);
                  },
                  decoration: const InputDecoration(hintText: 'Pilih pasangan'),
                ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _textAnswer({required int lines, bool numeric = false}) => TextField(
    key: Key('student-exam-answer-${question.id}'),
    controller: controllerFor(question, 'value'),
    maxLength: 10000,
    enableInteractiveSelection: false,
    keyboardType: numeric
        ? const TextInputType.numberWithOptions(decimal: true, signed: true)
        : TextInputType.multiline,
    minLines: lines,
    maxLines: lines == 1 ? 1 : null,
    onChanged: (value) => onAnswerChanged({'0': value}),
    decoration: InputDecoration(
      counterText: '',
      labelText: numeric ? 'Jawaban angka' : 'Jawaban',
      hintText: lines == 1 ? 'Tulis jawaban' : 'Tulis jawaban di sini',
      alignLabelWithHint: lines > 1,
    ),
  );
}

class StudentExamQuestionMedia extends StatelessWidget {
  const StudentExamQuestionMedia({required this.media, super.key});
  final StudentExamMedia media;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (media.image case final image? when image.url.isNotEmpty) ...[
        ExamQuestionImage(image: image),
        if (image.caption?.isNotEmpty == true)
          Padding(
            padding: const EdgeInsets.only(top: 5),
            child: Text(
              image.caption!,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10,
              ),
            ),
          ),
      ],
      if (media.table case final table? when table.rows.isNotEmpty) ...[
        if (table.title?.isNotEmpty == true)
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Text(
              table.title!,
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Table(
            defaultColumnWidth: const IntrinsicColumnWidth(),
            border: TableBorder.all(color: NusaColors.outline),
            children: table.rows.indexed
                .map(
                  (entry) => TableRow(
                    decoration: entry.$1 == 0
                        ? const BoxDecoration(color: NusaColors.surfaceBlue)
                        : null,
                    children: List.generate(
                      table.rows.fold<int>(
                        0,
                        (max, row) => row.length > max ? row.length : max,
                      ),
                      (index) => Padding(
                        padding: const EdgeInsets.all(8),
                        child: Text(
                          index < entry.$2.length ? entry.$2[index] : '',
                          style: TextStyle(
                            fontWeight: entry.$1 == 0
                                ? FontWeight.w800
                                : FontWeight.normal,
                          ),
                        ),
                      ),
                    ),
                  ),
                )
                .toList(growable: false),
          ),
        ),
      ],
      if (media.formula case final formula? when formula.latex.isNotEmpty) ...[
        Container(
          key: const Key('student-exam-formula'),
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: NusaColors.surfaceBlue,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Center(
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Math.tex(
                formula.latex,
                mathStyle: MathStyle.display,
                textStyle: const TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 18,
                ),
                onErrorFallback: (error) => const Text(
                  'Rumus belum dapat ditampilkan.',
                  style: TextStyle(color: NusaColors.danger),
                ),
              ),
            ),
          ),
        ),
        if (formula.caption?.isNotEmpty == true)
          Padding(
            padding: const EdgeInsets.only(top: 5),
            child: Text(
              formula.caption!,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10,
              ),
            ),
          ),
      ],
    ],
  );
}

bool hasStudentExamMedia(StudentExamMedia media) =>
    media.image?.url.isNotEmpty == true ||
    media.table?.rows.isNotEmpty == true ||
    media.formula?.latex.isNotEmpty == true;
