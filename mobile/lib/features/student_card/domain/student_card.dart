class StudentCardPage {
  const StudentCardPage({
    required this.items,
    required this.summary,
    required this.academicYears,
    required this.classes,
    required this.pagination,
    required this.query,
    required this.cardSize,
    required this.canPrint,
    required this.canManagePhoto,
    this.selectedAcademicYearId,
    this.selectedClassId,
  });

  factory StudentCardPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    final access = _map(json['hak_akses']);
    return StudentCardPage(
      items: _list(json['items'], StudentCardPerson.fromJson),
      summary: StudentCardSummary.fromJson(_map(json['ringkasan'])),
      academicYears: _list(
        json['tahun_pelajaran'],
        StudentCardAcademicYear.fromJson,
      ),
      classes: _list(json['kelas'], StudentCardClass.fromJson),
      pagination: StudentCardPagination.fromJson(_map(json['paginasi'])),
      selectedAcademicYearId: _nullableInteger(filter['tahun_pelajaran_id']),
      selectedClassId: _nullableInteger(filter['kelas_id']),
      query: filter['cari'] as String? ?? '',
      cardSize: StudentCardSize.fromJson(_map(json['ukuran_kartu'])),
      canPrint: access['dapat_cetak'] as bool? ?? false,
      canManagePhoto: access['dapat_kelola_foto'] as bool? ?? false,
    );
  }

  final List<StudentCardPerson> items;
  final StudentCardSummary summary;
  final List<StudentCardAcademicYear> academicYears;
  final List<StudentCardClass> classes;
  final StudentCardPagination pagination;
  final int? selectedAcademicYearId;
  final int? selectedClassId;
  final String query;
  final StudentCardSize cardSize;
  final bool canPrint;
  final bool canManagePhoto;

  StudentCardPage append(StudentCardPage next) => StudentCardPage(
    items: [...items, ...next.items],
    summary: next.summary,
    academicYears: next.academicYears,
    classes: next.classes,
    pagination: next.pagination,
    selectedAcademicYearId: next.selectedAcademicYearId,
    selectedClassId: next.selectedClassId,
    query: next.query,
    cardSize: next.cardSize,
    canPrint: next.canPrint,
    canManagePhoto: next.canManagePhoto,
  );
}

class StudentCardPerson {
  const StudentCardPerson({
    required this.membershipId,
    required this.studentId,
    required this.name,
    required this.birthLabel,
    required this.className,
    required this.academicYear,
    required this.hasPhoto,
    required this.canMakeQr,
    this.nis,
    this.nisn,
    this.gender,
    this.rollNumber,
    this.photoUrl,
    this.qrData,
  });

  factory StudentCardPerson.fromJson(Map<String, dynamic> json) =>
      StudentCardPerson(
        membershipId: _integer(json['anggota_kelas_id']),
        studentId: _integer(json['siswa_id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
        gender: json['jenis_kelamin'] as String?,
        birthLabel: json['tempat_tanggal_lahir'] as String? ?? '-',
        className: json['kelas'] as String? ?? '-',
        academicYear: json['tahun_pelajaran'] as String? ?? '-',
        rollNumber: _nullableInteger(json['nomor_absen']),
        photoUrl: json['foto_url'] as String?,
        hasPhoto: json['punya_foto'] as bool? ?? false,
        qrData: json['qr_data'] as String?,
        canMakeQr: json['qr_bisa_dibuat'] as bool? ?? false,
      );

  final int membershipId;
  final int studentId;
  final String name;
  final String? nis;
  final String? nisn;
  final String? gender;
  final String birthLabel;
  final String className;
  final String academicYear;
  final int? rollNumber;
  final String? photoUrl;
  final bool hasPhoto;
  final String? qrData;
  final bool canMakeQr;

  String get nisnLabel =>
      'NISN ${nisn?.trim().isNotEmpty == true ? nisn : '-'}';

  String get initials {
    final words = name.trim().split(RegExp(r'\s+'));
    return words
        .where((word) => word.isNotEmpty)
        .take(2)
        .map((word) => word[0].toUpperCase())
        .join();
  }
}

class StudentCardSummary {
  const StudentCardSummary({
    required this.total,
    required this.qrReady,
    required this.withPhoto,
  });

  factory StudentCardSummary.fromJson(Map<String, dynamic> json) =>
      StudentCardSummary(
        total: _integer(json['total']),
        qrReady: _integer(json['siap_qr']),
        withPhoto: _integer(json['dengan_foto']),
      );

  final int total;
  final int qrReady;
  final int withPhoto;
}

class StudentCardAcademicYear {
  const StudentCardAcademicYear({
    required this.id,
    required this.name,
    required this.active,
  });

  factory StudentCardAcademicYear.fromJson(Map<String, dynamic> json) =>
      StudentCardAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final bool active;
}

class StudentCardClass {
  const StudentCardClass({
    required this.id,
    required this.name,
    required this.active,
    this.level,
  });

  factory StudentCardClass.fromJson(Map<String, dynamic> json) =>
      StudentCardClass(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        level: _nullableInteger(json['tingkat']),
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final int? level;
  final bool active;
}

class StudentCardPagination {
  const StudentCardPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory StudentCardPagination.fromJson(Map<String, dynamic> json) =>
      StudentCardPagination(
        page: _integer(json['halaman'], fallback: 1),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class StudentCardSize {
  const StudentCardSize({
    required this.widthMillimeter,
    required this.heightMillimeter,
  });

  factory StudentCardSize.fromJson(Map<String, dynamic> json) =>
      StudentCardSize(
        widthMillimeter: _double(json['lebar_mm'], fallback: 53.98),
        heightMillimeter: _double(json['tinggi_mm'], fallback: 85.6),
      );

  final double widthMillimeter;
  final double heightMillimeter;

  double get aspectRatio => widthMillimeter / heightMillimeter;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(convert)
        .toList(growable: false);

int _integer(Object? value, {int fallback = 0}) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? fallback,
  _ => fallback,
};

int? _nullableInteger(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text),
  _ => null,
};

double _double(Object? value, {required double fallback}) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text) ?? fallback,
  _ => fallback,
};
