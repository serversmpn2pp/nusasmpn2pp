import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/private_confirmation/application/private_confirmation_controller.dart';
import 'package:nusa/features/private_confirmation/domain/private_confirmation.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class PrivateConfirmationDetailView extends ConsumerStatefulWidget {
  const PrivateConfirmationDetailView({required this.periodId, super.key});

  final int periodId;

  @override
  ConsumerState<PrivateConfirmationDetailView> createState() =>
      _PrivateConfirmationDetailViewState();
}

class _PrivateConfirmationDetailViewState
    extends ConsumerState<PrivateConfirmationDetailView> {
  final _noteController = TextEditingController();
  String _result = 'masih_berhalangan';
  int _reminderDays = 3;
  int? _initializedPeriodId;
  bool _submitting = false;

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final asyncDetail = ref.watch(
      privateConfirmationDetailProvider(widget.periodId),
    );
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Konfirmasi Privat'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: asyncDetail.isLoading || _submitting
                ? null
                : () => ref.invalidate(
                    privateConfirmationDetailProvider(widget.periodId),
                  ),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: asyncDetail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _DetailError(
            message: _errorMessage(error),
            onRetry: () => ref.invalidate(
              privateConfirmationDetailProvider(widget.periodId),
            ),
          ),
          data: (detail) {
            _initialize(detail);
            return _DetailContent(
              detail: detail,
              result: _result,
              reminderDays: _reminderDays,
              noteController: _noteController,
              submitting: _submitting,
              onResultChanged: (value) => setState(() => _result = value),
              onReminderChanged: (value) {
                if (value != null) setState(() => _reminderDays = value);
              },
              onSubmit: () => _submit(detail),
            );
          },
        ),
      ),
    );
  }

  void _initialize(PrivateConfirmationDetail detail) {
    if (_initializedPeriodId == detail.period.id) return;
    _initializedPeriodId = detail.period.id;
    _reminderDays = detail.initialReminderDays.clamp(1, 14);
  }

  Future<void> _submit(PrivateConfirmationDetail detail) async {
    if (_submitting) return;
    final finishing = _result == 'selesai';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: Icon(
          finishing ? Icons.task_alt_rounded : Icons.schedule_send_rounded,
          color: finishing ? NusaColors.success : NusaColors.primary,
        ),
        title: Text(
          finishing ? 'Tutup periode berhalangan?' : 'Simpan pengingat baru?',
        ),
        content: Text(
          finishing
              ? 'Pastikan siswi sudah menyatakan selesai melalui percakapan privat. Periode akan ditutup hari ini.'
              : 'Periode tetap dipantau dan akan masuk antrean konfirmasi lagi dalam $_reminderDays hari.',
        ),
        actions: [
          TextButton(
            onPressed: () => context.pop(false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('private-confirmation-confirm-submit'),
            onPressed: () => context.pop(true),
            child: const Text('Ya, simpan'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _submitting = true);
    try {
      final result = await ref
          .read(privateConfirmationActionsProvider)
          .update(
            periodId: detail.period.id,
            result: _result,
            reminderDays: finishing ? null : _reminderDays,
            privateNote: _noteController.text,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(result.message)));
      context.pop(true);
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({
    required this.detail,
    required this.result,
    required this.reminderDays,
    required this.noteController,
    required this.submitting,
    required this.onResultChanged,
    required this.onReminderChanged,
    required this.onSubmit,
  });

  final PrivateConfirmationDetail detail;
  final String result;
  final int reminderDays;
  final TextEditingController noteController;
  final bool submitting;
  final ValueChanged<String> onResultChanged;
  final ValueChanged<int?> onReminderChanged;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) => ListView(
    key: const PageStorageKey<String>('private-confirmation-detail'),
    padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
    children: [
      _PrivacyNotice(message: detail.privacyMessage),
      const SizedBox(height: 12),
      _StudentHeader(period: detail.period),
      const SizedBox(height: 12),
      _PeriodInformation(period: detail.period),
      if (detail.period.initialPrivateNote?.trim().isNotEmpty == true) ...[
        const SizedBox(height: 12),
        _PrivateNoteCard(
          title: 'Catatan awal periode',
          note: detail.period.initialPrivateNote!,
        ),
      ],
      const SizedBox(height: 12),
      _AttendanceCard(items: detail.attendance),
      const SizedBox(height: 12),
      if (detail.canConfirm)
        _ConfirmationForm(
          result: result,
          reminderDays: reminderDays,
          noteController: noteController,
          submitting: submitting,
          onResultChanged: onResultChanged,
          onReminderChanged: onReminderChanged,
          onSubmit: onSubmit,
        )
      else
        _ClosedStatusCard(period: detail.period),
      const SizedBox(height: 12),
      _HistoryCard(items: detail.history),
    ],
  );
}

class _PrivacyNotice extends StatelessWidget {
  const _PrivacyNotice({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF7DA),
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: NusaColors.accent.withValues(alpha: 0.45)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.lock_outline_rounded, color: Color(0xFF9A7200)),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(
              color: NusaColors.textPrimary,
              fontSize: 11.5,
              height: 1.45,
            ),
          ),
        ),
      ],
    ),
  );
}

class _StudentHeader extends StatelessWidget {
  const _StudentHeader({required this.period});

  final PrivateConfirmationPeriod period;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(19),
    ),
    child: Row(
      children: [
        CircleAvatar(
          radius: 27,
          backgroundColor: Colors.white.withValues(alpha: 0.14),
          child: Text(
            period.student.initials,
            style: const TextStyle(
              color: NusaColors.accent,
              fontSize: 17,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        const SizedBox(width: 13),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                period.student.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'NISN ${period.student.nisn} · ${period.schoolClass.name}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white70, fontSize: 11.5),
              ),
            ],
          ),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            'Hari ${period.dayNumber}',
            style: const TextStyle(
              color: NusaColors.accent,
              fontSize: 10,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ],
    ),
  );
}

class _PeriodInformation extends StatelessWidget {
  const _PeriodInformation({required this.period});

  final PrivateConfirmationPeriod period;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.event_note_rounded,
    title: 'Informasi Periode',
    child: Column(
      children: [
        _InfoRow(label: 'Status', value: period.statusLabel),
        const Divider(height: 20),
        _InfoRow(label: 'Dimulai', value: period.startDateLabel),
        const Divider(height: 20),
        _InfoRow(
          label: 'Batas awal',
          value: '${period.confirmationDayLimit} hari',
        ),
        if (period.nextConfirmationDate != null) ...[
          const Divider(height: 20),
          _InfoRow(
            label: 'Konfirmasi berikutnya',
            value: _dateLabel(period.nextConfirmationDate!),
          ),
        ],
        if (period.endDate != null) ...[
          const Divider(height: 20),
          _InfoRow(label: 'Selesai', value: _dateLabel(period.endDate!)),
        ],
      ],
    ),
  );
}

class _AttendanceCard extends StatelessWidget {
  const _AttendanceCard({required this.items});

  final List<PrivateConfirmationAttendance> items;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.fact_check_outlined,
    title: 'Catatan Scan Harian (${items.length})',
    child: items.isEmpty
        ? const Text(
            'Belum ada catatan scan harian.',
            style: TextStyle(color: NusaColors.textSecondary, fontSize: 12),
          )
        : Column(
            children: [
              for (var index = 0; index < items.length; index++) ...[
                _TimelineRow(item: items[index]),
                if (index < items.length - 1) const Divider(height: 18),
              ],
            ],
          ),
  );
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({required this.item});

  final PrivateConfirmationAttendance item;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 34,
        height: 34,
        decoration: BoxDecoration(
          color: NusaColors.successSurface,
          borderRadius: BorderRadius.circular(10),
        ),
        child: const Icon(
          Icons.check_circle_outline_rounded,
          color: NusaColors.success,
          size: 19,
        ),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              item.activity,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 2),
            Text(
              '${item.dateLabel} · ${item.time} WIB',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10.5,
              ),
            ),
          ],
        ),
      ),
    ],
  );
}

class _ConfirmationForm extends StatelessWidget {
  const _ConfirmationForm({
    required this.result,
    required this.reminderDays,
    required this.noteController,
    required this.submitting,
    required this.onResultChanged,
    required this.onReminderChanged,
    required this.onSubmit,
  });

  final String result;
  final int reminderDays;
  final TextEditingController noteController;
  final bool submitting;
  final ValueChanged<String> onResultChanged;
  final ValueChanged<int?> onReminderChanged;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.forum_rounded,
    title: 'Hasil Percakapan Privat',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _ResultChoice(
          key: const Key('private-confirmation-result-still'),
          selected: result == 'masih_berhalangan',
          icon: Icons.schedule_rounded,
          color: const Color(0xFFB57900),
          title: 'Masih berhalangan',
          subtitle: 'Tetap dipantau dan buat pengingat berikutnya.',
          onTap: submitting ? null : () => onResultChanged('masih_berhalangan'),
        ),
        const SizedBox(height: 9),
        _ResultChoice(
          key: const Key('private-confirmation-result-finished'),
          selected: result == 'selesai',
          icon: Icons.task_alt_rounded,
          color: NusaColors.success,
          title: 'Sudah selesai',
          subtitle: 'Tutup periode berdasarkan pernyataan siswi.',
          onTap: submitting ? null : () => onResultChanged('selesai'),
        ),
        if (result == 'masih_berhalangan') ...[
          const SizedBox(height: 13),
          NusaDropdownField<int>(
            fieldKey: const Key('private-confirmation-reminder'),
            value: reminderDays,
            options: [
              for (var day = 1; day <= 14; day++)
                NusaDropdownOption<int>(value: day, label: '$day hari lagi'),
            ],
            decoration: const InputDecoration(
              labelText: 'Pengingat berikutnya',
              prefixIcon: Icon(Icons.notifications_active_outlined),
            ),
            enabled: !submitting,
            onChanged: onReminderChanged,
          ),
        ],
        const SizedBox(height: 13),
        TextFormField(
          key: const Key('private-confirmation-note'),
          controller: noteController,
          enabled: !submitting,
          minLines: 3,
          maxLines: 5,
          maxLength: 500,
          decoration: const InputDecoration(
            labelText: 'Catatan privat (opsional)',
            hintText: 'Catat seperlunya tanpa detail medis.',
            alignLabelWithHint: true,
            prefixIcon: Padding(
              padding: EdgeInsets.only(bottom: 64),
              child: Icon(Icons.edit_note_rounded),
            ),
          ),
        ),
        const SizedBox(height: 5),
        NusaPrimaryButton(
          label: 'Simpan Konfirmasi Privat',
          loading: submitting,
          onPressed: onSubmit,
        ),
      ],
    ),
  );
}

class _ResultChoice extends StatelessWidget {
  const _ResultChoice({
    required this.selected,
    required this.icon,
    required this.color,
    required this.title,
    required this.subtitle,
    required this.onTap,
    super.key,
  });

  final bool selected;
  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: selected ? color.withValues(alpha: 0.08) : Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(14),
      side: BorderSide(
        color: selected ? color : NusaColors.outline,
        width: selected ? 1.5 : 1,
      ),
    ),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Icon(icon, color: color),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              selected
                  ? Icons.radio_button_checked_rounded
                  : Icons.radio_button_off_rounded,
              color: selected ? color : NusaColors.textSecondary,
            ),
          ],
        ),
      ),
    ),
  );
}

class _ClosedStatusCard extends StatelessWidget {
  const _ClosedStatusCard({required this.period});

  final PrivateConfirmationPeriod period;

  @override
  Widget build(BuildContext context) {
    final completed = period.status == 'selesai';
    final color = completed ? NusaColors.success : NusaColors.primary;
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Row(
        children: [
          Icon(
            completed ? Icons.task_alt_rounded : Icons.visibility_outlined,
            color: color,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              completed
                  ? 'Periode ini sudah selesai dan tidak dapat dikonfirmasi ulang.'
                  : 'Periode sedang dipantau hingga pengingat berikutnya.',
              style: const TextStyle(fontSize: 11.5, height: 1.4),
            ),
          ),
        ],
      ),
    );
  }
}

class _HistoryCard extends StatelessWidget {
  const _HistoryCard({required this.items});

  final List<PrivateConfirmationHistory> items;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.history_rounded,
    title: 'Riwayat Konfirmasi (${items.length})',
    child: items.isEmpty
        ? const Text(
            'Belum ada riwayat konfirmasi sebelumnya.',
            style: TextStyle(color: NusaColors.textSecondary, fontSize: 12),
          )
        : Column(
            children: [
              for (var index = 0; index < items.length; index++) ...[
                _HistoryItem(item: items[index]),
                if (index < items.length - 1) const Divider(height: 22),
              ],
            ],
          ),
  );
}

class _HistoryItem extends StatelessWidget {
  const _HistoryItem({required this.item});

  final PrivateConfirmationHistory item;

  @override
  Widget build(BuildContext context) {
    final completed = item.result == 'selesai';
    final color = completed ? NusaColors.success : const Color(0xFFB57900);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(
              completed ? Icons.task_alt_rounded : Icons.schedule_rounded,
              color: color,
              size: 19,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                item.resultLabel,
                style: TextStyle(
                  color: color,
                  fontSize: 12.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Text(
          '${item.confirmedAtLabel} · ${item.confirmedBy}',
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 10.5,
          ),
        ),
        if (item.nextConfirmationDate != null) ...[
          const SizedBox(height: 4),
          Text(
            'Pengingat: ${_dateLabel(item.nextConfirmationDate!)}',
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
            ),
          ),
        ],
        if (item.privateNote?.trim().isNotEmpty == true) ...[
          const SizedBox(height: 8),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: NusaColors.surfaceBlue,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              item.privateNote!,
              style: const TextStyle(fontSize: 11, height: 1.4),
            ),
          ),
        ],
      ],
    );
  }
}

class _PrivateNoteCard extends StatelessWidget {
  const _PrivateNoteCard({required this.title, required this.note});

  final String title;
  final String note;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.note_alt_rounded,
    title: title,
    child: Text(note, style: const TextStyle(fontSize: 12, height: 1.45)),
  );
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.icon,
    required this.title,
    required this.child,
  });

  final IconData icon;
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(15),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: NusaColors.surfaceBlue,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: NusaColors.primary, size: 19),
            ),
            const SizedBox(width: 9),
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 13),
        child,
      ],
    ),
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      SizedBox(
        width: 112,
        child: Text(
          label,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 11.5,
          ),
        ),
      ),
      const SizedBox(width: 8),
      Expanded(
        child: Text(
          value,
          textAlign: TextAlign.right,
          style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700),
        ),
      ),
    ],
  );
}

class _DetailError extends StatelessWidget {
  const _DetailError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.lock_person_rounded,
            size: 50,
            color: NusaColors.primary,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
          ),
        ],
      ),
    ),
  );
}

String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  if (date == null) return value;
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Konfirmasi privat belum dapat diproses. Silakan coba lagi.';
