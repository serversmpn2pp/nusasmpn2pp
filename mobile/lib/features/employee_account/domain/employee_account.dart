class EmployeeAccountPage {
  const EmployeeAccountPage({
    required this.items,
    required this.counts,
    required this.roles,
    required this.pagination,
    required this.query,
    required this.status,
    required this.canManage,
  });

  factory EmployeeAccountPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};
    final access = _map(json['hak_akses']) ?? const {};
    return EmployeeAccountPage(
      items: _list(json['items'], EmployeeAccountItem.fromJson),
      counts: EmployeeAccountCounts.fromJson(
        _map(json['ringkasan']) ?? const {},
      ),
      roles: _list(json['pilihan_peran'], EmployeeAccountRole.fromJson),
      pagination: EmployeeAccountPagination.fromJson(
        _map(json['paginasi']) ?? const {},
      ),
      query: filter['cari'] as String? ?? '',
      status: filter['status_akun'] as String? ?? 'semua',
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final List<EmployeeAccountItem> items;
  final EmployeeAccountCounts counts;
  final List<EmployeeAccountRole> roles;
  final EmployeeAccountPagination pagination;
  final String query;
  final String status;
  final bool canManage;

  EmployeeAccountPage append(EmployeeAccountPage next) => EmployeeAccountPage(
    items: [...items, ...next.items],
    counts: next.counts,
    roles: next.roles,
    pagination: next.pagination,
    query: next.query,
    status: next.status,
    canManage: next.canManage,
  );
}

class EmployeeAccountDetail {
  const EmployeeAccountDetail({
    required this.item,
    required this.roles,
    required this.canManage,
  });

  factory EmployeeAccountDetail.fromJson(Map<String, dynamic> json) {
    final access = _map(json['hak_akses']) ?? const {};
    return EmployeeAccountDetail(
      item: EmployeeAccountItem.fromJson(json),
      roles: _list(json['pilihan_peran'], EmployeeAccountRole.fromJson),
      canManage: access['dapat_kelola'] as bool? ?? false,
    );
  }

  final EmployeeAccountItem item;
  final List<EmployeeAccountRole> roles;
  final bool canManage;
}

class EmployeeAccountItem {
  const EmployeeAccountItem({
    required this.employee,
    required this.status,
    required this.account,
  });

  factory EmployeeAccountItem.fromJson(Map<String, dynamic> json) =>
      EmployeeAccountItem(
        employee: AccountEmployee.fromJson(_map(json['pegawai']) ?? const {}),
        status: json['status_akun'] as String? ?? 'belum',
        account: ManagedEmployeeAccount.fromJson(
          _map(json['akun']) ?? const {},
        ),
      );

  final AccountEmployee employee;
  final String status;
  final ManagedEmployeeAccount account;

  String get statusLabel => switch (status) {
    'aktif' => 'Akun Aktif',
    'nonaktif' => 'Akun Nonaktif',
    'tanpa_nip' => 'NIP Kosong',
    _ => 'Belum Ada Akun',
  };
}

class AccountEmployee {
  const AccountEmployee({
    required this.id,
    required this.name,
    required this.active,
    this.nip,
    this.primaryPosition,
    this.photoUrl,
  });

  factory AccountEmployee.fromJson(Map<String, dynamic> json) =>
      AccountEmployee(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nip: json['nip'] as String?,
        primaryPosition: json['jabatan_utama'] as String?,
        photoUrl: json['foto_url'] as String?,
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final String? nip;
  final String? primaryPosition;
  final String? photoUrl;
  final bool active;

  String get initials {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .take(2);
    final value = parts.map((part) => part[0]).join();
    return value.isEmpty ? 'PG' : value.toUpperCase();
  }
}

class ManagedEmployeeAccount {
  const ManagedEmployeeAccount({
    required this.available,
    required this.active,
    required this.systemAccount,
    required this.mustChangePassword,
    required this.roles,
    this.id,
    this.username,
    this.lastLoginAt,
  });

  factory ManagedEmployeeAccount.fromJson(Map<String, dynamic> json) =>
      ManagedEmployeeAccount(
        available: json['tersedia'] as bool? ?? false,
        id: _nullableInteger(json['id']),
        username: json['username'] as String?,
        active: json['aktif'] as bool? ?? false,
        systemAccount: json['akun_sistem'] as bool? ?? false,
        mustChangePassword: json['wajib_ganti_kata_sandi'] as bool? ?? false,
        lastLoginAt: _date(json['terakhir_login_pada']),
        roles: _list(json['peran'], EmployeeAccountRole.fromJson),
      );

  final bool available;
  final int? id;
  final String? username;
  final bool active;
  final bool systemAccount;
  final bool mustChangePassword;
  final DateTime? lastLoginAt;
  final List<EmployeeAccountRole> roles;
}

class EmployeeAccountRole {
  const EmployeeAccountRole({
    required this.id,
    required this.code,
    required this.name,
    required this.system,
    this.description,
  });

  factory EmployeeAccountRole.fromJson(Map<String, dynamic> json) =>
      EmployeeAccountRole(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '',
        name: json['nama'] as String? ?? '-',
        description: json['deskripsi'] as String?,
        system: json['sistem'] as bool? ?? false,
      );

  final int id;
  final String code;
  final String name;
  final String? description;
  final bool system;

  bool get isEmployeeBase => code == 'pegawai';
}

class EmployeeAccountCounts {
  const EmployeeAccountCounts({
    required this.activeEmployees,
    required this.withNip,
    required this.accounts,
    required this.withoutAccount,
  });

  factory EmployeeAccountCounts.fromJson(Map<String, dynamic> json) =>
      EmployeeAccountCounts(
        activeEmployees: _integer(json['pegawai_aktif']),
        withNip: _integer(json['punya_nip']),
        accounts: _integer(json['akun_pegawai']),
        withoutAccount: _integer(json['belum_akun']),
      );

  final int activeEmployees;
  final int withNip;
  final int accounts;
  final int withoutAccount;
}

class EmployeeAccountPagination {
  const EmployeeAccountPagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory EmployeeAccountPagination.fromJson(Map<String, dynamic> json) =>
      EmployeeAccountPagination(
        page: _integer(json['halaman']),
        lastPage: _integer(json['halaman_terakhir']),
        perPage: _integer(json['per_halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int lastPage;
  final int perPage;
  final int total;
  final bool hasNextPage;
}

class BulkAccountResult {
  const BulkAccountResult({
    required this.created,
    required this.skipped,
    required this.notes,
  });

  factory BulkAccountResult.fromJson(Map<String, dynamic> json) =>
      BulkAccountResult(
        created: _integer(json['dibuat']),
        skipped: _integer(json['dilewati']),
        notes: (json['catatan'] as List<dynamic>? ?? const [])
            .whereType<String>()
            .toList(growable: false),
      );

  final int created;
  final int skipped;
  final List<String> notes;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic>? _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;

int _integer(Object? value) => value is num ? value.toInt() : 0;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;

DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value) : null;
