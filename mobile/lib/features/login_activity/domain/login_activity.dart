class LoginActivityPage {
  const LoginActivityPage({
    required this.users,
    required this.attempts,
    required this.summary,
    required this.filter,
    required this.pagination,
  });

  factory LoginActivityPage.fromJson(Map<String, dynamic> json) {
    final filter = LoginActivityFilter.fromJson(_map(json['filter']));
    final items = json['items'] as List<dynamic>? ?? const [];
    return LoginActivityPage(
      users: filter.view == 'pengguna'
          ? items
                .whereType<Map<String, dynamic>>()
                .map(LoginActivityUser.fromJson)
                .toList(growable: false)
          : const [],
      attempts: filter.view == 'riwayat'
          ? items
                .whereType<Map<String, dynamic>>()
                .map(LoginAttempt.fromJson)
                .toList(growable: false)
          : const [],
      summary: LoginActivitySummary.fromJson(_map(json['ringkasan'])),
      filter: filter,
      pagination: LoginActivityPagination.fromJson(_map(json['paginasi'])),
    );
  }

  final List<LoginActivityUser> users;
  final List<LoginAttempt> attempts;
  final LoginActivitySummary summary;
  final LoginActivityFilter filter;
  final LoginActivityPagination pagination;

  LoginActivityPage append(LoginActivityPage next) => LoginActivityPage(
    users: [...users, ...next.users],
    attempts: [...attempts, ...next.attempts],
    summary: next.summary,
    filter: next.filter,
    pagination: next.pagination,
  );
}

class LoginAttemptDetail {
  const LoginAttemptDetail({required this.attempt});

  factory LoginAttemptDetail.fromJson(Map<String, dynamic> json) =>
      LoginAttemptDetail(attempt: LoginAttempt.fromJson(_map(json['riwayat'])));

  final LoginAttempt attempt;
}

class LoginActivityUser {
  const LoginActivityUser({
    required this.id,
    required this.name,
    required this.username,
    required this.accountType,
    required this.roles,
    required this.active,
    required this.successCount,
    required this.failureCount,
    this.lastLoginAt,
    this.lastDevice,
  });

  factory LoginActivityUser.fromJson(Map<String, dynamic> json) =>
      LoginActivityUser(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        username: json['username'] as String? ?? '-',
        accountType: LoginAccountType.fromJson(_map(json['jenis_akun'])),
        roles: _list(json['peran'], LoginActivityRole.fromJson),
        active: json['aktif'] as bool? ?? false,
        lastLoginAt: _date(json['terakhir_login_pada']),
        lastDevice: json['perangkat_terakhir'] as String?,
        successCount: _integer(json['jumlah_login_berhasil']),
        failureCount: _integer(json['jumlah_login_gagal']),
      );

  final int id;
  final String name;
  final String username;
  final LoginAccountType accountType;
  final List<LoginActivityRole> roles;
  final bool active;
  final DateTime? lastLoginAt;
  final String? lastDevice;
  final int successCount;
  final int failureCount;

  String get initials => _initials(name);
  String get roleLabel =>
      roles.isEmpty ? 'Tanpa role' : roles.map((role) => role.name).join(', ');
}

class LoginAttempt {
  const LoginAttempt({
    required this.id,
    required this.username,
    required this.success,
    required this.device,
    this.ipAddress,
    this.time,
    this.user,
  });

  factory LoginAttempt.fromJson(Map<String, dynamic> json) => LoginAttempt(
    id: _integer(json['id']),
    username: json['username'] as String? ?? '-',
    success: json['berhasil'] as bool? ?? false,
    ipAddress: json['alamat_ip'] as String?,
    device: LoginDevice.fromJson(_map(json['perangkat'])),
    time: _date(json['waktu']),
    user: json['pengguna'] is Map<String, dynamic>
        ? LoginAttemptUser.fromJson(json['pengguna'] as Map<String, dynamic>)
        : null,
  );

  final int id;
  final String username;
  final bool success;
  final String? ipAddress;
  final LoginDevice device;
  final DateTime? time;
  final LoginAttemptUser? user;

  String get displayName => user?.name ?? 'Akun tidak ditemukan';
  String get initials => _initials(user?.name ?? username);
}

class LoginAttemptUser {
  const LoginAttemptUser({
    required this.id,
    required this.name,
    required this.username,
    required this.accountType,
    required this.roles,
    required this.active,
  });

  factory LoginAttemptUser.fromJson(Map<String, dynamic> json) =>
      LoginAttemptUser(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        username: json['username'] as String? ?? '-',
        accountType: LoginAccountType.fromJson(_map(json['jenis_akun'])),
        roles: _list(json['peran'], LoginActivityRole.fromJson),
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final String username;
  final LoginAccountType accountType;
  final List<LoginActivityRole> roles;
  final bool active;

  String get roleLabel =>
      roles.isEmpty ? 'Tanpa role' : roles.map((role) => role.name).join(', ');
}

class LoginAccountType {
  const LoginAccountType({required this.code, required this.label});

  factory LoginAccountType.fromJson(Map<String, dynamic> json) =>
      LoginAccountType(
        code: json['kode'] as String? ?? 'lainnya',
        label: json['label'] as String? ?? 'Akun lainnya',
      );

  final String code;
  final String label;
}

class LoginActivityRole {
  const LoginActivityRole({required this.code, required this.name, this.id});

  factory LoginActivityRole.fromJson(Map<String, dynamic> json) =>
      LoginActivityRole(
        id: _nullableInteger(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
      );

  final int? id;
  final String code;
  final String name;
}

class LoginDevice {
  const LoginDevice({required this.code, required this.label, this.userAgent});

  factory LoginDevice.fromJson(Map<String, dynamic> json) => LoginDevice(
    code: json['kode'] as String? ?? 'lainnya',
    label: json['label'] as String? ?? 'Perangkat tidak diketahui',
    userAgent: json['user_agent'] as String?,
  );

  final String code;
  final String label;
  final String? userAgent;
}

class LoginActivitySummary {
  const LoginActivitySummary({
    required this.accounts,
    required this.loginsToday,
    required this.neverLoggedIn,
    required this.failuresToday,
  });

  factory LoginActivitySummary.fromJson(Map<String, dynamic> json) =>
      LoginActivitySummary(
        accounts: _integer(json['jumlah_akun']),
        loginsToday: _integer(json['login_hari_ini']),
        neverLoggedIn: _integer(json['belum_pernah_login']),
        failuresToday: _integer(json['gagal_hari_ini']),
      );

  final int accounts;
  final int loginsToday;
  final int neverLoggedIn;
  final int failuresToday;
}

class LoginActivityFilter {
  const LoginActivityFilter({
    required this.view,
    required this.query,
    required this.accountType,
    required this.loginStatus,
    required this.attemptStatus,
    required this.device,
    this.startDate,
    this.endDate,
  });

  factory LoginActivityFilter.fromJson(Map<String, dynamic> json) =>
      LoginActivityFilter(
        view: json['tampilan'] as String? ?? 'pengguna',
        query: json['cari'] as String? ?? '',
        accountType: json['jenis_akun'] as String? ?? 'semua',
        loginStatus: json['status_login'] as String? ?? 'semua',
        attemptStatus: json['status_percobaan'] as String? ?? 'semua',
        device: json['perangkat'] as String? ?? 'semua',
        startDate: json['tanggal_mulai'] as String?,
        endDate: json['tanggal_selesai'] as String?,
      );

  final String view;
  final String query;
  final String accountType;
  final String loginStatus;
  final String attemptStatus;
  final String device;
  final String? startDate;
  final String? endDate;
}

class LoginActivityPagination {
  const LoginActivityPagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory LoginActivityPagination.fromJson(Map<String, dynamic> json) =>
      LoginActivityPagination(
        page: _integer(json['halaman'], fallback: 1),
        lastPage: _integer(json['halaman_terakhir'], fallback: 1),
        perPage: _integer(json['per_halaman'], fallback: 15),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int lastPage;
  final int perPage;
  final int total;
  final bool hasNextPage;
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map<String, dynamic> ? value : const {};

List<T> _list<T>(dynamic value, T Function(Map<String, dynamic>) parser) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(parser)
        .toList(growable: false);

int _integer(dynamic value, {int fallback = 0}) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? fallback,
  _ => fallback,
};

int? _nullableInteger(dynamic value) => value == null ? null : _integer(value);

DateTime? _date(dynamic value) =>
    value is String ? DateTime.tryParse(value) : null;

String _initials(String value) {
  final parts = value
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .take(2);
  final result = parts.map((part) => part[0]).join();
  return result.isEmpty ? 'NA' : result.toUpperCase();
}
