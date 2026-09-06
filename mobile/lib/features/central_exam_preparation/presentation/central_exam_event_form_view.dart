import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_preparation/application/central_exam_preparation_controller.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class CentralExamEventFormView extends ConsumerStatefulWidget {
  const CentralExamEventFormView({
    this.eventId,
    this.initialReferences,
    super.key,
  });

  final int? eventId;
  final CentralExamPreparationReferences? initialReferences;

  @override
  ConsumerState<CentralExamEventFormView> createState() =>
      _CentralExamEventFormViewState();
}

class _CentralExamEventFormViewState
    extends ConsumerState<CentralExamEventFormView> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _notes = TextEditingController();
  int? _examTypeId;
  int? _academicYearId;
  String _semester = 'ganjil';
  String _status = 'draft';
  late DateTime _startsOn;
  late DateTime _endsOn;
  bool _initialized = false;
  bool _saving = false;

  bool get _editing => widget.eventId != null;

  @override
  void initState() {
    super.initState();
    final today = DateUtils.dateOnly(DateTime.now());
    _startsOn = today;
    _endsOn = today.add(const Duration(days: 5));
  }

  @override
  void dispose() {
    _name.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final listState = ref.watch(centralExamPreparationControllerProvider);
    final detailState = _editing
        ? ref.watch(centralExamPreparationDetailProvider(widget.eventId!))
        : null;
    final references = _editing
        ? detailState?.value?.references
        : widget.initialReferences ?? listState.value?.references;
    final event = detailState?.value?.event;
    if (references != null && (!_editing || event != null)) {
      _initialize(references, event);
    }
    final error = _editing ? detailState?.error : listState.error;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: Text(_editing ? 'Ubah Informasi Ujian' : 'Buat Ujian Terpusat'),
      ),
      body: references == null || (_editing && event == null)
          ? error == null
                ? const Center(child: CircularProgressIndicator())
                : _ErrorState(message: _message(error), onRetry: _retry)
          : _content(references),
      bottomNavigationBar: references == null || (_editing && event == null)
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
                  key: const Key('central-exam-event-save'),
                  onPressed: _saving ? null : _save,
                  icon: _saving
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.save_rounded),
                  label: Text(_editing ? 'Simpan Perubahan' : 'Buat Kegiatan'),
                ),
              ),
            ),
    );
  }

  Widget _content(CentralExamPreparationReferences references) => Form(
    key: _formKey,
    child: ListView(
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
      children: [
        const _InfoBanner(),
        const SizedBox(height: 11),
        _SectionCard(
          title: '1. Identitas kegiatan',
          subtitle:
              'Mata pelajaran dan paket soal ditambahkan pada tahap jadwal.',
          child: Column(
            children: [
              NusaTextField(
                fieldKey: const Key('central-exam-event-name'),
                controller: _name,
                hintText: 'Contoh: Sumatif Akhir Semester Ganjil 2026/2027',
                labelText: 'Nama kegiatan',
                prefixIcon: Icons.account_tree_rounded,
                validator: (value) => value?.trim().isEmpty == true
                    ? 'Nama kegiatan wajib diisi.'
                    : null,
              ),
              const SizedBox(height: 10),
              NusaDropdownField<int>(
                fieldKey: const Key('central-exam-event-type'),
                value: _examTypeId,
                decoration: const InputDecoration(
                  labelText: 'Jenis ujian',
                  hintText: 'Pilih jenis ujian',
                  prefixIcon: Icon(Icons.quiz_outlined),
                ),
                options: [
                  for (final item in references.examTypes)
                    NusaDropdownOption(value: item.id, label: item.name),
                ],
                onChanged: (value) => setState(() => _examTypeId = value),
              ),
              const SizedBox(height: 10),
              NusaDropdownField<int>(
                fieldKey: const Key('central-exam-event-year'),
                value: _academicYearId,
                decoration: const InputDecoration(
                  labelText: 'Tahun pelajaran',
                  hintText: 'Pilih tahun pelajaran',
                  prefixIcon: Icon(Icons.school_outlined),
                ),
                options: [
                  for (final item in references.academicYears)
                    NusaDropdownOption(
                      value: item.id,
                      label: '${item.name}${item.active ? ' · Aktif' : ''}',
                    ),
                ],
                onChanged: (value) => setState(() => _academicYearId = value),
              ),
              const SizedBox(height: 10),
              NusaDropdownField<String>(
                fieldKey: const Key('central-exam-event-semester'),
                value: _semester,
                decoration: const InputDecoration(
                  labelText: 'Semester',
                  prefixIcon: Icon(Icons.calendar_view_month_outlined),
                ),
                options: const [
                  NusaDropdownOption(value: 'ganjil', label: 'Ganjil'),
                  NusaDropdownOption(value: 'genap', label: 'Genap'),
                ],
                onChanged: (value) {
                  if (value != null) setState(() => _semester = value);
                },
              ),
            ],
          ),
        ),
        const SizedBox(height: 11),
        _SectionCard(
          title: '2. Periode dan status',
          subtitle: 'Gunakan Persiapan hingga panitia, sesi, dan ruang siap.',
          child: Column(
            children: [
              Row(
                children: [
                  Expanded(
                    child: _DateField(
                      fieldKey: const Key('central-exam-event-start'),
                      label: 'Tanggal mulai',
                      value: _startsOn,
                      onTap: () => _pickDate(true),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _DateField(
                      fieldKey: const Key('central-exam-event-end'),
                      label: 'Tanggal selesai',
                      value: _endsOn,
                      onTap: () => _pickDate(false),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              NusaDropdownField<String>(
                fieldKey: const Key('central-exam-event-status'),
                value: _status,
                decoration: const InputDecoration(
                  labelText: 'Status kegiatan',
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
            ],
          ),
        ),
        const SizedBox(height: 11),
        _SectionCard(
          title: 'Catatan panitia',
          subtitle: 'Opsional dan dapat diperbarui kembali.',
          child: TextFormField(
            key: const Key('central-exam-event-notes'),
            controller: _notes,
            minLines: 3,
            maxLines: 6,
            maxLength: 2000,
            textCapitalization: TextCapitalization.sentences,
            decoration: const InputDecoration(
              labelText: 'Keterangan',
              alignLabelWithHint: true,
              prefixIcon: Icon(Icons.notes_rounded),
            ),
          ),
        ),
      ],
    ),
  );

  void _initialize(
    CentralExamPreparationReferences references,
    CentralExamEvent? event,
  ) {
    if (_initialized) return;
    _initialized = true;
    if (event != null) {
      _name.text = event.name;
      _notes.text = event.notes ?? '';
      _examTypeId = event.examTypeId;
      _academicYearId = event.academicYearId;
      _semester = event.semester;
      _status = event.status;
      _startsOn = event.startsOn ?? _startsOn;
      _endsOn = event.endsOn ?? _endsOn;
      return;
    }
    _examTypeId = references.examTypes.firstOrNull?.id;
    _academicYearId = references.academicYears
        .where((item) => item.active)
        .firstOrNull
        ?.id;
    _academicYearId ??= references.academicYears.firstOrNull?.id;
  }

  Future<void> _pickDate(bool start) async {
    final selected = await showDatePicker(
      context: context,
      initialDate: start ? _startsOn : _endsOn,
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
      helpText: start ? 'Pilih tanggal mulai' : 'Pilih tanggal selesai',
    );
    if (selected == null || !mounted) return;
    setState(() {
      if (start) {
        _startsOn = selected;
        if (_endsOn.isBefore(selected)) _endsOn = selected;
      } else {
        _endsOn = selected;
      }
    });
  }

  Future<void> _save() async {
    if (_formKey.currentState?.validate() != true) return;
    if (_examTypeId == null || _academicYearId == null) {
      _showError('Jenis ujian dan tahun pelajaran wajib dipilih.');
      return;
    }
    if (_endsOn.isBefore(_startsOn)) {
      _showError('Tanggal selesai tidak boleh sebelum tanggal mulai.');
      return;
    }
    final value = CentralExamEventFormValue(
      examTypeId: _examTypeId!,
      academicYearId: _academicYearId!,
      name: _name.text.trim(),
      semester: _semester,
      startsOn: _startsOn,
      endsOn: _endsOn,
      status: _status,
      notes: _notes.text.trim(),
    );
    setState(() => _saving = true);
    try {
      final actions = ref.read(centralExamPreparationActionsProvider);
      if (_editing) {
        await actions.updateEvent(widget.eventId!, value);
        if (mounted) context.pop(true);
      } else {
        final id = await actions.createEvent(value);
        if (mounted) context.replace('/ujian-terpusat/$id');
      }
    } catch (error) {
      if (mounted) _showError(_message(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _retry() {
    if (_editing) {
      ref.invalidate(centralExamPreparationDetailProvider(widget.eventId!));
    } else {
      ref.invalidate(centralExamPreparationControllerProvider);
    }
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _InfoBanner extends StatelessWidget {
  const _InfoBanner();
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: const Row(
      children: [
        Icon(Icons.info_outline_rounded, color: NusaColors.accent),
        SizedBox(width: 10),
        Expanded(
          child: Text(
            'Tahap 1 dari alur Ujian Terpusat. Setelah disimpan, lanjutkan Panitia, Sesi, dan Ruang.',
            style: TextStyle(color: Colors.white, fontSize: 11.5, height: 1.4),
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
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 2),
          Text(
            subtitle,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
            ),
          ),
          const SizedBox(height: 13),
          child,
        ],
      ),
    ),
  );
}

class _DateField extends StatelessWidget {
  const _DateField({
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
    borderRadius: BorderRadius.circular(14),
    onTap: onTap,
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: const Icon(Icons.event_outlined),
      ),
      child: Text(_date(value), style: const TextStyle(fontSize: 12)),
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
          const Icon(Icons.cloud_off_rounded, size: 52),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton(onPressed: onRetry, child: const Text('Coba Lagi')),
        ],
      ),
    ),
  );
}

String _date(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}';
String _message(Object error) => error is AppException
    ? error.message
    : 'Data persiapan Ujian Terpusat belum dapat diproses.';
