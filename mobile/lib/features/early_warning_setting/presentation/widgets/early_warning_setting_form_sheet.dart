import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/early_warning_setting/domain/early_warning_setting.dart';

class EarlyWarningSettingFormSheet extends StatefulWidget {
  const EarlyWarningSettingFormSheet({required this.setting, super.key});

  final EarlyWarningSetting setting;

  @override
  State<EarlyWarningSettingFormSheet> createState() =>
      _EarlyWarningSettingFormSheetState();
}

class _EarlyWarningSettingFormSheetState
    extends State<EarlyWarningSettingFormSheet> {
  late bool _detectionActive;
  late bool _notificationActive;
  late final TextEditingController _percentageController;
  late final TextEditingController _violationCountController;
  late final TextEditingController _violationDaysController;
  late final TextEditingController _lateCountController;
  late final TextEditingController _lateDaysController;
  String? _error;

  @override
  void initState() {
    super.initState();
    final setting = widget.setting;
    _detectionActive = setting.detectionActive;
    _notificationActive = setting.notificationActive;
    _percentageController = TextEditingController(
      text: '${setting.nearThresholdPercentage}',
    );
    _violationCountController = TextEditingController(
      text: '${setting.repeatedViolationCount}',
    );
    _violationDaysController = TextEditingController(
      text: '${setting.violationPeriodDays}',
    );
    _lateCountController = TextEditingController(
      text: '${setting.repeatedLateCount}',
    );
    _lateDaysController = TextEditingController(
      text: '${setting.latePeriodDays}',
    );
  }

  @override
  void dispose() {
    _percentageController.dispose();
    _violationCountController.dispose();
    _violationDaysController.dispose();
    _lateCountController.dispose();
    _lateDaysController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.92).clamp(580.0, 820.0),
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
                        'Atur Peringatan Dini',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        'Tahun ${widget.setting.academicYear.name}',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  key: const Key('close-early-warning-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('early-warning-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                SwitchListTile.adaptive(
                  key: const Key('early-warning-form-detection-active'),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12),
                  shape: RoundedRectangleBorder(
                    side: const BorderSide(color: NusaColors.outline),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  title: const Text('Deteksi otomatis aktif'),
                  subtitle: const Text(
                    'Proses berkala memeriksa pemicu pada tahun ini.',
                  ),
                  value: _detectionActive,
                  onChanged: (value) =>
                      setState(() => _detectionActive = value),
                ),
                const SizedBox(height: 9),
                SwitchListTile.adaptive(
                  key: const Key('early-warning-form-notification-active'),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12),
                  shape: RoundedRectangleBorder(
                    side: const BorderSide(color: NusaColors.outline),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  title: const Text('Kirim notifikasi'),
                  subtitle: const Text(
                    'Kepada BK, wali kelas, guru wali, dan pimpinan terkait.',
                  ),
                  value: _notificationActive,
                  onChanged: _detectionActive
                      ? (value) => setState(() => _notificationActive = value)
                      : null,
                ),
                const SizedBox(height: 14),
                _SettingSection(
                  icon: Icons.trending_up_rounded,
                  title: 'Mendekati ambang sanksi',
                  description: 'Peringatan muncul saat saldo poin mencapai persentase dari ambang sanksi berikutnya.',
                  child: _NumberField(
                    fieldKey: const Key('early-warning-percentage'),
                    controller: _percentageController,
                    label: 'Persentase ambang',
                    suffix: '%',
                  ),
                ),
                const SizedBox(height: 12),
                _SettingSection(
                  icon: Icons.report_problem_outlined,
                  title: 'Pelanggaran berulang',
                  description: 'Hanya laporan pelanggaran yang sudah disahkan yang dihitung.',
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: _NumberField(
                          fieldKey: const Key('early-warning-violation-count'),
                          controller: _violationCountController,
                          label: 'Minimum',
                          suffix: 'kejadian',
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _NumberField(
                          fieldKey: const Key('early-warning-violation-days'),
                          controller: _violationDaysController,
                          label: 'Periode',
                          suffix: 'hari',
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                _SettingSection(
                  icon: Icons.access_time_rounded,
                  title: 'Keterlambatan berulang',
                  description: 'Penghitungan berasal langsung dari rekap presensi siswa.',
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: _NumberField(
                          fieldKey: const Key('early-warning-late-count'),
                          controller: _lateCountController,
                          label: 'Minimum',
                          suffix: 'kali',
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _NumberField(
                          fieldKey: const Key('early-warning-late-days'),
                          controller: _lateDaysController,
                          label: 'Periode',
                          suffix: 'hari',
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(11),
                  decoration: BoxDecoration(
                    color: NusaColors.accent.withValues(alpha: 0.13),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.policy_outlined, size: 18),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Sanksi yang belum selesai juga selalu menjadi pemicu selama deteksi otomatis aktif.',
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
                    key: const Key('early-warning-form-error'),
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
                key: const Key('save-early-warning-setting'),
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
    final percentage = _value(_percentageController);
    final violationCount = _value(_violationCountController);
    final violationDays = _value(_violationDaysController);
    final lateCount = _value(_lateCountController);
    final lateDays = _value(_lateDaysController);

    if (percentage == null || percentage < 50 || percentage > 99) {
      _setError('Persentase ambang harus antara 50–99%.');
      return;
    }
    if (violationCount == null || violationCount < 2 || violationCount > 20) {
      _setError('Jumlah pelanggaran minimum harus antara 2–20 kejadian.');
      return;
    }
    if (violationDays == null || violationDays < 7 || violationDays > 365) {
      _setError('Periode pelanggaran harus antara 7–365 hari.');
      return;
    }
    if (lateCount == null || lateCount < 2 || lateCount > 30) {
      _setError('Jumlah keterlambatan minimum harus antara 2–30 kali.');
      return;
    }
    if (lateDays == null || lateDays < 7 || lateDays > 365) {
      _setError('Periode keterlambatan harus antara 7–365 hari.');
      return;
    }

    Navigator.pop(
      context,
      EarlyWarningSettingFormValue(
        detectionActive: _detectionActive,
        notificationActive: _detectionActive && _notificationActive,
        nearThresholdPercentage: percentage,
        repeatedViolationCount: violationCount,
        violationPeriodDays: violationDays,
        repeatedLateCount: lateCount,
        latePeriodDays: lateDays,
      ),
    );
  }

  int? _value(TextEditingController controller) =>
      int.tryParse(controller.text.trim());

  void _setError(String message) => setState(() => _error = message);
}

class _SettingSection extends StatelessWidget {
  const _SettingSection({
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
        const SizedBox(height: 10),
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
