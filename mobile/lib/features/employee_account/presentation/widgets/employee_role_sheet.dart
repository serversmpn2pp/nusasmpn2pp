import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_account/domain/employee_account.dart';

Future<List<int>?> showEmployeeRoleSheet(
  BuildContext context, {
  required List<EmployeeAccountRole> roles,
  required List<EmployeeAccountRole> selectedRoles,
}) => showModalBottomSheet<List<int>>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  backgroundColor: NusaColors.background,
  builder: (context) =>
      _EmployeeRoleSheet(roles: roles, selectedRoles: selectedRoles),
);

class _EmployeeRoleSheet extends StatefulWidget {
  const _EmployeeRoleSheet({required this.roles, required this.selectedRoles});

  final List<EmployeeAccountRole> roles;
  final List<EmployeeAccountRole> selectedRoles;

  @override
  State<_EmployeeRoleSheet> createState() => _EmployeeRoleSheetState();
}

class _EmployeeRoleSheetState extends State<_EmployeeRoleSheet> {
  late final Set<int> _selected = widget.selectedRoles
      .map((role) => role.id)
      .toSet();

  @override
  Widget build(BuildContext context) {
    final height = MediaQuery.sizeOf(context).height;
    return SizedBox(
      height: height * 0.82,
      child: Column(
        children: [
          const SizedBox(height: 9),
          Container(
            width: 48,
            height: 5,
            decoration: BoxDecoration(
              color: NusaColors.outline,
              borderRadius: BorderRadius.circular(99),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 15, 8, 11),
            child: Row(
              children: [
                const Expanded(
                  child: Text(
                    'Atur Role Akun',
                    style: TextStyle(
                      color: NusaColors.textPrimary,
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: 'Tutup',
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: NusaColors.outline),
          Expanded(
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
              itemCount: widget.roles.length,
              separatorBuilder: (context, index) => const SizedBox(height: 9),
              itemBuilder: (context, index) {
                final role = widget.roles[index];
                final checked =
                    role.isEmployeeBase || _selected.contains(role.id);
                return Material(
                  color: role.isEmployeeBase
                      ? NusaColors.surfaceBlue
                      : Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  child: CheckboxListTile(
                    key: Key('employee-role-${role.id}'),
                    value: checked,
                    enabled: !role.isEmployeeBase,
                    controlAffinity: ListTileControlAffinity.leading,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    side: const BorderSide(color: NusaColors.outline),
                    title: Text(
                      role.name,
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    subtitle: Text(
                      role.isEmployeeBase
                          ? 'Role dasar—selalu terpasang.'
                          : role.description?.trim().isNotEmpty == true
                          ? role.description!.trim()
                          : 'Role tambahan akun pegawai.',
                      style: const TextStyle(fontSize: 11.5),
                    ),
                    onChanged: role.isEmployeeBase
                        ? null
                        : (value) => setState(() {
                            if (value == true) {
                              _selected.add(role.id);
                            } else {
                              _selected.remove(role.id);
                            }
                          }),
                  ),
                );
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
            child: SizedBox(
              width: double.infinity,
              height: 52,
              child: FilledButton.icon(
                key: const Key('save-employee-roles'),
                onPressed: () {
                  final baseIds = widget.roles
                      .where((role) => role.isEmployeeBase)
                      .map((role) => role.id);
                  Navigator.pop(
                    context,
                    {..._selected, ...baseIds}.toList(growable: false),
                  );
                },
                icon: const Icon(Icons.save_outlined),
                label: const Text('Simpan Role'),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
