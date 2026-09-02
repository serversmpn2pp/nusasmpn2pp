import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_guardian_students/application/my_guardian_student_controller.dart';
import 'package:nusa/features/my_guardian_students/domain/my_guardian_student.dart';

class MyGuardianStudentDetailView extends ConsumerWidget {
  const MyGuardianStudentDetailView({required this.studentId, super.key});
  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(myGuardianStudentDetailProvider(studentId));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Siswa Wali'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : () => ref.invalidate(
                    myGuardianStudentDetailProvider(studentId),
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
                ref.invalidate(myGuardianStudentDetailProvider(studentId)),
          ),
          data: (detail) => _Content(detail: detail),
        ),
      ),
    );
  }
}

class _Content extends StatelessWidget {
  const _Content({required this.detail});
  final MyGuardianStudentDetail detail;

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    padding: const EdgeInsets.fromLTRB(16, 9, 16, 28),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _ProfileHeader(detail: detail),
        const SizedBox(height: 10),
        _Metrics(detail: detail),
        if (detail.access.canViewPointRecap) ...[
          const SizedBox(height: 10),
          FilledButton.icon(
            key: const Key('my-guardian-student-point-recap'),
            onPressed: () => context.push(
              Uri(
                path: '/rekap-poin-siswa/${detail.student.id}',
                queryParameters: {'tahun': '${detail.academicYear?.id ?? ''}'}
                  ..removeWhere((key, value) => value.isEmpty),
              ).toString(),
            ),
            icon: const Icon(Icons.monitor_heart_outlined),
            label: const Text('Buka Pemantauan & Rekap Poin'),
          ),
        ],
        const SizedBox(height: 10),
        _InfoSection(
          key: const Key('my-guardian-student-assignment-section'),
          icon: Icons.supervisor_account_rounded,
          title: 'Penugasan Guru Wali',
          initiallyExpanded: true,
          rows: [
            _Info('Mulai didampingi', _date(detail.assignment.startDate)),
            _Info('Nomor SK', _text(detail.assignment.decreeNumber)),
            _Info('Tahun pelajaran', detail.academicYear?.name ?? '-'),
            if (_filled(detail.assignment.note))
              _Info('Catatan penugasan', detail.assignment.note!),
          ],
        ),
        const SizedBox(height: 9),
        _InfoSection(
          key: const Key('my-guardian-student-identity-section'),
          icon: Icons.badge_outlined,
          title: 'Identitas Siswa',
          initiallyExpanded: true,
          rows: [
            _Info('Nama lengkap', detail.student.name),
            _Info('Jenis kelamin', detail.student.genderLabel),
            _Info('NIS', _text(detail.student.nis)),
            _Info('NISN', _text(detail.student.nisn)),
            _Info('NIK', _text(detail.student.nik)),
            _Info('Tempat, tanggal lahir', _birth(detail.student)),
            _Info('Agama', _text(detail.student.religion)),
            _Info('Sekolah asal', _text(detail.student.previousSchool)),
            _Info('Status dalam keluarga', _text(detail.student.familyStatus)),
            _Info(
              'Anak ke',
              detail.student.childNumber == null
                  ? '-'
                  : '${detail.student.childNumber}',
            ),
          ],
        ),
        const SizedBox(height: 9),
        _ParentSection(contact: detail.student.parentContact),
        const SizedBox(height: 9),
        _InfoSection(
          key: const Key('my-guardian-student-address-section'),
          icon: Icons.home_outlined,
          title: 'Alamat & Catatan',
          rows: [
            _Info('Alamat', _text(detail.student.address)),
            _Info('Keterangan siswa', _text(detail.student.note)),
          ],
        ),
        const SizedBox(height: 9),
        _ReportSection(detail: detail),
      ],
    ),
  );
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({required this.detail});
  final MyGuardianStudentDetail detail;

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
        _Avatar(name: detail.student.name, photoUrl: detail.student.photoUrl),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                detail.student.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                '${detail.schoolClass?.name ?? 'Belum ditempatkan'} · NISN ${detail.student.nisn ?? '-'}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white70, fontSize: 10),
              ),
              const SizedBox(height: 7),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  detail.student.active ? 'Siswa aktif' : 'Siswa nonaktif',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.name, required this.photoUrl});
  final String name;
  final String? photoUrl;

  @override
  Widget build(BuildContext context) {
    final image = photoUrl == null || photoUrl!.isEmpty
        ? null
        : NetworkImage(photoUrl!);
    return CircleAvatar(
      radius: 30,
      backgroundColor: Colors.white.withValues(alpha: 0.16),
      backgroundImage: image,
      child: image == null
          ? Text(
              name.isEmpty ? '?' : name.substring(0, 1).toUpperCase(),
              style: const TextStyle(
                color: NusaColors.accent,
                fontSize: 21,
                fontWeight: FontWeight.w900,
              ),
            )
          : null,
    );
  }
}

class _Metrics extends StatelessWidget {
  const _Metrics({required this.detail});
  final MyGuardianStudentDetail detail;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final width = (constraints.maxWidth - 9) / 2;
      return Wrap(
        spacing: 9,
        runSpacing: 9,
        children: [
          _Metric(
            width: width,
            icon: Icons.class_outlined,
            label: 'Kelas saat ini',
            value: detail.schoolClass?.name ?? '-',
          ),
          _Metric(
            width: width,
            icon: Icons.format_list_numbered_rounded,
            label: 'Nomor absen',
            value: detail.schoolClass?.attendanceNumber == null
                ? '-'
                : '${detail.schoolClass!.attendanceNumber}',
          ),
          _Metric(
            width: width,
            icon: Icons.speed_rounded,
            label: 'Poin resmi',
            value: '${detail.summary.totalPoints} poin',
            accent: true,
          ),
          _Metric(
            width: width,
            icon: Icons.assignment_outlined,
            label: 'Riwayat laporan',
            value: '${detail.summary.reportCount} laporan',
            accent: true,
          ),
        ],
      );
    },
  );
}

class _Metric extends StatelessWidget {
  const _Metric({
    required this.width,
    required this.icon,
    required this.label,
    required this.value,
    this.accent = false,
  });
  final double width;
  final IconData icon;
  final String label;
  final String value;
  final bool accent;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: width,
    child: Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              icon,
              size: 20,
              color: accent ? const Color(0xFFC58F00) : NusaColors.primary,
            ),
            const SizedBox(height: 7),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 9,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _InfoSection extends StatelessWidget {
  const _InfoSection({
    required this.icon,
    required this.title,
    required this.rows,
    this.initiallyExpanded = false,
    super.key,
  });
  final IconData icon;
  final String title;
  final List<_Info> rows;
  final bool initiallyExpanded;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: ExpansionTile(
      initiallyExpanded: initiallyExpanded,
      tilePadding: const EdgeInsets.symmetric(horizontal: 14),
      childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 13),
      leading: Icon(icon, color: NusaColors.primary),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
      children: [for (final row in rows) _InfoRow(info: row)],
    ),
  );
}

class _ParentSection extends StatelessWidget {
  const _ParentSection({required this.contact});
  final MyGuardianParentContact contact;

  @override
  Widget build(BuildContext context) => _InfoSection(
    key: const Key('my-guardian-student-parent-section'),
    icon: Icons.family_restroom_rounded,
    title: 'Orang Tua & Wali',
    initiallyExpanded: true,
    rows: [
      _Info('Nama ayah', _text(contact.fatherName)),
      _Info('Nomor WA ayah', _text(contact.fatherPhone)),
      _Info('Pekerjaan ayah', _text(contact.fatherOccupation)),
      _Info('Nama ibu', _text(contact.motherName)),
      _Info('Nomor WA ibu', _text(contact.motherPhone)),
      _Info('Pekerjaan ibu', _text(contact.motherOccupation)),
      _Info('Nama wali lain', _text(contact.guardianName)),
      _Info('Hubungan wali', _text(contact.guardianRelationship)),
      _Info('Nomor WA wali lain', _text(contact.guardianPhone)),
      _Info(
        'Kontak presensi utama',
        _text(contact.primaryAttendanceContactLabel),
      ),
    ],
  );
}

class _Info {
  const _Info(this.label, this.value);
  final String label;
  final String value;
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.info});
  final _Info info;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.symmetric(vertical: 9),
    decoration: const BoxDecoration(
      border: Border(bottom: BorderSide(color: NusaColors.outline)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          info.label,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 3),
        SelectableText(
          info.value,
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
        ),
      ],
    ),
  );
}

class _ReportSection extends StatelessWidget {
  const _ReportSection({required this.detail});
  final MyGuardianStudentDetail detail;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Icon(Icons.history_edu_rounded, color: NusaColors.primary),
              const SizedBox(width: 8),
              const Expanded(
                child: Text(
                  'Pembinaan Terbaru',
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
              Text(
                '${detail.latestReports.length}/5',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          const Text(
            'Laporan terbaru pada tahun pelajaran aktif.',
            style: TextStyle(color: NusaColors.textSecondary, fontSize: 9.5),
          ),
          const SizedBox(height: 10),
          if (detail.latestReports.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 16),
              child: Text(
                'Belum ada riwayat pembinaan pada periode ini.',
                textAlign: TextAlign.center,
                style: TextStyle(color: NusaColors.textSecondary),
              ),
            )
          else
            for (final report in detail.latestReports)
              _ReportRow(
                report: report,
                onTap: detail.access.canViewPointRecap
                    ? () => context.push('/daftar-laporan-siswa/${report.id}')
                    : null,
              ),
        ],
      ),
    ),
  );
}

class _ReportRow extends StatelessWidget {
  const _ReportRow({required this.report, this.onTap});
  final MyGuardianStudentReport report;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    key: Key('my-guardian-student-report-${report.id}'),
    onTap: onTap,
    borderRadius: BorderRadius.circular(13),
    child: Container(
      margin: const EdgeInsets.only(top: 7),
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: NusaColors.surfaceBlue,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(11),
            ),
            child: const Icon(
              Icons.assignment_outlined,
              size: 20,
              color: NusaColors.primary,
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  report.number,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                Text(
                  '${_date(report.date)} · ${report.category ?? report.typeLabel}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 9.5,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  report.statusLabel,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 9.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 7),
          Column(
            children: [
              Text(
                '${report.points}',
                style: const TextStyle(
                  color: Color(0xFFC58F00),
                  fontSize: 16,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const Text(
                'poin',
                style: TextStyle(color: NusaColors.textSecondary, fontSize: 8),
              ),
            ],
          ),
        ],
      ),
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
          const Icon(Icons.lock_person_outlined, size: 48),
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

String _birth(MyGuardianStudentProfile student) {
  final values = [
    if (_filled(student.birthPlace)) student.birthPlace!,
    if (_filled(student.birthDate)) _date(student.birthDate),
  ];
  return values.isEmpty ? '-' : values.join(', ');
}

String _date(String? value) {
  final date = value == null ? null : DateTime.tryParse(value);
  if (date == null) return '-';
  return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String _text(String? value) => _filled(value) ? value!.trim() : '-';
bool _filled(String? value) => value != null && value.trim().isNotEmpty;
String _message(Object error) => switch (error) {
  AppException exception => exception.message,
  _ => 'Detail siswa wali belum dapat dimuat.',
};
