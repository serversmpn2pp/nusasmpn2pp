import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

import '../data/tahfidz_repository.dart';
import '../domain/tahfidz.dart';
import 'tahfidz_widgets.dart';

class TfCollectiveDepositView extends ConsumerStatefulWidget {
  const TfCollectiveDepositView({
    required this.path,
    required this.meetingLabel,
    required this.students,
    required this.surahs,
    super.key,
  });

  final String path, meetingLabel;
  final List<TfData> students, surahs;

  @override
  ConsumerState<TfCollectiveDepositView> createState() =>
      _TfCollectiveDepositViewState();
}

class _TfCollectiveDepositViewState
    extends ConsumerState<TfCollectiveDepositView> {
  static final _gradeFormat = RegExp(r'^\d{1,3}(?:\.\d{1,2})?$');

  late final _students = [
    for (final student in widget.students)
      NusaDropdownOption(
        value: tfInt(student['id']),
        label: tfText(student['nama']),
      ),
  ];
  late final _surahs = {
    for (final surah in widget.surahs)
      if (tfInt(surah['nomor']) >= 78 && tfInt(surah['nomor']) <= 114)
        tfInt(surah['nomor']): tfText(surah['nama']),
  };
  final _grades = <int, TextEditingController>{};
  Set<int> _selected = {};
  Set<int> _invalid = {};
  int? _student;
  String _token = tfToken();
  String? _error;
  bool _saving = false;

  @override
  void dispose() {
    for (final controller in _grades.values) {
      controller.dispose();
    }
    super.dispose();
  }

  String _grade(int number) => _grades[number]?.text.trim() ?? '';

  Future<void> _selectStudent(int? student) async {
    if (student == _student || _saving) return;
    if (_grades.values.any((c) => c.text.trim().isNotEmpty)) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialog) => AlertDialog(
          title: const Text('Ganti siswa?'),
          content: const Text('Nilai yang belum disimpan akan dikosongkan.'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Ganti siswa'),
            ),
          ],
        ),
      );
      if (!mounted || confirmed != true) return;
    }
    setState(() {
      for (final controller in _grades.values) {
        controller.clear();
      }
      _student = student;
      _invalid.clear();
      _error = null;
      _token = tfToken();
    });
  }

  Future<void> _selectSurahs() async {
    FocusScope.of(context).unfocus();
    final selected = await showModalBottomSheet<Set<int>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => FractionallySizedBox(
        heightFactor: .82,
        child: _TfSurahSelector(surahs: _surahs, selected: _selected),
      ),
    );
    if (mounted && selected != null) {
      setState(() {
        _selected = selected;
        _invalid.retainAll(selected);
        _error = null;
      });
    }
  }

  Future<void> _save() async {
    final student = _student;
    if (_saving || student == null) return;
    final values = <String, String>{};
    final invalid = <int>{};
    final surahs = _selected.toList()..sort();
    for (final number in surahs) {
      final grade = _grade(number).replaceAll(',', '.');
      if (grade.isEmpty) continue;
      if (!_gradeFormat.hasMatch(grade) || double.parse(grade) > 100) {
        invalid.add(number);
      } else {
        values['$number'] = grade;
      }
    }
    setState(() {
      _invalid = invalid;
      _error = invalid.isNotEmpty
          ? 'Periksa nilai ${_surahs[invalid.first]}. Gunakan 0-100, maksimal dua desimal.'
          : (values.isEmpty ? 'Isi minimal satu nilai untuk disimpan.' : null);
    });
    if (_error != null) return;

    FocusScope.of(context).unfocus();
    setState(() => _saving = true);
    try {
      final result = await ref
          .read(tahfidzRepositoryProvider)
          .call(
            'POST',
            widget.path,
            data: {
              'siswa': student,
              'surah': surahs,
              'token_batch': _token,
              'nilai': {'$student': values},
            },
          );
      if (mounted) Navigator.of(context).pop(tfInt(result['jumlah_setoran']));
    } catch (error) {
      if (mounted) setState(() => _error = tfError(error).split('\n').first);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final numbers = _selected.toList()..sort();
    final filled = numbers.where((number) => _grade(number).isNotEmpty).length;
    return PopScope(
      canPop: !_saving,
      child: Scaffold(
        appBar: AppBar(title: const Text('Input Kolektif Tahfidz')),
        body: SafeArea(
          top: false,
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 640),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(
                    child: ListView.builder(
                      keyboardDismissBehavior:
                          ScrollViewKeyboardDismissBehavior.onDrag,
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                      itemCount: numbers.length + 1,
                      itemBuilder: (context, index) {
                        if (index > 0) return _gradeRow(numbers[index - 1]);
                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(
                              widget.meetingLabel,
                              style: Theme.of(context).textTheme.titleSmall,
                            ),
                            const SizedBox(height: 12),
                            NusaDropdownField<int>(
                              fieldKey: const Key('tahfidz-kolektif-siswa'),
                              value: _student,
                              options: _students,
                              enabled: !_saving,
                              decoration: const InputDecoration(
                                labelText: 'Siswa',
                                hintText: 'Pilih siswa',
                              ),
                              onChanged: _selectStudent,
                            ),
                            const SizedBox(height: 10),
                            OutlinedButton.icon(
                              key: const Key('tahfidz-kolektif-pilih-surah'),
                              onPressed: _saving || _student == null
                                  ? null
                                  : _selectSurahs,
                              icon: const Icon(Icons.checklist_rounded),
                              label: Text(
                                numbers.isEmpty
                                    ? 'Pilih Surah'
                                    : '${numbers.length} surah dipilih',
                              ),
                            ),
                            if (numbers.isNotEmpty)
                              const TfSection('Nilai Setoran'),
                          ],
                        );
                      },
                    ),
                  ),
                  const Divider(height: 1),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (_error != null)
                          Semantics(
                            liveRegion: true,
                            child: Padding(
                              padding: const EdgeInsets.only(bottom: 6),
                              child: Text(
                                _error!,
                                style: TextStyle(
                                  color: Theme.of(context).colorScheme.error,
                                ),
                              ),
                            ),
                          ),
                        if (numbers.isNotEmpty)
                          Text(
                            '$filled dari ${numbers.length} nilai terisi',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        FilledButton.icon(
                          key: const Key('tahfidz-kolektif-simpan'),
                          onPressed:
                              _saving || _student == null || numbers.isEmpty
                              ? null
                              : _save,
                          icon: Icon(
                            _saving
                                ? Icons.hourglass_top_rounded
                                : Icons.save_outlined,
                          ),
                          label: Text(
                            _saving ? 'Menyimpan...' : 'Simpan Nilai',
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _gradeRow(int number) => Padding(
    key: ValueKey(number),
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      children: [
        Expanded(child: Text('$number. ${_surahs[number]}')),
        const SizedBox(width: 12),
        SizedBox(
          width: 104,
          child: Semantics(
            label: 'Nilai ${_surahs[number]}',
            child: TextField(
              key: Key('tahfidz-kolektif-nilai-$number'),
              controller: _grades.putIfAbsent(
                number,
                TextEditingController.new,
              ),
              enabled: !_saving,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              textInputAction: TextInputAction.next,
              decoration: InputDecoration(
                hintText: '0-100',
                isDense: true,
                errorText: _invalid.contains(number)
                    ? 'Nilai tidak valid'
                    : null,
                errorMaxLines: 2,
              ),
              onChanged: (_) => setState(() {
                _invalid.remove(number);
                _error = null;
              }),
            ),
          ),
        ),
      ],
    ),
  );
}

class _TfSurahSelector extends StatefulWidget {
  const _TfSurahSelector({required this.surahs, required this.selected});
  final Map<int, String> surahs;
  final Set<int> selected;

  @override
  State<_TfSurahSelector> createState() => _TfSurahSelectorState();
}

class _TfSurahSelectorState extends State<_TfSurahSelector> {
  late final _selected = {...widget.selected};

  @override
  Widget build(BuildContext context) {
    final surahs = widget.surahs.keys.toList()..sort();
    final all = _selected.length == surahs.length;
    return SafeArea(
      top: false,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Text(
              'Surah Juz 30',
              style: Theme.of(context).textTheme.titleLarge,
            ),
          ),
          CheckboxListTile(
            tristate: true,
            title: const Text('Pilih semua surah'),
            subtitle: Text('${_selected.length} dari ${surahs.length} dipilih'),
            value: _selected.isEmpty
                ? false
                : all
                ? true
                : null,
            onChanged: (_) => setState(() {
              _selected.clear();
              if (!all) _selected.addAll(surahs);
            }),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView.builder(
              itemCount: surahs.length,
              itemBuilder: (context, index) {
                final number = surahs[index];
                return CheckboxListTile(
                  key: Key('tahfidz-kolektif-surah-$number'),
                  title: Text('$number. ${widget.surahs[number]}'),
                  value: _selected.contains(number),
                  onChanged: (checked) => setState(() {
                    checked == true
                        ? _selected.add(number)
                        : _selected.remove(number);
                  }),
                );
              },
            ),
          ),
          const Divider(height: 1),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: FilledButton(
              onPressed: () => Navigator.pop(context, _selected),
              child: const Text('Terapkan Pilihan'),
            ),
          ),
        ],
      ),
    );
  }
}
