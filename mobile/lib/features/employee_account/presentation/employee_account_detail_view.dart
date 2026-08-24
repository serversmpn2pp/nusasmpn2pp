import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_account/application/employee_account_controller.dart';
import 'package:nusa/features/employee_account/domain/employee_account.dart';
import 'package:nusa/features/employee_account/presentation/widgets/employee_account_components.dart';
import 'package:nusa/features/employee_account/presentation/widgets/employee_role_sheet.dart';

class EmployeeAccountDetailView extends ConsumerStatefulWidget {
  const EmployeeAccountDetailView({required this.employeeId, super.key});

  final int employeeId;

  @override
  ConsumerState<EmployeeAccountDetailView> createState() =>
      _EmployeeAccountDetailViewState();
}

class _EmployeeAccountDetailViewState
    extends ConsumerState<EmployeeAccountDetailView> {
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final account = ref.watch(employeeAccountDetailProvider(widget.employeeId));

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Akun Pegawai'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: account.isLoading
                ? null
                : () => ref.invalidate(
                    employeeAccountDetailProvider(widget.employeeId),
                  ),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: account.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _DetailError(
            message: _errorMessage(error),
            onRetry: () => ref.invalidate(
              employeeAccountDetailProvider(widget.employeeId),
            ),
          ),
          data: (detail) => RefreshIndicator(
            onRefresh: () => _refreshDetail(),
            child: ListView(
              key: const PageStorageKey<String>('employee-account-detail'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 30),
              children: [
                _EmployeeAccountHero(detail: detail),
                const SizedBox(height: 12),
                if (!detail.item.account.available)
                  _MissingAccountCard(
                    detail: detail,
                    loading: _mutating,
                    onCreate: () => _createAccount(detail),
                  )
                else ...[
                  _LoginInformationCard(detail: detail),
                  const SizedBox(height: 12),
                  _RoleCard(
                    detail: detail,
                    loading: _mutating,
                    onEdit: () => _editRoles(detail),
                  ),
                  const SizedBox(height: 12),
                  _SecurityCard(
                    detail: detail,
                    loading: _mutating,
                    onResetPassword: () => _resetPassword(detail),
                    onToggleStatus: () => _toggleStatus(detail),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _createAccount(EmployeeAccountDetail detail) async {
    final nip = detail.item.employee.nip;
    if (nip?.trim().isEmpty != false) {
      _showMessage('Lengkapi NIP pegawai sebelum membuat akun.');
      return;
    }
    final confirmed = await _confirm(
      icon: Icons.person_add_alt_1_rounded,
      title: 'Buat akun pegawai?',
      message:
          'Username akan memakai NIP tanpa spasi: ${nip!.replaceAll(' ', '')}. '
          'Pegawai wajib mengganti kata sandi awal setelah login.',
      confirmLabel: 'Buat Akun',
      confirmKey: const Key('confirm-create-employee-account'),
    );
    if (!confirmed) return;

    await _runMutation(
      successMessage: 'Akun pegawai berhasil dibuat.',
      operation: () => ref
          .read(employeeAccountActionsProvider)
          .createAccount(widget.employeeId),
    );
  }

  Future<void> _resetPassword(EmployeeAccountDetail detail) async {
    final confirmed = await _confirm(
      icon: Icons.lock_reset_rounded,
      title: 'Reset kata sandi?',
      message:
          'Kata sandi ${detail.item.employee.name} akan dikembalikan ke '
          'kata sandi awal pegawai. Tindakan ini tidak dapat dibatalkan.',
      confirmLabel: 'Reset Sandi',
      confirmKey: const Key('confirm-reset-employee-password'),
    );
    if (!confirmed) return;

    await _runMutation(
      successMessage: 'Kata sandi berhasil direset ke default.',
      operation: () => ref
          .read(employeeAccountActionsProvider)
          .resetPassword(widget.employeeId),
    );
  }

  Future<void> _toggleStatus(EmployeeAccountDetail detail) async {
    final currentlyActive = detail.item.account.active;
    final confirmed = await _confirm(
      icon: currentlyActive ? Icons.block_rounded : Icons.check_circle_rounded,
      title: currentlyActive ? 'Nonaktifkan akun?' : 'Aktifkan akun?',
      message: currentlyActive
          ? '${detail.item.employee.name} tidak dapat masuk ke NUSA sampai akun diaktifkan kembali.'
          : '${detail.item.employee.name} akan dapat masuk kembali ke NUSA.',
      confirmLabel: currentlyActive ? 'Nonaktifkan' : 'Aktifkan',
      destructive: currentlyActive,
      confirmKey: const Key('confirm-toggle-employee-account'),
    );
    if (!confirmed) return;

    await _runMutation(
      successMessage: currentlyActive
          ? 'Akun berhasil dinonaktifkan.'
          : 'Akun berhasil diaktifkan.',
      operation: () => ref
          .read(employeeAccountActionsProvider)
          .updateStatus(
            employeeId: widget.employeeId,
            active: !currentlyActive,
          ),
    );
  }

  Future<void> _editRoles(EmployeeAccountDetail detail) async {
    final selected = await showEmployeeRoleSheet(
      context,
      roles: detail.roles,
      selectedRoles: detail.item.account.roles,
    );
    if (selected == null || !mounted) return;

    await _runMutation(
      successMessage: 'Role akun berhasil diperbarui.',
      operation: () => ref
          .read(employeeAccountActionsProvider)
          .updateRoles(employeeId: widget.employeeId, roleIds: selected),
    );
  }

  Future<bool> _confirm({
    required IconData icon,
    required String title,
    required String message,
    required String confirmLabel,
    required Key confirmKey,
    bool destructive = false,
  }) async {
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: Icon(
              icon,
              color: destructive ? const Color(0xFFB42318) : NusaColors.primary,
            ),
            title: Text(title),
            content: Text(message),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: confirmKey,
                style: destructive
                    ? FilledButton.styleFrom(
                        backgroundColor: const Color(0xFFB42318),
                      )
                    : null,
                onPressed: () => Navigator.pop(context, true),
                child: Text(confirmLabel),
              ),
            ],
          ),
        ) ??
        false;
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    if (_mutating) return;
    setState(() => _mutating = true);
    try {
      await operation();
      ref.invalidate(employeeAccountListControllerProvider);
      await _refreshDetail();
      if (mounted) _showMessage(successMessage);
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _refreshDetail() async {
    ref.invalidate(employeeAccountDetailProvider(widget.employeeId));
    await ref.read(employeeAccountDetailProvider(widget.employeeId).future);
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _EmployeeAccountHero extends StatelessWidget {
  const _EmployeeAccountHero({required this.detail});

  final EmployeeAccountDetail detail;

  @override
  Widget build(BuildContext context) {
    final item = detail.item;
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
            child: EmployeeAccountAvatar(employee: item.employee, size: 68),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.employee.name,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    height: 1.18,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  'NIP ${item.employee.nip ?? '-'}',
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
                const SizedBox(height: 4),
                Text(
                  item.employee.primaryPosition ?? 'Pegawai',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.accent,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 9),
                AccountStatusBadge(
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
}

class _MissingAccountCard extends StatelessWidget {
  const _MissingAccountCard({
    required this.detail,
    required this.loading,
    required this.onCreate,
  });

  final EmployeeAccountDetail detail;
  final bool loading;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    final hasNip = detail.item.employee.nip?.trim().isNotEmpty == true;
    return _SectionCard(
      icon: hasNip ? Icons.person_add_alt_1_rounded : Icons.badge_outlined,
      title: 'Akun Login Belum Tersedia',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            hasNip
                ? 'Akun dapat dibuat menggunakan NIP tanpa spasi sebagai username.'
                : 'Pegawai belum memiliki NIP. Lengkapi Data Pegawai terlebih dahulu.',
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 12.5,
              height: 1.4,
            ),
          ),
          if (detail.canManage) ...[
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('create-employee-account'),
                onPressed: loading || !hasNip ? null : onCreate,
                icon: const Icon(Icons.person_add_alt_1_rounded),
                label: const Text('Buat Akun Pegawai'),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _LoginInformationCard extends StatelessWidget {
  const _LoginInformationCard({required this.detail});

  final EmployeeAccountDetail detail;

  @override
  Widget build(BuildContext context) {
    final account = detail.item.account;
    return _SectionCard(
      icon: Icons.login_rounded,
      title: 'Informasi Login',
      child: Column(
        children: [
          _InformationRow(label: 'Username', value: account.username ?? '-'),
          const Divider(height: 20, color: NusaColors.outline),
          _InformationRow(
            label: 'Status akun',
            value: account.active ? 'Aktif' : 'Nonaktif',
          ),
          const Divider(height: 20, color: NusaColors.outline),
          _InformationRow(
            label: 'Kata sandi',
            value: account.mustChangePassword
                ? 'Wajib diganti saat login'
                : 'Sudah diperbarui pengguna',
          ),
          const Divider(height: 20, color: NusaColors.outline),
          _InformationRow(
            label: 'Login terakhir',
            value: _dateTimeLabel(account.lastLoginAt),
          ),
        ],
      ),
    );
  }
}

class _RoleCard extends StatelessWidget {
  const _RoleCard({
    required this.detail,
    required this.loading,
    required this.onEdit,
  });

  final EmployeeAccountDetail detail;
  final bool loading;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.admin_panel_settings_outlined,
    title: 'Role & Hak Akses',
    trailing: detail.canManage
        ? TextButton.icon(
            key: const Key('edit-employee-account-roles'),
            onPressed: loading ? null : onEdit,
            icon: const Icon(Icons.edit_outlined, size: 17),
            label: const Text('Atur'),
          )
        : null,
    child: detail.item.account.roles.isEmpty
        ? const Text(
            'Belum ada role terpasang.',
            style: TextStyle(color: NusaColors.textSecondary),
          )
        : Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              for (final role in detail.item.account.roles)
                Chip(
                  avatar: Icon(
                    role.isEmployeeBase
                        ? Icons.verified_user_outlined
                        : Icons.shield_outlined,
                    size: 16,
                    color: NusaColors.primary,
                  ),
                  label: Text(role.name),
                  side: const BorderSide(color: NusaColors.outline),
                  backgroundColor: role.isEmployeeBase
                      ? NusaColors.surfaceBlue
                      : Colors.white,
                ),
            ],
          ),
  );
}

class _SecurityCard extends StatelessWidget {
  const _SecurityCard({
    required this.detail,
    required this.loading,
    required this.onResetPassword,
    required this.onToggleStatus,
  });

  final EmployeeAccountDetail detail;
  final bool loading;
  final VoidCallback onResetPassword;
  final VoidCallback onToggleStatus;

  @override
  Widget build(BuildContext context) {
    final active = detail.item.account.active;
    return _SectionCard(
      icon: Icons.security_rounded,
      title: 'Keamanan Akun',
      child: Column(
        children: [
          _ActionTile(
            key: const Key('reset-employee-account-password'),
            icon: Icons.lock_reset_rounded,
            title: 'Reset Kata Sandi',
            subtitle: 'Kembalikan ke kata sandi awal pegawai.',
            enabled: detail.canManage && !loading,
            onTap: onResetPassword,
          ),
          const Divider(height: 17, color: NusaColors.outline),
          _ActionTile(
            key: const Key('toggle-employee-account-status'),
            icon: active ? Icons.block_rounded : Icons.check_circle_rounded,
            title: active ? 'Nonaktifkan Akun' : 'Aktifkan Akun',
            subtitle: active
                ? 'Cegah akun masuk sementara ke NUSA.'
                : 'Izinkan akun masuk kembali ke NUSA.',
            enabled: detail.canManage && !loading,
            destructive: active,
            onTap: onToggleStatus,
          ),
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
    this.trailing,
  });

  final IconData icon;
  final String title;
  final Widget child;
  final Widget? trailing;

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
                  color: NusaColors.textPrimary,
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            ?trailing,
          ],
        ),
        const SizedBox(height: 14),
        child,
      ],
    ),
  );
}

class _InformationRow extends StatelessWidget {
  const _InformationRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      SizedBox(
        width: 106,
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
            Icons.manage_accounts_outlined,
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
    : 'Detail akun pegawai belum dapat dimuat. Silakan coba lagi.';

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
