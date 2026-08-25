import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/parent_account/application/parent_account_controller.dart';
import 'package:nusa/features/parent_account/domain/parent_account.dart';
import 'package:nusa/features/parent_account/presentation/widgets/parent_account_components.dart';

class ParentAccountDetailView extends ConsumerStatefulWidget {
  const ParentAccountDetailView({required this.studentId, super.key});

  final int studentId;

  @override
  ConsumerState<ParentAccountDetailView> createState() =>
      _ParentAccountDetailViewState();
}

class _ParentAccountDetailViewState
    extends ConsumerState<ParentAccountDetailView> {
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final detail = ref.watch(parentAccountDetailProvider(widget.studentId));

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Akun Orang Tua'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: detail.isLoading
                ? null
                : () => ref.invalidate(
                    parentAccountDetailProvider(widget.studentId),
                  ),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: detail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _DetailError(
            message: _errorMessage(error),
            onRetry: () =>
                ref.invalidate(parentAccountDetailProvider(widget.studentId)),
          ),
          data: (detail) => _DetailContent(
            detail: detail,
            mutating: _mutating,
            onCreate: _createAccount,
            onResetPassword: _resetPassword,
            onToggleStatus: _toggleStatus,
            onCopy: _copy,
          ),
        ),
      ),
    );
  }

  Future<void> _createAccount(ParentAccountDetail detail) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: const Icon(Icons.family_restroom_rounded),
        title: const Text('Buat akun orang tua?'),
        content: Text(
          'Akun orang tua ${detail.item.student.name} akan dibuat dengan '
          'username ORT-${detail.item.student.nisn}. Identitas mengambil '
          'kontak utama ayah, ibu, atau wali.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('confirm-create-parent-account'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Buat Akun'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _mutate(
      () => ref
          .read(parentAccountActionsProvider)
          .createAccount(widget.studentId),
      'Akun orang tua berhasil dibuat.',
    );
  }

  Future<void> _resetPassword(ParentAccountDetail detail) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: const Icon(Icons.password_rounded),
        title: const Text('Reset kata sandi?'),
        content: Text(
          'Kata sandi akun orang tua ${detail.item.student.name} akan '
          'diganti dengan delapan angka acak baru dan wajib diganti saat login.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('confirm-reset-parent-password'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Reset'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _mutate(
      () => ref
          .read(parentAccountActionsProvider)
          .resetPassword(widget.studentId),
      'Kata sandi awal berhasil dibuat ulang.',
    );
  }

  Future<void> _toggleStatus(ParentAccountDetail detail) async {
    final account = detail.item.account;
    final nextActive = !account.active;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: Icon(
          nextActive ? Icons.check_circle_rounded : Icons.block_rounded,
        ),
        title: Text(nextActive ? 'Aktifkan akun?' : 'Nonaktifkan akun?'),
        content: Text(
          nextActive
              ? 'Orang tua dapat kembali menggunakan akun untuk masuk ke NUSA.'
              : 'Orang tua tidak dapat masuk ke NUSA sampai akun diaktifkan kembali.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('confirm-toggle-parent-account'),
            onPressed: () => Navigator.pop(context, true),
            child: Text(nextActive ? 'Aktifkan' : 'Nonaktifkan'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _mutate(
      () => ref
          .read(parentAccountActionsProvider)
          .updateStatus(studentId: widget.studentId, active: nextActive),
      nextActive
          ? 'Akun orang tua diaktifkan.'
          : 'Akun orang tua dinonaktifkan.',
    );
  }

  Future<void> _mutate(
    Future<void> Function() operation,
    String successMessage,
  ) async {
    if (_mutating) return;
    setState(() => _mutating = true);
    try {
      await operation();
      ref.invalidate(parentAccountListControllerProvider);
      ref.invalidate(parentAccountDetailProvider(widget.studentId));
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(successMessage)));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _copy(String label, String value) async {
    await Clipboard.setData(ClipboardData(text: value));
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text('$label disalin.')));
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({
    required this.detail,
    required this.mutating,
    required this.onCreate,
    required this.onResetPassword,
    required this.onToggleStatus,
    required this.onCopy,
  });

  final ParentAccountDetail detail;
  final bool mutating;
  final ValueChanged<ParentAccountDetail> onCreate;
  final ValueChanged<ParentAccountDetail> onResetPassword;
  final ValueChanged<ParentAccountDetail> onToggleStatus;
  final void Function(String label, String value) onCopy;

  @override
  Widget build(BuildContext context) {
    final item = detail.item;
    final account = item.account;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
      children: [
        _StudentHeader(item: item, academicYear: detail.academicYear),
        const SizedBox(height: 12),
        _ParentIdentityCard(parent: item.parent, onCopy: onCopy),
        const SizedBox(height: 12),
        _FamilyContactsCard(contacts: item.familyContacts, onCopy: onCopy),
        const SizedBox(height: 12),
        if (account.available) ...[
          _SectionCard(
            icon: Icons.login_rounded,
            title: 'Informasi Login Orang Tua',
            child: Column(
              children: [
                _InformationRow(
                  label: 'Username',
                  value: account.username ?? '-',
                  trailing: account.username == null
                      ? null
                      : IconButton(
                          tooltip: 'Salin username',
                          onPressed: () =>
                              onCopy('Username', account.username!),
                          icon: const Icon(Icons.copy_rounded, size: 19),
                        ),
                ),
                const Divider(height: 20),
                _InformationRow(
                  label: 'Kata sandi awal',
                  value: _passwordLabel(detail),
                  trailing:
                      detail.canViewCredentials &&
                          account.initialPassword != null
                      ? IconButton(
                          tooltip: 'Salin kata sandi',
                          onPressed: () => onCopy(
                            'Kata sandi awal',
                            account.initialPassword!,
                          ),
                          icon: const Icon(Icons.copy_rounded, size: 19),
                        )
                      : null,
                ),
                const Divider(height: 20),
                _InformationRow(
                  label: 'Status kata sandi',
                  value: account.mustChangePassword
                      ? 'Wajib diganti saat login'
                      : 'Kata sandi sudah diperbarui',
                ),
                const Divider(height: 20),
                _InformationRow(
                  label: 'Login terakhir',
                  value: _dateTimeLabel(account.lastLoginAt),
                ),
              ],
            ),
          ),
          if (detail.canManage) ...[
            const SizedBox(height: 12),
            _SectionCard(
              icon: Icons.admin_panel_settings_outlined,
              title: 'Pengelolaan Akun',
              child: Column(
                children: [
                  _ActionTile(
                    key: const Key('reset-parent-account-password'),
                    icon: Icons.password_rounded,
                    title: 'Reset Kata Sandi',
                    subtitle:
                        'Buat delapan angka acak baru sebagai sandi awal.',
                    enabled: !mutating,
                    onTap: () => onResetPassword(detail),
                  ),
                  const Divider(height: 18),
                  _ActionTile(
                    key: const Key('toggle-parent-account-status'),
                    icon: account.active
                        ? Icons.block_rounded
                        : Icons.check_circle_rounded,
                    title: account.active
                        ? 'Nonaktifkan Akun'
                        : 'Aktifkan Akun',
                    subtitle: account.active
                        ? 'Hentikan sementara akses login orang tua.'
                        : 'Izinkan orang tua kembali masuk ke NUSA.',
                    enabled: !mutating,
                    destructive: account.active,
                    onTap: () => onToggleStatus(detail),
                  ),
                ],
              ),
            ),
          ],
        ] else
          _UnavailableAccountCard(
            detail: detail,
            mutating: mutating,
            onCreate: () => onCreate(detail),
          ),
      ],
    );
  }

  String _passwordLabel(ParentAccountDetail detail) {
    final account = detail.item.account;
    if (detail.canViewCredentials && account.initialPassword != null) {
      return account.initialPassword!;
    }
    if (account.initialPasswordAvailable) {
      return 'Tersedia bagi pengguna berizin';
    }
    return 'Sudah diganti / tidak tersedia';
  }
}

class _StudentHeader extends StatelessWidget {
  const _StudentHeader({required this.item, required this.academicYear});

  final ParentAccountItem item;
  final ParentAcademicYear? academicYear;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      gradient: LinearGradient(
        colors: [
          NusaColors.primary,
          NusaColors.primaryDark.withValues(alpha: 0.96),
        ],
      ),
      borderRadius: BorderRadius.circular(19),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ParentAccountAvatar(student: item.student, size: 62),
        const SizedBox(width: 13),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.student.name,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                '${item.membership.schoolClass.name} • No. ${item.membership.attendanceNumber ?? '-'}',
                style: const TextStyle(color: Colors.white70, fontSize: 11.5),
              ),
              const SizedBox(height: 2),
              Text(
                'NIS ${item.student.nis ?? '-'} • NISN ${item.student.nisn ?? '-'}',
                style: const TextStyle(color: Colors.white70, fontSize: 11.5),
              ),
              if (academicYear != null) ...[
                const SizedBox(height: 2),
                Text(
                  'Tahun Pelajaran ${academicYear!.name}',
                  style: const TextStyle(color: Colors.white70, fontSize: 11.5),
                ),
              ],
              const SizedBox(height: 9),
              ParentAccountStatusBadge(
                status: item.status,
                label: item.statusLabel,
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _ParentIdentityCard extends StatelessWidget {
  const _ParentIdentityCard({required this.parent, required this.onCopy});

  final ParentGuardian parent;
  final void Function(String label, String value) onCopy;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.family_restroom_rounded,
    title: 'Orang Tua/Wali Pemilik Akun',
    child: parent.available
        ? Column(
            children: [
              _InformationRow(label: 'Nama', value: parent.name ?? '-'),
              const Divider(height: 20),
              _InformationRow(
                label: 'Hubungan',
                value: parent.relationshipLabel,
              ),
              const Divider(height: 20),
              _InformationRow(
                label: 'WhatsApp',
                value: parent.phone ?? '-',
                trailing: parent.phone?.trim().isNotEmpty == true
                    ? IconButton(
                        tooltip: 'Salin nomor WhatsApp',
                        onPressed: () =>
                            onCopy('Nomor WhatsApp', parent.phone!),
                        icon: const Icon(Icons.copy_rounded, size: 19),
                      )
                    : null,
              ),
            ],
          )
        : const Text(
            'Identitas pemilik akun akan ditentukan dari kontak utama '
            'ayah, ibu, atau wali ketika akun dibuat.',
            style: TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 12.5,
              height: 1.45,
            ),
          ),
  );
}

class _FamilyContactsCard extends StatelessWidget {
  const _FamilyContactsCard({required this.contacts, required this.onCopy});

  final List<ParentFamilyContact> contacts;
  final void Function(String label, String value) onCopy;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.contacts_outlined,
    title: 'Kontak Keluarga',
    child: contacts.isEmpty
        ? const Text(
            'Data ayah, ibu, dan wali belum tersedia.',
            style: TextStyle(color: NusaColors.textSecondary, fontSize: 12.5),
          )
        : Column(
            children: [
              for (var index = 0; index < contacts.length; index++) ...[
                _FamilyContactTile(contact: contacts[index], onCopy: onCopy),
                if (index < contacts.length - 1) const Divider(height: 20),
              ],
            ],
          ),
  );
}

class _FamilyContactTile extends StatelessWidget {
  const _FamilyContactTile({required this.contact, required this.onCopy});

  final ParentFamilyContact contact;
  final void Function(String label, String value) onCopy;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: contact.primary
              ? NusaColors.accent.withValues(alpha: 0.16)
              : NusaColors.surfaceBlue,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Icon(
          contact.code == 'ayah'
              ? Icons.man_rounded
              : contact.code == 'ibu'
              ? Icons.woman_rounded
              : Icons.person_outline_rounded,
          color: NusaColors.primary,
        ),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  contact.label,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (contact.primary) ...[
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 7,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: NusaColors.accent.withValues(alpha: 0.18),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: const Text(
                      'Utama',
                      style: TextStyle(
                        color: NusaColors.primaryDark,
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 2),
            Text(
              contact.name?.trim().isNotEmpty == true ? contact.name! : '-',
              style: const TextStyle(
                color: NusaColors.textPrimary,
                fontSize: 12.5,
              ),
            ),
            Text(
              contact.phone?.trim().isNotEmpty == true ? contact.phone! : '-',
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 11.5,
              ),
            ),
          ],
        ),
      ),
      if (contact.phone?.trim().isNotEmpty == true)
        IconButton(
          tooltip: 'Salin nomor ${contact.label}',
          onPressed: () => onCopy('Nomor ${contact.label}', contact.phone!),
          icon: const Icon(Icons.copy_rounded, size: 18),
        ),
    ],
  );
}

class _UnavailableAccountCard extends StatelessWidget {
  const _UnavailableAccountCard({
    required this.detail,
    required this.mutating,
    required this.onCreate,
  });

  final ParentAccountDetail detail;
  final bool mutating;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    final hasNisn = detail.item.student.nisn?.trim().isNotEmpty == true;
    return _SectionCard(
      icon: Icons.person_off_outlined,
      title: 'Akun Login Belum Tersedia',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            hasNisn
                ? 'Orang tua siswa ini belum memiliki akun. Username akan menggunakan ORT-${detail.item.student.nisn}.'
                : 'Akun belum dapat dibuat karena NISN siswa masih kosong. Lengkapi Data Siswa terlebih dahulu.',
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 12.5,
              height: 1.45,
            ),
          ),
          if (detail.canManage && hasNisn) ...[
            const SizedBox(height: 16),
            FilledButton.icon(
              key: const Key('create-parent-account'),
              onPressed: mutating ? null : onCreate,
              icon: const Icon(Icons.family_restroom_rounded),
              label: const Text('Buat Akun Orang Tua'),
            ),
          ],
        ],
      ),
    );
  }
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
              width: 38,
              height: 38,
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
                  color: NusaColors.textPrimary,
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        child,
      ],
    ),
  );
}

class _InformationRow extends StatelessWidget {
  const _InformationRow({
    required this.label,
    required this.value,
    this.trailing,
  });

  final String label;
  final String value;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.center,
    children: [
      SizedBox(
        width: 105,
        child: Text(
          label,
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 12),
        ),
      ),
      const SizedBox(width: 8),
      Expanded(
        child: Text(
          value,
          style: const TextStyle(
            color: NusaColors.textPrimary,
            fontSize: 12.5,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      ?trailing,
    ],
  );
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.enabled,
    required this.onTap,
    this.destructive = false,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool enabled;
  final VoidCallback onTap;
  final bool destructive;

  @override
  Widget build(BuildContext context) {
    final color = destructive ? const Color(0xFFB42318) : NusaColors.primary;
    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.09),
                borderRadius: BorderRadius.circular(11),
              ),
              child: Icon(icon, color: color, size: 21),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: color,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
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
            Icon(Icons.chevron_right_rounded, color: color),
          ],
        ),
      ),
    );
  }
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
            Icons.family_restroom_rounded,
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
    : 'Detail akun orang tua belum dapat dimuat. Silakan coba lagi.';

String _dateTimeLabel(DateTime? value) {
  if (value == null) return 'Belum pernah login';
  final local = value.toLocal();
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
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  return '${local.day} ${months[local.month - 1]} ${local.year}, $hour.$minute WIB';
}
