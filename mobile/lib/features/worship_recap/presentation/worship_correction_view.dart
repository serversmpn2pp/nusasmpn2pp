import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_recap/application/worship_recap_controller.dart';
import 'package:nusa/features/worship_recap/domain/worship_recap.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class WorshipCorrectionView extends ConsumerStatefulWidget {
  const WorshipCorrectionView({required this.query, super.key});

  final WorshipCorrectionQuery query;

  @override
  ConsumerState<WorshipCorrectionView> createState() =>
      _WorshipCorrectionViewState();
}

class _WorshipCorrectionViewState extends ConsumerState<WorshipCorrectionView> {
  final _reasonController = TextEditingController();
  String _status = 'sudah';
  String _time = '';
  WorshipCorrectionQuery? _initializedQuery;
  bool _submitting = false;

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final asyncDetail = ref.watch(
      worshipCorrectionDetailProvider(widget.query),
    );
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Koreksi Presensi Ibadah'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: asyncDetail.isLoading || _submitting
                ? null
                : () => ref.invalidate(
                    worshipCorrectionDetailProvider(widget.query),
                  ),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: asyncDetail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ErrorState(
            message: _errorMessage(error),
            onRetry: () =>
                ref.invalidate(worshipCorrectionDetailProvider(widget.query)),
          ),
          data: (detail) {
            _initialize(detail);
            return _CorrectionContent(
              detail: detail,
              status: _status,
              time: _time,
              reasonController: _reasonController,
              submitting: _submitting,
              onStatusChanged: (value) => setState(() => _status = value),
              onSelectTime: () => _selectTime(detail),
              onSubmit: () => _submit(detail),
            );
          },
        ),
      ),
    );
  }

  void _initialize(WorshipCorrectionDetail detail) {
    if (_initializedQuery == widget.query) return;
    _initializedQuery = widget.query;
    _status = 'sudah';
    _time = detail.initialTime;
    _reasonController.text = detail.attendance?.correctionNote ?? '';
  }

  Future<void> _selectTime(WorshipCorrectionDetail detail) async {
    final parts = _time.split(':');
    final initial = parts.length == 2
        ? TimeOfDay(
            hour: int.tryParse(parts[0]) ?? 12,
            minute: int.tryParse(parts[1]) ?? 0,
          )
        : const TimeOfDay(hour: 12, minute: 0);
    final selected = await showTimePicker(
      context: context,
      initialTime: initial,
      helpText: 'Pilih waktu presensi ibadah',
    );
    if (selected == null) return;
    setState(() {
      _time =
          '${selected.hour.toString().padLeft(2, '0')}:'
          '${selected.minute.toString().padLeft(2, '0')}';
    });
  }

  Future<void> _submit(WorshipCorrectionDetail detail) async {
    if (_submitting) return;
    final reason = _reasonController.text.trim();
    if (reason.length < 5) {
      _showMessage('Alasan koreksi minimal 5 karakter.');
      return;
    }
    if (_status == 'sudah' && _time.isEmpty) {
      _showMessage('Pilih waktu presensi terlebih dahulu.');
      return;
    }
    if (_status == 'sudah' && detail.attendance == null && !detail.canCreate) {
      _showMessage(
        'Input manual tidak tersedia karena tidak ada jadwal pada tanggal ini.',
      );
      return;
    }

    final cancelling = _status == 'belum';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: Icon(
          cancelling ? Icons.delete_outline_rounded : Icons.fact_check_rounded,
          color: cancelling ? const Color(0xFFB42318) : NusaColors.primary,
        ),
        title: Text(
          cancelling ? 'Batalkan catatan presensi?' : 'Simpan perubahan?',
        ),
        content: Text(
          cancelling
              ? 'Catatan presensi akan dihapus, tetapi riwayat pembatalan dan alasannya tetap tersimpan.'
              : 'Status dan waktu presensi akan disimpan bersama alasan serta identitas petugas.',
        ),
        actions: [
          TextButton(
            onPressed: () => context.pop(false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('worship-correction-confirm'),
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
          .read(worshipCorrectionActionsProvider)
          .update(
            query: widget.query,
            status: _status,
            time: _status == 'sudah' ? _time : null,
            reason: reason,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(result.message)));
      context.pop(true);
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _CorrectionContent extends StatelessWidget {
  const _CorrectionContent({
    required this.detail,
    required this.status,
    required this.time,
    required this.reasonController,
    required this.submitting,
    required this.onStatusChanged,
    required this.onSelectTime,
    required this.onSubmit,
  });

  final WorshipCorrectionDetail detail;
  final String status;
  final String time;
  final TextEditingController reasonController;
  final bool submitting;
  final ValueChanged<String> onStatusChanged;
  final VoidCallback onSelectTime;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) => ListView(
    key: const PageStorageKey<String>('worship-correction-scroll'),
    padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
    children: [
      _StudentHeader(detail: detail),
      const SizedBox(height: 12),
      _CurrentStatus(detail: detail),
      const SizedBox(height: 12),
      _ScheduleNotice(detail: detail),
      const SizedBox(height: 12),
      _CorrectionForm(
        detail: detail,
        status: status,
        time: time,
        reasonController: reasonController,
        submitting: submitting,
        onStatusChanged: onStatusChanged,
        onSelectTime: onSelectTime,
        onSubmit: onSubmit,
      ),
      if (detail.history.isNotEmpty) ...[
        const SizedBox(height: 12),
        _HistoryCard(items: detail.history),
      ],
    ],
  );
}

class _StudentHeader extends StatelessWidget {
  const _StudentHeader({required this.detail});

  final WorshipCorrectionDetail detail;

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
          radius: 26,
          backgroundColor: Colors.white.withValues(alpha: 0.14),
          child: Text(
            detail.member.student.initials,
            style: const TextStyle(
              color: NusaColors.accent,
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
                detail.member.student.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${detail.member.schoolClass.name} · NISN ${detail.member.student.nisn ?? '-'}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white70, fontSize: 11),
              ),
              const SizedBox(height: 3),
              Text(
                '${detail.activity.name} · ${detail.dateLabel}',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white70, fontSize: 10.5),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _CurrentStatus extends StatelessWidget {
  const _CurrentStatus({required this.detail});

  final WorshipCorrectionDetail detail;

  @override
  Widget build(BuildContext context) {
    final attendance = detail.attendance;
    final present = attendance != null;
    final color = present ? NusaColors.success : const Color(0xFFB57900);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: color.withValues(alpha: 0.24)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            present ? Icons.check_circle_rounded : Icons.pending_outlined,
            color: color,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  present ? 'Sudah presensi' : 'Belum presensi',
                  style: TextStyle(color: color, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 3),
                Text(
                  present
                      ? 'Tercatat pukul ${attendance.time} melalui ${attendance.sourceLabel}.'
                      : 'Belum ada scan atau input manual yang tercatat.',
                  style: const TextStyle(fontSize: 11, height: 1.4),
                ),
                if (attendance?.correctedBy != null) ...[
                  const SizedBox(height: 3),
                  Text(
                    'Koreksi terakhir oleh ${attendance!.correctedBy}.',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ScheduleNotice extends StatelessWidget {
  const _ScheduleNotice({required this.detail});

  final WorshipCorrectionDetail detail;

  @override
  Widget build(BuildContext context) {
    final schedule = detail.schedule;
    final unavailable = schedule == null;
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: unavailable ? const Color(0xFFFFECEA) : NusaColors.surfaceBlue,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            unavailable ? Icons.warning_amber_rounded : Icons.schedule_rounded,
            color: unavailable ? const Color(0xFFB42318) : NusaColors.primary,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              unavailable
                  ? 'Tidak ada jadwal pada tanggal ini. Catatan lama dapat dibatalkan, tetapi input baru tidak tersedia.'
                  : 'Pelaksanaan ${schedule.eventTime} WIB · Jendela scan ${schedule.scanRange}.',
              style: const TextStyle(fontSize: 11, height: 1.4),
            ),
          ),
        ],
      ),
    );
  }
}

class _CorrectionForm extends StatelessWidget {
  const _CorrectionForm({
    required this.detail,
    required this.status,
    required this.time,
    required this.reasonController,
    required this.submitting,
    required this.onStatusChanged,
    required this.onSelectTime,
    required this.onSubmit,
  });

  final WorshipCorrectionDetail detail;
  final String status;
  final String time;
  final TextEditingController reasonController;
  final bool submitting;
  final ValueChanged<String> onStatusChanged;
  final VoidCallback onSelectTime;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.edit_calendar_rounded,
    title: detail.attendance == null
        ? 'Input Presensi Manual'
        : 'Koreksi Presensi',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _StatusChoice(
          key: const Key('worship-correction-present'),
          selected: status == 'sudah',
          icon: Icons.check_circle_outline_rounded,
          color: NusaColors.success,
          title: 'Sudah presensi',
          subtitle: 'Tambah atau perbaiki waktu presensi.',
          onTap: submitting ? null : () => onStatusChanged('sudah'),
        ),
        if (detail.attendance != null) ...[
          const SizedBox(height: 9),
          _StatusChoice(
            key: const Key('worship-correction-not-present'),
            selected: status == 'belum',
            icon: Icons.cancel_outlined,
            color: const Color(0xFFB42318),
            title: 'Belum presensi',
            subtitle: 'Batalkan catatan yang keliru dengan riwayat audit.',
            onTap: submitting ? null : () => onStatusChanged('belum'),
          ),
        ],
        if (status == 'sudah') ...[
          const SizedBox(height: 13),
          Material(
            color: Colors.white,
            child: InkWell(
              key: const Key('worship-correction-time'),
              onTap: submitting ? null : onSelectTime,
              borderRadius: BorderRadius.circular(14),
              child: InputDecorator(
                decoration: const InputDecoration(
                  labelText: 'Waktu presensi',
                  prefixIcon: Icon(Icons.access_time_rounded),
                  suffixIcon: Icon(Icons.edit_rounded),
                ),
                child: Text(time.isEmpty ? 'Pilih waktu' : '$time WIB'),
              ),
            ),
          ),
        ],
        const SizedBox(height: 13),
        TextFormField(
          key: const Key('worship-correction-reason'),
          controller: reasonController,
          enabled: !submitting,
          minLines: 3,
          maxLines: 6,
          maxLength: 1000,
          decoration: const InputDecoration(
            labelText: 'Alasan koreksi/input manual',
            hintText:
                'Contoh: siswa lupa kartu, kehadiran dikonfirmasi guru piket.',
            alignLabelWithHint: true,
          ),
        ),
        const SizedBox(height: 4),
        NusaPrimaryButton(
          label: 'Simpan Perubahan',
          loading: submitting,
          onPressed: onSubmit,
        ),
      ],
    ),
  );
}

class _StatusChoice extends StatelessWidget {
  const _StatusChoice({
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

class _HistoryCard extends StatelessWidget {
  const _HistoryCard({required this.items});

  final List<WorshipCorrectionHistory> items;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.history_rounded,
    title: 'Riwayat Perubahan',
    child: Column(
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

  final WorshipCorrectionHistory item;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Row(
        children: [
          Expanded(
            child: Text(
              item.actionLabel,
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          Text(
            item.createdAtLabel,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 9.5,
            ),
          ),
        ],
      ),
      const SizedBox(height: 5),
      Text(
        '${item.beforeTime ?? 'Belum'} → ${item.afterTime ?? 'Belum'}',
        style: const TextStyle(
          color: NusaColors.primary,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
      const SizedBox(height: 5),
      Text(item.reason, style: const TextStyle(fontSize: 11, height: 1.4)),
      const SizedBox(height: 3),
      Text(
        'Oleh ${item.changedBy}',
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10),
      ),
    ],
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

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

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
            Icons.edit_calendar_outlined,
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

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Koreksi presensi ibadah belum dapat diproses. Silakan coba lagi.';
