import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_assistance/application/student_assistance_controller.dart';
import 'package:nusa/features/student_assistance/domain/student_assistance.dart';
import 'package:nusa/features/student_assistance/presentation/widgets/student_assistance_form.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentAssistanceCreateView extends ConsumerStatefulWidget {
  const StudentAssistanceCreateView({
    this.academicYearId,
    this.classId,
    super.key,
  });

  final int? academicYearId;
  final int? classId;

  @override
  ConsumerState<StudentAssistanceCreateView> createState() =>
      _StudentAssistanceCreateViewState();
}

class _StudentAssistanceCreateViewState
    extends ConsumerState<StudentAssistanceCreateView> {
  final _searchController = TextEditingController();
  final _noteController = TextEditingController();
  String _query = '';
  int? _studentId;
  int? _officerId;
  String _type = 'konseling';
  late String _date = _isoDate(DateTime.now());
  bool _submitting = false;

  @override
  void dispose() {
    _searchController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final key = (
      query: _query,
      academicYearId: widget.academicYearId,
      classId: widget.classId,
    );
    final reference = ref.watch(studentAssistanceReferenceProvider(key));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(title: const Text('Mulai Pendampingan')),
      body: SafeArea(
        top: false,
        child: reference.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _Error(
            message: _message(error),
            onRetry: () =>
                ref.invalidate(studentAssistanceReferenceProvider(key)),
          ),
          data: (data) => SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 26),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const _Intro(),
                const SizedBox(height: 12),
                Card(
                  margin: EdgeInsets.zero,
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        TextField(
                          key: const Key('student-assistance-student-search'),
                          controller: _searchController,
                          enabled: !_submitting,
                          textInputAction: TextInputAction.search,
                          onSubmitted: (value) => setState(() {
                            _query = value.trim();
                            _studentId = null;
                          }),
                          decoration: InputDecoration(
                            labelText: 'Cari siswa',
                            hintText: 'Nama, NIS, atau NISN',
                            prefixIcon: const Icon(Icons.search_rounded),
                            suffixIcon: IconButton(
                              tooltip: 'Cari',
                              onPressed: _submitting
                                  ? null
                                  : () => setState(() {
                                      _query = _searchController.text.trim();
                                      _studentId = null;
                                    }),
                              icon: const Icon(Icons.arrow_forward_rounded),
                            ),
                          ),
                        ),
                        const SizedBox(height: 11),
                        NusaDropdownField<int>(
                          fieldKey: const Key('student-assistance-student'),
                          value: _studentId,
                          enabled: !_submitting,
                          decoration: const InputDecoration(
                            labelText: 'Siswa yang didampingi',
                            hintText: 'Pilih siswa',
                            prefixIcon: Icon(Icons.person_search_rounded),
                          ),
                          options: [
                            for (final item in data.students)
                              NusaDropdownOption(
                                value: item.person.id,
                                label:
                                    '${item.person.name} · ${item.schoolClass?.name ?? '-'}${item.hasActiveAssistance ? ' · Sedang didampingi' : ''}',
                                enabled: !item.hasActiveAssistance,
                              ),
                          ],
                          onChanged: (value) =>
                              setState(() => _studentId = value),
                        ),
                        if (data.students.isEmpty) ...[
                          const SizedBox(height: 8),
                          const Text(
                            'Siswa tidak ditemukan dalam cakupan tugas dan filter aktif.',
                            style: TextStyle(
                              color: NusaColors.textSecondary,
                              fontSize: 10.5,
                            ),
                          ),
                        ],
                        const SizedBox(height: 14),
                        StudentAssistanceForm(
                          types: data.types,
                          officers: data.officers,
                          type: _type,
                          officerId: _officerId,
                          date: _date,
                          noteController: _noteController,
                          loading: _submitting,
                          buttonLabel: 'Mulai Pendampingan',
                          onTypeChanged: (value) =>
                              setState(() => _type = value),
                          onOfficerChanged: (value) =>
                              setState(() => _officerId = value),
                          onPickDate: _pickDate,
                          onSubmit: () => _submit(data),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _pickDate() async {
    final current = DateTime.tryParse(_date) ?? DateTime.now();
    final result = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime(current.year - 2),
      lastDate: DateTime(current.year + 2),
    );
    if (result != null) setState(() => _date = _isoDate(result));
  }

  Future<void> _submit(StudentAssistanceReference reference) async {
    final studentId = _studentId;
    final officerId = _officerId;
    final academicYearId = reference.academicYearId;
    if (studentId == null || officerId == null || academicYearId == null) {
      _snack('Pilih siswa, petugas, dan tahun pelajaran terlebih dahulu.');
      return;
    }
    if (_noteController.text.trim().isEmpty) {
      _snack('Catatan pendampingan wajib diisi.');
      return;
    }
    setState(() => _submitting = true);
    try {
      final detail = await ref
          .read(studentAssistanceActionsProvider)
          .create(
            StudentAssistancePayload(
              studentId: studentId,
              academicYearId: academicYearId,
              type: _type,
              officerId: officerId,
              date: _date,
              note: _noteController.text.trim(),
            ),
          );
      ref.invalidate(studentAssistanceControllerProvider);
      if (mounted) {
        _snack('Pendampingan siswa berhasil dimulai.');
        context.go('/pendampingan-siswa/${detail.item.id}');
      }
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _Intro extends StatelessWidget {
  const _Intro();
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: const Row(
      children: [
        Icon(Icons.handshake_rounded, color: NusaColors.accent, size: 32),
        SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Mulai bantuan yang terarah',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                ),
              ),
              SizedBox(height: 3),
              Text(
                'Satu siswa hanya dapat memiliki satu pendampingan aktif pada tahun pelajaran yang sama.',
                style: TextStyle(
                  color: Colors.white70,
                  fontSize: 10,
                  height: 1.4,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48),
          const SizedBox(height: 10),
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

String _isoDate(DateTime date) =>
    '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Pendampingan siswa belum dapat disimpan.',
};
