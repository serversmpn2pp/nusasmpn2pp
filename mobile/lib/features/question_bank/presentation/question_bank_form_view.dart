import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/question_bank/application/question_bank_controller.dart';
import 'package:nusa/features/question_bank/domain/question_bank.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class QuestionBankFormView extends ConsumerStatefulWidget {
  const QuestionBankFormView({this.questionId, super.key});

  final int? questionId;

  @override
  ConsumerState<QuestionBankFormView> createState() =>
      _QuestionBankFormViewState();
}

class _QuestionBankFormViewState extends ConsumerState<QuestionBankFormView> {
  static const _letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

  final _formKey = GlobalKey<FormState>();
  final _topic = TextEditingController();
  final _material = TextEditingController();
  final _learningObjective = TextEditingController();
  final _stimulus = TextEditingController();
  final _question = TextEditingController();
  final _maximumScore = TextEditingController(text: '1');
  final _explanation = TextEditingController();
  final _textKey = TextEditingController();
  final _rubric = TextEditingController();
  final _imageAlt = TextEditingController();
  final _imageCaption = TextEditingController();
  final _tableTitle = TextEditingController();
  final _tableText = TextEditingController();
  final _formula = TextEditingController();
  final _formulaCaption = TextEditingController();

  final List<TextEditingController> _options = [];
  final List<TextEditingController> _statements = [];
  final List<bool> _statementAnswers = [];
  final List<TextEditingController> _pairLeft = [];
  final List<TextEditingController> _pairRight = [];

  String? _contextKey;
  String _type = 'pilihan_ganda';
  String _difficulty = 'sedang';
  String _category = 'umum';
  String? _singleAnswer;
  final Set<String> _multipleAnswers = {};
  int? _academicYearId;
  QuestionPickedImage? _pickedImage;
  bool _removeExistingImage = false;
  bool _initialized = false;
  bool _saving = false;

  bool get _editing => widget.questionId != null;

  @override
  void initState() {
    super.initState();
    _replaceControllers(_options, const ['', '', '', ''], minimum: 4);
    _replaceControllers(_statements, const ['', ''], minimum: 2);
    _statementAnswers.addAll([true, false]);
    _replaceControllers(_pairLeft, const ['', ''], minimum: 2);
    _replaceControllers(_pairRight, const ['', ''], minimum: 2);
  }

  @override
  void dispose() {
    for (final controller in [
      _topic,
      _material,
      _learningObjective,
      _stimulus,
      _question,
      _maximumScore,
      _explanation,
      _textKey,
      _rubric,
      _imageAlt,
      _imageCaption,
      _tableTitle,
      _tableText,
      _formula,
      _formulaCaption,
      ..._options,
      ..._statements,
      ..._pairLeft,
      ..._pairRight,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bankState = ref.watch(questionBankControllerProvider);
    final bank = bankState.value;
    final detailState = _editing
        ? ref.watch(questionBankDetailProvider(widget.questionId!))
        : null;

    final editDetail = detailState?.value;
    if (_editing && editDetail != null) {
      _initializeAfterBuild(editDetail);
    }

    final loading = bank == null || (_editing && detailState?.value == null);
    final error = bankState.hasError
        ? bankState.error
        : (_editing && detailState?.hasError == true
              ? detailState?.error
              : null);

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(title: Text(_editing ? 'Ubah Soal' : 'Tambah Soal')),
      body: SafeArea(
        top: false,
        child: error != null && loading
            ? _FormError(
                message: _message(error),
                onRetry: () {
                  ref.invalidate(questionBankControllerProvider);
                  if (_editing) {
                    ref.invalidate(
                      questionBankDetailProvider(widget.questionId!),
                    );
                  }
                },
              )
            : loading
            ? const Center(child: CircularProgressIndicator())
            : bank.access.canManage
            ? Form(
                key: _formKey,
                child: Column(
                  children: [
                    Expanded(
                      child: ListView(
                        keyboardDismissBehavior:
                            ScrollViewKeyboardDismissBehavior.onDrag,
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 22),
                        children: [
                          _IntroCard(editing: _editing),
                          const SizedBox(height: 11),
                          _buildIdentity(bank.references),
                          const SizedBox(height: 11),
                          _buildContent(),
                          const SizedBox(height: 11),
                          _buildAnswer(),
                          const SizedBox(height: 11),
                          _buildMedia(detailState?.value),
                          const SizedBox(height: 11),
                          _buildExplanation(),
                        ],
                      ),
                    ),
                    _SaveBar(saving: _saving, onSave: _save),
                  ],
                ),
              )
            : const _FormError(
                message: 'Akun ini tidak memiliki izin mengelola Bank Soal.',
              ),
      ),
    );
  }

  Widget _buildIdentity(QuestionBankReferences references) => _SectionCard(
    icon: Icons.tune_rounded,
    title: 'Identitas Soal',
    subtitle: 'Tentukan bank, bentuk, dan bobot soal.',
    children: [
      NusaDropdownField<String>(
        fieldKey: const Key('question-form-context'),
        value: _contextKey,
        decoration: const InputDecoration(
          labelText: 'Mata pelajaran dan tingkat *',
          prefixIcon: Icon(Icons.menu_book_rounded),
        ),
        options: [
          for (final context in references.contexts)
            NusaDropdownOption(value: context.key, label: context.label),
        ],
        onChanged: _saving
            ? null
            : (value) => setState(() => _contextKey = value),
      ),
      const SizedBox(height: 10),
      NusaDropdownField<String>(
        fieldKey: const Key('question-form-type'),
        value: _type,
        decoration: const InputDecoration(
          labelText: 'Jenis soal *',
          prefixIcon: Icon(Icons.quiz_outlined),
        ),
        options: [
          for (final item in references.types)
            NusaDropdownOption(value: item.code, label: item.label),
        ],
        onChanged: _saving
            ? null
            : (value) {
                if (value != null) setState(() => _type = value);
              },
      ),
      const SizedBox(height: 10),
      LayoutBuilder(
        builder: (context, constraints) {
          final narrow = constraints.maxWidth < 330;
          final difficulty = NusaDropdownField<String>(
            fieldKey: const Key('question-form-difficulty'),
            value: _difficulty,
            decoration: const InputDecoration(labelText: 'Kesulitan'),
            options: [
              for (final item in references.difficulties)
                NusaDropdownOption(value: item.code, label: item.label),
            ],
            onChanged: _saving
                ? null
                : (value) {
                    if (value != null) {
                      setState(() => _difficulty = value);
                    }
                  },
          );
          final category = NusaDropdownField<String>(
            fieldKey: const Key('question-form-category'),
            value: _category,
            decoration: const InputDecoration(labelText: 'Kategori'),
            options: [
              for (final item in references.categories)
                NusaDropdownOption(value: item.code, label: item.label),
            ],
            onChanged: _saving
                ? null
                : (value) {
                    if (value != null) setState(() => _category = value);
                  },
          );
          if (narrow) {
            return Column(
              children: [difficulty, const SizedBox(height: 10), category],
            );
          }
          return Row(
            children: [
              Expanded(child: difficulty),
              const SizedBox(width: 9),
              Expanded(child: category),
            ],
          );
        },
      ),
      const SizedBox(height: 10),
      _TextInput(
        fieldKey: const Key('question-form-score'),
        controller: _maximumScore,
        label: 'Skor maksimal *',
        icon: Icons.stars_rounded,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        validator: (value) {
          final number = double.tryParse((value ?? '').replaceAll(',', '.'));
          if (number == null || number < 0.25 || number > 100) {
            return 'Isi skor antara 0,25 dan 100.';
          }
          return null;
        },
      ),
    ],
  );

  Widget _buildContent() => _SectionCard(
    icon: Icons.article_outlined,
    title: 'Isi Soal',
    subtitle: 'Kolom bertanda * wajib diisi.',
    children: [
      _TextInput(
        controller: _topic,
        label: 'Topik',
        icon: Icons.topic_outlined,
      ),
      const SizedBox(height: 10),
      _TextInput(
        controller: _material,
        label: 'Materi',
        icon: Icons.book_outlined,
      ),
      const SizedBox(height: 10),
      _TextInput(
        controller: _learningObjective,
        label: 'Tujuan pembelajaran',
        icon: Icons.flag_outlined,
        minLines: 2,
        maxLines: 4,
      ),
      const SizedBox(height: 10),
      _TextInput(
        controller: _stimulus,
        label: 'Stimulus',
        icon: Icons.auto_stories_outlined,
        minLines: 2,
        maxLines: 6,
      ),
      const SizedBox(height: 10),
      _TextInput(
        fieldKey: const Key('question-form-question'),
        controller: _question,
        label: 'Pertanyaan *',
        icon: Icons.help_outline_rounded,
        minLines: 3,
        maxLines: 8,
        validator: (value) =>
            (value ?? '').trim().isEmpty ? 'Pertanyaan wajib diisi.' : null,
      ),
    ],
  );

  Widget _buildAnswer() => _SectionCard(
    icon: Icons.task_alt_rounded,
    title: 'Jawaban & Kunci',
    subtitle: _answerHint,
    children: switch (_type) {
      'pilihan_ganda' => [_buildOptions(complex: false)],
      'pilihan_ganda_kompleks' => [_buildOptions(complex: true)],
      'benar_salah' => [_buildStatements()],
      'menjodohkan' => [_buildPairs()],
      'isian_singkat' => [
        _TextInput(
          fieldKey: const Key('question-form-text-key'),
          controller: _textKey,
          label: 'Kunci jawaban *',
          icon: Icons.key_rounded,
          minLines: 2,
          maxLines: 4,
        ),
      ],
      'numerik' => [
        _TextInput(
          fieldKey: const Key('question-form-text-key'),
          controller: _textKey,
          label: 'Nilai jawaban *',
          icon: Icons.calculate_outlined,
          keyboardType: const TextInputType.numberWithOptions(
            decimal: true,
            signed: true,
          ),
        ),
      ],
      'uraian' || 'upload_file' => [
        _TextInput(
          controller: _textKey,
          label: 'Contoh jawaban (opsional)',
          icon: Icons.key_rounded,
          minLines: 2,
          maxLines: 5,
        ),
        const SizedBox(height: 10),
        _TextInput(
          controller: _rubric,
          label: 'Rubrik pemeriksaan',
          icon: Icons.rule_rounded,
          minLines: 3,
          maxLines: 7,
        ),
      ],
      _ => const [Text('Jenis jawaban belum didukung.')],
    },
  );

  Widget _buildOptions({required bool complex}) => Column(
    children: [
      for (var index = 0; index < _options.length; index++) ...[
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: complex
                  ? Checkbox(
                      key: Key('question-option-check-$index'),
                      value: _multipleAnswers.contains(_letters[index]),
                      onChanged: _saving
                          ? null
                          : (checked) => setState(() {
                              if (checked == true) {
                                _multipleAnswers.add(_letters[index]);
                              } else {
                                _multipleAnswers.remove(_letters[index]);
                              }
                            }),
                    )
                  : ChoiceChip(
                      key: Key('question-option-choice-$index'),
                      label: Text(_letters[index]),
                      selected: _singleAnswer == _letters[index],
                      onSelected: _saving
                          ? null
                          : (selected) => setState(
                              () => _singleAnswer = selected
                                  ? _letters[index]
                                  : null,
                            ),
                    ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _TextInput(
                fieldKey: Key('question-option-$index'),
                controller: _options[index],
                label: 'Opsi ${_letters[index]}',
                minLines: 1,
                maxLines: 3,
              ),
            ),
            if (_options.length > 2)
              IconButton(
                tooltip: 'Hapus opsi',
                onPressed: _saving ? null : () => _removeOption(index),
                icon: const Icon(Icons.close_rounded),
              ),
          ],
        ),
        if (index < _options.length - 1) const SizedBox(height: 8),
      ],
      if (_options.length < 8) ...[
        const SizedBox(height: 8),
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: _saving
                ? null
                : () => setState(() => _options.add(TextEditingController())),
            icon: const Icon(Icons.add_rounded),
            label: const Text('Tambah opsi'),
          ),
        ),
      ],
    ],
  );

  Widget _buildStatements() => Column(
    children: [
      for (var index = 0; index < _statements.length; index++) ...[
        _TextInput(
          fieldKey: Key('question-statement-$index'),
          controller: _statements[index],
          label: 'Pernyataan ${index + 1}',
          minLines: 1,
          maxLines: 3,
          suffix: _statements.length > 1
              ? IconButton(
                  tooltip: 'Hapus pernyataan',
                  onPressed: _saving ? null : () => _removeStatement(index),
                  icon: const Icon(Icons.close_rounded),
                )
              : null,
        ),
        const SizedBox(height: 6),
        Align(
          alignment: Alignment.centerLeft,
          child: SegmentedButton<bool>(
            segments: const [
              ButtonSegment(value: true, label: Text('Benar')),
              ButtonSegment(value: false, label: Text('Salah')),
            ],
            selected: {_statementAnswers[index]},
            onSelectionChanged: _saving
                ? null
                : (value) =>
                      setState(() => _statementAnswers[index] = value.first),
          ),
        ),
        if (index < _statements.length - 1) const Divider(height: 24),
      ],
      if (_statements.length < 10) ...[
        const SizedBox(height: 8),
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: _saving
                ? null
                : () => setState(() {
                    _statements.add(TextEditingController());
                    _statementAnswers.add(true);
                  }),
            icon: const Icon(Icons.add_rounded),
            label: const Text('Tambah pernyataan'),
          ),
        ),
      ],
    ],
  );

  Widget _buildPairs() => Column(
    children: [
      for (var index = 0; index < _pairLeft.length; index++) ...[
        Row(
          children: [
            Expanded(
              child: _TextInput(
                fieldKey: Key('question-pair-left-$index'),
                controller: _pairLeft[index],
                label: 'Bagian kiri ${index + 1}',
                maxLines: 3,
              ),
            ),
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 6),
              child: Icon(Icons.compare_arrows_rounded),
            ),
            Expanded(
              child: _TextInput(
                fieldKey: Key('question-pair-right-$index'),
                controller: _pairRight[index],
                label: 'Pasangan',
                maxLines: 3,
              ),
            ),
            if (_pairLeft.length > 1)
              IconButton(
                tooltip: 'Hapus pasangan',
                onPressed: _saving ? null : () => _removePair(index),
                icon: const Icon(Icons.close_rounded),
              ),
          ],
        ),
        if (index < _pairLeft.length - 1) const SizedBox(height: 9),
      ],
      if (_pairLeft.length < 10) ...[
        const SizedBox(height: 8),
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: _saving
                ? null
                : () => setState(() {
                    _pairLeft.add(TextEditingController());
                    _pairRight.add(TextEditingController());
                  }),
            icon: const Icon(Icons.add_rounded),
            label: const Text('Tambah pasangan'),
          ),
        ),
      ],
    ],
  );

  Widget _buildMedia(BankQuestionDetail? detail) => _SectionCard(
    icon: Icons.perm_media_outlined,
    title: 'Media Pendukung',
    subtitle: 'Opsional: gambar, tabel, dan rumus LaTeX.',
    children: [
      _MediaHeading(icon: Icons.image_outlined, label: 'Gambar soal'),
      const SizedBox(height: 8),
      if (_pickedImage case final picked?)
        _ImagePreview(bytes: picked.bytes)
      else if (!_removeExistingImage && detail?.media.image != null)
        _ImagePreview(url: detail!.media.image!.url),
      Row(
        children: [
          Expanded(
            child: OutlinedButton.icon(
              key: const Key('question-form-pick-image'),
              onPressed: _saving ? null : _pickImage,
              icon: const Icon(Icons.photo_library_outlined),
              label: Text(
                _pickedImage == null && detail?.media.image == null
                    ? 'Pilih Gambar'
                    : 'Ganti Gambar',
              ),
            ),
          ),
          if (_pickedImage != null ||
              (!_removeExistingImage && detail?.media.image != null)) ...[
            const SizedBox(width: 8),
            IconButton.outlined(
              tooltip: 'Hapus gambar',
              onPressed: _saving
                  ? null
                  : () => setState(() {
                      _pickedImage = null;
                      _removeExistingImage = true;
                    }),
              icon: const Icon(Icons.delete_outline_rounded),
            ),
          ],
        ],
      ),
      const SizedBox(height: 8),
      _TextInput(controller: _imageAlt, label: 'Teks alternatif gambar'),
      const SizedBox(height: 8),
      _TextInput(controller: _imageCaption, label: 'Keterangan gambar'),
      const Divider(height: 28),
      _MediaHeading(icon: Icons.table_chart_outlined, label: 'Tabel'),
      const SizedBox(height: 8),
      _TextInput(controller: _tableTitle, label: 'Judul tabel'),
      const SizedBox(height: 8),
      _TextInput(
        fieldKey: const Key('question-form-table'),
        controller: _tableText,
        label: 'Isi tabel',
        hint: 'Nama | Nilai\nFrekuensi | 2 Hz',
        minLines: 3,
        maxLines: 10,
      ),
      const SizedBox(height: 5),
      const Text(
        'Pisahkan kolom dengan tanda | dan baris dengan Enter (maks. 10 × 8).',
        style: TextStyle(color: NusaColors.textSecondary, fontSize: 10),
      ),
      const Divider(height: 28),
      _MediaHeading(icon: Icons.functions_rounded, label: 'Rumus'),
      const SizedBox(height: 8),
      _TextInput(
        fieldKey: const Key('question-form-formula'),
        controller: _formula,
        label: 'Rumus LaTeX',
        hint: r'f = \frac{n}{t}',
        minLines: 2,
        maxLines: 5,
      ),
      const SizedBox(height: 8),
      _TextInput(controller: _formulaCaption, label: 'Keterangan rumus'),
    ],
  );

  Widget _buildExplanation() => _SectionCard(
    icon: Icons.lightbulb_outline_rounded,
    title: 'Pembahasan',
    subtitle: 'Opsional, dapat dipakai sebagai umpan balik setelah ujian.',
    children: [
      _TextInput(
        controller: _explanation,
        label: 'Pembahasan jawaban',
        minLines: 3,
        maxLines: 8,
      ),
    ],
  );

  String get _answerHint => switch (_type) {
    'pilihan_ganda' => 'Pilih lingkaran pada satu jawaban benar.',
    'pilihan_ganda_kompleks' => 'Centang semua jawaban yang benar.',
    'benar_salah' => 'Tentukan nilai benar atau salah setiap pernyataan.',
    'menjodohkan' => 'Isi pasangan kiri dan kanan secara lengkap.',
    'isian_singkat' => 'Jawaban akan dibandingkan dengan kunci teks.',
    'numerik' => 'Masukkan nilai numerik sebagai kunci.',
    'uraian' => 'Jawaban dinilai manual menggunakan rubrik.',
    'upload_file' => 'Berkas jawaban dinilai manual menggunakan rubrik.',
    _ => 'Lengkapi pengaturan jawaban.',
  };

  void _initializeAfterBuild(BankQuestionDetail detail) {
    if (_initialized) return;
    _initialized = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      setState(() {
        _contextKey = detail.subject == null
            ? null
            : '${detail.subject!.id}-${detail.grade}';
        _type = detail.type;
        _difficulty = detail.difficulty;
        _category = detail.category;
        _academicYearId = detail.academicYear?.id;
        _setText(_topic, detail.topic);
        _setText(_material, detail.material);
        _setText(_learningObjective, detail.learningObjective);
        _setText(_stimulus, detail.stimulus);
        _setText(_question, detail.question);
        _setText(_maximumScore, _number(detail.maximumScore));
        _setText(_explanation, detail.explanation);
        _setText(_textKey, detail.answer.textKey);
        _setText(_rubric, detail.answer.rubric);

        if (detail.answer.options.isNotEmpty) {
          _replaceControllers(
            _options,
            detail.answer.options.map((item) => item.text).toList(),
            minimum: 2,
          );
          _multipleAnswers
            ..clear()
            ..addAll(
              detail.answer.options
                  .where((item) => item.correct)
                  .map((item) => item.code),
            );
          _singleAnswer = detail.answer.options
              .where((item) => item.correct)
              .firstOrNull
              ?.code;
        }
        if (detail.answer.statements.isNotEmpty) {
          _replaceControllers(
            _statements,
            detail.answer.statements.map((item) => item.text).toList(),
            minimum: 1,
          );
          _statementAnswers
            ..clear()
            ..addAll(detail.answer.statements.map((item) => item.answer));
        }
        if (detail.answer.pairs.isNotEmpty) {
          _replaceControllers(
            _pairLeft,
            detail.answer.pairs.map((item) => item.left).toList(),
            minimum: 1,
          );
          _replaceControllers(
            _pairRight,
            detail.answer.pairs.map((item) => item.right).toList(),
            minimum: 1,
          );
        }

        final image = detail.media.image;
        _setText(_imageAlt, image?.alt);
        _setText(_imageCaption, image?.caption);
        final table = detail.media.table;
        _setText(_tableTitle, table?.title);
        _setText(
          _tableText,
          table?.rows.map((row) => row.join(' | ')).join('\n'),
        );
        final formula = detail.media.formula;
        _setText(_formula, formula?.latex);
        _setText(_formulaCaption, formula?.caption);
      });
    });
  }

  void _replaceControllers(
    List<TextEditingController> target,
    Iterable<String> values, {
    required int minimum,
  }) {
    for (final controller in target) {
      controller.dispose();
    }
    target
      ..clear()
      ..addAll(
        values.take(10).map((value) => TextEditingController(text: value)),
      );
    while (target.length < minimum) {
      target.add(TextEditingController());
    }
  }

  void _removeOption(int index) {
    final removedCode = _letters[index];
    _options.removeAt(index).dispose();
    if (_singleAnswer == removedCode) {
      _singleAnswer = null;
    } else if (_singleAnswer case final selected?) {
      final oldIndex = _letters.indexOf(selected);
      if (oldIndex > index) _singleAnswer = _letters[oldIndex - 1];
    }
    _multipleAnswers.remove(removedCode);
    final shifted = _multipleAnswers.map((code) {
      final oldIndex = _letters.indexOf(code);
      return oldIndex > index ? _letters[oldIndex - 1] : code;
    }).toSet();
    _multipleAnswers
      ..clear()
      ..addAll(shifted);
    setState(() {});
  }

  void _removeStatement(int index) {
    setState(() {
      _statements.removeAt(index).dispose();
      _statementAnswers.removeAt(index);
    });
  }

  void _removePair(int index) {
    setState(() {
      _pairLeft.removeAt(index).dispose();
      _pairRight.removeAt(index).dispose();
    });
  }

  Future<void> _pickImage() async {
    final file = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1800,
      imageQuality: 90,
    );
    if (file == null || !mounted) return;
    final bytes = await file.readAsBytes();
    if (!mounted) return;
    if (bytes.length > 5 * 1024 * 1024) {
      _showMessage('Ukuran gambar maksimal 5 MB.');
      return;
    }
    setState(() {
      _pickedImage = QuestionPickedImage(name: file.name, bytes: bytes);
      _removeExistingImage = false;
    });
  }

  Future<void> _save(String action) async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final bank = ref.read(questionBankControllerProvider).value;
    final selectedContext = bank?.references.contexts
        .where((item) => item.key == _contextKey)
        .firstOrNull;
    if (selectedContext == null) {
      _showMessage('Pilih mata pelajaran dan tingkat terlebih dahulu.');
      return;
    }
    final answerError = _validateAnswer();
    if (answerError != null) {
      _showMessage(answerError);
      return;
    }

    setState(() => _saving = true);
    try {
      final payload = <String, dynamic>{
        'tahun_pelajaran_id': _academicYearId,
        'mata_pelajaran_id': selectedContext.subjectId,
        'tingkat': selectedContext.grade,
        'jenis_soal': _type,
        'tingkat_kesulitan': _difficulty,
        'kategori': _category,
        'topik': _nullableText(_topic),
        'materi': _nullableText(_material),
        'tujuan_pembelajaran': _nullableText(_learningObjective),
        'stimulus': _nullableText(_stimulus),
        'pertanyaan': _question.text.trim(),
        'skor_maksimal': double.parse(
          _maximumScore.text.trim().replaceAll(',', '.'),
        ),
        'pembahasan': _nullableText(_explanation),
        'aksi': action,
        'opsi': {
          for (var index = 0; index < _options.length; index++)
            _letters[index]: _options[index].text.trim(),
        },
        'kunci_pg': _singleAnswer,
        'kunci_pgk': _multipleAnswers.toList()..sort(),
        'pernyataan': [
          for (var index = 0; index < _statements.length; index++)
            {
              'teks': _statements[index].text.trim(),
              'jawaban': _statementAnswers[index],
            },
        ],
        'pasangan': [
          for (var index = 0; index < _pairLeft.length; index++)
            {
              'kiri': _pairLeft[index].text.trim(),
              'kanan': _pairRight[index].text.trim(),
            },
        ],
        'kunci_teks': _nullableText(_textKey),
        'rubrik_teks': _nullableText(_rubric),
        'hapus_gambar_soal': _removeExistingImage,
        'gambar_alt': _nullableText(_imageAlt),
        'gambar_keterangan': _nullableText(_imageCaption),
        'tabel': _parseTable(),
        'tabel_judul': _nullableText(_tableTitle),
        'rumus_latex': _formula.text.trim(),
        'rumus_keterangan': _nullableText(_formulaCaption),
      };
      final saved = await ref
          .read(questionBankControllerProvider.notifier)
          .save(
            id: widget.questionId,
            value: QuestionFormValue(payload: payload, image: _pickedImage),
          );
      if (!mounted) return;
      _showMessage(
        action == 'simpan_siap'
            ? 'Soal berhasil disimpan dan siap digunakan.'
            : 'Soal berhasil disimpan sebagai draf.',
      );
      context.go('/bank-soal/${saved.id}');
    } catch (error) {
      if (mounted) _showMessage(_message(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  String? _validateAnswer() {
    switch (_type) {
      case 'pilihan_ganda':
      case 'pilihan_ganda_kompleks':
        final filledCodes = <String>{
          for (var index = 0; index < _options.length; index++)
            if (_options[index].text.trim().isNotEmpty) _letters[index],
        };
        if (filledCodes.length < 2) return 'Isi minimal dua opsi jawaban.';
        if (_type == 'pilihan_ganda' && !filledCodes.contains(_singleAnswer)) {
          return 'Pilih satu kunci jawaban yang sudah diisi.';
        }
        if (_type == 'pilihan_ganda_kompleks' &&
            _multipleAnswers.intersection(filledCodes).isEmpty) {
          return 'Pilih minimal satu jawaban benar yang sudah diisi.';
        }
      case 'benar_salah':
        if (!_statements.any((item) => item.text.trim().isNotEmpty)) {
          return 'Isi minimal satu pernyataan benar-salah.';
        }
      case 'menjodohkan':
        if (!_pairLeft.indexed.any(
          (item) =>
              item.$2.text.trim().isNotEmpty &&
              _pairRight[item.$1].text.trim().isNotEmpty,
        )) {
          return 'Isi minimal satu pasangan kiri dan kanan.';
        }
      case 'isian_singkat':
      case 'numerik':
        if (_textKey.text.trim().isEmpty) {
          return 'Kunci jawaban wajib diisi untuk jenis soal ini.';
        }
    }
    return null;
  }

  List<List<String>> _parseTable() => _tableText.text
      .split('\n')
      .map(
        (line) => line.split('|').take(8).map((cell) => cell.trim()).toList(),
      )
      .where((row) => row.any((cell) => cell.isNotEmpty))
      .take(10)
      .toList();

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _IntroCard extends StatelessWidget {
  const _IntroCard({required this.editing});
  final bool editing;

  @override
  Widget build(BuildContext context) => Container(
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
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(13),
          ),
          child: const Icon(
            Icons.library_add_rounded,
            color: NusaColors.accent,
          ),
        ),
        const SizedBox(width: 11),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                editing ? 'Perbarui soal CBT' : 'Susun soal CBT baru',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 15,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              const Text(
                'Kode soal dibuat otomatis oleh server agar tetap unik.',
                style: TextStyle(color: Colors.white70, fontSize: 10.5),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.children,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, color: NusaColors.primary, size: 21),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          ...children,
        ],
      ),
    ),
  );
}

class _TextInput extends StatelessWidget {
  const _TextInput({
    required this.controller,
    required this.label,
    this.fieldKey,
    this.icon,
    this.hint,
    this.minLines = 1,
    this.maxLines = 1,
    this.keyboardType,
    this.validator,
    this.suffix,
  });

  final Key? fieldKey;
  final TextEditingController controller;
  final String label;
  final IconData? icon;
  final String? hint;
  final int minLines;
  final int maxLines;
  final TextInputType? keyboardType;
  final FormFieldValidator<String>? validator;
  final Widget? suffix;

  @override
  Widget build(BuildContext context) => TextFormField(
    key: fieldKey,
    controller: controller,
    minLines: minLines,
    maxLines: maxLines,
    keyboardType: keyboardType,
    validator: validator,
    textCapitalization: maxLines > 1
        ? TextCapitalization.sentences
        : TextCapitalization.none,
    decoration: InputDecoration(
      labelText: label,
      hintText: hint,
      alignLabelWithHint: maxLines > 1,
      prefixIcon: icon == null ? null : Icon(icon),
      suffixIcon: suffix,
    ),
  );
}

class _MediaHeading extends StatelessWidget {
  const _MediaHeading({required this.icon, required this.label});
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon, size: 18, color: NusaColors.primary),
      const SizedBox(width: 7),
      Text(label, style: const TextStyle(fontWeight: FontWeight.w800)),
    ],
  );
}

class _ImagePreview extends StatelessWidget {
  const _ImagePreview({this.bytes, this.url});
  final Uint8List? bytes;
  final String? url;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 9),
    child: ClipRRect(
      borderRadius: BorderRadius.circular(13),
      child: bytes != null
          ? Image.memory(
              bytes!,
              height: 150,
              width: double.infinity,
              fit: BoxFit.contain,
            )
          : Image.network(
              url!,
              height: 150,
              width: double.infinity,
              fit: BoxFit.contain,
              errorBuilder: (context, error, stackTrace) => Container(
                height: 110,
                color: NusaColors.surfaceBlue,
                alignment: Alignment.center,
                child: const Text('Gambar tidak dapat dimuat'),
              ),
            ),
    ),
  );
}

class _SaveBar extends StatelessWidget {
  const _SaveBar({required this.saving, required this.onSave});
  final bool saving;
  final ValueChanged<String> onSave;

  @override
  Widget build(BuildContext context) => Material(
    elevation: 10,
    color: Colors.white,
    child: SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
        child: Row(
          children: [
            Expanded(
              child: OutlinedButton(
                key: const Key('question-form-save-draft'),
                onPressed: saving ? null : () => onSave('simpan_draf'),
                child: const Text('Simpan Draf'),
              ),
            ),
            const SizedBox(width: 9),
            Expanded(
              child: FilledButton.icon(
                key: const Key('question-form-save-ready'),
                onPressed: saving ? null : () => onSave('simpan_siap'),
                icon: saving
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.check_rounded),
                label: const Text('Simpan Siap'),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _FormError extends StatelessWidget {
  const _FormError({required this.message, this.onRetry});
  final String message;
  final VoidCallback? onRetry;

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
          if (onRetry != null) ...[
            const SizedBox(height: 14),
            FilledButton.tonal(
              onPressed: onRetry,
              child: const Text('Coba Lagi'),
            ),
          ],
        ],
      ),
    ),
  );
}

void _setText(TextEditingController controller, String? value) {
  controller.text = value ?? '';
}

String? _nullableText(TextEditingController controller) {
  final value = controller.text.trim();
  return value.isEmpty ? null : value;
}

String _number(double value) =>
    value == value.roundToDouble() ? '${value.toInt()}' : '$value';

String _message(Object error) => error is AppException
    ? error.message
    : 'Perubahan soal belum dapat disimpan. Silakan coba lagi.';
