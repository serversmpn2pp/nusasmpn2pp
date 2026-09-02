import 'dart:typed_data';

class QuestionBankPage {
  const QuestionBankPage({
    required this.summary,
    required this.items,
    required this.references,
    required this.filter,
    required this.pagination,
    required this.access,
  });

  factory QuestionBankPage.fromJson(Map<String, dynamic> json) =>
      QuestionBankPage(
        summary: QuestionBankSummary.fromJson(_map(json['ringkasan'])),
        items: _list(json['items'], BankQuestion.fromJson),
        references: QuestionBankReferences.fromJson(_map(json['referensi'])),
        filter: QuestionBankFilter.fromJson(_map(json['filter'])),
        pagination: QuestionBankPagination.fromJson(_map(json['paginasi'])),
        access: QuestionBankAccess.fromJson(_map(json['hak_akses'])),
      );

  final QuestionBankSummary summary;
  final List<BankQuestion> items;
  final QuestionBankReferences references;
  final QuestionBankFilter filter;
  final QuestionBankPagination pagination;
  final QuestionBankAccess access;

  QuestionBankPage append(QuestionBankPage next) => QuestionBankPage(
    summary: next.summary,
    items: [...items, ...next.items],
    references: next.references,
    filter: next.filter,
    pagination: next.pagination,
    access: next.access,
  );
}

class QuestionBankSummary {
  const QuestionBankSummary({
    required this.total,
    required this.ready,
    required this.draft,
    required this.archived,
  });

  factory QuestionBankSummary.fromJson(Map<String, dynamic> json) =>
      QuestionBankSummary(
        total: _integer(json['total']),
        ready: _integer(json['siap']),
        draft: _integer(json['draft']),
        archived: _integer(json['arsip']),
      );

  final int total;
  final int ready;
  final int draft;
  final int archived;
}

class BankQuestion {
  const BankQuestion({
    required this.id,
    required this.code,
    required this.grade,
    required this.type,
    required this.typeLabel,
    required this.difficulty,
    required this.difficultyLabel,
    required this.category,
    required this.categoryLabel,
    required this.question,
    required this.maximumScore,
    required this.status,
    required this.statusLabel,
    required this.active,
    required this.usageCount,
    this.subject,
    this.topic,
    this.material,
    this.updatedAt,
  });

  factory BankQuestion.fromJson(Map<String, dynamic> json) => BankQuestion(
    id: _integer(json['id']),
    code: json['kode'] as String? ?? '-',
    subject: _nullable(json['mata_pelajaran'], QuestionSubject.fromJson),
    grade: _integer(json['tingkat']),
    type: json['jenis_soal'] as String? ?? 'pilihan_ganda',
    typeLabel: json['label_jenis_soal'] as String? ?? '-',
    difficulty: json['tingkat_kesulitan'] as String? ?? 'sedang',
    difficultyLabel: json['label_tingkat_kesulitan'] as String? ?? '-',
    category: json['kategori'] as String? ?? 'umum',
    categoryLabel: json['label_kategori'] as String? ?? '-',
    topic: json['topik'] as String?,
    material: json['materi'] as String?,
    question: json['pertanyaan'] as String? ?? '-',
    maximumScore: _decimal(json['skor_maksimal']),
    status: json['status'] as String? ?? 'draft',
    statusLabel: json['label_status'] as String? ?? '-',
    active: json['aktif'] as bool? ?? false,
    usageCount: _integer(json['jumlah_pemakaian']),
    updatedAt: _date(json['diperbarui_pada']),
  );

  final int id;
  final String code;
  final QuestionSubject? subject;
  final int grade;
  final String type;
  final String typeLabel;
  final String difficulty;
  final String difficultyLabel;
  final String category;
  final String categoryLabel;
  final String? topic;
  final String? material;
  final String question;
  final double maximumScore;
  final String status;
  final String statusLabel;
  final bool active;
  final int usageCount;
  final DateTime? updatedAt;
}

class BankQuestionDetail extends BankQuestion {
  const BankQuestionDetail({
    required super.id,
    required super.code,
    required super.grade,
    required super.type,
    required super.typeLabel,
    required super.difficulty,
    required super.difficultyLabel,
    required super.category,
    required super.categoryLabel,
    required super.question,
    required super.maximumScore,
    required super.status,
    required super.statusLabel,
    required super.active,
    required super.usageCount,
    required this.answer,
    required this.media,
    required this.access,
    super.subject,
    super.topic,
    super.material,
    super.updatedAt,
    this.academicYear,
    this.learningObjective,
    this.stimulus,
    this.explanation,
    this.createdBy,
  });

  factory BankQuestionDetail.fromJson(Map<String, dynamic> json) {
    final base = BankQuestion.fromJson(json);
    return BankQuestionDetail(
      id: base.id,
      code: base.code,
      subject: base.subject,
      grade: base.grade,
      type: base.type,
      typeLabel: base.typeLabel,
      difficulty: base.difficulty,
      difficultyLabel: base.difficultyLabel,
      category: base.category,
      categoryLabel: base.categoryLabel,
      topic: base.topic,
      material: base.material,
      question: base.question,
      maximumScore: base.maximumScore,
      status: base.status,
      statusLabel: base.statusLabel,
      active: base.active,
      usageCount: base.usageCount,
      updatedAt: base.updatedAt,
      academicYear: _nullable(
        json['tahun_pelajaran'],
        QuestionAcademicYear.fromJson,
      ),
      learningObjective: json['tujuan_pembelajaran'] as String?,
      stimulus: json['stimulus'] as String?,
      explanation: json['pembahasan'] as String?,
      answer: QuestionAnswer.fromJson(_map(json['jawaban'])),
      media: QuestionMedia.fromJson(_map(json['media'])),
      createdBy: json['dibuat_oleh'] as String?,
      access: QuestionDetailAccess.fromJson(_map(json['hak_akses'])),
    );
  }

  final QuestionAcademicYear? academicYear;
  final String? learningObjective;
  final String? stimulus;
  final String? explanation;
  final QuestionAnswer answer;
  final QuestionMedia media;
  final String? createdBy;
  final QuestionDetailAccess access;
}

class QuestionAnswer {
  const QuestionAnswer({
    required this.options,
    required this.statements,
    required this.pairs,
    this.textKey,
    this.rubric,
  });

  factory QuestionAnswer.fromJson(Map<String, dynamic> json) => QuestionAnswer(
    options: _list(json['opsi'], QuestionOption.fromJson),
    statements: _list(json['pernyataan'], QuestionStatement.fromJson),
    pairs: _list(json['pasangan'], QuestionPair.fromJson),
    textKey: json['kunci_teks'] as String?,
    rubric: json['rubrik'] as String?,
  );

  final List<QuestionOption> options;
  final List<QuestionStatement> statements;
  final List<QuestionPair> pairs;
  final String? textKey;
  final String? rubric;
}

class QuestionOption {
  const QuestionOption({
    required this.code,
    required this.text,
    required this.correct,
  });

  factory QuestionOption.fromJson(Map<String, dynamic> json) => QuestionOption(
    code: json['kode'] as String? ?? '-',
    text: json['teks'] as String? ?? '-',
    correct: json['benar'] as bool? ?? false,
  );

  final String code;
  final String text;
  final bool correct;
}

class QuestionStatement {
  const QuestionStatement({
    required this.number,
    required this.text,
    required this.answer,
  });

  factory QuestionStatement.fromJson(Map<String, dynamic> json) =>
      QuestionStatement(
        number: _integer(json['nomor']),
        text: json['teks'] as String? ?? '-',
        answer: json['jawaban'] as bool? ?? false,
      );

  final int number;
  final String text;
  final bool answer;
}

class QuestionPair {
  const QuestionPair({
    required this.number,
    required this.left,
    required this.right,
  });

  factory QuestionPair.fromJson(Map<String, dynamic> json) => QuestionPair(
    number: _integer(json['nomor']),
    left: json['kiri'] as String? ?? '-',
    right: json['kanan'] as String? ?? '-',
  );

  final int number;
  final String left;
  final String right;
}

class QuestionMedia {
  const QuestionMedia({this.image, this.table, this.formula});

  factory QuestionMedia.fromJson(Map<String, dynamic> json) => QuestionMedia(
    image: _nullable(json['gambar'], QuestionImage.fromJson),
    table: _nullable(json['tabel'], QuestionTable.fromJson),
    formula: _nullable(json['rumus'], QuestionFormula.fromJson),
  );

  final QuestionImage? image;
  final QuestionTable? table;
  final QuestionFormula? formula;
}

class QuestionImage {
  const QuestionImage({required this.url, this.alt, this.caption});
  factory QuestionImage.fromJson(Map<String, dynamic> json) => QuestionImage(
    url: json['url'] as String? ?? '',
    alt: json['alt'] as String?,
    caption: json['keterangan'] as String?,
  );
  final String url;
  final String? alt;
  final String? caption;
}

class QuestionTable {
  const QuestionTable({required this.rows, this.title});
  factory QuestionTable.fromJson(Map<String, dynamic> json) => QuestionTable(
    title: json['judul'] as String?,
    rows: (json['baris'] as List? ?? const [])
        .whereType<List>()
        .map((row) => row.map((cell) => cell?.toString() ?? '').toList())
        .toList(),
  );
  final String? title;
  final List<List<String>> rows;
}

class QuestionFormula {
  const QuestionFormula({required this.latex, this.caption});
  factory QuestionFormula.fromJson(Map<String, dynamic> json) =>
      QuestionFormula(
        latex: json['latex'] as String? ?? '',
        caption: json['keterangan'] as String?,
      );
  final String latex;
  final String? caption;
}

class QuestionBankReferences {
  const QuestionBankReferences({
    required this.contexts,
    required this.types,
    required this.difficulties,
    required this.categories,
    required this.statuses,
  });

  factory QuestionBankReferences.fromJson(Map<String, dynamic> json) =>
      QuestionBankReferences(
        contexts: _list(json['konteks'], QuestionContext.fromJson),
        types: _list(json['jenis_soal'], QuestionOptionReference.fromJson),
        difficulties: _list(
          json['tingkat_kesulitan'],
          QuestionOptionReference.fromJson,
        ),
        categories: _list(json['kategori'], QuestionOptionReference.fromJson),
        statuses: _list(json['status'], QuestionOptionReference.fromJson),
      );

  final List<QuestionContext> contexts;
  final List<QuestionOptionReference> types;
  final List<QuestionOptionReference> difficulties;
  final List<QuestionOptionReference> categories;
  final List<QuestionOptionReference> statuses;
}

class QuestionContext {
  const QuestionContext({
    required this.key,
    required this.subjectId,
    required this.grade,
    required this.subjectName,
    required this.label,
  });

  factory QuestionContext.fromJson(Map<String, dynamic> json) =>
      QuestionContext(
        key: json['kunci'] as String? ?? '',
        subjectId: _integer(json['mata_pelajaran_id']),
        grade: _integer(json['tingkat']),
        subjectName: json['nama_mata_pelajaran'] as String? ?? '-',
        label: json['label'] as String? ?? '-',
      );

  final String key;
  final int subjectId;
  final int grade;
  final String subjectName;
  final String label;
}

class QuestionOptionReference {
  const QuestionOptionReference({required this.code, required this.label});
  factory QuestionOptionReference.fromJson(Map<String, dynamic> json) =>
      QuestionOptionReference(
        code: json['kode'] as String? ?? '',
        label: json['label'] as String? ?? '-',
      );
  final String code;
  final String label;
}

class QuestionSubject {
  const QuestionSubject({
    required this.id,
    required this.code,
    required this.name,
  });
  factory QuestionSubject.fromJson(Map<String, dynamic> json) =>
      QuestionSubject(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
      );
  final int id;
  final String code;
  final String name;
}

class QuestionAcademicYear {
  const QuestionAcademicYear({required this.id, required this.name});
  factory QuestionAcademicYear.fromJson(Map<String, dynamic> json) =>
      QuestionAcademicYear(
        id: _integer(json['id']),
        name: json['nama'] as String? ?? '-',
      );
  final int id;
  final String name;
}

class QuestionBankFilter {
  const QuestionBankFilter({
    required this.query,
    required this.grade,
    required this.type,
    required this.status,
    this.subjectId,
  });
  factory QuestionBankFilter.fromJson(Map<String, dynamic> json) =>
      QuestionBankFilter(
        query: json['kata_kunci'] as String? ?? '',
        subjectId: _nullableInteger(json['mata_pelajaran_id']),
        grade: json['tingkat']?.toString() ?? 'semua',
        type: json['jenis_soal'] as String? ?? 'semua',
        status: json['status'] as String? ?? 'semua',
      );
  final String query;
  final int? subjectId;
  final String grade;
  final String type;
  final String status;
}

class QuestionBankPagination {
  const QuestionBankPagination({
    required this.page,
    required this.lastPage,
    required this.total,
    required this.hasNextPage,
  });
  factory QuestionBankPagination.fromJson(Map<String, dynamic> json) =>
      QuestionBankPagination(
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

class QuestionBankAccess {
  const QuestionBankAccess({required this.canManage});
  factory QuestionBankAccess.fromJson(Map<String, dynamic> json) =>
      QuestionBankAccess(canManage: json['dapat_kelola'] as bool? ?? false);
  final bool canManage;
}

class QuestionDetailAccess {
  const QuestionDetailAccess({
    required this.canManage,
    required this.canArchive,
  });
  factory QuestionDetailAccess.fromJson(Map<String, dynamic> json) =>
      QuestionDetailAccess(
        canManage: json['dapat_kelola'] as bool? ?? false,
        canArchive: json['dapat_arsipkan'] as bool? ?? false,
      );
  final bool canManage;
  final bool canArchive;
}

class QuestionPickedImage {
  const QuestionPickedImage({required this.name, required this.bytes});
  final String name;
  final Uint8List bytes;
}

class QuestionFormValue {
  const QuestionFormValue({required this.payload, this.image});
  final Map<String, dynamic> payload;
  final QuestionPickedImage? image;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value.whereType<Map<String, dynamic>>().map(convert).toList()
    : <T>[];

T? _nullable<T>(Object? value, T Function(Map<String, dynamic>) convert) =>
    value is Map<String, dynamic> ? convert(value) : null;

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
