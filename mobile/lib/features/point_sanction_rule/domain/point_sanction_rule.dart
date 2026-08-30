class PointSanctionRulePage {
  const PointSanctionRulePage({
    required this.items,
    required this.summary,
    required this.access,
    required this.query,
    required this.status,
  });

  factory PointSanctionRulePage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return PointSanctionRulePage(
      items: _list(json['items'], PointSanctionRule.fromJson),
      summary: PointSanctionRuleSummary.fromJson(_map(json['ringkasan'])),
      access: PointSanctionRuleAccess.fromJson(_map(json['hak_akses'])),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
    );
  }

  final List<PointSanctionRule> items;
  final PointSanctionRuleSummary summary;
  final PointSanctionRuleAccess access;
  final String query;
  final String status;
}

class PointSanctionRule {
  const PointSanctionRule({
    required this.id,
    required this.pointThreshold,
    required this.name,
    required this.description,
    required this.order,
    required this.active,
    required this.triggeredCount,
  });

  factory PointSanctionRule.fromJson(Map<String, dynamic> json) =>
      PointSanctionRule(
        id: _integer(json['id']),
        pointThreshold: _integer(json['batas_poin']),
        name: json['nama'] as String? ?? '-',
        description: json['deskripsi'] as String? ?? '-',
        order: _integer(json['urutan']),
        active: json['aktif'] as bool? ?? false,
        triggeredCount: _integer(json['jumlah_sanksi_terpicu']),
      );

  final int id;
  final int pointThreshold;
  final String name;
  final String description;
  final int order;
  final bool active;
  final int triggeredCount;
}

class PointSanctionRuleSummary {
  const PointSanctionRuleSummary({
    required this.total,
    required this.active,
    required this.inactive,
    required this.triggeredCount,
  });

  factory PointSanctionRuleSummary.fromJson(Map<String, dynamic> json) =>
      PointSanctionRuleSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        inactive: _integer(json['nonaktif']),
        triggeredCount: _integer(json['jumlah_sanksi_terpicu']),
      );

  final int total;
  final int active;
  final int inactive;
  final int triggeredCount;
}

class PointSanctionRuleAccess {
  const PointSanctionRuleAccess({required this.canManage});

  factory PointSanctionRuleAccess.fromJson(Map<String, dynamic> json) =>
      PointSanctionRuleAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
      );

  final bool canManage;
}

class PointSanctionRuleFormValue {
  const PointSanctionRuleFormValue({
    required this.pointThreshold,
    required this.name,
    required this.description,
    required this.order,
    required this.active,
  });

  final int pointThreshold;
  final String name;
  final String description;
  final int order;
  final bool active;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value
          .whereType<Map>()
          .map((item) => convert(Map<String, dynamic>.from(item)))
          .toList(growable: false)
    : <T>[];

int _integer(Object? value) => value is num ? value.toInt() : 0;
