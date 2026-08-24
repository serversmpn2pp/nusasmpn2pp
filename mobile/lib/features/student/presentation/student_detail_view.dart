import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student/application/student_controller.dart';
import 'package:nusa/features/student/domain/student.dart';
import 'package:nusa/features/student/presentation/widgets/student_components.dart';

class StudentDetailView extends ConsumerWidget {
  const StudentDetailView({required this.studentId, super.key});

  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final student = ref.watch(studentDetailProvider(studentId));

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(title: const Text('Detail Siswa')),
      body: SafeArea(
        top: false,
        child: student.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _DetailErrorState(
            message: error is AppException
                ? error.message
                : 'Detail siswa belum dapat dimuat.',
            onRetry: () => ref.invalidate(studentDetailProvider(studentId)),
          ),
          data: (detail) => RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(studentDetailProvider(studentId));
              await ref.read(studentDetailProvider(studentId).future);
            },
            child: ListView(
              key: const PageStorageKey<String>('student-detail-scroll'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                _StudentHero(detail: detail),
                const SizedBox(height: 14),
                _DetailSection(
                  title: 'Identitas Siswa',
                  icon: Icons.badge_outlined,
                  rows: [
                    _DetailValue('NIS', detail.summary.nis),
                    _DetailValue('NISN', detail.summary.nisn),
                    _DetailValue('NIK', detail.nik),
                    _DetailValue(
                      'Jenis Kelamin',
                      switch (detail.summary.gender) {
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                        _ => null,
                      },
                    ),
                    _DetailValue(
                      'Tempat, Tanggal Lahir',
                      _birthLabel(detail.birthPlace, detail.birthDate),
                    ),
                    _DetailValue('Agama', detail.religion),
                    _DetailValue('Status dalam Keluarga', detail.familyStatus),
                    _DetailValue('Anak ke', detail.childOrder?.toString()),
                  ],
                ),
                const SizedBox(height: 12),
                _ClassSection(studentClass: detail.summary.activeClass),
                if (detail.parents.hasData) ...[
                  const SizedBox(height: 12),
                  _DetailSection(
                    title: 'Orang Tua dan Wali',
                    icon: Icons.family_restroom_rounded,
                    rows: [
                      _DetailValue('Nama Ayah', detail.parents.fatherName),
                      _DetailValue('WhatsApp Ayah', detail.parents.fatherPhone),
                      _DetailValue(
                        'Pekerjaan Ayah',
                        detail.parents.fatherOccupation,
                      ),
                      _DetailValue('Nama Ibu', detail.parents.motherName),
                      _DetailValue('WhatsApp Ibu', detail.parents.motherPhone),
                      _DetailValue(
                        'Pekerjaan Ibu',
                        detail.parents.motherOccupation,
                      ),
                      _DetailValue('Nama Wali', detail.parents.guardianName),
                      _DetailValue(
                        'Hubungan Wali',
                        detail.parents.guardianRelation,
                      ),
                      _DetailValue(
                        'WhatsApp Wali',
                        detail.parents.guardianPhone,
                      ),
                      _DetailValue(
                        'Kontak Absensi Utama',
                        _capitalize(detail.parents.primaryAttendanceContact),
                      ),
                    ],
                  ),
                ],
                if (_hasText([
                  detail.address,
                  detail.previousSchool,
                  detail.notes,
                ])) ...[
                  const SizedBox(height: 12),
                  _DetailSection(
                    title: 'Informasi Tambahan',
                    icon: Icons.info_outline_rounded,
                    rows: [
                      _DetailValue('Alamat', detail.address),
                      _DetailValue('Sekolah Asal', detail.previousSchool),
                      _DetailValue('Keterangan', detail.notes),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StudentHero extends StatelessWidget {
  const _StudentHero({required this.detail});

  final StudentDetail detail;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: NusaColors.primary.withValues(alpha: 0.18),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(3),
            decoration: const BoxDecoration(
              color: NusaColors.accent,
              shape: BoxShape.circle,
            ),
            child: StudentAvatar(student: detail.summary, size: 68),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  detail.summary.name,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 19,
                    fontWeight: FontWeight.w800,
                    height: 1.15,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  detail.summary.identityLabel,
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    StudentStatusBadge(active: detail.summary.active),
                    if (detail.summary.activeClass
                        case final studentClass?) ...[
                      const SizedBox(width: 8),
                      Flexible(
                        child: Text(
                          studentClass.name,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: NusaColors.accent,
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ClassSection extends StatelessWidget {
  const _ClassSection({required this.studentClass});

  final StudentActiveClass? studentClass;

  @override
  Widget build(BuildContext context) {
    return _DetailSection(
      title: 'Kelas Aktif',
      icon: Icons.class_outlined,
      rows: studentClass == null
          ? const [_DetailValue('Kelas', 'Belum ditempatkan')]
          : [
              _DetailValue('Kelas', studentClass!.name),
              _DetailValue('Tingkat', studentClass!.level?.toString()),
              _DetailValue(
                'Nomor Absen',
                studentClass!.attendanceNumber?.toString(),
              ),
              _DetailValue('Tahun Pelajaran', studentClass!.academicYear),
            ],
    );
  }
}

class _DetailValue {
  const _DetailValue(this.label, this.value);

  final String label;
  final String? value;
}

class _DetailSection extends StatelessWidget {
  const _DetailSection({
    required this.title,
    required this.icon,
    required this.rows,
  });

  final String title;
  final IconData icon;
  final List<_DetailValue> rows;

  @override
  Widget build(BuildContext context) {
    final visibleRows = rows
        .where((row) => row.value?.trim().isNotEmpty == true)
        .toList(growable: false);

    return Container(
      padding: const EdgeInsets.all(16),
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
                child: Icon(icon, color: NusaColors.primary, size: 20),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (visibleRows.isEmpty)
            const Text(
              'Data belum tersedia.',
              style: TextStyle(color: NusaColors.textSecondary),
            )
          else
            for (var index = 0; index < visibleRows.length; index++) ...[
              _DetailRow(row: visibleRows[index]),
              if (index < visibleRows.length - 1)
                const Divider(height: 17, color: NusaColors.outline),
            ],
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.row});

  final _DetailValue row;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 112,
          child: Text(
            row.label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 12,
            ),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            row.value!,
            style: const TextStyle(
              color: NusaColors.textPrimary,
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}

class _DetailErrorState extends StatelessWidget {
  const _DetailErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.person_off_outlined,
              size: 48,
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
}

String? _birthLabel(String? place, DateTime? date) {
  final parts = <String>[];
  if (place?.trim().isNotEmpty == true) {
    parts.add(place!.trim());
  }
  if (date != null) {
    const months = [
      'Januari',
      'Februari',
      'Maret',
      'April',
      'Mei',
      'Juni',
      'Juli',
      'Agustus',
      'September',
      'Oktober',
      'November',
      'Desember',
    ];
    parts.add('${date.day} ${months[date.month - 1]} ${date.year}');
  }

  return parts.isEmpty ? null : parts.join(', ');
}

String? _capitalize(String? value) {
  if (value == null || value.isEmpty) {
    return null;
  }

  return '${value[0].toUpperCase()}${value.substring(1)}';
}

bool _hasText(List<String?> values) {
  return values.any((value) => value?.trim().isNotEmpty == true);
}
