class ClassAssessmentPage {
  const ClassAssessmentPage({
    required this.summary,
    required this.items,
    required this.references,
    required this.filter,
    required this.pagination,
  });

  factory ClassAssessmentPage.fromJson(Map<String, dynamic> json) =>
      ClassAssessmentPage(
        summary: ClassAssessmentSummary.fromJson(_map(json['ringkasan'])),
        items: _list(json['items'], ClassAssessment.fromJson),
        references: ClassAssessmentReferences.fromJson(_map(json['referensi'])),
        filter: ClassAssessmentFilter.fromJson(_map(json['filter'])),
        pagination: ClassAssessmentPagination.fromJson(_map(json['paginasi'])),
      );

  final ClassAssessmentSummary summary;
  final List<ClassAssessment> items;
  final ClassAssessmentReferences references;
  final ClassAssessmentFilter filter;
  final ClassAssessmentPagination pagination;

  ClassAssessmentPage append(ClassAssessmentPage next) => ClassAssessmentPage(
    summary: next.summary,
    items: [...items, ...next.items],
    references: next.references,
    filter: next.filter,
    pagination: next.pagination,
  );
}

class ClassAssessmentSummary {
  const ClassAssessmentSummary({
    required this.total,
    required this.draft,
    required this.scheduled,
    required this.ongoing,
  });
  factory ClassAssessmentSummary.fromJson(Map<String, dynamic> json) =>
      ClassAssessmentSummary(
        total: _integer(json['total']),
        draft: _integer(json['draft']),
        scheduled: _integer(json['terjadwal']),
        ongoing: _integer(json['berlangsung']),
      );
  final int total;
  final int draft;
  final int scheduled;
  final int ongoing;
}

class ClassAssessment {
  const ClassAssessment({
    required this.id,
    required this.name,
    required this.subject,
    required this.semester,
    required this.grade,
    required this.durationMinutes,
    required this.targetQuestions,
    required this.questionCount,
    required this.participantCount,
    required this.classes,
    required this.status,
    required this.statusLabel,
    required this.questionsReady,
    this.academicYear,
    this.startsAt,
    this.endsAt,
  });

  factory ClassAssessment.fromJson(Map<String, dynamic> json) =>
      ClassAssessment(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        subject: json['mata_pelajaran'] as String? ?? '-',
        academicYear: json['tahun_pelajaran'] as String?,
        semester: json['semester'] as String? ?? '-',
        grade: _integer(json['tingkat']),
        startsAt: _date(json['tanggal_mulai']),
        endsAt: _date(json['tanggal_selesai']),
        durationMinutes: _integer(json['durasi_menit']),
        targetQuestions: _integer(json['target_soal']),
        questionCount: _integer(json['jumlah_soal']),
        participantCount: _integer(json['jumlah_peserta']),
        classes: (json['kelas'] as List? ?? const [])
            .map((item) => item.toString())
            .toList(),
        status: json['status'] as String? ?? 'draft',
        statusLabel: json['label_status'] as String? ?? '-',
        questionsReady: json['siap_soal'] as bool? ?? false,
      );

  final int id;
  final String name;
  final String subject;
  final String? academicYear;
  final String semester;
  final int grade;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final int durationMinutes;
  final int targetQuestions;
  final int questionCount;
  final int participantCount;
  final List<String> classes;
  final String status;
  final String statusLabel;
  final bool questionsReady;
}

class ClassAssessmentDetail {
  const ClassAssessmentDetail({
    required this.assessment,
    required this.code,
    required this.minimumScore,
    required this.shuffleQuestions,
    required this.shuffleAnswers,
    required this.singleDevice,
    required this.detectTabChange,
    required this.showResult,
    required this.classes,
    required this.references,
    required this.access,
    this.instructions,
    this.creator,
    this.teachingGroup,
  });

  factory ClassAssessmentDetail.fromJson(Map<String, dynamic> json) =>
      ClassAssessmentDetail(
        assessment: ClassAssessment.fromJson(json),
        code: json['kode'] as String? ?? '-',
        minimumScore: _integer(json['kkm']),
        shuffleQuestions: json['acak_soal'] as bool? ?? false,
        shuffleAnswers: json['acak_jawaban'] as bool? ?? false,
        singleDevice: json['batasi_satu_perangkat'] as bool? ?? false,
        detectTabChange: json['deteksi_pindah_tab'] as bool? ?? false,
        showResult: json['tampilkan_hasil'] as bool? ?? false,
        instructions: json['petunjuk'] as String?,
        creator: json['dibuat_oleh'] as String?,
        teachingGroup: json['kelompok_pengajaran'] as String?,
        classes: _list(json['kelas_tujuan'], AssessmentTargetClass.fromJson),
        references: ClassAssessmentReferences.fromJson(_map(json['referensi'])),
        access: ClassAssessmentAccess.fromJson(_map(json['hak_akses'])),
      );

  final ClassAssessment assessment;
  final String code;
  final int minimumScore;
  final bool shuffleQuestions;
  final bool shuffleAnswers;
  final bool singleDevice;
  final bool detectTabChange;
  final bool showResult;
  final String? instructions;
  final String? creator;
  final String? teachingGroup;
  final List<AssessmentTargetClass> classes;
  final ClassAssessmentReferences references;
  final ClassAssessmentAccess access;
}

class AssessmentTargetClass {
  const AssessmentTargetClass({
    required this.classId,
    required this.name,
    required this.participantCount,
    this.componentId,
    this.component,
  });
  factory AssessmentTargetClass.fromJson(Map<String, dynamic> json) =>
      AssessmentTargetClass(
        classId: _integer(json['kelas_id']),
        name: json['nama'] as String? ?? '-',
        componentId: _nullableInteger(json['komponen_nilai_id']),
        component: json['komponen_nilai'] as String?,
        participantCount: _integer(json['jumlah_peserta']),
      );
  final int classId;
  final String name;
  final int? componentId;
  final String? component;
  final int participantCount;
}

class ClassAssessmentReferences {
  const ClassAssessmentReferences({
    required this.academicYear,
    required this.teachingGroups,
    required this.statuses,
  });
  factory ClassAssessmentReferences.fromJson(Map<String, dynamic> json) =>
      ClassAssessmentReferences(
        academicYear: AssessmentAcademicYear.fromJson(
          _map(json['tahun_pelajaran']),
        ),
        teachingGroups: _list(
          json['kelompok_pengajaran'],
          AssessmentTeachingGroup.fromJson,
        ),
        statuses: _list(json['status'], AssessmentOption.fromJson),
      );
  final AssessmentAcademicYear academicYear;
  final List<AssessmentTeachingGroup> teachingGroups;
  final List<AssessmentOption> statuses;
}

class AssessmentAcademicYear {
  const AssessmentAcademicYear({required this.id, required this.name});
  factory AssessmentAcademicYear.fromJson(Map<String, dynamic> json) =>
      AssessmentAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );
  final int id;
  final String name;
}

class AssessmentTeachingGroup {
  const AssessmentTeachingGroup({
    required this.key,
    required this.subjectId,
    required this.subject,
    required this.grade,
    required this.minimumScore,
    required this.label,
    required this.classes,
    this.teacher,
  });
  factory AssessmentTeachingGroup.fromJson(Map<String, dynamic> json) =>
      AssessmentTeachingGroup(
        key: json['key'] as String? ?? '',
        subjectId: _integer(json['mata_pelajaran_id']),
        subject: json['mata_pelajaran'] as String? ?? '-',
        teacher: json['pegawai'] as String?,
        grade: _integer(json['tingkat']),
        minimumScore: _integer(json['kkm']),
        label: json['label'] as String? ?? '-',
        classes: _list(json['kelas'], AssessmentClassOption.fromJson),
      );
  final String key;
  final int subjectId;
  final String subject;
  final String? teacher;
  final int grade;
  final int minimumScore;
  final String label;
  final List<AssessmentClassOption> classes;
}

class AssessmentClassOption {
  const AssessmentClassOption({
    required this.id,
    required this.name,
    required this.assignmentId,
    required this.components,
  });
  factory AssessmentClassOption.fromJson(Map<String, dynamic> json) =>
      AssessmentClassOption(
        id: _integer(json['kelas_id']),
        name: json['nama'] as String? ?? '-',
        assignmentId: _integer(json['guru_mata_pelajaran_id']),
        components: _list(json['komponen'], AssessmentGradeComponent.fromJson),
      );
  final int id;
  final String name;
  final int assignmentId;
  final List<AssessmentGradeComponent> components;
}

class AssessmentGradeComponent {
  const AssessmentGradeComponent({
    required this.id,
    required this.name,
    required this.semester,
  });
  factory AssessmentGradeComponent.fromJson(Map<String, dynamic> json) =>
      AssessmentGradeComponent(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
        semester: json['semester'] as String? ?? '-',
      );
  final int id;
  final String name;
  final String semester;
}

class AssessmentOption {
  const AssessmentOption({required this.code, required this.label});
  factory AssessmentOption.fromJson(Map<String, dynamic> json) =>
      AssessmentOption(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String code;
  final String label;
}

class ClassAssessmentAccess {
  const ClassAssessmentAccess({
    required this.canManage,
    required this.canDeactivate,
  });
  factory ClassAssessmentAccess.fromJson(Map<String, dynamic> json) =>
      ClassAssessmentAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
        canDeactivate: json['dapat_nonaktifkan'] as bool? ?? false,
      );
  final bool canManage;
  final bool canDeactivate;
}

class ClassAssessmentFilter {
  const ClassAssessmentFilter({required this.query, required this.status});
  factory ClassAssessmentFilter.fromJson(Map<String, dynamic> json) =>
      ClassAssessmentFilter(
        query: json['kata_kunci'] as String? ?? '',
        status: json['status'] as String? ?? 'semua',
      );
  final String query;
  final String status;
}

class ClassAssessmentPagination {
  const ClassAssessmentPagination({
    required this.page,
    required this.lastPage,
    required this.hasNextPage,
  });
  factory ClassAssessmentPagination.fromJson(Map<String, dynamic> json) =>
      ClassAssessmentPagination(
        page: _integer(json['halaman']),
        lastPage: _integer(json['halaman_terakhir']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );
  final int page;
  final int lastPage;
  final bool hasNextPage;
}

class ClassAssessmentPayload {
  const ClassAssessmentPayload({
    required this.teachingGroup,
    required this.name,
    required this.semester,
    required this.startsAt,
    required this.endsAt,
    required this.durationMinutes,
    required this.questionCount,
    required this.status,
    required this.shuffleQuestions,
    required this.shuffleAnswers,
    required this.singleDevice,
    required this.detectTabChange,
    required this.showResult,
    required this.classes,
    this.instructions,
  });
  final String teachingGroup;
  final String name;
  final String semester;
  final String startsAt;
  final String endsAt;
  final int durationMinutes;
  final int questionCount;
  final String status;
  final bool shuffleQuestions;
  final bool shuffleAnswers;
  final bool singleDevice;
  final bool detectTabChange;
  final bool showResult;
  final String? instructions;
  final List<AssessmentClassPayload> classes;

  Map<String, dynamic> toJson() => {
    'kelompok_pengajaran': teachingGroup,
    'nama': name,
    'semester': semester,
    'tanggal_mulai': startsAt,
    'tanggal_selesai': endsAt,
    'durasi_menit': durationMinutes,
    'jumlah_soal': questionCount,
    'status': status,
    'acak_soal': shuffleQuestions,
    'acak_jawaban': shuffleAnswers,
    'batasi_satu_perangkat': singleDevice,
    'deteksi_pindah_tab': detectTabChange,
    'tampilkan_hasil': showResult,
    'petunjuk': instructions,
    'kelas_peserta': classes.map((item) => item.toJson()).toList(),
  };
}

class AssessmentClassPayload {
  const AssessmentClassPayload({
    required this.classId,
    required this.componentId,
  });
  final int classId;
  final String componentId;
  Map<String, dynamic> toJson() => {
    'kelas_id': classId,
    'komponen_nilai_id': componentId,
  };
}

class AssessmentQuestions {
  const AssessmentQuestions({
    required this.assessment,
    required this.questions,
    required this.types,
    required this.difficulties,
    required this.canEdit,
  });
  factory AssessmentQuestions.fromJson(Map<String, dynamic> json) =>
      AssessmentQuestions(
        assessment: ClassAssessment.fromJson(_map(json['asesmen'])),
        questions: _list(json['soal'], AssessmentQuestion.fromJson),
        types: _list(
          _map(json['referensi'])['jenis_soal'],
          AssessmentOption.fromJson,
        ),
        difficulties: _list(
          _map(json['referensi'])['tingkat_kesulitan'],
          AssessmentOption.fromJson,
        ),
        canEdit: _map(json['hak_akses'])['dapat_ubah'] as bool? ?? false,
      );
  final ClassAssessment assessment;
  final List<AssessmentQuestion> questions;
  final List<AssessmentOption> types;
  final List<AssessmentOption> difficulties;
  final bool canEdit;
}

class AssessmentQuestion {
  const AssessmentQuestion({
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
    this.topic,
    this.material,
    this.order,
    this.imageUrl,
  });
  factory AssessmentQuestion.fromJson(Map<String, dynamic> json) =>
      AssessmentQuestion(
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
  final String? imageUrl;
}

class AssessmentQuestionPayload {
  const AssessmentQuestionPayload({required this.id, required this.weight});
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
DateTime? _date(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
