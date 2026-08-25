import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/role_access/application/role_access_controller.dart';
import 'package:nusa/features/role_access/domain/role_access.dart';
import 'package:nusa/features/role_access/presentation/widgets/role_access_form_sheet.dart';

class RoleAccessDetailView extends ConsumerStatefulWidget {
  const RoleAccessDetailView({required this.roleId, super.key});

  final int roleId;

  @override
  ConsumerState<RoleAccessDetailView> createState() =>
      _RoleAccessDetailViewState();
}

class _RoleAccessDetailViewState extends ConsumerState<RoleAccessDetailView> {
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final detail = ref.watch(roleAccessDetailProvider(widget.roleId));
    final current = detail.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Role'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: detail.isLoading ? null : _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('edit-role'),
              onPressed: _mutating ? null : () => _openEdit(current!),
              icon: const Icon(Icons.edit_rounded),
              label: const Text('Ubah Role'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: detail.when(
          loading: () =>
              const Center(child: CircularProgressIndicator(strokeWidth: 2.5)),
          error: (error, stackTrace) =>
              _DetailError(message: _errorMessage(error), onRetry: _refresh),
          data: (value) => RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
              children: [
                _RoleHero(detail: value),
                const SizedBox(height: 12),
                _RoleMetrics(role: value.role),
                const SizedBox(height: 12),
                _PermissionSection(detail: value),
                if (value.canManage && !value.role.system) ...[
                  const SizedBox(height: 12),
                  _SecuritySection(
                    active: value.role.active,
                    loading: _mutating,
                    onDeactivate: _deactivate,
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _openEdit(RoleAccessDetail detail) async {
    setState(() => _mutating = true);
    var saved = false;
    try {
      await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        builder: (context) => RoleAccessFormSheet(
          initial: detail.role,
          permissionGroups: detail.permissionGroups,
          onSubmit: (value) async {
            await ref
                .read(roleAccessActionsProvider)
                .update(roleId: widget.roleId, value: value);
            saved = true;
          },
        ),
      );
      if (saved && mounted) {
        ref.invalidate(roleAccessControllerProvider);
        await _refresh();
        if (mounted) _showMessage('Role berhasil diperbarui.');
      }
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _deactivate() async {
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Nonaktifkan role?'),
            content: const Text(
              'Role tetap tersimpan, tetapi tidak dapat diberikan kepada akun baru.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                key: const Key('confirm-deactivate-role'),
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFFB42318),
                ),
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Nonaktifkan'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || _mutating) return;
    setState(() => _mutating = true);
    try {
      await ref.read(roleAccessActionsProvider).deactivate(widget.roleId);
      ref.invalidate(roleAccessControllerProvider);
      await _refresh();
      if (mounted) _showMessage('Role berhasil dinonaktifkan.');
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _refresh() async {
    ref.invalidate(roleAccessDetailProvider(widget.roleId));
    await ref.read(roleAccessDetailProvider(widget.roleId).future);
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _RoleHero extends StatelessWidget {
  const _RoleHero({required this.detail});
  final RoleAccessDetail detail;

  @override
  Widget build(BuildContext context) {
    final role = detail.role;
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
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              color: NusaColors.accent,
              borderRadius: BorderRadius.circular(17),
            ),
            child: Icon(
              role.system ? Icons.verified_user_rounded : Icons.shield_outlined,
              color: NusaColors.primaryDark,
              size: 31,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  role.name,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 19,
                    fontWeight: FontWeight.w800,
                    height: 1.2,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  role.code,
                  style: const TextStyle(
                    color: NusaColors.accent,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (role.description?.trim().isNotEmpty == true) ...[
                  const SizedBox(height: 8),
                  Text(
                    role.description!,
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 11.5,
                      height: 1.35,
                    ),
                  ),
                ],
                const SizedBox(height: 10),
                Wrap(
                  spacing: 7,
                  runSpacing: 6,
                  children: [
                    _HeroBadge(label: role.active ? 'Aktif' : 'Nonaktif'),
                    _HeroBadge(
                      label: role.system ? 'Role sistem' : 'Role tambahan',
                    ),
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

class _HeroBadge extends StatelessWidget {
  const _HeroBadge({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(99),
      border: Border.all(color: Colors.white24),
    ),
    child: Text(
      label,
      style: const TextStyle(
        color: Colors.white,
        fontSize: 9.5,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

class _RoleMetrics extends StatelessWidget {
  const _RoleMetrics({required this.role});
  final RoleAccess role;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: _MetricCard(
          icon: Icons.key_rounded,
          value: '${role.permissionCount}',
          label: 'Izin aktif',
        ),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: _MetricCard(
          icon: Icons.people_outline_rounded,
          value: '${role.userCount}',
          label: 'Pengguna',
        ),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: _MetricCard(
          icon: Icons.donut_large_rounded,
          value: '${role.permissionPercentage}%',
          label: 'Cakupan',
        ),
      ),
    ],
  );
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.icon,
    required this.value,
    required this.label,
  });
  final IconData icon;
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 13),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      children: [
        Icon(icon, color: NusaColors.primary, size: 20),
        const SizedBox(height: 5),
        Text(
          value,
          style: const TextStyle(
            color: NusaColors.textPrimary,
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
          ),
        ),
      ],
    ),
  );
}

class _PermissionSection extends StatelessWidget {
  const _PermissionSection({required this.detail});
  final RoleAccessDetail detail;

  @override
  Widget build(BuildContext context) {
    final selected = detail.role.permissionIds.toSet();
    final groups = detail.permissionGroups
        .map(
          (group) => PermissionGroup(
            name: group.name,
            permissions: group.permissions
                .where((permission) => selected.contains(permission.id))
                .toList(growable: false),
          ),
        )
        .where((group) => group.permissions.isNotEmpty)
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
          const Row(
            children: [
              Icon(Icons.key_rounded, color: NusaColors.primary, size: 21),
              SizedBox(width: 9),
              Expanded(
                child: Text(
                  'Izin Akses Terpasang',
                  style: TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (groups.isEmpty)
            const Text(
              'Role ini belum memiliki izin aktif.',
              style: TextStyle(color: NusaColors.textSecondary, fontSize: 12),
            )
          else
            for (final group in groups)
              ExpansionTile(
                key: Key('detail-permission-group-${group.name}'),
                tilePadding: EdgeInsets.zero,
                childrenPadding: const EdgeInsets.only(bottom: 8),
                shape: const Border(),
                title: Text(
                  group.name,
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                trailing: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: NusaColors.surfaceBlue,
                    borderRadius: BorderRadius.circular(99),
                  ),
                  child: Text(
                    '${group.permissions.length}',
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                children: [
                  for (final permission in group.permissions)
                    ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(
                        Icons.check_circle_rounded,
                        color: NusaColors.success,
                        size: 19,
                      ),
                      title: Text(
                        permission.name,
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      subtitle: Text(
                        permission.code,
                        style: const TextStyle(fontSize: 10),
                      ),
                    ),
                ],
              ),
        ],
      ),
    );
  }
}

class _SecuritySection extends StatelessWidget {
  const _SecuritySection({
    required this.active,
    required this.loading,
    required this.onDeactivate,
  });
  final bool active;
  final bool loading;
  final VoidCallback onDeactivate;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: RoundedRectangleBorder(
      side: const BorderSide(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: ListTile(
        key: const Key('deactivate-role'),
        enabled: active && !loading,
        contentPadding: EdgeInsets.zero,
        onTap: active && !loading ? onDeactivate : null,
        leading: Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: const Color(0xFFB42318).withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Icon(Icons.block_rounded, color: Color(0xFFB42318)),
        ),
        title: Text(
          active ? 'Nonaktifkan Role' : 'Role Sudah Nonaktif',
          style: TextStyle(
            color: active ? const Color(0xFFB42318) : NusaColors.textSecondary,
            fontSize: 13.5,
            fontWeight: FontWeight.w800,
          ),
        ),
        subtitle: const Text(
          'Data role dan hubungan pengguna tetap tersimpan.',
          style: TextStyle(fontSize: 10.5),
        ),
        trailing: const Icon(Icons.chevron_right_rounded),
      ),
    ),
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
            Icons.admin_panel_settings_outlined,
            size: 52,
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
    : 'Detail role belum dapat dimuat. Silakan coba lagi.';
