import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/role_access/domain/role_access.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class RoleAccessFormSheet extends StatefulWidget {
  const RoleAccessFormSheet({
    required this.permissionGroups,
    required this.onSubmit,
    this.initial,
    super.key,
  });

  final List<PermissionGroup> permissionGroups;
  final RoleAccess? initial;
  final Future<void> Function(RoleAccessFormValue value) onSubmit;

  @override
  State<RoleAccessFormSheet> createState() => _RoleAccessFormSheetState();
}

class _RoleAccessFormSheetState extends State<RoleAccessFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameController;
  late final TextEditingController _codeController;
  late final TextEditingController _descriptionController;
  final _searchController = TextEditingController();
  late Set<int> _selected;
  late bool _active;
  bool _saving = false;
  String? _error;

  RoleAccess? get _initial => widget.initial;
  bool get _isAdministrator => _initial?.isAdministrator == true;
  bool get _isSystem => _initial?.system == true;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: _initial?.name);
    _codeController = TextEditingController(text: _initial?.code);
    _descriptionController = TextEditingController(text: _initial?.description);
    _selected = _isAdministrator
        ? widget.permissionGroups
              .expand((group) => group.permissions)
              .map((permission) => permission.id)
              .toSet()
        : _initial?.permissionIds.toSet() ?? <int>{};
    _active = _isSystem ? true : _initial?.active ?? true;
  }

  @override
  void dispose() {
    _nameController.dispose();
    _codeController.dispose();
    _descriptionController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final query = _searchController.text.trim().toLowerCase();
    final groups = widget.permissionGroups
        .map(
          (group) => PermissionGroup(
            name: group.name,
            permissions: group.permissions
                .where(
                  (permission) =>
                      query.isEmpty ||
                      '${group.name} ${permission.name} ${permission.code} ${permission.description ?? ''}'
                          .toLowerCase()
                          .contains(query),
                )
                .toList(growable: false),
          ),
        )
        .where((group) => group.permissions.isNotEmpty)
        .toList(growable: false);
    final total = widget.permissionGroups
        .expand((group) => group.permissions)
        .length;

    return SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.92,
      child: SafeArea(
        top: false,
        child: Column(
          children: [
            const SizedBox(height: 9),
            Container(
              width: 42,
              height: 4,
              decoration: BoxDecoration(
                color: NusaColors.outline,
                borderRadius: BorderRadius.circular(99),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 13, 8, 11),
              child: Row(
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: NusaColors.surfaceBlue,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.admin_panel_settings_rounded,
                      color: NusaColors.primary,
                    ),
                  ),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _initial == null ? 'Tambah Role' : 'Ubah Role',
                          style: const TextStyle(
                            color: NusaColors.textPrimary,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          '$_selectedCount dari $total izin dipilih',
                          style: const TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 11.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Tutup',
                    onPressed: _saving ? null : () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: Form(
                key: _formKey,
                child: ListView(
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: const EdgeInsets.fromLTRB(18, 16, 18, 22),
                  children: [
                    TextFormField(
                      key: const Key('role-name'),
                      controller: _nameController,
                      enabled: !_saving,
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        labelText: 'Nama role',
                        prefixIcon: Icon(Icons.shield_outlined),
                      ),
                      validator: (value) => value?.trim().isEmpty == true
                          ? 'Nama role wajib diisi.'
                          : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      key: const Key('role-code'),
                      controller: _codeController,
                      enabled: !_saving && !_isSystem,
                      textInputAction: TextInputAction.next,
                      inputFormatters: [
                        FilteringTextInputFormatter.allow(
                          RegExp(r'[a-z0-9_\-]'),
                        ),
                      ],
                      decoration: InputDecoration(
                        labelText: 'Kode',
                        hintText: 'Otomatis dari nama',
                        prefixIcon: const Icon(Icons.code_rounded),
                        helperText: _isSystem
                            ? 'Kode role sistem tidak dapat diubah.'
                            : 'Huruf kecil, angka, garis bawah, atau tanda hubung.',
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      key: const Key('role-description'),
                      controller: _descriptionController,
                      enabled: !_saving,
                      minLines: 2,
                      maxLines: 4,
                      textInputAction: TextInputAction.newline,
                      decoration: const InputDecoration(
                        labelText: 'Deskripsi',
                        alignLabelWithHint: true,
                        prefixIcon: Icon(Icons.notes_rounded),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Material(
                      color: Colors.white,
                      shape: RoundedRectangleBorder(
                        side: const BorderSide(color: NusaColors.outline),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: SwitchListTile.adaptive(
                        key: const Key('role-active'),
                        value: _active,
                        onChanged: _saving || _isSystem
                            ? null
                            : (value) => setState(() => _active = value),
                        secondary: Icon(
                          _active
                              ? Icons.check_circle_outline_rounded
                              : Icons.block_rounded,
                          color: _active
                              ? NusaColors.success
                              : NusaColors.textSecondary,
                        ),
                        title: const Text(
                          'Role aktif',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: Text(
                          _isSystem
                              ? 'Role sistem harus selalu aktif.'
                              : 'Role aktif dapat diberikan ke akun pengguna.',
                          style: const TextStyle(fontSize: 11),
                        ),
                      ),
                    ),
                    const SizedBox(height: 22),
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'Izin Akses',
                            style: TextStyle(
                              color: NusaColors.textPrimary,
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        TextButton(
                          key: const Key('select-all-permissions'),
                          onPressed: _saving || _isAdministrator
                              ? null
                              : () => _setAll(true),
                          child: const Text('Semua'),
                        ),
                        TextButton(
                          onPressed: _saving || _isAdministrator
                              ? null
                              : () => _setAll(false),
                          child: const Text('Kosongkan'),
                        ),
                      ],
                    ),
                    if (_isAdministrator)
                      Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: NusaColors.surfaceBlue,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(
                              Icons.lock_rounded,
                              color: NusaColors.primary,
                              size: 19,
                            ),
                            SizedBox(width: 9),
                            Expanded(
                              child: Text(
                                'Administrator selalu memiliki semua izin aktif.',
                                style: TextStyle(
                                  color: NusaColors.primary,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    NusaTextField(
                      fieldKey: const Key('permission-search'),
                      controller: _searchController,
                      hintText: 'Cari nama, kode, atau kelompok izin',
                      prefixIcon: Icons.search_rounded,
                      onChanged: (_) => setState(() {}),
                    ),
                    const SizedBox(height: 12),
                    if (groups.isEmpty)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 24),
                        child: Text(
                          'Tidak ada izin yang cocok.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: NusaColors.textSecondary),
                        ),
                      )
                    else
                      for (final group in groups) ...[
                        _PermissionGroupCard(
                          group: group,
                          selected: _selected,
                          locked: _saving || _isAdministrator,
                          onTogglePermission: _togglePermission,
                          onToggleGroup: _toggleGroup,
                        ),
                        const SizedBox(height: 10),
                      ],
                  ],
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 14),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: NusaColors.outline)),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (_error != null) ...[
                    Text(
                      _error!,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                        fontSize: 11.5,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 9),
                  ],
                  NusaPrimaryButton(
                    key: const Key('save-role'),
                    label: _initial == null
                        ? 'Simpan Role'
                        : 'Simpan Perubahan',
                    loading: _saving,
                    onPressed: _save,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  int get _selectedCount => _selected.length;

  void _setAll(bool selected) {
    setState(() {
      _selected = selected
          ? widget.permissionGroups
                .expand((group) => group.permissions)
                .map((permission) => permission.id)
                .toSet()
          : <int>{};
    });
  }

  void _togglePermission(int id, bool selected) {
    setState(() => selected ? _selected.add(id) : _selected.remove(id));
  }

  void _toggleGroup(PermissionGroup group, bool selected) {
    setState(() {
      for (final permission in group.permissions) {
        selected
            ? _selected.add(permission.id)
            : _selected.remove(permission.id);
      }
    });
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate() || _saving) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.onSubmit(
        RoleAccessFormValue(
          name: _nameController.text,
          code: _isSystem ? _initial!.code : _codeController.text,
          description: _descriptionController.text,
          active: _isSystem ? true : _active,
          permissionIds: _selected.toList(growable: false)..sort(),
        ),
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = error is AppException
              ? error.message
              : 'Role belum dapat disimpan. Silakan coba lagi.';
        });
      }
    }
  }
}

class _PermissionGroupCard extends StatelessWidget {
  const _PermissionGroupCard({
    required this.group,
    required this.selected,
    required this.locked,
    required this.onTogglePermission,
    required this.onToggleGroup,
  });

  final PermissionGroup group;
  final Set<int> selected;
  final bool locked;
  final void Function(int id, bool selected) onTogglePermission;
  final void Function(PermissionGroup group, bool selected) onToggleGroup;

  @override
  Widget build(BuildContext context) {
    final selectedCount = group.permissions
        .where((permission) => selected.contains(permission.id))
        .length;
    final complete = selectedCount == group.permissions.length;
    return Container(
      decoration: BoxDecoration(
        color: complete ? NusaColors.surfaceBlue : Colors.white,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(
          color: complete
              ? NusaColors.primary.withValues(alpha: 0.25)
              : NusaColors.outline,
        ),
      ),
      child: ExpansionTile(
        tilePadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
        childrenPadding: const EdgeInsets.fromLTRB(8, 0, 8, 10),
        shape: const Border(),
        title: Text(
          group.name,
          style: const TextStyle(
            color: NusaColors.primary,
            fontSize: 14,
            fontWeight: FontWeight.w800,
          ),
        ),
        subtitle: Text(
          '$selectedCount/${group.permissions.length} izin dipilih',
          style: const TextStyle(fontSize: 10.5),
        ),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            IconButton(
              tooltip: complete ? 'Kosongkan kelompok' : 'Pilih kelompok',
              onPressed: locked ? null : () => onToggleGroup(group, !complete),
              icon: Icon(
                complete
                    ? Icons.check_circle_rounded
                    : Icons.radio_button_unchecked_rounded,
                color: complete ? NusaColors.success : NusaColors.textSecondary,
                size: 20,
              ),
            ),
            const Icon(Icons.expand_more_rounded),
          ],
        ),
        children: [
          for (final permission in group.permissions)
            CheckboxListTile(
              key: Key('permission-${permission.id}'),
              value: selected.contains(permission.id),
              onChanged: locked
                  ? null
                  : (value) =>
                        onTogglePermission(permission.id, value ?? false),
              controlAffinity: ListTileControlAffinity.leading,
              dense: true,
              contentPadding: const EdgeInsets.symmetric(horizontal: 4),
              title: Text(
                permission.name,
                style: const TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    permission.code,
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  if (permission.description?.isNotEmpty == true)
                    Text(
                      permission.description!,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 10),
                    ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
