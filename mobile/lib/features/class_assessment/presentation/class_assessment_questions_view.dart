import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/class_assessment/application/class_assessment_controller.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class ClassAssessmentQuestionsView extends ConsumerStatefulWidget {
  const ClassAssessmentQuestionsView({required this.assessmentId, super.key});
  final int assessmentId;

  @override
  ConsumerState<ClassAssessmentQuestionsView> createState() =>
      _ClassAssessmentQuestionsViewState();
}

class _ClassAssessmentQuestionsViewState
    extends ConsumerState<ClassAssessmentQuestionsView> {
  final _search = TextEditingController();
  final List<int> _selected = [];
  final Map<int, double> _weights = {};
  String _type = 'semua';
  String _difficulty = 'semua';
  bool _initialized = false;
  bool _saving = false;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(
      classAssessmentQuestionsProvider(widget.assessmentId),
    );
    final current = state.value;
    if (current != null) _initializeAfterBuild(current);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pilih Soal Asesmen'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : () => ref.invalidate(
                    classAssessmentQuestionsProvider(widget.assessmentId),
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
              : _content(current),
          error: (error, stackTrace) => _ErrorState(
            message: _message(error),
            onRetry: () => ref.invalidate(
              classAssessmentQuestionsProvider(widget.assessmentId),
            ),
          ),
          data: _content,
        ),
      ),
      bottomNavigationBar: current?.canEdit == true
          ? SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  border: Border(top: BorderSide(color: NusaColors.outline)),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${_selected.length} soal · ${_number(_totalWeight)} bobot',
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ),
                    SizedBox(
                      width: 130,
                      child: FilledButton.icon(
                        key: const Key('class-assessment-save-questions'),
                        onPressed: _saving ? null : _save,
                        icon: _saving
                            ? const SizedBox.square(
                                dimension: 17,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Icon(Icons.save_rounded),
                        label: const Text('Simpan'),
                      ),
                    ),
                  ],
                ),
              ),
            )
          : null,
    );
  }

  Widget _content(AssessmentQuestions data) {
    final visible = _visibleQuestions(data);
    return RefreshIndicator(
      onRefresh: () => ref.refresh(
        classAssessmentQuestionsProvider(widget.assessmentId).future,
      ),
      child: ListView(
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 110),
        children: [
          _Hero(assessment: data.assessment),
          const SizedBox(height: 11),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(13),
              child: Column(
                children: [
                  NusaTextField(
                    fieldKey: const Key('class-assessment-question-search'),
                    controller: _search,
                    hintText: 'Cari kode, topik, atau pertanyaan',
                    prefixIcon: Icons.search_rounded,
                    onChanged: (_) => setState(() {}),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: NusaDropdownField<String>(
                          fieldKey: const Key('class-assessment-question-type'),
                          value: _type,
                          decoration: const InputDecoration(labelText: 'Jenis'),
                          options: [
                            const NusaDropdownOption(
                              value: 'semua',
                              label: 'Semua jenis',
                            ),
                            for (final item in data.types)
                              NusaDropdownOption(
                                value: item.code,
                                label: item.label,
                              ),
                          ],
                          onChanged: (value) {
                            if (value != null) setState(() => _type = value);
                          },
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: NusaDropdownField<String>(
                          fieldKey: const Key(
                            'class-assessment-question-difficulty',
                          ),
                          value: _difficulty,
                          decoration: const InputDecoration(
                            labelText: 'Kesulitan',
                          ),
                          options: [
                            const NusaDropdownOption(
                              value: 'semua',
                              label: 'Semua tingkat',
                            ),
                            for (final item in data.difficulties)
                              NusaDropdownOption(
                                value: item.code,
                                label: item.label,
                              ),
                          ],
                          onChanged: (value) {
                            if (value != null) {
                              setState(() => _difficulty = value);
                            }
                          },
                        ),
                      ),
                    ],
                  ),
                  if (data.canEdit) ...[
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 4,
                      runSpacing: 2,
                      children: [
                        TextButton.icon(
                          onPressed: () => _selectVisible(visible),
                          icon: const Icon(Icons.select_all_rounded),
                          label: const Text('Pilih tampil'),
                        ),
                        TextButton(
                          onPressed: _selected.isEmpty
                              ? null
                              : () => setState(_selected.clear),
                          child: const Text('Kosongkan'),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 11),
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Daftar Soal',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
                ),
              ),
              Text(
                '${visible.length} ditampilkan',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          if (visible.isEmpty)
            const _EmptyQuestions()
          else
            for (final question in visible)
              Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: _QuestionCard(
                  question: question,
                  selected: _selected.contains(question.id),
                  order: _selected.indexOf(question.id) + 1,
                  weight: _weights[question.id] ?? question.maximumScore,
                  editable: data.canEdit,
                  onToggle: () => _toggle(question),
                  onWeight: () => _editWeight(question),
                  onMoveUp: () => _move(question.id, -1),
                  onMoveDown: () => _move(question.id, 1),
                  onPreview: () => _preview(question),
                ),
              ),
        ],
      ),
    );
  }

  List<AssessmentQuestion> _visibleQuestions(AssessmentQuestions data) {
    final query = _search.text.trim().toLowerCase();
    final result = data.questions.where((item) {
      final matches =
          query.isEmpty ||
          '${item.code} ${item.topic ?? ''} ${item.material ?? ''} ${item.question}'
              .toLowerCase()
              .contains(query);
      return matches &&
          (_type == 'semua' || item.type == _type) &&
          (_difficulty == 'semua' || item.difficulty == _difficulty);
    }).toList();
    result.sort((a, b) {
      final ai = _selected.indexOf(a.id);
      final bi = _selected.indexOf(b.id);
      if (ai >= 0 && bi >= 0) return ai.compareTo(bi);
      if (ai >= 0) return -1;
      if (bi >= 0) return 1;
      return a.code.compareTo(b.code);
    });
    return result;
  }

  double get _totalWeight =>
      _selected.fold(0, (sum, id) => sum + (_weights[id] ?? 0));

  void _initializeAfterBuild(AssessmentQuestions data) {
    if (_initialized) return;
    _initialized = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final chosen = data.questions.where((item) => item.selected).toList()
        ..sort((a, b) => (a.order ?? 9999).compareTo(b.order ?? 9999));
      setState(() {
        _selected
          ..clear()
          ..addAll(chosen.map((item) => item.id));
        _weights
          ..clear()
          ..addEntries(
            data.questions.map((item) => MapEntry(item.id, item.weight)),
          );
      });
    });
  }

  void _toggle(AssessmentQuestion question) {
    if (!question.selectable && !_selected.contains(question.id)) return;
    setState(() {
      if (_selected.contains(question.id)) {
        _selected.remove(question.id);
      } else {
        _selected.add(question.id);
        _weights[question.id] = question.maximumScore;
      }
    });
  }

  void _selectVisible(List<AssessmentQuestion> visible) {
    setState(() {
      for (final item in visible.where((item) => item.selectable)) {
        if (!_selected.contains(item.id)) _selected.add(item.id);
        _weights.putIfAbsent(item.id, () => item.maximumScore);
      }
    });
  }

  void _move(int id, int delta) {
    final index = _selected.indexOf(id);
    final target = index + delta;
    if (index < 0 || target < 0 || target >= _selected.length) return;
    setState(() {
      final item = _selected.removeAt(index);
      _selected.insert(target, item);
    });
  }

  Future<void> _editWeight(AssessmentQuestion question) async {
    final controller = TextEditingController(
      text: _number(_weights[question.id] ?? question.maximumScore),
    );
    final value = await showDialog<double>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Bobot ${question.code}'),
        content: TextField(
          key: const Key('class-assessment-weight-input'),
          controller: controller,
          autofocus: true,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(
            labelText: 'Bobot soal',
            helperText: 'Nilai 0,25 sampai 100',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('class-assessment-weight-apply'),
            onPressed: () {
              final number = double.tryParse(
                controller.text.trim().replaceAll(',', '.'),
              );
              if (number != null && number >= 0.25 && number <= 100) {
                Navigator.pop(context, number);
              }
            },
            child: const Text('Terapkan'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (value != null && mounted) {
      setState(() => _weights[question.id] = value);
    }
  }

  void _preview(AssessmentQuestion question) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                question.code,
                style: const TextStyle(
                  color: NusaColors.primary,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              Text(question.question, style: const TextStyle(height: 1.55)),
              if (question.imageUrl != null) ...[
                const SizedBox(height: 12),
                Image.network(
                  question.imageUrl!,
                  errorBuilder: (context, error, stackTrace) =>
                      const Text('Gambar tidak dapat dimuat.'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await ref.read(classAssessmentControllerProvider.notifier).saveQuestions(
        widget.assessmentId,
        [
          for (final id in _selected)
            AssessmentQuestionPayload(id: id, weight: _weights[id] ?? 1),
        ],
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pilihan soal berhasil disimpan.')),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_message(error))));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.assessment});
  final ClassAssessment assessment;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(19),
    ),
    child: Row(
      children: [
        const Icon(Icons.quiz_rounded, color: NusaColors.accent, size: 32),
        const SizedBox(width: 11),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                assessment.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                '${assessment.subject} · Tingkat ${assessment.grade} · target ${assessment.targetQuestions} soal',
                style: const TextStyle(color: Colors.white70, fontSize: 10),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({
    required this.question,
    required this.selected,
    required this.order,
    required this.weight,
    required this.editable,
    required this.onToggle,
    required this.onWeight,
    required this.onMoveUp,
    required this.onMoveDown,
    required this.onPreview,
  });
  final AssessmentQuestion question;
  final bool selected;
  final int order;
  final double weight;
  final bool editable;
  final VoidCallback onToggle;
  final VoidCallback onWeight;
  final VoidCallback onMoveUp;
  final VoidCallback onMoveDown;
  final VoidCallback onPreview;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: editable ? onToggle : onPreview,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(8, 10, 11, 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (editable)
              Checkbox(value: selected, onChanged: (_) => onToggle())
            else
              const Padding(
                padding: EdgeInsets.all(9),
                child: Icon(Icons.quiz_outlined, color: NusaColors.primary),
              ),
            const SizedBox(width: 2),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      if (selected)
                        Container(
                          margin: const EdgeInsets.only(right: 6),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 7,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: NusaColors.primary,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            '$order',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 9,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      Expanded(
                        child: Text(
                          '${question.code} · ${question.typeLabel}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: NusaColors.primary,
                            fontSize: 10,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 5),
                  Text(
                    question.question,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 11.5, height: 1.4),
                  ),
                  const SizedBox(height: 7),
                  Wrap(
                    crossAxisAlignment: WrapCrossAlignment.center,
                    spacing: 7,
                    runSpacing: 4,
                    children: [
                      Text(
                        question.difficultyLabel,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 9.5,
                        ),
                      ),
                      if (selected)
                        ActionChip(
                          visualDensity: VisualDensity.compact,
                          onPressed: editable ? onWeight : null,
                          avatar: const Icon(Icons.stars_rounded, size: 14),
                          label: Text('Bobot ${_number(weight)}'),
                        ),
                      TextButton(
                        onPressed: onPreview,
                        child: const Text('Pratinjau'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            if (editable && selected)
              Column(
                children: [
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    onPressed: onMoveUp,
                    icon: const Icon(Icons.keyboard_arrow_up_rounded),
                  ),
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    onPressed: onMoveDown,
                    icon: const Icon(Icons.keyboard_arrow_down_rounded),
                  ),
                ],
              ),
          ],
        ),
      ),
    ),
  );
}

class _EmptyQuestions extends StatelessWidget {
  const _EmptyQuestions();
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 35, horizontal: 18),
    child: const Column(
      children: [
        Icon(Icons.quiz_outlined, size: 44, color: NusaColors.textSecondary),
        SizedBox(height: 9),
        Text(
          'Belum ada soal siap yang sesuai dengan mapel, tingkat, atau filter.',
          textAlign: TextAlign.center,
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

String _number(double value) =>
    value == value.roundToDouble() ? '${value.toInt()}' : '$value';
String _message(Object error) => error is AppException
    ? error.message
    : 'Soal asesmen belum dapat diproses.';
