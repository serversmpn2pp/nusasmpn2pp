class StudentPage {
  const StudentPage({
    required this.items,
    required this.counts,
    required this.pagination,
    required this.query,
    required this.status,
  });

  factory StudentPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']) ?? const {};

    return StudentPage(
      items: (json['items'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map(
            (item) => StudentSummary.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
      counts: StudentCounts.fromJson(_map(json['ringkasan']) ?? const {}),
      pagination: StudentPagination.fromJson(
        _map(json['paginasi']) ?? const {},
      ),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
    );
  }

  final List<StudentSummary> items;
  final StudentCounts counts;
  final StudentPagination pagination;
  final String query;
  final String status;

  StudentPage append(StudentPage next) {
    return StudentPage(
      items: List.unmodifiable([...items, ...next.items]),
      counts: next.counts,
      pagination: next.pagination,
      query: next.query,
      status: next.status,
    );
  }
}

class StudentCounts {
  const StudentCounts({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory StudentCounts.fromJson(Map<String, dynamic> json) {
    return StudentCounts(
      total: _integer(json['total']),
      active: _integer(json['aktif']),
      inactive: _integer(json['nonaktif']),
    );
  }

  final int total;
  final int active;
  final int inactive;
}

class StudentPagination {
  const StudentPagination({
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasNextPage,
  });

  factory StudentPagination.fromJson(Map<String, dynamic> json) {
    return StudentPagination(
      page: _integer(json['halaman']),
      lastPage: _integer(json['halaman_terakhir']),
      perPage: _integer(json['per_halaman']),
      total: _integer(json['total']),
      hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
    );
  }

  final int page;
  final int lastPage;
  final int perPage;
  final int total;
  final bool hasNextPage;
}

class StudentSummary {
  const StudentSummary({
    required this.id,
    required this.name,
    required this.active,
    this.nis,
    this.nisn,
    this.gender,
    this.photoUrl,
    this.activeClass,
  });

  factory StudentSummary.fromJson(Map<String, dynamic> json) {
    return StudentSummary(
      id: _integer(json['id']),
      name: json['nama'] as String? ?? '-',
      nis: json['nis'] as String?,
      nisn: json['nisn'] as String?,
      gender: json['jenis_kelamin'] as String?,
      photoUrl: json['foto_url'] as String?,
      active: json['aktif'] as bool? ?? false,
      activeClass: switch (_map(json['kelas_aktif'])) {
        final data? => StudentActiveClass.fromJson(data),
        _ => null,
      },
    );
  }

  final int id;
  final String name;
  final String? nis;
  final String? nisn;
  final String? gender;
  final String? photoUrl;
  final bool active;
  final StudentActiveClass? activeClass;

  String get initials {
    final words = name.trim().split(RegExp(r'\s+'));
    if (words.isEmpty || words.first.isEmpty) {
      return 'SW';
    }

    return words.take(2).map((word) => word[0]).join().toUpperCase();
  }

  String get identityLabel {
    if (nisn?.isNotEmpty == true) {
      return 'NISN $nisn';
    }
    if (nis?.isNotEmpty == true) {
      return 'NIS $nis';
    }

    return 'Identitas belum lengkap';
  }
}

class StudentActiveClass {
  const StudentActiveClass({
    required this.id,
    required this.name,
    this.level,
    this.attendanceNumber,
    this.academicYear,
  });

  factory StudentActiveClass.fromJson(Map<String, dynamic> json) {
    return StudentActiveClass(
      id: _integer(json['id']),
      name: json['nama'] as String? ?? '-',
      level: _nullableInteger(json['tingkat']),
      attendanceNumber: _nullableInteger(json['nomor_absen']),
      academicYear: json['tahun_pelajaran'] as String?,
    );
  }

  final int id;
  final String name;
  final int? level;
  final int? attendanceNumber;
  final String? academicYear;
}

class StudentDetail {
  const StudentDetail({
    required this.summary,
    required this.parents,
    this.nik,
    this.birthPlace,
    this.birthDate,
    this.religion,
    this.familyStatus,
    this.childOrder,
    this.address,
    this.previousSchool,
    this.notes,
  });

  factory StudentDetail.fromJson(Map<String, dynamic> json) {
    return StudentDetail(
      summary: StudentSummary.fromJson(json),
      nik: json['nik'] as String?,
      birthPlace: json['tempat_lahir'] as String?,
      birthDate: DateTime.tryParse(json['tanggal_lahir'] as String? ?? ''),
      religion: json['agama'] as String?,
      familyStatus: json['status_dalam_keluarga'] as String?,
      childOrder: _nullableInteger(json['anak_ke']),
      parents: StudentParents.fromJson(_map(json['orang_tua']) ?? const {}),
      address: json['alamat'] as String?,
      previousSchool: json['sekolah_asal'] as String?,
      notes: json['keterangan'] as String?,
    );
  }

  final StudentSummary summary;
  final String? nik;
  final String? birthPlace;
  final DateTime? birthDate;
  final String? religion;
  final String? familyStatus;
  final int? childOrder;
  final StudentParents parents;
  final String? address;
  final String? previousSchool;
  final String? notes;
}

class StudentParents {
  const StudentParents({
    this.fatherName,
    this.fatherPhone,
    this.fatherOccupation,
    this.motherName,
    this.motherPhone,
    this.motherOccupation,
    this.guardianName,
    this.guardianRelation,
    this.guardianPhone,
    this.primaryAttendanceContact,
  });

  factory StudentParents.fromJson(Map<String, dynamic> json) {
    return StudentParents(
      fatherName: json['nama_ayah'] as String?,
      fatherPhone: json['nomor_wa_ayah'] as String?,
      fatherOccupation: json['pekerjaan_ayah'] as String?,
      motherName: json['nama_ibu'] as String?,
      motherPhone: json['nomor_wa_ibu'] as String?,
      motherOccupation: json['pekerjaan_ibu'] as String?,
      guardianName: json['nama_wali'] as String?,
      guardianRelation: json['hubungan_wali'] as String?,
      guardianPhone: json['nomor_wa_wali'] as String?,
      primaryAttendanceContact: json['kontak_absensi_utama'] as String?,
    );
  }

  final String? fatherName;
  final String? fatherPhone;
  final String? fatherOccupation;
  final String? motherName;
  final String? motherPhone;
  final String? motherOccupation;
  final String? guardianName;
  final String? guardianRelation;
  final String? guardianPhone;
  final String? primaryAttendanceContact;

  bool get hasData => [
    fatherName,
    fatherPhone,
    motherName,
    motherPhone,
    guardianName,
    guardianPhone,
  ].any((value) => value?.isNotEmpty == true);
}

Map<String, dynamic>? _map(Object? value) {
  return value is Map ? Map<String, dynamic>.from(value) : null;
}

int _integer(Object? value) => value is num ? value.toInt() : 0;

int? _nullableInteger(Object? value) => value is num ? value.toInt() : null;
