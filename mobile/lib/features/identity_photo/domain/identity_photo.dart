import 'dart:typed_data';

class IdentityPhotoPage {
  const IdentityPhotoPage({
    required this.items,
    required this.summary,
    required this.academicYears,
    required this.classes,
    required this.employeeTypes,
    required this.pagination,
    required this.tab,
    required this.photoStatus,
    required this.employeeStatus,
    required this.employeeType,
    required this.query,
    required this.canManageStudents,
    required this.canManageEmployees,
    this.academicYearId,
    this.classId,
  });

  factory IdentityPhotoPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final access = _map(json['hak_akses']);
    return IdentityPhotoPage(
      items: _list(json['items'], IdentityPhotoPerson.fromJson),
      summary: IdentityPhotoSummary.fromJson(_map(json['ringkasan'])),
      academicYears: _list(
        json['tahun_pelajaran'],
        IdentityPhotoAcademicYear.fromJson,
      ),
      classes: _list(json['kelas'], IdentityPhotoClass.fromJson),
      employeeTypes: (json['jenis_pegawai'] as List<dynamic>? ?? const [])
          .whereType<String>()
          .toList(growable: false),
      pagination: IdentityPhotoPagination.fromJson(_map(json['paginasi'])),
      tab: json['tab'] as String? ?? 'siswa',
      academicYearId: _nullableInteger(filter['tahun_pelajaran_id']),
      classId: _nullableInteger(filter['kelas_id']),
      photoStatus: filter['status_foto'] as String? ?? 'semua',
      employeeStatus: filter['status_pegawai'] as String? ?? 'aktif',
      employeeType: filter['jenis_pegawai'] as String? ?? '',
      query: filter['cari'] as String? ?? '',
      canManageStudents: access['dapat_kelola_siswa'] as bool? ?? false,
      canManageEmployees: access['dapat_kelola_pegawai'] as bool? ?? false,
    );
  }

  final List<IdentityPhotoPerson> items;
  final IdentityPhotoSummary summary;
  final List<IdentityPhotoAcademicYear> academicYears;
  final List<IdentityPhotoClass> classes;
  final List<String> employeeTypes;
  final IdentityPhotoPagination pagination;
  final String tab;
  final int? academicYearId;
  final int? classId;
  final String photoStatus;
  final String employeeStatus;
  final String employeeType;
  final String query;
  final bool canManageStudents;
  final bool canManageEmployees;

  IdentityPhotoPage append(IdentityPhotoPage next) => IdentityPhotoPage(
    items: [...items, ...next.items],
    summary: next.summary,
    academicYears: next.academicYears,
    classes: next.classes,
    employeeTypes: next.employeeTypes,
    pagination: next.pagination,
    tab: next.tab,
    academicYearId: next.academicYearId,
    classId: next.classId,
    photoStatus: next.photoStatus,
    employeeStatus: next.employeeStatus,
    employeeType: next.employeeType,
    query: next.query,
    canManageStudents: next.canManageStudents,
    canManageEmployees: next.canManageEmployees,
  );
}

class IdentityPhotoPerson {
  const IdentityPhotoPerson({
    required this.id,
    required this.name,
    required this.identity,
    required this.detail,
    required this.hasPhoto,
    required this.active,
    this.photoUrl,
    this.gender,
  });

  factory IdentityPhotoPerson.fromJson(Map<String, dynamic> json) =>
      IdentityPhotoPerson(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        identity: json['identitas'] as String? ?? '-',
        detail: json['detail'] as String? ?? '',
        photoUrl: json['foto_url'] as String?,
        hasPhoto: json['punya_foto'] as bool? ?? false,
        gender: json['jenis_kelamin'] as String?,
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final String identity;
  final String detail;
  final String? photoUrl;
  final bool hasPhoto;
  final String? gender;
  final bool active;
}

class IdentityPhotoSummary {
  const IdentityPhotoSummary({
    required this.total,
    required this.withPhoto,
    required this.withoutPhoto,
  });

  factory IdentityPhotoSummary.fromJson(Map<String, dynamic> json) =>
      IdentityPhotoSummary(
        total: _integer(json['total']),
        withPhoto: _integer(json['sudah']),
        withoutPhoto: _integer(json['belum']),
      );

  final int total;
  final int withPhoto;
  final int withoutPhoto;
}

class IdentityPhotoAcademicYear {
  const IdentityPhotoAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory IdentityPhotoAcademicYear.fromJson(Map<String, dynamic> json) =>
      IdentityPhotoAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;
}

class IdentityPhotoClass {
  const IdentityPhotoClass({required this.id, required this.name, this.level});

  factory IdentityPhotoClass.fromJson(Map<String, dynamic> json) =>
      IdentityPhotoClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        level: _nullableInteger(json['tingkat']),
      );

  final int id;
  final String name;
  final int? level;
}

class IdentityPhotoPagination {
  const IdentityPhotoPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory IdentityPhotoPagination.fromJson(Map<String, dynamic> json) =>
      IdentityPhotoPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

enum IdentityPhotoSource { camera, gallery }

class IdentityPhotoPickedFile {
  const IdentityPhotoPickedFile({required this.name, required this.bytes});

  final String name;
  final Uint8List bytes;
}

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

int _integer(Object? value) => value is num ? value.toInt() : 0;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
