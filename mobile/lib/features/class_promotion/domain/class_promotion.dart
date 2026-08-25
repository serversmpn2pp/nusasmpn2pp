class ClassPromotionPage {
  const ClassPromotionPage({
    required this.academicYears,
    required this.sourceClasses,
    required this.destinationClasses,
    required this.members,
    required this.summary,
    required this.filter,
    required this.ready,
    required this.warnings,
    this.selectedSourceClass,
    this.suggestedDestinationClassId,
  });

  factory ClassPromotionPage.fromJson(Map<String, dynamic> json) =>
      ClassPromotionPage(
        academicYears: _list(
          json['tahun_pelajaran'],
          PromotionAcademicYear.fromJson,
        ),
        sourceClasses: _list(json['kelas_asal'], PromotionClass.fromJson),
        destinationClasses: _list(
          json['kelas_tujuan'],
          PromotionClass.fromJson,
        ),
        selectedSourceClass: json['kelas_asal_dipilih'] is Map<String, dynamic>
            ? PromotionClass.fromJson(
                json['kelas_asal_dipilih'] as Map<String, dynamic>,
              )
            : null,
        members: _list(json['anggota'], PromotionMember.fromJson),
        summary: PromotionSummary.fromJson(_map(json['ringkasan'])),
        filter: PromotionFilter.fromJson(_map(json['filter'])),
        suggestedDestinationClassId: _nullableInteger(
          json['saran_kelas_tujuan_id'],
        ),
        ready: json['siap_diproses'] as bool? ?? false,
        warnings: (json['peringatan'] as List<dynamic>? ?? const [])
            .whereType<String>()
            .toList(growable: false),
      );

  final List<PromotionAcademicYear> academicYears;
  final List<PromotionClass> sourceClasses;
  final List<PromotionClass> destinationClasses;
  final PromotionClass? selectedSourceClass;
  final List<PromotionMember> members;
  final PromotionSummary summary;
  final PromotionFilter filter;
  final int? suggestedDestinationClassId;
  final bool ready;
  final List<String> warnings;
}

class PromotionAcademicYear {
  const PromotionAcademicYear({
    required this.id,
    required this.name,
    required this.active,
    required this.classCount,
    this.startDate,
    this.endDate,
  });

  factory PromotionAcademicYear.fromJson(Map<String, dynamic> json) =>
      PromotionAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        active: json['aktif'] as bool? ?? false,
        classCount: _integer(json['jumlah_kelas']),
        startDate: _date(json['tanggal_mulai']),
        endDate: _date(json['tanggal_selesai']),
      );

  final int id;
  final String name;
  final bool active;
  final int classCount;
  final DateTime? startDate;
  final DateTime? endDate;
}

class PromotionClass {
  const PromotionClass({
    required this.id,
    required this.name,
    required this.grade,
    required this.studentCount,
    required this.active,
    this.capacity,
    this.remainingCapacity,
  });

  factory PromotionClass.fromJson(Map<String, dynamic> json) => PromotionClass(
    id: _integer(json['id']),
    name: json['nama'] as String? ?? '-',
    grade: _integer(json['tingkat']),
    capacity: _nullableInteger(json['kapasitas']),
    studentCount: _integer(json['jumlah_siswa']),
    remainingCapacity: _nullableInteger(json['sisa_kapasitas']),
    active: json['aktif'] as bool? ?? false,
  );

  final int id;
  final String name;
  final int grade;
  final int? capacity;
  final int studentCount;
  final int? remainingCapacity;
  final bool active;

  String get occupancyLabel => capacity == null
      ? '$studentCount siswa · tanpa batas'
      : '$studentCount/$capacity siswa';
}

class PromotionMember {
  const PromotionMember({
    required this.id,
    required this.student,
    required this.initialNote,
    this.attendanceNumber,
    this.currentPlacement,
    this.suggestedDestinationClassId,
  });

  factory PromotionMember.fromJson(Map<String, dynamic> json) =>
      PromotionMember(
        id: _integer(json['id']),
        attendanceNumber: _nullableInteger(json['nomor_absen']),
        student: PromotionStudent.fromJson(_map(json['siswa'])),
        currentPlacement: json['penempatan_tujuan'] is Map<String, dynamic>
            ? ExistingPromotionPlacement.fromJson(
                json['penempatan_tujuan'] as Map<String, dynamic>,
              )
            : null,
        suggestedDestinationClassId: _nullableInteger(
          json['kelas_tujuan_disarankan_id'],
        ),
        initialNote: json['keterangan_awal'] as String? ?? 'Penempatan massal',
      );

  final int id;
  final int? attendanceNumber;
  final PromotionStudent student;
  final ExistingPromotionPlacement? currentPlacement;
  final int? suggestedDestinationClassId;
  final String initialNote;
}

class PromotionStudent {
  const PromotionStudent({
    required this.id,
    required this.name,
    required this.active,
    this.nis,
    this.nisn,
    this.gender,
    this.photoUrl,
  });

  factory PromotionStudent.fromJson(Map<String, dynamic> json) =>
      PromotionStudent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        nis: json['nis'] as String?,
        nisn: json['nisn'] as String?,
        gender: json['jenis_kelamin'] as String?,
        photoUrl: json['foto_url'] as String?,
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String name;
  final String? nis;
  final String? nisn;
  final String? gender;
  final String? photoUrl;
  final bool active;

  String get initials {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .take(2);
    final value = parts.map((part) => part[0]).join();
    return value.isEmpty ? 'SW' : value.toUpperCase();
  }
}

class ExistingPromotionPlacement {
  const ExistingPromotionPlacement({
    required this.membershipId,
    required this.schoolClass,
  });

  factory ExistingPromotionPlacement.fromJson(Map<String, dynamic> json) =>
      ExistingPromotionPlacement(
        membershipId: _integer(json['anggota_kelas_id']),
        schoolClass: PromotionClass.fromJson(_map(json['kelas'])),
      );

  final int membershipId;
  final PromotionClass schoolClass;
}

class PromotionSummary {
  const PromotionSummary({
    required this.sourceStudents,
    required this.alreadyPlaced,
    required this.notPlaced,
    required this.destinationClasses,
  });

  factory PromotionSummary.fromJson(Map<String, dynamic> json) =>
      PromotionSummary(
        sourceStudents: _integer(json['jumlah_siswa_asal']),
        alreadyPlaced: _integer(json['sudah_ditempatkan']),
        notPlaced: _integer(json['belum_ditempatkan']),
        destinationClasses: _integer(json['jumlah_kelas_tujuan']),
      );

  final int sourceStudents;
  final int alreadyPlaced;
  final int notPlaced;
  final int destinationClasses;
}

class PromotionFilter {
  const PromotionFilter({
    this.sourceYearId,
    this.destinationYearId,
    this.sourceClassId,
  });

  factory PromotionFilter.fromJson(Map<String, dynamic> json) =>
      PromotionFilter(
        sourceYearId: _nullableInteger(json['tahun_asal_id']),
        destinationYearId: _nullableInteger(json['tahun_tujuan_id']),
        sourceClassId: _nullableInteger(json['kelas_asal_id']),
      );

  final int? sourceYearId;
  final int? destinationYearId;
  final int? sourceClassId;
}

class PromotionAssignment {
  const PromotionAssignment({
    required this.memberId,
    required this.destinationClassId,
    required this.note,
  });

  final int memberId;
  final int? destinationClassId;
  final String note;

  Map<String, dynamic> toJson() => {
    'anggota_kelas_id': memberId,
    'kelas_tujuan_id': destinationClassId,
    'keterangan': note,
  };
}

class PromotionResult {
  const PromotionResult({
    required this.processed,
    required this.placed,
    required this.skipped,
    required this.notes,
  });

  factory PromotionResult.fromJson(Map<String, dynamic> json) =>
      PromotionResult(
        processed: _integer(json['diproses']),
        placed: _integer(json['ditempatkan']),
        skipped: _integer(json['dilewati']),
        notes: (json['catatan'] as List<dynamic>? ?? const [])
            .whereType<String>()
            .toList(growable: false),
      );

  final int processed;
  final int placed;
  final int skipped;
  final List<String> notes;
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
