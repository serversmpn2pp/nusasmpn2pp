class RoleAccessPage {
  const RoleAccessPage({
    required this.items,
    required this.summary,
    required this.pagination,
    required this.query,
    required this.status,
    required this.canManage,
  });

  factory RoleAccessPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final access = _map(json['hak_akses']);
    return RoleAccessPage(
      items: _list(json['items'], RoleAccess.fromJson),
      summary: RoleAccessSummary.fromJson(_map(json['ringkasan'])),
      pagination: RoleAccessPagination.fromJson(_map(json['paginasi'])),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final List<RoleAccess> items;
  final RoleAccessSummary summary;
  final RoleAccessPagination pagination;
  final String query;
  final String status;
  final bool canManage;

  RoleAccessPage append(RoleAccessPage next) => RoleAccessPage(
    items: [...items, ...next.items],
    summary: next.summary,
    pagination: next.pagination,
    query: next.query,
    status: next.status,
    canManage: next.canManage,
  );
}

class RoleAccessSummary {
  const RoleAccessSummary({
    required this.total,
    required this.active,
    required this.system,
    required this.additional,
    required this.activePermissions,
    required this.connectedUsers,
  });

  factory RoleAccessSummary.fromJson(Map<String, dynamic> json) =>
      RoleAccessSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        system: _integer(json['sistem']),
        additional: _integer(json['tambahan']),
        activePermissions: _integer(json['izin_aktif']),
        connectedUsers: _integer(json['pengguna_terhubung']),
      );

  final int total;
  final int active;
  final int system;
  final int additional;
  final int activePermissions;
  final int connectedUsers;
}

class RoleAccessPagination {
  const RoleAccessPagination({
    required this.page,
    required this.lastPage,
    required this.total,
    required this.hasNextPage,
  });

  factory RoleAccessPagination.fromJson(Map<String, dynamic> json) =>
      RoleAccessPagination(
        page: _integer(json['halaman']),
        lastPage: _integer(json['halaman_terakhir']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int lastPage;
  final int total;
  final bool hasNextPage;
}

class RoleAccess {
  const RoleAccess({
    required this.id,
    required this.name,
    required this.code,
    required this.system,
    required this.active,
    required this.permissionCount,
    required this.userCount,
    required this.permissionPercentage,
    this.description,
    this.permissionIds = const [],
  });

  factory RoleAccess.fromJson(Map<String, dynamic> json) => RoleAccess(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    code: json['kode'] as String? ?? '-',
    description: json['deskripsi'] as String?,
    system: json['sistem'] as bool? ?? false,
    active: json['aktif'] as bool? ?? false,
    permissionCount: _integer(json['jumlah_izin']),
    userCount: _integer(json['jumlah_pengguna']),
    permissionPercentage: _integer(json['persentase_izin']),
    permissionIds: (json['izin_ids'] as List<dynamic>? ?? const [])
        .whereType<num>()
        .map((value) => value.toInt())
        .toList(growable: false),
  );

  final int id;
  final String name;
  final String code;
  final String? description;
  final bool system;
  final bool active;
  final int permissionCount;
  final int userCount;
  final int permissionPercentage;
  final List<int> permissionIds;

  bool get isAdministrator => code == 'administrator';
}

class RoleAccessDetail {
  const RoleAccessDetail({
    required this.role,
    required this.permissionGroups,
    required this.canManage,
  });

  factory RoleAccessDetail.fromJson(Map<String, dynamic> json) {
    final access = _map(json['hak_akses']);
    return RoleAccessDetail(
      role: RoleAccess.fromJson(_map(json['peran'])),
      permissionGroups: _list(json['kelompok_izin'], PermissionGroup.fromJson),
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final RoleAccess role;
  final List<PermissionGroup> permissionGroups;
  final bool canManage;
}

class RoleAccessReference {
  const RoleAccessReference({
    required this.permissionGroups,
    required this.permissionCount,
    required this.canManage,
  });

  factory RoleAccessReference.fromJson(Map<String, dynamic> json) {
    final access = _map(json['hak_akses']);
    return RoleAccessReference(
      permissionGroups: _list(json['kelompok_izin'], PermissionGroup.fromJson),
      permissionCount: _integer(json['jumlah_izin']),
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final List<PermissionGroup> permissionGroups;
  final int permissionCount;
  final bool canManage;
}

class PermissionGroup {
  const PermissionGroup({required this.name, required this.permissions});

  factory PermissionGroup.fromJson(Map<String, dynamic> json) =>
      PermissionGroup(
        name: json['nama'] as String? ?? 'Lainnya',
        permissions: _list(json['izin'], RolePermission.fromJson),
      );

  final String name;
  final List<RolePermission> permissions;
}

class RolePermission {
  const RolePermission({
    required this.id,
    required this.name,
    required this.code,
    this.description,
  });

  factory RolePermission.fromJson(Map<String, dynamic> json) => RolePermission(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    code: json['kode'] as String? ?? '-',
    description: json['deskripsi'] as String?,
  );

  final int id;
  final String name;
  final String code;
  final String? description;
}

class RoleAccessFormValue {
  const RoleAccessFormValue({
    required this.name,
    required this.active,
    required this.permissionIds,
    this.code,
    this.description,
  });

  final String name;
  final String? code;
  final String? description;
  final bool active;
  final List<int> permissionIds;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

int _integer(Object? value) => value is num ? value.toInt() : 0;
