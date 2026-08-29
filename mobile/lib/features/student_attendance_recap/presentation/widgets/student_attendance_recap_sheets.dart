import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_attendance_recap/domain/student_attendance_recap.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentAttendanceDetailSheet extends StatelessWidget {
  const StudentAttendanceDetailSheet({required this.future, super.key});
  final Future<StudentAttendanceDetail> future;

  @override
  Widget build(BuildContext context) => FutureBuilder<StudentAttendanceDetail>(
    future: future,
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const SizedBox(
          height: 280,
          child: Center(child: CircularProgressIndicator()),
        );
      }
      if (snapshot.hasError || snapshot.data == null) {
        return SizedBox(
          height: 280,
          child: Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Text(
                'Detail presensi belum dapat dimuat.',
                textAlign: TextAlign.center,
              ),
            ),
          ),
        );
      }
      final detail = snapshot.data!;
      final item = detail.record;
      final color = attendanceStatusColor(item.status);
      return DraggableScrollableSheet(
        expand: false,
        initialChildSize: .78,
        minChildSize: .48,
        maxChildSize: .94,
        builder: (context, controller) => Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
          child: Column(
            children: [
              Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: NusaColors.outline,
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  CircleAvatar(
                    radius: 25,
                    backgroundColor: NusaColors.surfaceBlue,
                    backgroundImage: item.photoUrl == null
                        ? null
                        : NetworkImage(item.photoUrl!),
                    child: item.photoUrl == null
                        ? Text(
                            item.initials,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              color: NusaColors.primary,
                            ),
                          )
                        : null,
                  ),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.name,
                          style: const TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w800,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        Text(
                          '${item.className} · ${detail.dateLabel}',
                          style: const TextStyle(
                            fontSize: 11,
                            color: NusaColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: .1),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      item.statusLabel,
                      style: TextStyle(
                        color: color,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Expanded(
                child: ListView(
                  controller: controller,
                  children: [
                    _AttendanceDetailCard(detail: detail),
                    const SizedBox(height: 14),
                    const Text(
                      'Riwayat perubahan',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                      ),
                    ),
                    const SizedBox(height: 8),
                    if (detail.history.isEmpty)
                      const _InfoBox(
                        icon: Icons.history_toggle_off_rounded,
                        text: 'Belum ada riwayat koreksi pada tanggal ini.',
                      )
                    else
                      ...detail.history.map(
                        (history) => _HistoryCard(history: history),
                      ),
                    if (!detail.correction.allowed &&
                        detail.correction.reason != null) ...[
                      const SizedBox(height: 10),
                      _InfoBox(
                        icon: Icons.lock_outline_rounded,
                        text: detail.correction.reason!,
                      ),
                    ],
                  ],
                ),
              ),
              if (detail.correction.allowed) ...[
                const SizedBox(height: 12),
                NusaPrimaryButton(
                  label: 'Koreksi Presensi',
                  onPressed: () => Navigator.pop(context, detail),
                ),
              ],
            ],
          ),
        ),
      );
    },
  );
}

class _AttendanceDetailCard extends StatelessWidget {
  const _AttendanceDetailCard({required this.detail});
  final StudentAttendanceDetail detail;
  @override
  Widget build(BuildContext context) {
    final item = detail.record;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: NusaColors.surfaceBlue,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Column(
        children: [
          _DetailRow(label: 'Sumber data', value: item.sourceLabel),
          _DetailRow(label: 'Jam masuk', value: item.checkInTime ?? '-'),
          _DetailRow(label: 'Jam pulang', value: item.checkOutTime ?? '-'),
          _DetailRow(
            label: 'Keterlambatan',
            value: item.lateMinutes > 0 ? '${item.lateMinutes} menit' : '-',
          ),
          _DetailRow(
            label: 'Pulang cepat',
            value: item.earlyLeaveMinutes > 0
                ? '${item.earlyLeaveMinutes} menit'
                : '-',
          ),
          _DetailRow(
            label: 'Jadwal resmi',
            value: detail.scheduleAvailable
                ? '${detail.officialCheckIn ?? '-'}–${detail.officialCheckOut ?? '-'}'
                : 'Tidak tersedia',
            last: item.notes == null,
          ),
          if (item.notes != null)
            _DetailRow(label: 'Catatan', value: item.notes!, last: true),
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.label,
    required this.value,
    this.last = false,
  });
  final String label;
  final String value;
  final bool last;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 7),
    decoration: BoxDecoration(
      border: last
          ? null
          : const Border(bottom: BorderSide(color: NusaColors.outline)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 105,
          child: Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              color: NusaColors.textSecondary,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

class _HistoryCard extends StatelessWidget {
  const _HistoryCard({required this.history});
  final AttendanceHistoryEntry history;
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 8),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: NusaColors.primary.withValues(alpha: .08),
            borderRadius: BorderRadius.circular(11),
          ),
          child: const Icon(
            Icons.history_rounded,
            size: 18,
            color: NusaColors.primary,
          ),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${_statusLabel(history.beforeStatus)} → ${_statusLabel(history.afterStatus)}',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 12,
                ),
              ),
              Text(
                '${history.sourceLabel}${history.createdBy == null ? '' : ' · ${history.createdBy}'}',
                style: const TextStyle(
                  fontSize: 10,
                  color: NusaColors.textSecondary,
                ),
              ),
              if (history.notes?.isNotEmpty == true)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    history.notes!,
                    style: const TextStyle(fontSize: 11),
                  ),
                ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _InfoBox extends StatelessWidget {
  const _InfoBox({required this.icon, required this.text});
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      children: [
        Icon(icon, size: 20, color: NusaColors.textSecondary),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              fontSize: 11,
              color: NusaColors.textSecondary,
              height: 1.35,
            ),
          ),
        ),
      ],
    ),
  );
}

class StudentAttendanceCorrectionSheet extends StatefulWidget {
  const StudentAttendanceCorrectionSheet({required this.detail, super.key});
  final StudentAttendanceDetail detail;
  @override
  State<StudentAttendanceCorrectionSheet> createState() =>
      _StudentAttendanceCorrectionSheetState();
}

class _StudentAttendanceCorrectionSheetState
    extends State<StudentAttendanceCorrectionSheet> {
  String? _status;
  late final TextEditingController _checkIn;
  late final TextEditingController _checkOut;
  late final TextEditingController _notes;
  @override
  void initState() {
    super.initState();
    final current = widget.detail.record;
    _status = const {'hadir', 'izin', 'sakit', 'alfa'}.contains(current.status)
        ? current.status
        : null;
    _checkIn = TextEditingController(text: current.checkInTime);
    _checkOut = TextEditingController(text: current.checkOutTime);
    _notes = TextEditingController(text: current.notes);
  }

  @override
  void dispose() {
    _checkIn.dispose();
    _checkOut.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    final present = _status == 'hadir';
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 16, 20, bottom + 20),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Koreksi Presensi',
              style: Theme.of(context).textTheme.titleLarge
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            Text(
              '${widget.detail.record.name} · ${widget.detail.dateLabel}',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 11,
              ),
            ),
            const SizedBox(height: 16),
            NusaDropdownField<String>(
              fieldKey: const Key('attendance-correction-status'),
              value: _status,
              options: const [
                NusaDropdownOption(value: 'hadir', label: 'Hadir'),
                NusaDropdownOption(value: 'izin', label: 'Izin'),
                NusaDropdownOption(value: 'sakit', label: 'Sakit'),
                NusaDropdownOption(value: 'alfa', label: 'Alfa'),
              ],
              decoration: const InputDecoration(
                labelText: 'Status kehadiran',
                prefixIcon: Icon(Icons.fact_check_outlined),
              ),
              onChanged: (value) => setState(() {
                _status = value;
                if (value != 'hadir') {
                  _checkIn.clear();
                  _checkOut.clear();
                }
              }),
            ),
            if (present) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _TimeField(
                      label: 'Jam masuk',
                      controller: _checkIn,
                      onTap: () => _pickTime(_checkIn),
                    ),
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: _TimeField(
                      label: 'Jam pulang',
                      controller: _checkOut,
                      onTap: () => _pickTime(_checkOut),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              const Text(
                'Jam masuk wajib diisi. Jam pulang boleh kosong jika siswa belum pulang.',
                style: TextStyle(fontSize: 10, color: NusaColors.textSecondary),
              ),
            ],
            const SizedBox(height: 12),
            TextField(
              key: const Key('attendance-correction-notes'),
              controller: _notes,
              minLines: 3,
              maxLines: 5,
              decoration: const InputDecoration(
                labelText: 'Alasan koreksi',
                hintText: 'Tuliskan dasar perubahan data presensi',
              ),
            ),
            const SizedBox(height: 7),
            const Text(
              'Alasan disimpan dalam riwayat audit dan wajib diisi.',
              style: TextStyle(fontSize: 10, color: NusaColors.textSecondary),
            ),
            const SizedBox(height: 18),
            NusaPrimaryButton(label: 'Simpan Koreksi', onPressed: _submit),
          ],
        ),
      ),
    );
  }

  Future<void> _pickTime(TextEditingController controller) async {
    final initial = _parseTime(controller.text) ?? TimeOfDay.now();
    final value = await showTimePicker(context: context, initialTime: initial);
    if (value != null) {
      controller.text =
          '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
    }
  }

  void _submit() {
    if (_status == null) {
      _message('Pilih status kehadiran.');
      return;
    }
    if (_status == 'hadir' && _checkIn.text.isEmpty) {
      _message('Jam masuk wajib diisi untuk status hadir.');
      return;
    }
    if (_notes.text.trim().length < 3) {
      _message('Alasan koreksi minimal 3 karakter.');
      return;
    }
    Navigator.pop(
      context,
      AttendanceCorrectionValue(
        status: _status!,
        checkInTime: _checkIn.text.isEmpty ? null : _checkIn.text,
        checkOutTime: _checkOut.text.isEmpty ? null : _checkOut.text,
        notes: _notes.text.trim(),
      ),
    );
  }

  void _message(String value) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(value)));
}

class _TimeField extends StatelessWidget {
  const _TimeField({
    required this.label,
    required this.controller,
    required this.onTap,
  });
  final String label;
  final TextEditingController controller;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => TextField(
    controller: controller,
    readOnly: true,
    onTap: onTap,
    decoration: InputDecoration(
      labelText: label,
      prefixIcon: const Icon(Icons.schedule_rounded),
      suffixIcon: controller.text.isEmpty
          ? null
          : IconButton(
              onPressed: controller.clear,
              icon: const Icon(Icons.close_rounded),
            ),
    ),
  );
}

TimeOfDay? _parseTime(String value) {
  final parts = value.split(':');
  if (parts.length < 2) return null;
  final hour = int.tryParse(parts[0]);
  final minute = int.tryParse(parts[1]);
  return hour == null || minute == null
      ? null
      : TimeOfDay(hour: hour, minute: minute);
}

String _statusLabel(String? status) => switch (status) {
  'hadir' => 'Hadir',
  'izin' => 'Izin',
  'sakit' => 'Sakit',
  'alfa' => 'Alfa',
  null => 'Belum tercatat',
  _ => status,
};
Color attendanceStatusColor(String status) => switch (status) {
  'hadir' => NusaColors.success,
  'izin' => const Color(0xFF7A56B3),
  'sakit' => const Color(0xFFD97706),
  'alfa' => const Color(0xFFB42318),
  _ => NusaColors.textSecondary,
};

class StudentAttendanceWhatsAppSheet extends StatefulWidget {
  const StudentAttendanceWhatsAppSheet({required this.data, super.key});
  final StudentAttendanceWhatsAppMessage data;

  @override
  State<StudentAttendanceWhatsAppSheet> createState() =>
      _StudentAttendanceWhatsAppSheetState();
}

class _StudentAttendanceWhatsAppSheetState
    extends State<StudentAttendanceWhatsAppSheet> {
  bool _copied = false;

  @override
  Widget build(BuildContext context) => DraggableScrollableSheet(
    expand: false,
    initialChildSize: .82,
    minChildSize: .55,
    maxChildSize: .95,
    builder: (context, controller) => Padding(
      padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
      child: Column(
        children: [
          Container(
            width: 42,
            height: 4,
            decoration: BoxDecoration(
              color: NusaColors.outline,
              borderRadius: BorderRadius.circular(10),
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: NusaColors.success.withValues(alpha: .1),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: const Icon(
                  Icons.forum_rounded,
                  color: NusaColors.success,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Pesan WA Grup Orang Tua',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                    Text(
                      '${widget.data.dateLabel} · ${widget.data.scope}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: 'Tutup',
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close_rounded),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: NusaColors.surfaceBlue,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              '${widget.data.studentCount} siswa · Pesan siap ditempel ke grup WhatsApp orang tua.',
              style: const TextStyle(
                color: NusaColors.primary,
                fontSize: 10.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const SizedBox(height: 10),
          Expanded(
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.all(13),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: NusaColors.outline),
              ),
              child: SingleChildScrollView(
                controller: controller,
                child: SelectableText(
                  widget.data.message,
                  key: const Key('attendance-whatsapp-message'),
                  style: const TextStyle(fontSize: 12.5, height: 1.48),
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
          NusaPrimaryButton(
            key: const Key('attendance-whatsapp-copy'),
            label: _copied ? 'Pesan Berhasil Disalin' : 'Salin Pesan',
            onPressed: _copy,
          ),
        ],
      ),
    ),
  );

  Future<void> _copy() async {
    await Clipboard.setData(ClipboardData(text: widget.data.message));
    if (!mounted) return;
    setState(() => _copied = true);
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(const SnackBar(content: Text('Pesan berhasil disalin.')));
  }
}
