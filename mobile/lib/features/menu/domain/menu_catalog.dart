class MenuCatalog {
  const MenuCatalog({
    required this.generatedAt,
    required this.itemCount,
    required this.groups,
  });

  factory MenuCatalog.fromJson(Map<String, dynamic> json) {
    final groups = (json['kelompok'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(MenuGroup.fromJson)
        .toList(growable: false);

    return MenuCatalog(
      generatedAt: DateTime.parse(json['dihasilkan_pada'] as String),
      itemCount:
          json['jumlah_menu'] as int? ??
          groups.fold(0, (total, group) => total + group.items.length),
      groups: groups,
    );
  }

  final DateTime generatedAt;
  final int itemCount;
  final List<MenuGroup> groups;

  MenuGroup? groupByCode(String code) {
    for (final group in groups) {
      if (group.code == code) {
        return group;
      }
    }

    return null;
  }

  MenuEntry? entryByCode(String code) {
    for (final group in groups) {
      for (final item in group.items) {
        if (item.code == code) {
          return item;
        }
      }
    }

    return null;
  }
}

class MenuGroup {
  const MenuGroup({
    required this.code,
    required this.label,
    required this.description,
    required this.icon,
    required this.items,
  });

  factory MenuGroup.fromJson(Map<String, dynamic> json) {
    return MenuGroup(
      code: json['kode'] as String,
      label: json['label'] as String,
      description: json['deskripsi'] as String? ?? '',
      icon: json['ikon'] as String? ?? 'apps',
      items: (json['items'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(MenuEntry.fromJson)
          .toList(growable: false),
    );
  }

  final String code;
  final String label;
  final String description;
  final String icon;
  final List<MenuEntry> items;

  MenuGroup copyWithItems(List<MenuEntry> filteredItems) {
    return MenuGroup(
      code: code,
      label: label,
      description: description,
      icon: icon,
      items: filteredItems,
    );
  }
}

class MenuEntry {
  const MenuEntry({
    required this.code,
    required this.label,
    required this.description,
    required this.initials,
    required this.subgroup,
    required this.icon,
    required this.status,
    required this.route,
  });

  factory MenuEntry.fromJson(Map<String, dynamic> json) {
    return MenuEntry(
      code: json['kode'] as String,
      label: json['label'] as String,
      description: json['deskripsi'] as String? ?? '',
      initials: json['inisial'] as String? ?? '',
      subgroup: json['subkelompok'] as String?,
      icon: json['ikon'] as String?,
      status: json['status'] as String? ?? 'segera_hadir',
      route: json['rute'] as String?,
    );
  }

  final String code;
  final String label;
  final String description;
  final String initials;
  final String? subgroup;
  final String? icon;
  final String status;
  final String? route;

  bool get isAvailable => status == 'tersedia' && route != null;

  bool matches(String query) {
    final searchable = [
      label,
      description,
      subgroup ?? '',
      code,
    ].join(' ').toLowerCase();

    return searchable.contains(query.toLowerCase());
  }
}
