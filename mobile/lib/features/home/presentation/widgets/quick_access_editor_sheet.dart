import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';
import 'package:nusa/features/menu/presentation/menu_visuals.dart';

class QuickAccessEditResult {
  const QuickAccessEditResult.save(this.menuCodes) : reset = false;

  const QuickAccessEditResult.reset() : reset = true, menuCodes = const [];

  final bool reset;
  final List<String> menuCodes;
}

Future<QuickAccessEditResult?> showQuickAccessEditor(
  BuildContext context,
  MenuCatalog catalog,
) {
  return showModalBottomSheet<QuickAccessEditResult>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.transparent,
    builder: (context) => _QuickAccessEditor(catalog: catalog),
  );
}

class _QuickAccessEditor extends StatefulWidget {
  const _QuickAccessEditor({required this.catalog});

  final MenuCatalog catalog;

  @override
  State<_QuickAccessEditor> createState() => _QuickAccessEditorState();
}

class _QuickAccessEditorState extends State<_QuickAccessEditor> {
  late final List<String> _selected;
  final _searchController = TextEditingController();
  String _query = '';

  int get _maximum => widget.catalog.quickAccess.maximum;

  @override
  void initState() {
    super.initState();
    _selected = widget.catalog.quickAccessEntries
        .map((item) => item.code)
        .take(_maximum)
        .toList();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;
    final entries = widget.catalog.availableEntries
        .where((item) {
          if (_query.isEmpty) return true;
          return item.matches(_query);
        })
        .toList(growable: false);

    return FractionallySizedBox(
      heightFactor: 0.92,
      child: Material(
        color: NusaColors.background,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        clipBehavior: Clip.antiAlias,
        child: Padding(
          padding: EdgeInsets.only(bottom: bottomInset),
          child: Column(
            children: [
              const SizedBox(height: 9),
              Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: NusaColors.outline,
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 11, 8, 10),
                child: Row(
                  children: [
                    Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        color: NusaColors.primary.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(13),
                      ),
                      child: const Icon(
                        Icons.dashboard_customize_rounded,
                        color: NusaColors.primary,
                      ),
                    ),
                    const SizedBox(width: 11),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Atur Akses Cepat',
                            style: TextStyle(
                              color: NusaColors.textPrimary,
                              fontSize: 17,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          SizedBox(height: 2),
                          Text(
                            'Pilih dan urutkan menu yang paling sering digunakan.',
                            style: TextStyle(
                              color: NusaColors.textSecondary,
                              fontSize: 10.5,
                            ),
                          ),
                        ],
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
              const Divider(height: 1),
              Expanded(
                child: ListView(
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                  children: [
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'Pintasan terpilih',
                            style: TextStyle(
                              color: NusaColors.textPrimary,
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 9,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: NusaColors.primary.withValues(alpha: 0.08),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            '${_selected.length}/$_maximum',
                            style: const TextStyle(
                              color: NusaColors.primary,
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 9),
                    if (_selected.isEmpty)
                      _EmptySelection(maximum: _maximum)
                    else
                      for (
                        var index = 0;
                        index < _selected.length;
                        index++
                      ) ...[
                        _SelectedMenuTile(
                          key: ValueKey(
                            'quick-access-selected-${_selected[index]}',
                          ),
                          item: widget.catalog.entryByCode(_selected[index])!,
                          color: _colorFor(_selected[index]),
                          index: index,
                          length: _selected.length,
                          onMoveUp: () => _move(index, index - 1),
                          onMoveDown: () => _move(index, index + 1),
                          onRemove: () =>
                              setState(() => _selected.removeAt(index)),
                        ),
                        if (index < _selected.length - 1)
                          const SizedBox(height: 7),
                      ],
                    const SizedBox(height: 17),
                    TextField(
                      key: const Key('quick-access-search'),
                      controller: _searchController,
                      onChanged: (value) =>
                          setState(() => _query = value.trim()),
                      decoration: InputDecoration(
                        labelText: 'Cari menu',
                        hintText: 'Contoh: nilai atau presensi',
                        prefixIcon: const Icon(Icons.search_rounded),
                        suffixIcon: _query.isEmpty
                            ? null
                            : IconButton(
                                tooltip: 'Hapus pencarian',
                                onPressed: () {
                                  _searchController.clear();
                                  setState(() => _query = '');
                                },
                                icon: const Icon(Icons.close_rounded),
                              ),
                      ),
                    ),
                    const SizedBox(height: 13),
                    if (entries.isEmpty)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 30),
                        child: Text(
                          'Menu tidak ditemukan.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: NusaColors.textSecondary),
                        ),
                      )
                    else
                      for (final group in widget.catalog.groups) ...[
                        if (_entriesForGroup(group, entries).isNotEmpty) ...[
                          Padding(
                            padding: const EdgeInsets.only(top: 5, bottom: 6),
                            child: Text(
                              group.label,
                              style: TextStyle(
                                color: nusaMenuGroupColor(group.code),
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          for (final item in _entriesForGroup(group, entries))
                            _AvailableMenuTile(
                              item: item,
                              color: nusaMenuGroupColor(group.code),
                              selected: _selected.contains(item.code),
                              onTap: () => _toggle(item),
                            ),
                        ],
                      ],
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  border: Border(top: BorderSide(color: NusaColors.outline)),
                ),
                child: Row(
                  children: [
                    TextButton.icon(
                      key: const Key('quick-access-reset'),
                      onPressed: () => Navigator.pop(
                        context,
                        const QuickAccessEditResult.reset(),
                      ),
                      icon: const Icon(Icons.restart_alt_rounded, size: 19),
                      label: const Text('Rekomendasi'),
                    ),
                    const SizedBox(width: 9),
                    Expanded(
                      child: FilledButton.icon(
                        key: const Key('quick-access-save'),
                        onPressed: _selected.isEmpty
                            ? null
                            : () => Navigator.pop(
                                context,
                                QuickAccessEditResult.save(
                                  List.unmodifiable(_selected),
                                ),
                              ),
                        icon: const Icon(Icons.save_rounded, size: 19),
                        label: const Text('Simpan'),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  List<MenuEntry> _entriesForGroup(MenuGroup group, List<MenuEntry> entries) {
    final codes = entries.map((item) => item.code).toSet();
    return group.items
        .where((item) => item.isAvailable && codes.contains(item.code))
        .toList(growable: false);
  }

  Color _colorFor(String code) {
    final group = widget.catalog.groupForEntry(code);
    return group == null ? NusaColors.primary : nusaMenuGroupColor(group.code);
  }

  void _move(int from, int to) {
    if (to < 0 || to >= _selected.length) return;
    setState(() {
      final item = _selected.removeAt(from);
      _selected.insert(to, item);
    });
  }

  void _toggle(MenuEntry item) {
    FocusScope.of(context).unfocus();
    if (_selected.contains(item.code)) {
      setState(() => _selected.remove(item.code));
      return;
    }
    if (_selected.length >= _maximum) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(content: Text('Maksimal $_maximum menu akses cepat.')),
        );
      return;
    }
    setState(() => _selected.add(item.code));
  }
}

class _EmptySelection extends StatelessWidget {
  const _EmptySelection({required this.maximum});

  final int maximum;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Text(
      'Pilih hingga $maximum menu dari daftar di bawah.',
      textAlign: TextAlign.center,
      style: const TextStyle(color: NusaColors.textSecondary, fontSize: 11),
    ),
  );
}

class _SelectedMenuTile extends StatelessWidget {
  const _SelectedMenuTile({
    required this.item,
    required this.color,
    required this.index,
    required this.length,
    required this.onMoveUp,
    required this.onMoveDown,
    required this.onRemove,
    super.key,
  });

  final MenuEntry item;
  final Color color;
  final int index;
  final int length;
  final VoidCallback onMoveUp;
  final VoidCallback onMoveDown;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(10, 7, 5, 7),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      children: [
        Container(
          width: 35,
          height: 35,
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(nusaMenuEntryIcon(item), color: color, size: 20),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            item.label,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: NusaColors.textPrimary,
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        IconButton(
          key: Key('quick-access-up-${item.code}'),
          tooltip: 'Naikkan',
          visualDensity: VisualDensity.compact,
          constraints: const BoxConstraints.tightFor(width: 32, height: 36),
          padding: EdgeInsets.zero,
          onPressed: index == 0 ? null : onMoveUp,
          icon: const Icon(Icons.keyboard_arrow_up_rounded, size: 20),
        ),
        IconButton(
          key: Key('quick-access-down-${item.code}'),
          tooltip: 'Turunkan',
          visualDensity: VisualDensity.compact,
          constraints: const BoxConstraints.tightFor(width: 32, height: 36),
          padding: EdgeInsets.zero,
          onPressed: index == length - 1 ? null : onMoveDown,
          icon: const Icon(Icons.keyboard_arrow_down_rounded, size: 20),
        ),
        IconButton(
          key: Key('quick-access-remove-${item.code}'),
          tooltip: 'Hapus',
          visualDensity: VisualDensity.compact,
          constraints: const BoxConstraints.tightFor(width: 32, height: 36),
          padding: EdgeInsets.zero,
          onPressed: onRemove,
          icon: const Icon(Icons.close_rounded, size: 18),
        ),
      ],
    ),
  );
}

class _AvailableMenuTile extends StatelessWidget {
  const _AvailableMenuTile({
    required this.item,
    required this.color,
    required this.selected,
    required this.onTap,
  });

  final MenuEntry item;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Material(
      color: selected ? color.withValues(alpha: 0.07) : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(13),
        side: BorderSide(
          color: selected ? color.withValues(alpha: 0.35) : NusaColors.outline,
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        key: Key('quick-access-option-${item.code}'),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
          child: Row(
            children: [
              Icon(nusaMenuEntryIcon(item), color: color, size: 21),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  item.label,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Icon(
                selected
                    ? Icons.check_circle_rounded
                    : Icons.add_circle_outline_rounded,
                color: selected ? color : NusaColors.textSecondary,
                size: 21,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
