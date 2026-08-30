import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/violation_process_deadline/domain/violation_process_deadline.dart';

class ViolationProcessDeadlineFormSheet extends StatefulWidget {
  const ViolationProcessDeadlineFormSheet({required this.deadline, super.key});

  final ViolationProcessDeadline deadline;

  @override
  State<ViolationProcessDeadlineFormSheet> createState() =>
      _ViolationProcessDeadlineFormSheetState();
}

class _ViolationProcessDeadlineFormSheetState
    extends State<ViolationProcessDeadlineFormSheet> {
  late final TextEditingController _counselingController;
  late final TextEditingController _approvalController;
  late final TextEditingController _reminderController;
  late bool _reminderActive;
  late bool _overdueActive;
  String? _error;

  @override
  void initState() {
    super.initState();
    _counselingController = TextEditingController(
      text: '${widget.deadline.counselingDays}',
    );
    _approvalController = TextEditingController(
      text: '${widget.deadline.approvalDays}',
    );
    _reminderController = TextEditingController(
      text: '${widget.deadline.reminderDaysBefore}',
    );
    _reminderActive = widget.deadline.reminderNotificationActive;
    _overdueActive = widget.deadline.overdueNotificationActive;
  }

  @override
  void dispose() {
    _counselingController.dispose();
    _approvalController.dispose();
    _reminderController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.86).clamp(540.0, 740.0),
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
            padding: const EdgeInsets.fromLTRB(16, 13, 8, 9),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Atur Batas Proses',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        'Tahun ${widget.deadline.academicYear.name}',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  key: const Key('close-violation-deadline-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('violation-deadline-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                Container(
                  padding: const EdgeInsets.all(11),
                  decoration: BoxDecoration(
                    color: NusaColors.accent.withValues(alpha: 0.13),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.history_toggle_off_rounded, size: 18),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Gunakan hari kalender. Pengaturan baru tidak menggeser tenggat laporan yang sudah berjalan.',
                          style: TextStyle(fontSize: 11.5, height: 1.35),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                _DeadlineSection(
                  icon: Icons.psychology_alt_outlined,
                  title: 'Pemeriksaan BK',
                  description: 'Sejak laporan dibuat atau dikembalikan untuk diperiksa ulang.',
                  child: _NumberField(
                    fieldKey: const Key('violation-deadline-counseling-days'),
                    controller: _counselingController,
                    label: 'Batas pemeriksaan',
                    suffix: 'hari',
                  ),
                ),
                const SizedBox(height: 11),
                _DeadlineSection(
                  icon: Icons.approval_outlined,
                  title: 'Pengesahan Wakil Kesiswaan',
                  description:
                      'Sejak rekomendasi pelanggaran berpoin dikirim oleh BK.',
                  child: _NumberField(
                    fieldKey: const Key('violation-deadline-approval-days'),
                    controller: _approvalController,
                    label: 'Batas pengesahan',
                    suffix: 'hari',
                  ),
                ),
                const SizedBox(height: 11),
                _DeadlineSection(
                  icon: Icons.notifications_active_outlined,
                  title: 'Pengingat otomatis',
                  description:
                      'Isi 0 untuk mengingatkan pada hari jatuh tempo.',
                  child: _NumberField(
                    fieldKey: const Key('violation-deadline-reminder-days'),
                    controller: _reminderController,
                    label: 'Sebelum batas',
                    suffix: 'hari',
                  ),
                ),
                const SizedBox(height: 11),
                SwitchListTile.adaptive(
                  key: const Key('violation-deadline-reminder-active'),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12),
                  shape: RoundedRectangleBorder(
                    side: const BorderSide(color: NusaColors.outline),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  title: const Text('Pengingat jatuh tempo'),
                  subtitle: const Text(
                    'Dikirim satu kali kepada petugas tahap tersebut.',
                  ),
                  value: _reminderActive,
                  onChanged: (value) => setState(() => _reminderActive = value),
                ),
                const SizedBox(height: 9),
                SwitchListTile.adaptive(
                  key: const Key('violation-deadline-overdue-active'),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12),
                  shape: RoundedRectangleBorder(
                    side: const BorderSide(color: NusaColors.outline),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  title: const Text('Pemberitahuan keterlambatan'),
                  subtitle: const Text(
                    'Dikirim satu kali setelah tenggat terlewati.',
                  ),
                  value: _overdueActive,
                  onChanged: (value) => setState(() => _overdueActive = value),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(
                    _error!,
                    key: const Key('violation-deadline-form-error'),
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
                key: const Key('save-violation-deadline'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: const Text('Simpan Pengaturan'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  void _submit() {
    final counseling = int.tryParse(_counselingController.text.trim());
    final approval = int.tryParse(_approvalController.text.trim());
    final reminder = int.tryParse(_reminderController.text.trim());

    if (counseling == null || counseling < 1 || counseling > 30) {
      _setError('Batas pemeriksaan BK harus antara 1–30 hari.');
      return;
    }
    if (approval == null || approval < 1 || approval > 30) {
      _setError('Batas pengesahan Wakil harus antara 1–30 hari.');
      return;
    }
    if (reminder == null || reminder < 0 || reminder > 29) {
      _setError('Pengingat harus antara 0–29 hari.');
      return;
    }
    if (reminder >= (counseling < approval ? counseling : approval)) {
      _setError('Pengingat harus lebih kecil daripada batas hari terpendek.');
      return;
    }

    Navigator.pop(
      context,
      ViolationProcessDeadlineFormValue(
        counselingDays: counseling,
        approvalDays: approval,
        reminderDaysBefore: reminder,
        reminderNotificationActive: _reminderActive,
        overdueNotificationActive: _overdueActive,
      ),
    );
  }

  void _setError(String message) => setState(() => _error = message);
}

class _DeadlineSection extends StatelessWidget {
  const _DeadlineSection({
    required this.icon,
    required this.title,
    required this.description,
    required this.child,
  });

  final IconData icon;
  final String title;
  final String description;
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: NusaColors.surface,
      border: Border.all(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, size: 20, color: NusaColors.primary),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                title,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          ],
        ),
        const SizedBox(height: 5),
        Text(
          description,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 10.5,
            height: 1.35,
          ),
        ),
        const SizedBox(height: 9),
        child,
      ],
    ),
  );
}

class _NumberField extends StatelessWidget {
  const _NumberField({
    required this.fieldKey,
    required this.controller,
    required this.label,
    required this.suffix,
  });

  final Key fieldKey;
  final TextEditingController controller;
  final String label;
  final String suffix;

  @override
  Widget build(BuildContext context) => TextField(
    key: fieldKey,
    controller: controller,
    keyboardType: TextInputType.number,
    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
    decoration: InputDecoration(labelText: label, suffixText: suffix),
  );
}
