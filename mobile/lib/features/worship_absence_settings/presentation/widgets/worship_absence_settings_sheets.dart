import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_absence_settings/domain/worship_absence_settings.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class WorshipAbsenceLimitSheet extends StatefulWidget {
  const WorshipAbsenceLimitSheet({required this.settings, super.key});

  final WorshipAbsenceSettings settings;

  @override
  State<WorshipAbsenceLimitSheet> createState() =>
      _WorshipAbsenceLimitSheetState();
}

class _WorshipAbsenceLimitSheetState extends State<WorshipAbsenceLimitSheet> {
  late final TextEditingController _daysController;
  late bool _active;
  String? _error;

  @override
  void initState() {
    super.initState();
    _daysController = TextEditingController(
      text: '${widget.settings.confirmationDayLimit}',
    );
    _active = widget.settings.active;
  }

  @override
  void dispose() {
    _daysController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 42,
            height: 4,
            decoration: BoxDecoration(
              color: NusaColors.outline,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
          const SizedBox(height: 16),
          const Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'Batas Konfirmasi Privat',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Batas ini hanya menjadi pengingat untuk guru pendamping. '
            'Siswi tidak otomatis dianggap melakukan pelanggaran.',
            style: TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 12,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 18),
          TextField(
            key: const Key('worship-absence-limit-days'),
            controller: _daysController,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            decoration: const InputDecoration(
              labelText: 'Batas hari kalender',
              prefixIcon: Icon(Icons.date_range_outlined),
              helperText: 'Nilai yang diperbolehkan 1 sampai 30 hari.',
            ),
          ),
          const SizedBox(height: 8),
          SwitchListTile.adaptive(
            key: const Key('worship-absence-limit-active'),
            contentPadding: EdgeInsets.zero,
            title: const Text('Pengingat aktif'),
            subtitle: const Text(
              'Pantau batas berhalangan pada tahun pelajaran aktif.',
            ),
            value: _active,
            onChanged: (value) => setState(() => _active = value),
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                _error!,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontSize: 12,
                ),
              ),
            ),
          ],
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              key: const Key('save-worship-absence-limit'),
              onPressed: _submit,
              icon: const Icon(Icons.save_outlined),
              label: const Text('Simpan Pengaturan'),
            ),
          ),
        ],
      ),
    ),
  );

  void _submit() {
    final days = int.tryParse(_daysController.text.trim());
    if (days == null || days < 1 || days > 30) {
      setState(() => _error = 'Batas konfirmasi harus 1 sampai 30 hari.');
      return;
    }
    Navigator.pop(
      context,
      WorshipAbsenceSettingsValue(confirmationDayLimit: days, active: _active),
    );
  }
}

class WorshipCompanionFormSheet extends StatefulWidget {
  const WorshipCompanionFormSheet({
    required this.page,
    this.existing,
    super.key,
  });

  final WorshipAbsenceSettingsPage page;
  final WorshipCompanionAssignment? existing;

  @override
  State<WorshipCompanionFormSheet> createState() =>
      _WorshipCompanionFormSheetState();
}

class _WorshipCompanionFormSheetState extends State<WorshipCompanionFormSheet> {
  int? _employeeId;
  late bool _allClasses;
  late final Set<int> _classIds;
  String? _error;

  bool get _editing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _employeeId = existing?.employeeId;
    _allClasses = existing?.allClasses ?? true;
    _classIds = existing?.classes.map((item) => item.id).toSet() ?? <int>{};
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.82).clamp(500.0, 760.0),
      child: Column(
        children: [
          const SizedBox(height: 10),
          Container(
            width: 42,
            height: 4,
            decoration: BoxDecoration(
              color: NusaColors.outline,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 8, 10),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    _editing ? 'Atur Ulang Pendamping' : 'Tambah Pendamping',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-worship-companion-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('worship-companion-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                if (_editing)
                  _SelectedEmployee(assignment: widget.existing!)
                else
                  NusaDropdownField<int>(
                    fieldKey: const Key('worship-companion-employee'),
                    value: _employeeId,
                    options: widget.page.employees
                        .map(
                          (item) => NusaDropdownOption(
                            value: item.id,
                            label:
                                '${item.name}${item.accountActive ? '' : ' · Akun belum aktif'}',
                          ),
                        )
                        .toList(growable: false),
                    decoration: const InputDecoration(
                      labelText: 'Guru pendamping perempuan',
                      prefixIcon: Icon(Icons.person_outline_rounded),
                    ),
                    onChanged: (value) => setState(() => _employeeId = value),
                  ),
                const SizedBox(height: 16),
                SwitchListTile.adaptive(
                  key: const Key('worship-companion-all-classes'),
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Mendampingi seluruh kelas'),
                  subtitle: Text(
                    _allClasses
                        ? 'Guru dapat menerima tindak lanjut dari seluruh kelas.'
                        : 'Pilih kelas yang menjadi tanggung jawab guru.',
                  ),
                  value: _allClasses,
                  onChanged: (value) => setState(() => _allClasses = value),
                ),
                if (!_allClasses) ...[
                  const SizedBox(height: 10),
                  const Text(
                    'Pilih kelas',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 7,
                    runSpacing: 7,
                    children: widget.page.classes
                        .map(
                          (item) => FilterChip(
                            key: Key('worship-companion-class-${item.id}'),
                            label: Text(item.name),
                            selected: _classIds.contains(item.id),
                            onSelected: (selected) => setState(() {
                              if (selected) {
                                _classIds.add(item.id);
                              } else {
                                _classIds.remove(item.id);
                              }
                            }),
                          ),
                        )
                        .toList(growable: false),
                  ),
                ],
                const SizedBox(height: 18),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: NusaColors.accent.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        Icons.lock_outline_rounded,
                        size: 19,
                        color: NusaColors.primary,
                      ),
                      SizedBox(width: 9),
                      Expanded(
                        child: Text(
                          'Informasi berhalangan bersifat privat dan hanya ditindaklanjuti oleh pendamping yang ditugaskan.',
                          style: TextStyle(fontSize: 11.5, height: 1.35),
                        ),
                      ),
                    ],
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontSize: 12,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('save-worship-companion'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: const Text('Simpan Pendamping'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  void _submit() {
    if (_employeeId == null) {
      setState(() => _error = 'Pilih guru pendamping perempuan.');
      return;
    }
    if (!_allClasses && _classIds.isEmpty) {
      setState(
        () => _error =
            'Pilih sedikitnya satu kelas atau gunakan cakupan seluruh kelas.',
      );
      return;
    }
    Navigator.pop(
      context,
      WorshipCompanionAssignmentValue(
        employeeId: _employeeId!,
        allClasses: _allClasses,
        classIds: _classIds.toList(growable: false),
      ),
    );
  }
}

class _SelectedEmployee extends StatelessWidget {
  const _SelectedEmployee({required this.assignment});

  final WorshipCompanionAssignment assignment;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: NusaColors.primary.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Row(
      children: [
        const Icon(Icons.person_outline_rounded, color: NusaColors.primary),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                assignment.employeeName,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              if (assignment.employeeNip?.isNotEmpty == true)
                Text(
                  assignment.employeeNip!,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 11,
                  ),
                ),
            ],
          ),
        ),
      ],
    ),
  );
}
