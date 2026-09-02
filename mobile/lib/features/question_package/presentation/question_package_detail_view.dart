import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/question_package/application/question_package_controller.dart';
import 'package:nusa/features/question_package/domain/question_package.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class QuestionPackageDetailView extends ConsumerStatefulWidget {
  const QuestionPackageDetailView({required this.scheduleId, super.key});
  final int scheduleId;

  @override
  ConsumerState<QuestionPackageDetailView> createState() =>
      _QuestionPackageDetailViewState();
}

class _QuestionPackageDetailViewState
    extends ConsumerState<QuestionPackageDetailView> {
  final _search = TextEditingController();
  final List<int> _selected = [];
  final Map<int, double> _weights = {};
  String _type = 'semua';
  String _difficulty = 'semua';
  bool _shuffleQuestions = true;
  bool _shuffleAnswers = true;
  bool _initialized = false;
  bool _saving = false;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(questionPackageDetailProvider(widget.scheduleId));
    final detail = state.value;
    if (detail != null) _initializeAfterBuild(detail);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: Text(
          detail?.access.canManage == true ? 'Susun Paket' : 'Detail Paket',
        ),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : () => ref.invalidate(
                    questionPackageDetailProvider(widget.scheduleId),
                  ),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => detail == null
              ? const Center(child: CircularProgressIndicator())
              : _content(detail),
          error: (error, stackTrace) => _ErrorState(
            message: _message(error),
            onRetry: () => ref.invalidate(
              questionPackageDetailProvider(widget.scheduleId),
            ),
          ),
          data: _content,
        ),
      ),
    );
  }

  Widget _content(QuestionPackageDetail detail) {
    final visible = _visibleQuestions(detail);
    return Column(
      children: [
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => ref.refresh(
              questionPackageDetailProvider(widget.scheduleId).future,
            ),
            child: ListView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                _ScheduleHero(detail: detail),
                const SizedBox(height: 11),
                _PackageSummary(
                  selected: _selected.length,
                  totalWeight: _totalWeight,
                  package: detail.package,
                ),
                const SizedBox(height: 11),
                if (detail.access.started)
                  const _Notice(
                    message: 'Paket sudah dikerjakan peserta sehingga susunan dan bobot dikunci.',
                    warning: true,
                  )
                else if (!detail.access.canManage)
                  const _Notice(
                    message: 'Mode pantau: panitia dapat melihat isi paket tanpa mengubahnya.',
                  ),
                if (detail.access.started || !detail.access.canManage)
                  const SizedBox(height: 11),
                if (detail.access.canEdit) ...[
                  _ShuffleCard(
                    shuffleQuestions: _shuffleQuestions,
                    shuffleAnswers: _shuffleAnswers,
                    onQuestionsChanged: (value) =>
                        setState(() => _shuffleQuestions = value),
                    onAnswersChanged: (value) =>
                        setState(() => _shuffleAnswers = value),
                  ),
                  const SizedBox(height: 11),
                  _FilterCard(
                    search: _search,
                    type: _type,
                    difficulty: _difficulty,
                    references: detail.references,
                    onSearch: (_) => setState(() {}),
                    onType: (value) => setState(() => _type = value),
                    onDifficulty: (value) =>
                        setState(() => _difficulty = value),
                    onSelectVisible: () => _selectVisible(visible),
                    onClear: () => setState(() => _selected.clear()),
                  ),
                  const SizedBox(height: 11),
                ],
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Daftar Soal',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w900,
                        ),
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
                  ...visible.map(
                    (question) => Padding(
                      padding: const EdgeInsets.only(bottom: 9),
                      child: _QuestionCard(
                        question: question,
                        selected: _selected.contains(question.id),
                        order: _selected.indexOf(question.id) + 1,
                        weight: _weights[question.id] ?? question.maximumScore,
                        editable: detail.access.canEdit,
                        onToggle: () => _toggle(question),
                        onWeight: () => _editWeight(question),
                        onMoveUp: () => _move(question.id, -1),
                        onMoveDown: () => _move(question.id, 1),
                        onPreview: () => _preview(question),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
        if (detail.access.canEdit)
          _SaveBar(
            saving: _saving,
            ready:
                detail.package != null &&
                [
                  'terjadwal',
                  'berlangsung',
                  'selesai',
                ].contains(detail.package!.status),
            onSecondary: () => _save(detail, 'draf'),
            onPrimary: () => _save(
              detail,
              detail.package != null &&
                      [
                        'terjadwal',
                        'berlangsung',
                        'selesai',
                      ].contains(detail.package!.status)
                  ? 'simpan'
                  : 'terbitkan',
            ),
          ),
      ],
    );
  }

  List<PackageQuestion> _visibleQuestions(QuestionPackageDetail detail) {
    final query = _search.text.trim().toLowerCase();
    final result = detail.questions.where((item) {
      final matchesSearch =
          query.isEmpty ||
          '${item.code} ${item.topic} ${item.material} ${item.question}'
              .toLowerCase()
              .contains(query);
      return matchesSearch &&
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
      _selected.fold(0, (total, id) => total + (_weights[id] ?? 0));

  void _initializeAfterBuild(QuestionPackageDetail detail) {
    if (_initialized) return;
    _initialized = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final chosen = detail.questions.where((item) => item.selected).toList()
        ..sort((a, b) => (a.order ?? 9999).compareTo(b.order ?? 9999));
      setState(() {
        _selected
          ..clear()
          ..addAll(chosen.map((item) => item.id));
        _weights
          ..clear()
          ..addEntries(
            detail.questions.map((item) => MapEntry(item.id, item.weight)),
          );
        _shuffleQuestions = detail.package?.shuffleQuestions ?? true;
        _shuffleAnswers = detail.package?.shuffleAnswers ?? true;
      });
    });
  }

  void _toggle(PackageQuestion question) {
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

  void _selectVisible(List<PackageQuestion> visible) {
    setState(() {
      for (final question in visible.where((item) => item.selectable)) {
        if (!_selected.contains(question.id)) _selected.add(question.id);
        _weights.putIfAbsent(question.id, () => question.maximumScore);
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

  Future<void> _editWeight(PackageQuestion question) async {
    final controller = TextEditingController(
      text: _number(_weights[question.id] ?? question.maximumScore),
    );
    final value = await showDialog<double>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Bobot ${question.code}'),
        content: TextField(
          key: const Key('question-package-weight-input'),
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
            key: const Key('question-package-weight-save'),
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

  void _preview(PackageQuestion question) {
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
              const SizedBox(height: 5),
              Text(question.question, style: const TextStyle(height: 1.55)),
              if (question.imageUrl != null) ...[
                const SizedBox(height: 12),
                Image.network(
                  question.imageUrl!,
                  errorBuilder: (context, error, stackTrace) =>
                      const Text('Gambar tidak dapat dimuat.'),
                ),
              ],
              const Divider(height: 28),
              const Text(
                'Kunci jawaban',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 5),
              Text(
                question.answer?.trim().isNotEmpty == true
                    ? question.answer!
                    : 'Diperiksa manual oleh guru.',
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _save(QuestionPackageDetail detail, String action) async {
    if (action == 'terbitkan' && _selected.isEmpty) {
      _show('Pilih minimal satu soal sebelum paket diterbitkan.');
      return;
    }
    if (action == 'terbitkan') {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Terbitkan paket?'),
          content: Text(
            '${_selected.length} soal dengan total bobot ${_number(_totalWeight)} akan disiapkan untuk jadwal ini.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Batal'),
            ),
            FilledButton(
              key: const Key('question-package-confirm-publish'),
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Terbitkan'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
    }
    setState(() => _saving = true);
    try {
      await ref
          .read(questionPackageControllerProvider.notifier)
          .save(
            widget.scheduleId,
            QuestionPackagePayload(
              action: action,
              shuffleQuestions: _shuffleQuestions,
              shuffleAnswers: _shuffleAnswers,
              questions: [
                for (final id in _selected)
                  PackageQuestionPayload(id: id, weight: _weights[id] ?? 1),
              ],
            ),
          );
      if (mounted) {
        _show(
          action == 'terbitkan'
              ? 'Paket berhasil diterbitkan.'
              : 'Perubahan paket berhasil disimpan.',
        );
      }
    } catch (error) {
      if (mounted) _show(_message(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _show(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _ScheduleHero extends StatelessWidget {
  const _ScheduleHero({required this.detail});
  final QuestionPackageDetail detail;
  @override
  Widget build(BuildContext context) {
    final item = detail.schedule;
    return Container(
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
          Text(
            item.event.name,
            style: const TextStyle(color: NusaColors.accent, fontSize: 11),
          ),
          const SizedBox(height: 4),
          Text(
            '${item.subject} · Tingkat ${item.grade}',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 17,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 12,
            runSpacing: 7,
            children: [
              _HeroInfo(Icons.calendar_today_rounded, _date(item.date)),
              _HeroInfo(Icons.schedule_rounded, item.time ?? '-'),
              _HeroInfo(
                Icons.class_rounded,
                item.classes.isEmpty ? '-' : item.classes.join(', '),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HeroInfo extends StatelessWidget {
  const _HeroInfo(this.icon, this.text);
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, color: Colors.white70, size: 14),
      const SizedBox(width: 4),
      Text(text, style: const TextStyle(color: Colors.white70, fontSize: 10)),
    ],
  );
}

class _PackageSummary extends StatelessWidget {
  const _PackageSummary({
    required this.selected,
    required this.totalWeight,
    this.package,
  });
  final int selected;
  final double totalWeight;
  final QuestionPackageInfo? package;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          _SummaryItem(label: 'Soal', value: '$selected'),
          _SummaryItem(label: 'Bobot', value: _number(totalWeight)),
          _SummaryItem(
            label: 'Durasi',
            value: '${package?.durationMinutes ?? 0} mnt',
          ),
          _SummaryItem(label: 'Status', value: package?.statusLabel ?? 'Belum'),
        ],
      ),
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({required this.label, required this.value});
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
          style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 13),
        ),
        Text(
          label,
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 9),
        ),
      ],
    ),
  );
}

class _ShuffleCard extends StatelessWidget {
  const _ShuffleCard({
    required this.shuffleQuestions,
    required this.shuffleAnswers,
    required this.onQuestionsChanged,
    required this.onAnswersChanged,
  });
  final bool shuffleQuestions;
  final bool shuffleAnswers;
  final ValueChanged<bool> onQuestionsChanged;
  final ValueChanged<bool> onAnswersChanged;
  @override
  Widget build(BuildContext context) => Card(
    child: Column(
      children: [
        SwitchListTile(
          key: const Key('question-package-shuffle-questions'),
          title: const Text('Acak urutan soal'),
          subtitle: const Text('Urutan berbeda untuk setiap siswa.'),
          value: shuffleQuestions,
          onChanged: onQuestionsChanged,
        ),
        const Divider(height: 1),
        SwitchListTile(
          key: const Key('question-package-shuffle-answers'),
          title: const Text('Acak pilihan jawaban'),
          subtitle: const Text('Berlaku untuk PG dan PG kompleks.'),
          value: shuffleAnswers,
          onChanged: onAnswersChanged,
        ),
      ],
    ),
  );
}

class _FilterCard extends StatelessWidget {
  const _FilterCard({
    required this.search,
    required this.type,
    required this.difficulty,
    required this.references,
    required this.onSearch,
    required this.onType,
    required this.onDifficulty,
    required this.onSelectVisible,
    required this.onClear,
  });
  final TextEditingController search;
  final String type;
  final String difficulty;
  final PackageQuestionReferences references;
  final ValueChanged<String> onSearch;
  final ValueChanged<String> onType;
  final ValueChanged<String> onDifficulty;
  final VoidCallback onSelectVisible;
  final VoidCallback onClear;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        children: [
          NusaTextField(
            fieldKey: const Key('question-package-question-search'),
            controller: search,
            hintText: 'Cari kode, topik, atau pertanyaan',
            prefixIcon: Icons.search_rounded,
            onChanged: onSearch,
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: NusaDropdownField<String>(
                  fieldKey: const Key('question-package-type-filter'),
                  value: type,
                  decoration: const InputDecoration(labelText: 'Jenis'),
                  options: [
                    const NusaDropdownOption(
                      value: 'semua',
                      label: 'Semua jenis',
                    ),
                    for (final item in references.types)
                      NusaDropdownOption(value: item.code, label: item.label),
                  ],
                  onChanged: (value) {
                    if (value != null) onType(value);
                  },
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: NusaDropdownField<String>(
                  fieldKey: const Key('question-package-difficulty-filter'),
                  value: difficulty,
                  decoration: const InputDecoration(labelText: 'Kesulitan'),
                  options: [
                    const NusaDropdownOption(value: 'semua', label: 'Semua'),
                    for (final item in references.difficulties)
                      NusaDropdownOption(value: item.code, label: item.label),
                  ],
                  onChanged: (value) {
                    if (value != null) onDifficulty(value);
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onSelectVisible,
                  icon: const Icon(Icons.done_all_rounded),
                  label: const Text('Pilih tampil'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: TextButton.icon(
                  onPressed: onClear,
                  icon: const Icon(Icons.clear_all_rounded),
                  label: const Text('Kosongkan'),
                ),
              ),
            ],
          ),
        ],
      ),
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
  final PackageQuestion question;
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
    color: selected ? NusaColors.surfaceBlue : Colors.white,
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (editable)
                Checkbox(
                  key: Key('question-package-select-${question.id}'),
                  value: selected,
                  onChanged: question.selectable || selected
                      ? (_) => onToggle()
                      : null,
                )
              else
                CircleAvatar(radius: 14, child: Text('$order')),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      question.code,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    Text(
                      '${question.typeLabel} · ${question.difficultyLabel}',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),
              if (selected)
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: NusaColors.primary,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    '#$order',
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
          Text(question.question, maxLines: 3, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 8),
          Row(
            children: [
              TextButton.icon(
                onPressed: onPreview,
                icon: const Icon(Icons.visibility_outlined, size: 17),
                label: const Text('Pratinjau'),
              ),
              const Spacer(),
              if (selected && editable) ...[
                IconButton(
                  visualDensity: VisualDensity.compact,
                  tooltip: 'Naikkan urutan',
                  onPressed: onMoveUp,
                  icon: const Icon(Icons.arrow_upward_rounded, size: 18),
                ),
                IconButton(
                  visualDensity: VisualDensity.compact,
                  tooltip: 'Turunkan urutan',
                  onPressed: onMoveDown,
                  icon: const Icon(Icons.arrow_downward_rounded, size: 18),
                ),
                TextButton(
                  key: Key('question-package-weight-${question.id}'),
                  onPressed: onWeight,
                  child: Text('Bobot ${_number(weight)}'),
                ),
              ] else if (selected)
                Text(
                  'Bobot ${_number(weight)}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
            ],
          ),
        ],
      ),
    ),
  );
}

class _SaveBar extends StatelessWidget {
  const _SaveBar({
    required this.saving,
    required this.ready,
    required this.onSecondary,
    required this.onPrimary,
  });
  final bool saving;
  final bool ready;
  final VoidCallback onSecondary;
  final VoidCallback onPrimary;
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
                key: const Key('question-package-save-draft'),
                onPressed: saving ? null : onSecondary,
                child: Text(ready ? 'Kembali ke Draf' : 'Simpan Draf'),
              ),
            ),
            const SizedBox(width: 9),
            Expanded(
              child: FilledButton.icon(
                key: const Key('question-package-save-primary'),
                onPressed: saving ? null : onPrimary,
                icon: saving
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.publish_rounded),
                label: Text(ready ? 'Simpan' : 'Terbitkan'),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _Notice extends StatelessWidget {
  const _Notice({required this.message, this.warning = false});
  final String message;
  final bool warning;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: warning ? const Color(0xFFFFF5DD) : NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(13),
    ),
    child: Row(
      children: [
        Icon(
          warning ? Icons.lock_outline_rounded : Icons.info_outline_rounded,
          color: NusaColors.primary,
        ),
        const SizedBox(width: 8),
        Expanded(child: Text(message, style: const TextStyle(fontSize: 11))),
      ],
    ),
  );
}

class _EmptyQuestions extends StatelessWidget {
  const _EmptyQuestions();
  @override
  Widget build(BuildContext context) => const Card(
    child: Padding(
      padding: EdgeInsets.all(22),
      child: Text(
        'Belum ada soal siap yang sesuai. Tambahkan melalui Bank Soal terlebih dahulu.',
        textAlign: TextAlign.center,
      ),
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

String _date(String? value) {
  final date = value == null ? null : DateTime.tryParse(value);
  if (date == null) return value ?? '-';
  return '${date.day.toString().padLeft(2, '0')}-${date.month.toString().padLeft(2, '0')}-${date.year}';
}

String _number(double value) => value == value.roundToDouble()
    ? '${value.toInt()}'
    : value
          .toStringAsFixed(2)
          .replaceFirst(RegExp(r'0+$'), '')
          .replaceFirst(RegExp(r'\.$'), '');
String _message(Object error) => error is AppException
    ? error.message
    : 'Paket soal belum dapat diproses. Silakan coba lagi.';
