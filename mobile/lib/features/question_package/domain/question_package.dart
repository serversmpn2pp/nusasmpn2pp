class QuestionPackagePage {
  const QuestionPackagePage({
    required this.summary,
    required this.items,
    required this.references,
    required this.filter,
    required this.pagination,
  });

  factory QuestionPackagePage.fromJson(Map<String, dynamic> json) =>
      QuestionPackagePage(
        summary: QuestionPackageSummary.fromJson(_map(json['ringkasan'])),
        items: _list(json['items'], QuestionPackageSchedule.fromJson),
        references: QuestionPackageReferences.fromJson(_map(json['referensi'])),
        filter: QuestionPackageFilter.fromJson(_map(json['filter'])),
        pagination: QuestionPackagePagination.fromJson(_map(json['paginasi'])),
      );

  final QuestionPackageSummary summary;
  final List<QuestionPackageSchedule> items;
  final QuestionPackageReferences references;
  final QuestionPackageFilter filter;
  final QuestionPackagePagination pagination;

  QuestionPackagePage append(QuestionPackagePage next) => QuestionPackagePage(
    summary: next.summary,
    items: [...items, ...next.items],
    references: next.references,
    filter: next.filter,
    pagination: next.pagination,
  );
}

class QuestionPackageSummary {
  const QuestionPackageSummary({
    required this.total,
    required this.ready,
    required this.draft,
    required this.unbuilt,
  });
  factory QuestionPackageSummary.fromJson(Map<String, dynamic> json) =>
      QuestionPackageSummary(
        total: _integer(json['total']),
        ready: _integer(json['siap']),
        draft: _integer(json['draft']),
        unbuilt: _integer(json['belum_disusun']),
      );
  final int total;
  final int ready;
  final int draft;
  final int unbuilt;
}

class QuestionPackageSchedule {
  const QuestionPackageSchedule({
    required this.id,
    required this.event,
    required this.subject,
    required this.grade,
    required this.classes,
    required this.status,
    required this.statusLabel,
    required this.questionCount,
    required this.totalWeight,
    required this.canManage,
    this.date,
    this.time,
    this.session,
  });

  factory QuestionPackageSchedule.fromJson(Map<String, dynamic> json) =>
      QuestionPackageSchedule(
        id: _integer(json['id']),
        event: QuestionPackageEvent.fromJson(_map(json['kegiatan'])),
        subject: json['mata_pelajaran'] as String? ?? '-',
        grade: _integer(json['tingkat']),
        classes: (json['kelas'] as List? ?? const [])
            .map((item) => item.toString())
            .toList(),
        date: json['tanggal'] as String?,
        time: json['waktu'] as String?,
        session: json['sesi'] as String?,
        status: json['status'] as String? ?? 'belum_disusun',
        statusLabel: json['label_status'] as String? ?? '-',
        questionCount: _integer(json['jumlah_soal']),
        totalWeight: _decimal(json['total_bobot']),
        canManage: json['dapat_kelola'] as bool? ?? false,
      );

  final int id;
  final QuestionPackageEvent event;
  final String subject;
  final int grade;
  final List<String> classes;
  final String? date;
  final String? time;
  final String? session;
  final String status;
  final String statusLabel;
  final int questionCount;
  final double totalWeight;
  final bool canManage;
}

class QuestionPackageEvent {
  const QuestionPackageEvent({
    required this.id,
    required this.name,
    this.type,
    this.academicYear,
    this.semester,
  });
  factory QuestionPackageEvent.fromJson(Map<String, dynamic> json) =>
      QuestionPackageEvent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        type: json['jenis'] as String?,
        academicYear: json['tahun_pelajaran'] as String?,
        semester: json['semester'] as String?,
      );
  final int id;
  final String name;
  final String? type;
  final String? academicYear;
  final String? semester;
}

class QuestionPackageDetail {
  const QuestionPackageDetail({
    required this.schedule,
    required this.questions,
    required this.references,
    required this.access,
    this.package,
  });
  factory QuestionPackageDetail.fromJson(Map<String, dynamic> json) =>
      QuestionPackageDetail(
        schedule: QuestionPackageSchedule.fromJson(_map(json['jadwal'])),
        package: json['paket'] is Map<String, dynamic>
            ? QuestionPackageInfo.fromJson(_map(json['paket']))
            : null,
        questions: _list(json['soal'], PackageQuestion.fromJson),
        references: PackageQuestionReferences.fromJson(_map(json['referensi'])),
        access: QuestionPackageAccess.fromJson(_map(json['hak_akses'])),
      );
  final QuestionPackageSchedule schedule;
  final QuestionPackageInfo? package;
  final List<PackageQuestion> questions;
  final PackageQuestionReferences references;
  final QuestionPackageAccess access;
}

class QuestionPackageInfo {
  const QuestionPackageInfo({
    required this.id,
    required this.code,
    required this.name,
    required this.status,
    required this.statusLabel,
    required this.shuffleQuestions,
    required this.shuffleAnswers,
    required this.durationMinutes,
    required this.minimumScore,
    this.token,
  });
  factory QuestionPackageInfo.fromJson(Map<String, dynamic> json) =>
      QuestionPackageInfo(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        status: json['status'] as String? ?? 'draft',
        statusLabel: json['label_status'] as String? ?? '-',
        shuffleQuestions: json['acak_soal'] as bool? ?? true,
        shuffleAnswers: json['acak_jawaban'] as bool? ?? true,
        token: json['token'] as String?,
        durationMinutes: _integer(json['durasi_menit']),
        minimumScore: _integer(json['kkm']),
      );
  final int id;
  final String code;
  final String name;
  final String status;
  final String statusLabel;
  final bool shuffleQuestions;
  final bool shuffleAnswers;
  final String? token;
  final int durationMinutes;
  final int minimumScore;
}

class PackageQuestion {
  const PackageQuestion({
    required this.id,
    required this.code,
    required this.type,
    required this.typeLabel,
    required this.difficulty,
    required this.difficultyLabel,
    required this.question,
    required this.maximumScore,
    required this.selected,
    required this.weight,
    required this.selectable,
    this.order,
    this.topic,
    this.material,
    this.answer,
    this.imageUrl,
  });
  factory PackageQuestion.fromJson(Map<String, dynamic> json) =>
      PackageQuestion(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        type: json['jenis_soal'] as String? ?? '-',
        typeLabel: json['label_jenis_soal'] as String? ?? '-',
        difficulty: json['tingkat_kesulitan'] as String? ?? '-',
        difficultyLabel: json['label_tingkat_kesulitan'] as String? ?? '-',
        topic: json['topik'] as String?,
        material: json['materi'] as String?,
        question: json['pertanyaan'] as String? ?? '-',
        maximumScore: _decimal(json['skor_maksimal']),
        selected: json['dipilih'] as bool? ?? false,
        weight: _decimal(json['bobot']),
        order: _nullableInteger(json['nomor_urut']),
        selectable: json['dapat_dipilih'] as bool? ?? false,
        answer: json['jawaban']?.toString(),
        imageUrl: json['gambar_url'] as String?,
      );
  final int id;
  final String code;
  final String type;
  final String typeLabel;
  final String difficulty;
  final String difficultyLabel;
  final String? topic;
  final String? material;
  final String question;
  final double maximumScore;
  final bool selected;
  final double weight;
  final int? order;
  final bool selectable;
  final String? answer;
  final String? imageUrl;
}

class QuestionPackageReferences {
  const QuestionPackageReferences({
    required this.events,
    required this.statuses,
  });
  factory QuestionPackageReferences.fromJson(Map<String, dynamic> json) =>
      QuestionPackageReferences(
        events: _list(json['kegiatan'], QuestionPackageEvent.fromJson),
        statuses: _list(json['status'], PackageOption.fromJson),
      );
  final List<QuestionPackageEvent> events;
  final List<PackageOption> statuses;
}

class PackageQuestionReferences {
  const PackageQuestionReferences({
    required this.types,
    required this.difficulties,
  });
  factory PackageQuestionReferences.fromJson(Map<String, dynamic> json) =>
      PackageQuestionReferences(
        types: _list(json['jenis_soal'], PackageOption.fromJson),
        difficulties: _list(json['tingkat_kesulitan'], PackageOption.fromJson),
      );
  final List<PackageOption> types;
  final List<PackageOption> difficulties;
}

class PackageOption {
  const PackageOption({required this.code, required this.label});
  factory PackageOption.fromJson(Map<String, dynamic> json) => PackageOption(
    code: json['kode'] as String? ?? '',
    label: json['label'] as String? ?? '-',
  );
  final String code;
  final String label;
}

class QuestionPackageAccess {
  const QuestionPackageAccess({
    required this.canManage,
    required this.canEdit,
    required this.started,
  });
  factory QuestionPackageAccess.fromJson(Map<String, dynamic> json) =>
      QuestionPackageAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
        canEdit: json['dapat_ubah'] as bool? ?? false,
        started: json['sudah_dikerjakan'] as bool? ?? false,
      );
  final bool canManage;
  final bool canEdit;
  final bool started;
}

class QuestionPackageFilter {
  const QuestionPackageFilter({
    required this.query,
    required this.status,
    this.eventId,
  });
  factory QuestionPackageFilter.fromJson(Map<String, dynamic> json) =>
      QuestionPackageFilter(
        query: json['kata_kunci'] as String? ?? '',
        eventId: _nullableInteger(json['kegiatan_id']),
        status: json['status'] as String? ?? 'semua',
      );
  final String query;
  final int? eventId;
  final String status;
}

class QuestionPackagePagination {
  const QuestionPackagePagination({
    required this.page,
    required this.lastPage,
    required this.hasNextPage,
  });
  factory QuestionPackagePagination.fromJson(Map<String, dynamic> json) =>
      QuestionPackagePagination(
        page: _integer(json['halaman']),
        lastPage: _integer(json['halaman_terakhir']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int lastPage;
  final bool hasNextPage;
}

class QuestionPackagePayload {
  const QuestionPackagePayload({
    required this.action,
    required this.shuffleQuestions,
    required this.shuffleAnswers,
    required this.questions,
  });
  final String action;
  final bool shuffleQuestions;
  final bool shuffleAnswers;
  final List<PackageQuestionPayload> questions;

  Map<String, dynamic> toJson() => {
    'aksi': action,
    'acak_soal': shuffleQuestions,
    'acak_jawaban': shuffleAnswers,
    'soal': questions.map((item) => item.toJson()).toList(),
  };
}

class PackageQuestionPayload {
  const PackageQuestionPayload({required this.id, required this.weight});
  final int id;
  final double weight;
  Map<String, dynamic> toJson() => {'id': id, 'bobot': weight};
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};
List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value.whereType<Map<String, dynamic>>().map(convert).toList()
    : <T>[];
int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};
int? _nullableInteger(Object? value) => value == null ? null : _integer(value);
double _decimal(Object? value) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text) ?? 0,
  _ => 0,
};
