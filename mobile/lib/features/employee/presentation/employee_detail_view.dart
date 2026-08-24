import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee/application/employee_controller.dart';
import 'package:nusa/features/employee/domain/employee.dart';
import 'package:nusa/features/employee/presentation/widgets/employee_components.dart';
import 'package:nusa/features/employee/presentation/widgets/employee_form_sheet.dart';

class EmployeeDetailView extends ConsumerStatefulWidget {
  const EmployeeDetailView({required this.employeeId, super.key});

  final int employeeId;

  @override
  ConsumerState<EmployeeDetailView> createState() => _EmployeeDetailViewState();
}

class _EmployeeDetailViewState extends ConsumerState<EmployeeDetailView> {
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final employee = ref.watch(employeeDetailProvider(widget.employeeId));
    final current = employee.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Pegawai'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: employee.isLoading
                ? null
                : () =>
                      ref.invalidate(employeeDetailProvider(widget.employeeId)),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('edit-employee'),
              onPressed: _mutating ? null : () => _edit(current!),
              icon: const Icon(Icons.edit_outlined),
              label: const Text('Ubah Pegawai'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: employee.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _DetailError(
            message: _errorMessage(error),
            onRetry: () =>
                ref.invalidate(employeeDetailProvider(widget.employeeId)),
          ),
          data: (detail) => RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(employeeDetailProvider(widget.employeeId));
              await ref.read(employeeDetailProvider(widget.employeeId).future);
            },
            child: ListView(
              key: const PageStorageKey<String>('employee-detail-scroll'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
              children: [
                _EmployeeHero(detail: detail),
                const SizedBox(height: 12),
                _AssignmentSummary(counts: detail.assignmentCounts),
                const SizedBox(height: 12),
                _DetailSection(
                  title: 'Identitas Utama',
                  icon: Icons.badge_outlined,
                  rows: [
                    _DetailValue('NIP', detail.summary.nip),
                    _DetailValue('NUPTK', detail.summary.nuptk),
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
                  ],
                ),
                const SizedBox(height: 12),
                _DetailSection(
                  title: 'Kontak',
                  icon: Icons.contact_phone_outlined,
                  rows: [
                    _DetailValue('Email', detail.email),
                    _DetailValue('Nomor HP', detail.phone),
                    _DetailValue('Alamat', detail.address),
                  ],
                ),
                const SizedBox(height: 12),
                _DetailSection(
                  title: 'Kepegawaian',
                  icon: Icons.work_outline_rounded,
                  rows: [
                    _DetailValue('Jenis Pegawai', detail.summary.employeeType),
                    _DetailValue(
                      'Status Kepegawaian',
                      detail.summary.employmentStatus,
                    ),
                    _DetailValue(
                      'Jabatan Utama',
                      detail.summary.primaryPosition,
                    ),
                    _DetailValue('Golongan', detail.rank),
                    _DetailValue(
                      'Mulai Kerja',
                      _dateLabel(detail.workStartDate),
                    ),
                    _DetailValue(
                      'Mulai Bertugas',
                      _dateLabel(detail.dutyStartDate),
                    ),
                    _DetailValue('Sumber Gaji', detail.salarySource),
                  ],
                ),
                const SizedBox(height: 12),
                _DetailSection(
                  title: 'Pendidikan & Catatan',
                  icon: Icons.school_outlined,
                  rows: [
                    _DetailValue('Pendidikan Terakhir', detail.lastEducation),
                    _DetailValue('Jurusan', detail.educationMajor),
                    _DetailValue(
                      'Tahun Lulus',
                      detail.graduationYear?.toString(),
                    ),
                    _DetailValue('Keterangan', detail.notes),
                  ],
                ),
                const SizedBox(height: 12),
                _AccountSection(account: detail.account),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _edit(EmployeeDetail detail) async {
    final value = await showEmployeeFormSheet(context, existing: detail);
    if (value == null || !mounted) return;
    setState(() => _mutating = true);
    try {
      await ref
          .read(employeeActionsProvider)
          .update(id: widget.employeeId, value: value);
      ref.invalidate(employeeListControllerProvider);
      ref.invalidate(employeeDetailProvider(widget.employeeId));
      await ref.read(employeeDetailProvider(widget.employeeId).future);
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(content: Text('Data pegawai berhasil diperbarui.')),
        );
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }
}

class _EmployeeHero extends StatelessWidget {
  const _EmployeeHero({required this.detail});

  final EmployeeDetail detail;

  @override
  Widget build(BuildContext context) => Container(
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
          child: EmployeeAvatar(employee: detail.summary, size: 68),
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
              const SizedBox(height: 5),
              Text(
                detail.summary.identityLabel,
                style: const TextStyle(color: Colors.white70, fontSize: 12),
              ),
              const SizedBox(height: 4),
              Text(
                detail.summary.roleLabel,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.accent,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              EmployeeStatusBadge(active: detail.summary.active),
            ],
          ),
        ),
      ],
    ),
  );
}

class _AssignmentSummary extends StatelessWidget {
  const _AssignmentSummary({required this.counts});

  final EmployeeAssignmentCounts counts;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: _AssignmentTile(
          icon: Icons.class_outlined,
          value: counts.activeHomeroomClasses,
          label: 'Kelas Wali Aktif',
        ),
      ),
      const SizedBox(width: 9),
      Expanded(
        child: _AssignmentTile(
          icon: Icons.menu_book_outlined,
          value: counts.activeSubjectAssignments,
          label: 'Penugasan Mapel',
        ),
      ),
    ],
  );
}

class _AssignmentTile extends StatelessWidget {
  const _AssignmentTile({
    required this.icon,
    required this.value,
    required this.label,
  });

  final IconData icon;
  final int value;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: NusaColors.surfaceBlue,
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(icon, color: NusaColors.primary, size: 20),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '$value',
                style: const TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                label,
                maxLines: 2,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 9.5,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _AccountSection extends StatelessWidget {
  const _AccountSection({required this.account});

  final EmployeeAccount account;

  @override
  Widget build(BuildContext context) => _DetailSection(
    title: 'Akun Login',
    icon: Icons.manage_accounts_outlined,
    rows: account.available
        ? [
            _DetailValue('Username', account.username),
            _DetailValue('Status Akun', account.active ? 'Aktif' : 'Nonaktif'),
          ]
        : const [_DetailValue('Status Akun', 'Belum memiliki akun login')],
  );
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
                  style: const TextStyle(
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
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      SizedBox(
        width: 112,
        child: Text(
          row.label,
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 12),
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

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Detail pegawai belum dapat dimuat. Silakan coba lagi.';

String? _birthLabel(String? place, DateTime? date) {
  final parts = <String>[];
  if (place?.trim().isNotEmpty == true) parts.add(place!.trim());
  final dateText = _dateLabel(date);
  if (dateText != null) parts.add(dateText);
  return parts.isEmpty ? null : parts.join(', ');
}

String? _dateLabel(DateTime? value) {
  if (value == null) return null;
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
  return '${value.day} ${months[value.month - 1]} ${value.year}';
}
