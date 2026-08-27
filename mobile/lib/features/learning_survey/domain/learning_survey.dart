class LearningSurveyArgs {
  const LearningSurveyArgs({
    required this.assignmentId,
    required this.semester,
  });

  final int assignmentId;
  final String semester;

  @override
  bool operator ==(Object other) =>
      other is LearningSurveyArgs &&
      other.assignmentId == assignmentId &&
      other.semester == semester;

  @override
  int get hashCode => Object.hash(assignmentId, semester);
}

class LearningSurveyPage {
  const LearningSurveyPage({
    required this.assignmentId,
    required this.semester,
    required this.alreadyCompleted,
    required this.context,
    required this.questions,
    required this.options,
    required this.note,
  });

  factory LearningSurveyPage.fromJson(Map<String, dynamic> json) =>
      LearningSurveyPage(
        assignmentId: _integer(json['guru_mata_pelajaran_id']),
        semester: json['semester'] as String? ?? 'ganjil',
        alreadyCompleted: json['sudah_diisi'] as bool? ?? false,
        context: LearningSurveyContext.fromJson(_map(json['pembelajaran'])),
        questions: _list(json['pertanyaan'], LearningSurveyQuestion.fromJson),
        options: _list(json['pilihan'], LearningSurveyOption.fromJson),
        note: json['keterangan'] as String? ?? '',
      );

  final int assignmentId;
  final String semester;
  final bool alreadyCompleted;
  final LearningSurveyContext context;
  final List<LearningSurveyQuestion> questions;
  final List<LearningSurveyOption> options;
  final String note;
}

class LearningSurveyContext {
  const LearningSurveyContext({
    required this.subjectName,
    required this.teacherName,
    required this.className,
    required this.academicYearName,
  });

  factory LearningSurveyContext.fromJson(Map<String, dynamic> json) {
    final subject = _map(json['mata_pelajaran']);
    final teacher = _map(json['guru']);
    final schoolClass = _map(json['kelas']);
    final academicYear = _map(json['tahun_pelajaran']);
    return LearningSurveyContext(
      subjectName: subject['nama'] as String? ?? '-',
      teacherName: teacher['nama'] as String? ?? '-',
      className: schoolClass['nama'] as String? ?? '-',
      academicYearName: academicYear['nama'] as String? ?? '-',
    );
  }

  final String subjectName;
  final String teacherName;
  final String className;
  final String academicYearName;
}

class LearningSurveyQuestion {
  const LearningSurveyQuestion({
    required this.id,
    required this.code,
    required this.statement,
    required this.order,
  });

  factory LearningSurveyQuestion.fromJson(Map<String, dynamic> json) =>
      LearningSurveyQuestion(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        statement: json['pernyataan'] as String? ?? '-',
        order: _integer(json['urutan']),
      );

  final int id;
  final String code;
  final String statement;
  final int order;
}

class LearningSurveyOption {
  const LearningSurveyOption({required this.value, required this.label});

  factory LearningSurveyOption.fromJson(Map<String, dynamic> json) =>
      LearningSurveyOption(
        value: _integer(json['nilai']),
        label: json['label'] as String? ?? '-',
      );

  final int value;
  final String label;
}

class LearningSurveySubmission {
  const LearningSurveySubmission({
    required this.answers,
    required this.suggestion,
  });

  final Map<String, int> answers;
  final String suggestion;

  Map<String, dynamic> toJson() => {
    'jawaban': answers,
    'saran': suggestion.trim().isEmpty ? null : suggestion.trim(),
  };
}

class LearningSurveySubmitResult {
  const LearningSurveySubmitResult({
    required this.message,
    required this.alreadyCompleted,
  });

  final String message;
  final bool alreadyCompleted;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

int _integer(Object? value) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? 0,
  _ => 0,
};
