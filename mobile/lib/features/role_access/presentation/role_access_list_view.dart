import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/role_access/application/role_access_controller.dart';
import 'package:nusa/features/role_access/domain/role_access.dart';
import 'package:nusa/features/role_access/presentation/widgets/role_access_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class RoleAccessListView extends ConsumerStatefulWidget {
  const RoleAccessListView({super.key});

  @override
  ConsumerState<RoleAccessListView> createState() => _RoleAccessListViewState();
}

class _RoleAccessListViewState extends ConsumerState<RoleAccessListView> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  bool _loadingMore = false;
  bool _openingForm = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final roles = ref.watch(roleAccessControllerProvider);
    final current = roles.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Role & Hak Akses'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: roles.isLoading
                ? null
                : () =>
                      ref.read(roleAccessControllerProvider.notifier).refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-role'),
              onPressed: _openingForm ? null : _openCreate,
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah Role'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 9),
              child: Column(
                children: [
                  if (current != null) ...[
                    _RoleSummary(summary: current.summary),
                    const SizedBox(height: 10),
                  ],
                  NusaTextField(
                    fieldKey: const Key('role-search'),
                    controller: _searchController,
                    hintText: 'Cari nama, kode, atau deskripsi role',
                    prefixIcon: Icons.search_rounded,
                    onChanged: _search,
                    suffixIcon: _searchController.text.isEmpty
                        ? null
                        : IconButton(
                            onPressed: () {
                              _searchController.clear();
                              setState(() {});
                              ref
                                  .read(roleAccessControllerProvider.notifier)
                                  .search('');
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                  ),
                  const SizedBox(height: 8),
                  if (current != null)
                    NusaDropdownField<String>(
                      fieldKey: const Key('role-status-filter'),
                      value: current.status,
                      enabled: !roles.isLoading,
                      decoration: const InputDecoration(
                        labelText: 'Status role',
                        prefixIcon: Icon(Icons.tune_rounded),
                      ),
                      options: const [
                        NusaDropdownOption(
                          value: 'semua',
                          label: 'Semua status',
                        ),
                        NusaDropdownOption(value: 'aktif', label: 'Aktif'),
                        NusaDropdownOption(
                          value: 'nonaktif',
                          label: 'Nonaktif',
                        ),
                      ],
                      onChanged: (value) {
                        if (value != null) {
                          ref
                              .read(roleAccessControllerProvider.notifier)
                              .filterStatus(value);
                        }
                      },
                    ),
                ],
              ),
            ),
            Expanded(
              child: roles.when(
                loading: () => const Center(
                  child: CircularProgressIndicator(strokeWidth: 2.5),
                ),
                error: (error, stackTrace) => _RoleError(
                  message: _errorMessage(error),
                  onRetry: () =>
                      ref.read(roleAccessControllerProvider.notifier).refresh(),
                ),
                data: (page) => RefreshIndicator(
                  onRefresh: () =>
                      ref.read(roleAccessControllerProvider.notifier).refresh(),
                  child: page.items.isEmpty
                      ? const _RoleEmpty()
                      : ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 4, 16, 100),
                          itemCount:
                              page.items.length +
                              (page.pagination.hasNextPage ? 1 : 0),
                          separatorBuilder: (context, index) =>
                              const SizedBox(height: 10),
                          itemBuilder: (context, index) {
                            if (index == page.items.length) {
                              return Center(
                                child: OutlinedButton.icon(
                                  onPressed: _loadingMore ? null : _loadMore,
                                  icon: _loadingMore
                                      ? const SizedBox.square(
                                          dimension: 16,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                          ),
                                        )
                                      : const Icon(Icons.expand_more_rounded),
                                  label: const Text('Muat lebih banyak'),
                                ),
                              );
                            }
                            final role = page.items[index];
                            return _RoleCard(
                              key: Key('role-${role.id}'),
                              role: role,
                              activePermissionCount:
                                  page.summary.activePermissions,
                              onTap: () =>
                                  context.push('/role-hak-akses/${role.id}'),
                            );
                          },
                        ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _search(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 420), () {
      ref.read(roleAccessControllerProvider.notifier).search(value);
    });
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(roleAccessControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openCreate() async {
    setState(() => _openingForm = true);
    try {
      final reference = await ref.read(roleAccessReferenceProvider.future);
      if (!mounted) return;
      var saved = false;
      await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        builder: (sheetContext) => RoleAccessFormSheet(
          permissionGroups: reference.permissionGroups,
          onSubmit: (value) async {
            await ref.read(roleAccessActionsProvider).create(value);
            saved = true;
          },
        ),
      );
      if (saved && mounted) {
        await ref.read(roleAccessControllerProvider.notifier).refresh();
        if (mounted) _showMessage('Role baru berhasil ditambahkan.');
      }
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _openingForm = false);
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _RoleSummary extends StatelessWidget {
  const _RoleSummary({required this.summary});

  final RoleAccessSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        _SummaryItem(value: summary.total, label: 'Total'),
        _SummaryItem(value: summary.active, label: 'Aktif'),
        _SummaryItem(value: summary.activePermissions, label: 'Izin'),
        _SummaryItem(value: summary.connectedUsers, label: 'Pengguna'),
      ],
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({required this.value, required this.label});

  final int value;
  final String label;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 13, horizontal: 4),
      child: Column(
        children: [
          Text(
            '$value',
            maxLines: 1,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 17,
              fontWeight: FontWeight.w800,
            ),
          ),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white70, fontSize: 9.5),
          ),
        ],
      ),
    ),
  );
}

class _RoleCard extends StatelessWidget {
  const _RoleCard({
    required super.key,
    required this.role,
    required this.activePermissionCount,
    required this.onTap,
  });

  final RoleAccess role;
  final int activePermissionCount;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(17),
      side: const BorderSide(color: NusaColors.outline),
    ),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(17),
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 45,
                  height: 45,
                  decoration: BoxDecoration(
                    color: role.system
                        ? NusaColors.surfaceBlue
                        : NusaColors.accent.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Icon(
                    role.system
                        ? Icons.verified_user_rounded
                        : Icons.shield_outlined,
                    color: role.system
                        ? NusaColors.primary
                        : const Color(0xFF9B7200),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        role.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textPrimary,
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        role.code,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.primary,
                          fontSize: 10.5,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                _StatusBadge(active: role.active),
                const SizedBox(width: 2),
                const Icon(
                  Icons.chevron_right_rounded,
                  color: NusaColors.textSecondary,
                ),
              ],
            ),
            if (role.description?.trim().isNotEmpty == true) ...[
              const SizedBox(height: 10),
              Text(
                role.description!,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 11.5,
                  height: 1.35,
                ),
              ),
            ],
            const SizedBox(height: 12),
            LinearProgressIndicator(
              value: activePermissionCount == 0
                  ? 0
                  : role.permissionCount / activePermissionCount,
              minHeight: 5,
              borderRadius: BorderRadius.circular(99),
              backgroundColor: NusaColors.outline,
              color: role.isAdministrator
                  ? NusaColors.accent
                  : NusaColors.primary,
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 12,
              runSpacing: 7,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                _CardMetric(
                  icon: Icons.key_rounded,
                  label:
                      '${role.permissionCount} izin (${role.permissionPercentage}%)',
                ),
                _CardMetric(
                  icon: Icons.people_outline_rounded,
                  label: '${role.userCount} pengguna',
                ),
                _TypeBadge(system: role.system),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _CardMetric extends StatelessWidget {
  const _CardMetric({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 15, color: NusaColors.textSecondary),
      const SizedBox(width: 5),
      Text(
        label,
        style: const TextStyle(
          color: NusaColors.textSecondary,
          fontSize: 10.5,
          fontWeight: FontWeight.w600,
        ),
      ),
    ],
  );
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.active});
  final bool active;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: active
          ? NusaColors.successSurface
          : NusaColors.textSecondary.withValues(alpha: 0.09),
      borderRadius: BorderRadius.circular(99),
    ),
    child: Text(
      active ? 'Aktif' : 'Nonaktif',
      style: TextStyle(
        color: active ? NusaColors.success : NusaColors.textSecondary,
        fontSize: 9.5,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _TypeBadge extends StatelessWidget {
  const _TypeBadge({required this.system});
  final bool system;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: system
          ? NusaColors.surfaceBlue
          : NusaColors.accent.withValues(alpha: 0.13),
      borderRadius: BorderRadius.circular(99),
    ),
    child: Text(
      system ? 'Sistem' : 'Tambahan',
      style: TextStyle(
        color: system ? NusaColors.primary : const Color(0xFF8A6500),
        fontSize: 9,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _RoleEmpty extends StatelessWidget {
  const _RoleEmpty();

  @override
  Widget build(BuildContext context) => ListView(
    physics: const AlwaysScrollableScrollPhysics(),
    children: const [
      SizedBox(height: 80),
      Icon(
        Icons.admin_panel_settings_outlined,
        size: 58,
        color: NusaColors.primary,
      ),
      SizedBox(height: 14),
      Text(
        'Tidak ada role yang cocok.',
        textAlign: TextAlign.center,
        style: TextStyle(fontWeight: FontWeight.w700),
      ),
    ],
  );
}

class _RoleError extends StatelessWidget {
  const _RoleError({required this.message, required this.onRetry});
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
            Icons.shield_outlined,
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
    : 'Data role belum dapat dimuat. Silakan coba lagi.';
