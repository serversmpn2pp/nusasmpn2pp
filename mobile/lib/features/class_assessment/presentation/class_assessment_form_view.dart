import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/class_assessment/application/class_assessment_controller.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class ClassAssessmentFormView extends ConsumerStatefulWidget {
  const ClassAssessmentFormView({this.assessmentId, super.key});
  final int? assessmentId;

  @override
  ConsumerState<ClassAssessmentFormView> createState() =>
      _ClassAssessmentFormViewState();
}

class _ClassAssessmentFormViewState
    extends ConsumerState<ClassAssessmentFormView> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _duration = TextEditingController(text: '40');
  final _questionCount = TextEditingController(text: '20');
  final _appSwitchGrace = TextEditingController(text: '3');
  final _appSwitchLimit = TextEditingController(text: '3');
  final _instructions = TextEditingController();
  final Map<int, String> _selectedComponents = {};
  String? _teachingGroup;
  String _semester = 'ganjil';
  String _status = 'draft';
  late DateTime _startsAt;
  late DateTime _endsAt;
  bool _shuffleQuestions = true;
  bool _shuffleAnswers = true;
  bool _singleDevice = false;
  bool _detectTabChange = false;
  bool _requireFullscreen = false;
  bool _secureScreen = true;
  String _appSwitchAction = 'catat';
  bool _showResult = false;
  bool _initialized = false;
  bool _saving = false;

  bool get _editing => widget.assessmentId != null;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _startsAt = DateTime(now.year, now.month, now.day, now.hour + 1);
    _endsAt = _startsAt.add(const Duration(hours: 1));
  }

  @override
  void dispose() {
    _name.dispose();
    _duration.dispose();
    _questionCount.dispose();
    _appSwitchGrace.dispose();
    _appSwitchLimit.dispose();
    _instructions.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final pageState = ref.watch(classAssessmentControllerProvider);
    final detailState = _editing
        ? ref.watch(classAssessmentDetailProvider(widget.assessmentId!))
        : null;
    final page = pageState.value;
    final detail = detailState?.value;
    if (page != null && (!_editing || detail != null)) {
      _initializeAfterBuild(page.references, detail);
    }
    final loading = page == null || (_editing && detail == null);
    final error = pageState.hasError
        ? pageState.error
        : (detailState?.hasError == true ? detailState?.error : null);

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(title: Text(_editing ? 'Ubah Asesmen' : 'Tambah Asesmen')),
      body: loading
          ? error != null
                ? _ErrorState(message: _message(error), onRetry: _retry)
                : const Center(child: CircularProgressIndicator())
          : _content(page.references),
      bottomNavigationBar: loading
          ? null
          : SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  border: Border(top: BorderSide(color: NusaColors.outline)),
                ),
                child: FilledButton.icon(
                  key: const Key('class-assessment-save'),
                  onPressed: _saving ? null : () => _save(page.references),
                  icon: _saving
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.save_rounded),
                  label: Text(_editing ? 'Simpan Perubahan' : 'Buat Asesmen'),
                ),
              ),
            ),
    );
  }

  Widget _content(ClassAssessmentReferences references) {
    final group = _selectedGroup(references);
    return Form(
      key: _formKey,
      child: ListView(
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
        children: [
          _AcademicYearCard(name: references.academicYear.name),
          const SizedBox(height: 11),
          _SectionCard(
            title: '1. Informasi asesmen',
            subtitle: 'Pilih penugasan mengajar yang sesuai.',
            child: Column(
              children: [
                NusaDropdownField<String>(
                  fieldKey: const Key('class-assessment-teaching-group'),
                  value: _teachingGroup,
                  decoration: const InputDecoration(
                    labelText: 'Mata pelajaran dan tingkat',
                    hintText: 'Pilih penugasan mengajar',
                    prefixIcon: Icon(Icons.menu_book_rounded),
                  ),
                  options: [
                    for (final item in references.teachingGroups)
                      NusaDropdownOption(value: item.key, label: item.label),
                  ],
                  onChanged: (value) => setState(() {
                    _teachingGroup = value;
                    _selectedComponents.clear();
                  }),
                ),
                const SizedBox(height: 10),
                NusaTextField(
                  fieldKey: const Key('class-assessment-name'),
                  controller: _name,
                  hintText: 'Contoh: Sumatif Bab Bilangan',
                  labelText: 'Nama asesmen',
                  prefixIcon: Icons.assignment_rounded,
                  validator: (value) => value?.trim().isEmpty == true
                      ? 'Nama asesmen wajib diisi.'
                      : null,
                ),
                const SizedBox(height: 10),
                NusaDropdownField<String>(
                  fieldKey: const Key('class-assessment-semester'),
                  value: _semester,
                  decoration: const InputDecoration(
                    labelText: 'Semester',
                    prefixIcon: Icon(Icons.school_outlined),
                  ),
                  options: const [
                    NusaDropdownOption(value: 'ganjil', label: 'Ganjil'),
                    NusaDropdownOption(value: 'genap', label: 'Genap'),
                  ],
                  onChanged: (value) {
                    if (value == null) return;
                    setState(() {
                      _semester = value;
                      for (final id in _selectedComponents.keys.toList()) {
                        final schoolClass = group?.classes
                            .where((item) => item.id == id)
                            .firstOrNull;
                        final componentId = _selectedComponents[id];
                        final valid = schoolClass?.components.any(
                          (item) =>
                              item.semester == value &&
                              '${item.id}' == componentId,
                        );
                        if (valid != true) _selectedComponents[id] = 'baru';
                      }
                    });
                  },
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        key: const Key('class-assessment-duration'),
                        controller: _duration,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'Durasi (menit)',
                          prefixIcon: Icon(Icons.timer_outlined),
                        ),
                        validator: (value) {
                          final number = int.tryParse(value ?? '');
                          return number == null || number < 10 || number > 300
                              ? '10–300'
                              : null;
                        },
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextFormField(
                        key: const Key('class-assessment-question-count'),
                        controller: _questionCount,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'Target soal',
                          prefixIcon: Icon(Icons.quiz_outlined),
                        ),
                        validator: (value) {
                          final number = int.tryParse(value ?? '');
                          return number == null || number < 1 || number > 120
                              ? '1–120'
                              : null;
                        },
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 11),
          _SectionCard(
            title: '2. Kelas dan tujuan nilai',
            subtitle: 'Peserta aktif akan dimasukkan otomatis. Komponen Sumatif dapat dibuat oleh NUSA.',
            child: group == null
                ? const _EmptyHint(
                    text: 'Pilih mata pelajaran dan tingkat terlebih dahulu.',
                  )
                : Column(
                    children: [
                      for (final schoolClass in group.classes)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 9),
                          child: _ClassSelector(
                            schoolClass: schoolClass,
                            semester: _semester,
                            componentId: _selectedComponents[schoolClass.id],
                            onSelected: (selected) => setState(() {
                              if (selected) {
                                _selectedComponents[schoolClass.id] = 'baru';
                              } else {
                                _selectedComponents.remove(schoolClass.id);
                              }
                            }),
                            onComponent: (value) {
                              if (value != null) {
                                setState(
                                  () => _selectedComponents[schoolClass.id] =
                                      value,
                                );
                              }
                            },
                          ),
                        ),
                    ],
                  ),
          ),
          const SizedBox(height: 11),
          _SectionCard(
            title: '3. Waktu pelaksanaan',
            subtitle: 'Waktu akses dan status yang dilihat siswa.',
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: _DateTimeField(
                        fieldKey: const Key('class-assessment-start'),
                        label: 'Dibuka',
                        value: _startsAt,
                        onTap: () => _pickDateTime(true),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _DateTimeField(
                        fieldKey: const Key('class-assessment-end'),
                        label: 'Ditutup',
                        value: _endsAt,
                        onTap: () => _pickDateTime(false),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                NusaDropdownField<String>(
                  fieldKey: const Key('class-assessment-status'),
                  value: _status,
                  decoration: const InputDecoration(
                    labelText: 'Status',
                    prefixIcon: Icon(Icons.flag_outlined),
                  ),
                  options: [
                    for (final item in references.statuses)
                      NusaDropdownOption(value: item.code, label: item.label),
                  ],
                  onChanged: (value) {
                    if (value != null) setState(() => _status = value);
                  },
                ),
                const SizedBox(height: 10),
                TextFormField(
                  key: const Key('class-assessment-instructions'),
                  controller: _instructions,
                  minLines: 3,
                  maxLines: 6,
                  maxLength: 3000,
                  decoration: const InputDecoration(
                    labelText: 'Petunjuk untuk siswa (opsional)',
                    alignLabelWithHint: true,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 11),
          _SectionCard(
            title: 'Pengaturan pengerjaan',
            subtitle: 'Atur perilaku asesmen pada perangkat siswa.',
            child: Column(
              children: [
                _Toggle(
                  title: 'Acak soal',
                  subtitle: 'Urutan berbeda untuk setiap siswa.',
                  value: _shuffleQuestions,
                  onChanged: (value) =>
                      setState(() => _shuffleQuestions = value),
                ),
                _Toggle(
                  title: 'Acak pilihan jawaban',
                  subtitle: 'Berlaku untuk soal pilihan.',
                  value: _shuffleAnswers,
                  onChanged: (value) => setState(() => _shuffleAnswers = value),
                ),
                _Toggle(
                  title: 'Batasi satu perangkat',
                  subtitle: 'Mencegah akun aktif pada dua perangkat.',
                  value: _singleDevice,
                  onChanged: (value) => setState(() => _singleDevice = value),
                ),
                _Toggle(
                  title: 'Catat pindah aplikasi',
                  subtitle: 'Aktivitas keluar dari NUSA dicatat untuk guru.',
                  value: _detectTabChange,
                  onChanged: (value) =>
                      setState(() => _detectTabChange = value),
                ),
                _Toggle(
                  title: 'Blokir tangkapan layar',
                  subtitle: 'Mencegah screenshot dan rekaman layar di Android.',
                  value: _secureScreen,
                  onChanged: (value) => setState(() => _secureScreen = value),
                ),
                _Toggle(
                  title: 'Wajib layar penuh',
                  subtitle: 'Sembunyikan navigasi sistem selama pengerjaan.',
                  value: _requireFullscreen,
                  onChanged: (value) =>
                      setState(() => _requireFullscreen = value),
                ),
                if (_detectTabChange) ...[
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    child: Row(
                      children: [
                        Expanded(
                          child: TextFormField(
                            key: const Key('class-assessment-switch-grace'),
                            controller: _appSwitchGrace,
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(
                              labelText: 'Toleransi (detik)',
                            ),
                            validator: (value) {
                              final number = int.tryParse(value ?? '');
                              return number == null || number < 1 || number > 60
                                  ? '1–60'
                                  : null;
                            },
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: TextFormField(
                            key: const Key('class-assessment-switch-limit'),
                            controller: _appSwitchLimit,
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(
                              labelText: 'Batas kejadian',
                            ),
                            validator: (value) {
                              final number = int.tryParse(value ?? '');
                              return number == null || number < 1 || number > 20
                                  ? '1–20'
                                  : null;
                            },
                          ),
                        ),
                      ],
                    ),
                  ),
                  NusaDropdownField<String>(
                    fieldKey: const Key('class-assessment-switch-action'),
                    value: _appSwitchAction,
                    decoration: const InputDecoration(
                      labelText: 'Tindakan setelah batas tercapai',
                      prefixIcon: Icon(Icons.gpp_maybe_outlined),
                    ),
                    options: const [
                      NusaDropdownOption(
                        value: 'catat',
                        label: 'Tetap lanjut, hanya dicatat',
                      ),
                      NusaDropdownOption(
                        value: 'tahan',
                        label: 'Tahan ujian, perlu dibuka guru',
                      ),
                    ],
                    onChanged: (value) {
                      if (value != null) {
                        setState(() => _appSwitchAction = value);
                      }
                    },
                  ),
                  const SizedBox(height: 4),
                ],
                _Toggle(
                  title: 'Tampilkan hasil',
                  subtitle: 'Nilai terlihat setelah asesmen selesai.',
                  value: _showResult,
                  onChanged: (value) => setState(() => _showResult = value),
                  divider: false,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  AssessmentTeachingGroup? _selectedGroup(
    ClassAssessmentReferences references,
  ) => references.teachingGroups
      .where((item) => item.key == _teachingGroup)
      .firstOrNull;

  void _initializeAfterBuild(
    ClassAssessmentReferences references,
    ClassAssessmentDetail? detail,
  ) {
    if (_initialized) return;
    _initialized = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      setState(() {
        if (detail == null) {
          if (references.teachingGroups.length == 1) {
            _teachingGroup = references.teachingGroups.single.key;
          }
          return;
        }
        final item = detail.assessment;
        _name.text = item.name;
        _duration.text = '${item.durationMinutes}';
        _questionCount.text = '${item.targetQuestions}';
        _instructions.text = detail.instructions ?? '';
        _teachingGroup = detail.teachingGroup;
        _semester = item.semester;
        _status = item.status == 'nonaktif' ? 'draft' : item.status;
        _startsAt = item.startsAt ?? _startsAt;
        _endsAt = item.endsAt ?? _endsAt;
        _shuffleQuestions = detail.shuffleQuestions;
        _shuffleAnswers = detail.shuffleAnswers;
        _singleDevice = detail.singleDevice;
        _detectTabChange = detail.detectTabChange;
        _requireFullscreen = detail.requireFullscreen;
        _secureScreen = detail.secureScreen;
        _appSwitchGrace.text = '${detail.appSwitchGraceSeconds}';
        _appSwitchLimit.text = '${detail.appSwitchLimit}';
        _appSwitchAction = detail.appSwitchAction;
        _showResult = detail.showResult;
        for (final target in detail.classes) {
          _selectedComponents[target.classId] =
              '${target.componentId ?? 'baru'}';
        }
      });
    });
  }

  Future<void> _pickDateTime(bool start) async {
    final initial = start ? _startsAt : _endsAt;
    final date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 1095)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial),
    );
    if (time == null || !mounted) return;
    final value = DateTime(
      date.year,
      date.month,
      date.day,
      time.hour,
      time.minute,
    );
    setState(() {
      if (start) {
        _startsAt = value;
        if (!_endsAt.isAfter(value)) {
          _endsAt = value.add(const Duration(hours: 1));
        }
      } else {
        _endsAt = value;
      }
    });
  }

  Future<void> _save(ClassAssessmentReferences references) async {
    if (_formKey.currentState?.validate() != true) return;
    if (_selectedGroup(references) == null) {
      _snack('Pilih mata pelajaran dan tingkat.');
      return;
    }
    if (_selectedComponents.isEmpty) {
      _snack('Pilih minimal satu kelas peserta.');
      return;
    }
    if (_endsAt.isBefore(_startsAt)) {
      _snack('Waktu ditutup tidak boleh sebelum waktu dibuka.');
      return;
    }
    setState(() => _saving = true);
    try {
      final payload = ClassAssessmentPayload(
        teachingGroup: _teachingGroup!,
        name: _name.text.trim(),
        semester: _semester,
        startsAt: _dateTimePayload(_startsAt),
        endsAt: _dateTimePayload(_endsAt),
        durationMinutes: int.parse(_duration.text),
        questionCount: int.parse(_questionCount.text),
        status: _status,
        shuffleQuestions: _shuffleQuestions,
        shuffleAnswers: _shuffleAnswers,
        singleDevice: _singleDevice,
        detectTabChange: _detectTabChange,
        requireFullscreen: _requireFullscreen,
        secureScreen: _secureScreen,
        appSwitchGraceSeconds: int.parse(_appSwitchGrace.text),
        appSwitchLimit: int.parse(_appSwitchLimit.text),
        appSwitchAction: _appSwitchAction,
        showResult: _showResult,
        instructions: _optional(_instructions.text),
        classes: [
          for (final item in _selectedComponents.entries)
            AssessmentClassPayload(classId: item.key, componentId: item.value),
        ],
      );
      final detail = _editing
          ? await ref
                .read(classAssessmentControllerProvider.notifier)
                .updateAssessment(widget.assessmentId!, payload)
          : await ref
                .read(classAssessmentControllerProvider.notifier)
                .create(payload);
      if (mounted) context.go('/asesmen-kelas/${detail.assessment.id}');
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _retry() {
    ref.read(classAssessmentControllerProvider.notifier).refresh();
    if (_editing) {
      ref.invalidate(classAssessmentDetailProvider(widget.assessmentId!));
    }
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _AcademicYearCard extends StatelessWidget {
  const _AcademicYearCard({required this.name});
  final String name;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        const Icon(Icons.school_rounded, color: NusaColors.accent),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Tahun pelajaran aktif',
                style: TextStyle(color: Colors.white70, fontSize: 10),
              ),
              Text(
                name,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                ),
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
    required this.title,
    required this.subtitle,
    required this.child,
  });
  final String title;
  final String subtitle;
  final Widget child;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 3),
          Text(
            subtitle,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 13),
          child,
        ],
      ),
    ),
  );
}

class _ClassSelector extends StatelessWidget {
  const _ClassSelector({
    required this.schoolClass,
    required this.semester,
    required this.componentId,
    required this.onSelected,
    required this.onComponent,
  });
  final AssessmentClassOption schoolClass;
  final String semester;
  final String? componentId;
  final ValueChanged<bool> onSelected;
  final ValueChanged<String?> onComponent;

  @override
  Widget build(BuildContext context) {
    final selected = componentId != null;
    final components = schoolClass.components
        .where((item) => item.semester == semester)
        .toList();
    final validValue =
        componentId == 'baru' ||
            components.any((item) => '${item.id}' == componentId)
        ? componentId
        : 'baru';
    return Material(
      color: selected ? NusaColors.surfaceBlue : Colors.white,
      shape: RoundedRectangleBorder(
        side: BorderSide(
          color: selected ? NusaColors.primary : NusaColors.outline,
        ),
        borderRadius: BorderRadius.circular(14),
      ),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(8, 7, 10, 10),
        child: Column(
          children: [
            CheckboxListTile(
              key: Key('class-assessment-class-${schoolClass.id}'),
              contentPadding: EdgeInsets.zero,
              dense: true,
              value: selected,
              onChanged: (value) => onSelected(value ?? false),
              title: Text(
                schoolClass.name,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: const Text('Siswa aktif menjadi peserta otomatis.'),
            ),
            if (selected)
              NusaDropdownField<String>(
                fieldKey: Key('class-assessment-component-${schoolClass.id}'),
                value: validValue,
                decoration: const InputDecoration(
                  labelText: 'Masuk ke nilai',
                  prefixIcon: Icon(Icons.fact_check_outlined),
                ),
                options: [
                  const NusaDropdownOption(
                    value: 'baru',
                    label: 'Buat komponen Sumatif baru',
                  ),
                  for (final item in components)
                    NusaDropdownOption(value: '${item.id}', label: item.name),
                ],
                onChanged: onComponent,
              ),
          ],
        ),
      ),
    );
  }
}

class _DateTimeField extends StatelessWidget {
  const _DateTimeField({
    required this.fieldKey,
    required this.label,
    required this.value,
    required this.onTap,
  });
  final Key fieldKey;
  final String label;
  final DateTime value;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => InkWell(
    key: fieldKey,
    onTap: onTap,
    borderRadius: BorderRadius.circular(14),
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: const Icon(Icons.event_rounded),
      ),
      child: Text(
        '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}\n${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}',
        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
      ),
    ),
  );
}

class _Toggle extends StatelessWidget {
  const _Toggle({
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
    this.divider = true,
  });
  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;
  final bool divider;
  @override
  Widget build(BuildContext context) => Column(
    children: [
      SwitchListTile.adaptive(
        contentPadding: EdgeInsets.zero,
        value: value,
        onChanged: onChanged,
        title: Text(
          title,
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
        ),
        subtitle: Text(subtitle, style: const TextStyle(fontSize: 10)),
      ),
      if (divider) const Divider(height: 1),
    ],
  );
}

class _EmptyHint extends StatelessWidget {
  const _EmptyHint({required this.text});
  final String text;
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(15),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(13),
    ),
    child: Text(
      text,
      textAlign: TextAlign.center,
      style: const TextStyle(color: NusaColors.textSecondary, fontSize: 11),
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

String _dateTimePayload(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')} ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}:00';
String? _optional(String value) {
  final text = value.trim();
  return text.isEmpty ? null : text;
}

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((items) => items).join('\n'),
  AppException exception => exception.message,
  _ => 'Asesmen kelas belum dapat diproses.',
};
