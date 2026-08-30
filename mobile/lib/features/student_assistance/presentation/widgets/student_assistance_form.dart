import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_assistance/domain/student_assistance.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentAssistanceForm extends StatelessWidget {
  const StudentAssistanceForm({
    required this.types,
    required this.officers,
    required this.type,
    required this.officerId,
    required this.date,
    required this.noteController,
    required this.loading,
    required this.buttonLabel,
    required this.onTypeChanged,
    required this.onOfficerChanged,
    required this.onPickDate,
    required this.onSubmit,
    this.statuses = const [],
    this.status,
    this.resultController,
    this.onStatusChanged,
    super.key,
  });

  final List<CodeLabelOption> types;
  final List<PersonOption> officers;
  final List<CodeLabelOption> statuses;
  final String type;
  final int? officerId;
  final String date;
  final String? status;
  final TextEditingController noteController;
  final TextEditingController? resultController;
  final bool loading;
  final String buttonLabel;
  final ValueChanged<String> onTypeChanged;
  final ValueChanged<int> onOfficerChanged;
  final ValueChanged<String>? onStatusChanged;
  final VoidCallback onPickDate;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      NusaDropdownField<String>(
        fieldKey: const Key('student-assistance-type'),
        value: type,
        enabled: !loading,
        decoration: const InputDecoration(
          labelText: 'Jenis pendampingan',
          prefixIcon: Icon(Icons.psychology_alt_rounded),
        ),
        options: [
          for (final item in types)
            NusaDropdownOption(value: item.code, label: item.label),
        ],
        onChanged: (value) {
          if (value != null) onTypeChanged(value);
        },
      ),
      const SizedBox(height: 11),
      NusaDropdownField<int>(
        fieldKey: const Key('student-assistance-officer'),
        value: officerId,
        enabled: !loading,
        decoration: const InputDecoration(
          labelText: 'Petugas penanggung jawab',
          prefixIcon: Icon(Icons.supervisor_account_rounded),
        ),
        options: [
          for (final item in officers)
            NusaDropdownOption(
              value: item.id,
              label:
                  '${item.name}${_filled(item.position) ? ' · ${item.position}' : ''}',
            ),
        ],
        onChanged: (value) {
          if (value != null) onOfficerChanged(value);
        },
      ),
      const SizedBox(height: 11),
      InkWell(
        key: const Key('student-assistance-date'),
        borderRadius: BorderRadius.circular(14),
        onTap: loading ? null : onPickDate,
        child: InputDecorator(
          decoration: const InputDecoration(
            labelText: 'Tanggal pendampingan',
            prefixIcon: Icon(Icons.event_rounded),
            suffixIcon: Icon(Icons.calendar_month_rounded),
          ),
          child: Text(_dateLabel(date)),
        ),
      ),
      if (statuses.isNotEmpty) ...[
        const SizedBox(height: 11),
        NusaDropdownField<String>(
          fieldKey: const Key('student-assistance-status'),
          value: status,
          enabled: !loading,
          decoration: const InputDecoration(
            labelText: 'Status pendampingan',
            prefixIcon: Icon(Icons.flag_circle_rounded),
          ),
          options: [
            for (final item in statuses)
              NusaDropdownOption(value: item.code, label: item.label),
          ],
          onChanged: (value) {
            if (value != null) onStatusChanged?.call(value);
          },
        ),
      ],
      const SizedBox(height: 11),
      TextField(
        key: const Key('student-assistance-note'),
        controller: noteController,
        enabled: !loading,
        minLines: 4,
        maxLines: 7,
        textCapitalization: TextCapitalization.sentences,
        decoration: const InputDecoration(
          labelText: 'Catatan singkat',
          hintText: 'Tindakan yang dilakukan atau rencana pertemuan',
          alignLabelWithHint: true,
          prefixIcon: Icon(Icons.notes_rounded),
        ),
      ),
      if (resultController != null) ...[
        const SizedBox(height: 11),
        TextField(
          key: const Key('student-assistance-result'),
          controller: resultController,
          enabled: !loading,
          minLines: 4,
          maxLines: 7,
          textCapitalization: TextCapitalization.sentences,
          decoration: InputDecoration(
            labelText: status == 'selesai'
                ? 'Hasil penanganan (wajib)'
                : 'Hasil sementara (opsional)',
            alignLabelWithHint: true,
            prefixIcon: const Icon(Icons.task_alt_rounded),
          ),
        ),
      ],
      const SizedBox(height: 16),
      Container(
        padding: const EdgeInsets.all(11),
        decoration: BoxDecoration(
          color: NusaColors.surfaceBlue,
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Text(
          'Pendampingan membantu perubahan perilaku dan tidak otomatis menambah poin siswa.',
          style: TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 10.5,
            height: 1.4,
          ),
        ),
      ),
      const SizedBox(height: 14),
      NusaPrimaryButton(
        key: const Key('student-assistance-submit'),
        label: buttonLabel,
        loading: loading,
        onPressed: onSubmit,
      ),
    ],
  );
}

bool _filled(String? value) => value != null && value.trim().isNotEmpty;
String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  return date == null
      ? value
      : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}
