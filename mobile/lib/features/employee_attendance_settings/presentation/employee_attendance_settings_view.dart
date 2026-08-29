import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_attendance_settings/application/employee_attendance_settings_controller.dart';
import 'package:nusa/features/employee_attendance_settings/domain/employee_attendance_settings.dart';
import 'package:nusa/features/employee_attendance_settings/presentation/widgets/employee_attendance_settings_form_sheet.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class EmployeeAttendanceSettingsView extends ConsumerStatefulWidget {
  const EmployeeAttendanceSettingsView({super.key});

  @override
  ConsumerState<EmployeeAttendanceSettingsView> createState() =>
      _EmployeeAttendanceSettingsViewState();
}

class _EmployeeAttendanceSettingsViewState
    extends ConsumerState<EmployeeAttendanceSettingsView> {
  final _searchController = TextEditingController();
  bool _mutating = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final settings = ref.watch(employeeAttendanceSettingsControllerProvider);
    final current = settings.value;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text(
          'Pengaturan Presensi Pegawai',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: settings.isLoading
                ? null
                : () => ref
                      .read(
                        employeeAttendanceSettingsControllerProvider.notifier,
                      )
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('add-employee-attendance-setting'),
              onPressed: _mutating ? null : () => _openForm(current!),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah Jadwal'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (current != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
                child: Column(
                  children: [
                    _SummaryCard(summary: current.summary),
                    const SizedBox(height: 10),
                    NusaTextField(
                      fieldKey: const Key(
                        'employee-attendance-settings-search',
                      ),
                      controller: _searchController,
                      hintText: 'Cari jadwal, pegawai, atau NIP',
                      prefixIcon: Icons.search_rounded,
                      enabled: !settings.isLoading,
                      onChanged: ref
                          .read(
                            employeeAttendanceSettingsControllerProvider
                                .notifier,
                          )
                          .search,
                    ),
                    const SizedBox(height: 9),
                    _Filters(
                      catalog: current,
                      enabled: !settings.isLoading,
                      onDayChanged: (value) => ref
                          .read(
                            employeeAttendanceSettingsControllerProvider
                                .notifier,
                          )
                          .filterDay(value),
                      onScopeChanged: (value) => ref
                          .read(
                            employeeAttendanceSettingsControllerProvider
                                .notifier,
                          )
                          .filterScope(value),
                      onStatusChanged: (value) => ref
                          .read(
                            employeeAttendanceSettingsControllerProvider
                                .notifier,
                          )
                          .filterStatus(value),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: settings.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _ErrorState(
                  message: _errorMessage(error),
                  onRetry: () => ref
                      .read(
                        employeeAttendanceSettingsControllerProvider.notifier,
                      )
                      .refresh(),
                ),
                data: (catalog) => _Results(
                  catalog: catalog,
                  enabled: !_mutating,
                  onRefresh: () => ref
                      .read(
                        employeeAttendanceSettingsControllerProvider.notifier,
                      )
                      .refresh(),
                  onEdit: catalog.canManage
                      ? (item) => _openForm(catalog, existing: item)
                      : null,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openForm(
    EmployeeAttendanceSettingsCatalog catalog, {
    EmployeeAttendanceSetting? existing,
  }) async {
    final value =
        await showModalBottomSheet<EmployeeAttendanceSettingsFormValue>(
          context: context,
          isScrollControlled: true,
          useSafeArea: true,
          builder: (context) => EmployeeAttendanceSettingsFormSheet(
            days: catalog.days,
            scopes: catalog.scopes,
            employeeTypes: catalog.employeeTypes,
            employees: catalog.employees,
            existing: existing,
          ),
        );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? 'Jadwal presensi pegawai berhasil ditambahkan.'
          : 'Jadwal presensi pegawai berhasil diperbarui.',
      operation: () => existing == null
          ? ref.read(employeeAttendanceSettingsActionsProvider).create(value)
          : ref
                .read(employeeAttendanceSettingsActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(employeeAttendanceSettingsControllerProvider.future);
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
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});

  final EmployeeAttendanceSettingsSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 13),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.16),
          blurRadius: 14,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: Row(
      children: [
        for (final item in [
          ('Total', summary.total),
          ('Aktif', summary.active),
          ('Nonaktif', summary.inactive),
        ])
          Expanded(
            child: Column(
              children: [
                Text(
                  '${item.$2}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  item.$1,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.76),
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

class _Filters extends StatelessWidget {
  const _Filters({
    required this.catalog,
    required this.enabled,
    required this.onDayChanged,
    required this.onScopeChanged,
    required this.onStatusChanged,
  });

  final EmployeeAttendanceSettingsCatalog catalog;
  final bool enabled;
  final ValueChanged<String> onDayChanged;
  final ValueChanged<String> onScopeChanged;
  final ValueChanged<String> onStatusChanged;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final day = NusaDropdownField<String>(
        fieldKey: const Key('employee-attendance-settings-day-filter'),
        value: catalog.selectedDay,
        decoration: const InputDecoration(labelText: 'Hari'),
        options: [
          const NusaDropdownOption(value: 'semua', label: 'Semua hari'),
          for (final item in catalog.days)
            NusaDropdownOption(value: item.code, label: item.label),
        ],
        enabled: enabled,
        onChanged: (value) {
          if (value != null) onDayChanged(value);
        },
      );
      final scope = NusaDropdownField<String>(
        fieldKey: const Key('employee-attendance-settings-scope-filter'),
        value: catalog.selectedScope,
        decoration: const InputDecoration(labelText: 'Cakupan'),
        options: [
          const NusaDropdownOption(
            value: 'semua_cakupan',
            label: 'Semua cakupan',
          ),
          for (final item in catalog.scopes)
            NusaDropdownOption(value: item.code, label: item.label),
        ],
        enabled: enabled,
        onChanged: (value) {
          if (value != null) onScopeChanged(value);
        },
      );
      final status = NusaDropdownField<String>(
        fieldKey: const Key('employee-attendance-settings-status-filter'),
        value: catalog.status,
        decoration: const InputDecoration(labelText: 'Status'),
        options: const [
          NusaDropdownOption(value: 'semua_status', label: 'Semua status'),
          NusaDropdownOption(value: 'aktif', label: 'Aktif'),
          NusaDropdownOption(value: 'nonaktif', label: 'Nonaktif'),
        ],
        enabled: enabled,
        onChanged: (value) {
          if (value != null) onStatusChanged(value);
        },
      );

      if (constraints.maxWidth < 390) {
        return Column(
          children: [
            Row(
              children: [
                Expanded(child: day),
                const SizedBox(width: 8),
                Expanded(child: status),
              ],
            ),
            const SizedBox(height: 8),
            scope,
          ],
        );
      }
      return Row(
        children: [
          Expanded(child: day),
          const SizedBox(width: 8),
          Expanded(child: scope),
          const SizedBox(width: 8),
          Expanded(child: status),
        ],
      );
    },
  );
}

class _Results extends StatelessWidget {
  const _Results({
    required this.catalog,
    required this.enabled,
    required this.onRefresh,
    this.onEdit,
  });

  final EmployeeAttendanceSettingsCatalog catalog;
  final bool enabled;
  final Future<void> Function() onRefresh;
  final ValueChanged<EmployeeAttendanceSetting>? onEdit;

  @override
  Widget build(BuildContext context) {
    if (catalog.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(36, 45, 36, 110),
          children: const [
            Icon(Icons.event_busy_rounded, size: 52, color: NusaColors.primary),
            SizedBox(height: 12),
            Text(
              'Belum ada jadwal presensi pegawai pada filter ini.',
              textAlign: TextAlign.center,
              style: TextStyle(color: NusaColors.textSecondary),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        key: const PageStorageKey<String>('employee-attendance-settings-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 100),
        itemCount: catalog.items.length,
        separatorBuilder: (context, index) => const SizedBox(height: 10),
        itemBuilder: (context, index) {
          final item = catalog.items[index];
          return _SettingCard(
            setting: item,
            onTap: onEdit == null || !enabled ? null : () => onEdit!(item),
          );
        },
      ),
    );
  }
}

class _SettingCard extends StatelessWidget {
  const _SettingCard({required this.setting, this.onTap});

  final EmployeeAttendanceSetting setting;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Material(
    key: Key('employee-attendance-setting-${setting.id}'),
    color: Colors.white,
    borderRadius: BorderRadius.circular(17),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(17),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(17),
          border: Border.all(
            color: setting.active
                ? NusaColors.primary.withValues(alpha: 0.16)
                : NusaColors.outline,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 43,
                  height: 43,
                  decoration: BoxDecoration(
                    color: setting.active
                        ? NusaColors.surfaceBlue
                        : NusaColors.background,
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Icon(
                    setting.scope == 'pegawai'
                        ? Icons.person_rounded
                        : Icons.groups_2_rounded,
                    color: setting.active
                        ? NusaColors.primary
                        : NusaColors.textSecondary,
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        setting.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        '${setting.dayLabel} · ${setting.targetLabel}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10.5,
                        ),
                      ),
                    ],
                  ),
                ),
                _StatusBadge(active: setting.active),
                if (onTap != null) ...[
                  const SizedBox(width: 5),
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: NusaColors.primary,
                  ),
                ],
              ],
            ),
            const SizedBox(height: 11),
            Row(
              children: [
                Expanded(
                  child: _TimeSummary(
                    icon: Icons.login_rounded,
                    title: 'Masuk ${setting.checkInTime}',
                    subtitle: 'Scan ${setting.checkInWindow}',
                    color: NusaColors.success,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _TimeSummary(
                    icon: Icons.logout_rounded,
                    title: 'Pulang ${setting.checkOutTime}',
                    subtitle: 'Scan ${setting.checkOutWindow}',
                    color: NusaColors.primary,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _TimeSummary extends StatelessWidget {
  const _TimeSummary({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      children: [
        Icon(icon, size: 17, color: color),
        const SizedBox(width: 6),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 10.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 8.5,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: (active ? NusaColors.success : NusaColors.textSecondary)
          .withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(20),
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

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 45),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Pengaturan presensi pegawai belum dapat diproses.';
}
