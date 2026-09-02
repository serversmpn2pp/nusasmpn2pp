import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_early_warning/application/student_early_warning_controller.dart';
import 'package:nusa/features/student_early_warning/domain/student_early_warning.dart';

class StudentEarlyWarningDetailView extends ConsumerWidget {
  const StudentEarlyWarningDetailView({required this.warningId, super.key});
  final int warningId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(studentEarlyWarningDetailProvider(warningId));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Peringatan Dini'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : () => ref.invalidate(
                    studentEarlyWarningDetailProvider(warningId),
                  ),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _Error(
            message: _message(error),
            onRetry: () =>
                ref.invalidate(studentEarlyWarningDetailProvider(warningId)),
          ),
          data: (detail) => _Content(detail: detail),
        ),
      ),
    );
  }
}

class _Content extends StatelessWidget {
  const _Content({required this.detail});
  final StudentEarlyWarningDetail detail;

  @override
  Widget build(BuildContext context) {
    final item = detail.item;
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _StudentHeader(item: item),
          const SizedBox(height: 11),
          _WarningCard(item: item),
          if (item.supportingData.isNotEmpty) ...[
            const SizedBox(height: 11),
            _SupportingDataCard(items: item.supportingData),
          ],
          const SizedBox(height: 11),
          _TimelineCard(item: item),
          const SizedBox(height: 11),
          OutlinedButton.icon(
            key: const Key('student-warning-open-point-recap'),
            onPressed: () => context.push(
              Uri(
                path: '/rekap-poin-siswa/${item.student.id}',
                queryParameters: {
                  if (item.academicYear != null)
                    'tahun': '${item.academicYear!.id}',
                },
              ).toString(),
            ),
            icon: const Icon(Icons.score_rounded),
            label: const Text('Buka Rekap Poin Siswa'),
          ),
          const SizedBox(height: 11),
          _AssistanceCard(item: item, access: detail.access),
          if (item.sanction != null) ...[
            const SizedBox(height: 11),
            _SanctionCard(sanction: item.sanction!),
          ],
          const SizedBox(height: 11),
          const _AutomaticInfo(),
        ],
      ),
    );
  }
}

class _StudentHeader extends StatelessWidget {
  const _StudentHeader({required this.item});
  final StudentEarlyWarningItem item;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(15),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.13),
            borderRadius: BorderRadius.circular(15),
          ),
          child: const Icon(Icons.person_rounded, color: NusaColors.accent),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.student.name,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                '${item.schoolClass?.name ?? 'Tanpa kelas'} · ${item.academicYear?.name ?? '-'}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white70, fontSize: 10.5),
              ),
              if (_filled(item.student.nis) || _filled(item.student.nisn)) ...[
                const SizedBox(height: 2),
                Text(
                  'NIS ${item.student.nis ?? '-'} · NISN ${item.student.nisn ?? '-'}',
                  style: const TextStyle(color: Colors.white60, fontSize: 9.5),
                ),
              ],
            ],
          ),
        ),
      ],
    ),
  );
}

class _WarningCard extends StatelessWidget {
  const _WarningCard({required this.item});
  final StudentEarlyWarningItem item;

  @override
  Widget build(BuildContext context) {
    final active = item.status == 'aktif';
    final important = item.level == 'penting';
    final color = !active
        ? NusaColors.textSecondary
        : important
        ? const Color(0xFFD84A3A)
        : const Color(0xFFC58F00);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.warning_amber_rounded, color: color),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    item.typeLabel,
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
                _Badge(label: item.levelLabel, color: color),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              item.title,
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 6),
            Text(
              item.message,
              style: const TextStyle(fontSize: 11, height: 1.5),
            ),
            const SizedBox(height: 11),
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [
                _Badge(
                  label: item.statusLabel,
                  color: active ? NusaColors.success : NusaColors.textSecondary,
                ),
                _Badge(
                  label: 'Siklus ${item.cycle}',
                  color: NusaColors.primary,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _SupportingDataCard extends StatelessWidget {
  const _SupportingDataCard({required this.items});
  final List<StudentWarningSupportingDatum> items;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionTitle(
            icon: Icons.analytics_outlined,
            title: 'Data Pendukung',
          ),
          const SizedBox(height: 11),
          for (var index = 0; index < items.length; index++) ...[
            _LabelValue(label: items[index].label, value: items[index].value),
            if (index != items.length - 1) const Divider(height: 18),
          ],
        ],
      ),
    ),
  );
}

class _TimelineCard extends StatelessWidget {
  const _TimelineCard({required this.item});
  final StudentEarlyWarningItem item;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionTitle(
            icon: Icons.history_rounded,
            title: 'Riwayat Deteksi',
          ),
          const SizedBox(height: 11),
          _LabelValue(
            label: 'Pertama terdeteksi',
            value: _dateTimeLabel(item.detectedAt),
          ),
          const Divider(height: 18),
          _LabelValue(
            label: 'Terakhir terdeteksi',
            value: _dateTimeLabel(item.lastDetectedAt),
          ),
          if (item.resolvedAt != null) ...[
            const Divider(height: 18),
            _LabelValue(
              label: 'Diselesaikan otomatis',
              value: _dateTimeLabel(item.resolvedAt),
            ),
          ],
          if (item.homeroomTeacher != null) ...[
            const Divider(height: 18),
            _LabelValue(label: 'Guru wali', value: item.homeroomTeacher!.name),
          ],
        ],
      ),
    ),
  );
}

class _AssistanceCard extends StatelessWidget {
  const _AssistanceCard({required this.item, required this.access});
  final StudentEarlyWarningItem item;
  final StudentEarlyWarningAccess access;

  @override
  Widget build(BuildContext context) {
    final assistance = item.activeAssistance;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const _SectionTitle(
              icon: Icons.handshake_outlined,
              title: 'Tindak Lanjut Pendampingan',
            ),
            const SizedBox(height: 10),
            if (assistance != null) ...[
              Text(
                assistance.typeLabel,
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 4),
              Text(
                '${_dateLabel(assistance.date)} · ${assistance.officer?.name ?? 'Petugas belum ditentukan'}',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10.5,
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                key: const Key('student-warning-open-assistance'),
                onPressed: () =>
                    context.push('/pendampingan-siswa/${assistance.id}'),
                icon: const Icon(Icons.open_in_new_rounded),
                label: const Text('Buka Pendampingan'),
              ),
            ] else ...[
              const Text(
                'Belum ada pendampingan aktif untuk siswa ini pada tahun pelajaran terkait.',
                style: TextStyle(fontSize: 10.5, height: 1.45),
              ),
              if (access.canManageAssistance && item.status == 'aktif') ...[
                const SizedBox(height: 12),
                FilledButton.icon(
                  key: const Key('student-warning-start-assistance'),
                  onPressed: () => context.push(
                    Uri(
                      path: '/pendampingan-siswa/tambah',
                      queryParameters: {
                        'tahun': '${item.academicYear?.id ?? ''}',
                        'kelas': '${item.schoolClass?.id ?? ''}',
                        'peringatan': '${item.id}',
                        'siswa': '${item.student.id}',
                        'q': item.student.name,
                      }..removeWhere((key, value) => value.isEmpty),
                    ).toString(),
                  ),
                  icon: const Icon(Icons.add_rounded),
                  label: const Text('Mulai Pendampingan'),
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

class _SanctionCard extends StatelessWidget {
  const _SanctionCard({required this.sanction});
  final StudentWarningSanction sanction;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _SectionTitle(
            icon: Icons.gavel_rounded,
            title: 'Sanksi Terkait',
          ),
          const SizedBox(height: 10),
          Text(
            sanction.name,
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 4),
          Text(
            '${sanction.statusLabel}${_filled(sanction.deadline) ? ' · Batas ${_dateLabel(sanction.deadline!)}' : ''}',
            style: TextStyle(
              color: sanction.overdue
                  ? const Color(0xFFD84A3A)
                  : NusaColors.textSecondary,
              fontSize: 10.5,
            ),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            key: const Key('student-warning-open-sanction'),
            onPressed: () =>
                context.push('/pelaksanaan-sanksi-siswa/${sanction.id}'),
            icon: const Icon(Icons.open_in_new_rounded),
            label: const Text('Buka Pelaksanaan Sanksi'),
          ),
        ],
      ),
    ),
  );
}

class _AutomaticInfo extends StatelessWidget {
  const _AutomaticInfo();
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      border: Border.all(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(15),
    ),
    child: const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.auto_awesome_rounded, color: NusaColors.primary, size: 20),
        SizedBox(width: 9),
        Expanded(
          child: Text(
            'Status peringatan dikelola otomatis oleh NUSA berdasarkan poin, pelanggaran, keterlambatan, dan sanksi siswa. Tindak lanjut dicatat melalui Pendampingan Siswa.',
            style: TextStyle(fontSize: 10, height: 1.45),
          ),
        ),
      ],
    ),
  );
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.icon, required this.title});
  final IconData icon;
  final String title;
  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon, size: 20, color: NusaColors.primary),
      const SizedBox(width: 8),
      Expanded(
        child: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
    ],
  );
}

class _LabelValue extends StatelessWidget {
  const _LabelValue({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Expanded(
        child: Text(
          label,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 10.5,
          ),
        ),
      ),
      const SizedBox(width: 12),
      Expanded(
        child: Text(
          value,
          textAlign: TextAlign.right,
          style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800),
        ),
      ),
    ],
  );
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(10),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w800),
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

bool _filled(String? value) => value != null && value.trim().isNotEmpty;

String _dateLabel(String value) {
  final date = DateTime.tryParse(value);
  return date == null
      ? value
      : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String _dateTimeLabel(DateTime? date) {
  if (date == null) return '-';
  return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')} WIB';
}

String _message(Object error) => switch (error) {
  ValidationException exception when exception.errors.isNotEmpty =>
    exception.errors.values.expand((messages) => messages).join('\n'),
  AppException exception => exception.message,
  _ => 'Detail peringatan dini belum dapat dimuat.',
};
