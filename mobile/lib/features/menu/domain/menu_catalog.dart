class MenuCatalog {
  const MenuCatalog({
    required this.generatedAt,
    required this.itemCount,
    required this.groups,
    this.quickAccess = const QuickAccessConfig(),
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
      quickAccess: QuickAccessConfig.fromJson(
        json['akses_cepat'] is Map
            ? Map<String, dynamic>.from(json['akses_cepat'] as Map)
            : const {},
      ),
    );
  }

  final DateTime generatedAt;
  final int itemCount;
  final List<MenuGroup> groups;
  final QuickAccessConfig quickAccess;

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

  MenuGroup? groupForEntry(String code) {
    for (final group in groups) {
      if (group.items.any((item) => item.code == code)) return group;
    }

    return null;
  }

  List<MenuEntry> get availableEntries => groups
      .expand((group) => group.items)
      .where((item) => item.isAvailable)
      .toList(growable: false);

  List<MenuEntry> get quickAccessEntries => quickAccess.menuCodes
      .map(entryByCode)
      .whereType<MenuEntry>()
      .where((item) => item.isAvailable)
      .toList(growable: false);
}

class QuickAccessConfig {
  const QuickAccessConfig({
    this.canCustomize = false,
    this.customized = false,
    this.maximum = 4,
    this.source = 'bawaan',
    this.menuCodes = const [],
  });

  factory QuickAccessConfig.fromJson(Map<String, dynamic> json) =>
      QuickAccessConfig(
        canCustomize: json['dapat_diatur'] as bool? ?? false,
        customized: json['dikustomisasi'] as bool? ?? false,
        maximum: _integer(json['maksimal'], fallback: 4),
        source: json['sumber'] as String? ?? 'bawaan',
        menuCodes: (json['kode_menu'] as List<dynamic>? ?? const [])
            .map((item) => item.toString())
            .toList(growable: false),
      );

  final bool canCustomize;
  final bool customized;
  final int maximum;
  final String source;
  final List<String> menuCodes;
}

int _integer(Object? value, {required int fallback}) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? fallback,
  _ => fallback,
};

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
