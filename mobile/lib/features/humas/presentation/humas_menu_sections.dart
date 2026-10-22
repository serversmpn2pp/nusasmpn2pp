import 'package:nusa/features/auth/domain/pengguna.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';

class HumasMenuLink {
  const HumasMenuLink({
    required this.entry,
    this.native,
    this.filter = const {},
  });

  final MenuEntry entry;
  final MenuEntry? native;
  final Map<String, String> filter;
}

class HumasMenuSection {
  const HumasMenuSection({
    required this.code,
    required this.label,
    required this.items,
  });

  final String code;
  final String label;
  final List<HumasMenuLink> items;
}

List<HumasMenuSection> humasMenuSections({
  required MenuGroup? group,
  required Pengguna? user,
  required List<HumasData> workspaces,
}) {
  if (group == null || user == null) return const [];
  final nativeItems = {
    for (final item in group.items)
      if (HumasModule.fromMenuCode(item.code)?.canOpen(user) == true)
        item.code: item,
  };
  final sections = <String, HumasMenuSection>{};
  final destinations = <String>{};
  final codes = <String>{};

  String destination(MenuEntry item, Map<String, String> filter) => Uri(
    path: HumasModule.fromMenuCode(item.code)!.route,
    queryParameters: filter.isEmpty ? null : filter,
  ).toString();

  if (humasCanViewDashboard(user)) {
    for (final workspace in workspaces) {
      final links = <HumasMenuLink>[];
      for (final item in humasItems(workspace['items'])) {
        final code = humasText(item['kode'], '');
        final label = humasText(item['label'], '');
        if (code.isEmpty || label.isEmpty || codes.contains(code)) continue;
        final nativeCode = humasText(item['menu_mobile'], '');
        final native = nativeItems[nativeCode];
        // A newer menu catalog may already have revoked a stale dashboard link.
        if (nativeCode.isNotEmpty && native == null) continue;
        final rawFilter = humasMap(item['filter']);
        final filter = <String, String>{
          for (final key in ['urusan', 'jenis'])
            if (rawFilter[key] is String &&
                (rawFilter[key] as String).isNotEmpty)
              key: rawFilter[key] as String,
        };
        final target = native == null
            ? 'web:$code'
            : destination(native, filter);
        if (!destinations.add(target)) continue;
        codes.add(code);
        final available =
            item['tersedia_mobile'] == true &&
            native != null &&
            native.isAvailable;
        links.add(
          HumasMenuLink(
            entry: MenuEntry(
              code: code,
              label: label,
              description: native?.description ?? '',
              initials: native?.initials ?? 'HM',
              subgroup: humasText(workspace['label']),
              icon: humasText(item['ikon'], ''),
              status: available ? 'tersedia' : 'web',
              route: available ? native.route : null,
            ),
            native: native,
            filter: filter,
          ),
        );
      }
      if (links.isNotEmpty) {
        final label = humasText(workspace['label']);
        sections[label] = HumasMenuSection(
          code: humasText(workspace['kode']),
          label: label,
          items: links,
        );
      }
    }
  }

  // Keep native menus usable when the dashboard is unavailable or not permitted.
  for (final item in nativeItems.values) {
    if (codes.contains(item.code) ||
        !destinations.add(destination(item, const {}))) {
      continue;
    }
    codes.add(item.code);
    final label = item.subgroup ?? 'Menu Humas';
    final section = sections.putIfAbsent(
      label,
      () => HumasMenuSection(
        code: 'catalog-${sections.length}',
        label: label,
        items: [],
      ),
    );
    section.items.add(HumasMenuLink(entry: item, native: item));
  }
  return sections.values.toList(growable: false);
}
