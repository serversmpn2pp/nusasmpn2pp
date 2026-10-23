import 'dart:async';
import 'dart:convert';

import 'package:crypto/crypto.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:nusa/core/storage/token_storage.dart';
import 'package:nusa/features/student_exam/data/exam_security_journal.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';

class ExamAnswerDraft {
  const ExamAnswerDraft({
    required this.id,
    required this.questionId,
    required this.type,
    required this.answer,
    required this.doubtful,
    required this.serverVersion,
  });
  factory ExamAnswerDraft.fromQuestion(StudentExamQuestion question) =>
      ExamAnswerDraft(
        id: ExamSecurityJournal.newEventId(),
        questionId: question.id,
        type: question.type,
        answer: Map.unmodifiable(question.answer),
        doubtful: question.doubtful,
        serverVersion: question.serverVersion,
      );
  factory ExamAnswerDraft.fromJson(Map<String, dynamic> json) {
    final answer = json['answer'];
    if (json['id'] is! String ||
        json['question'] is! int ||
        json['type'] is! String ||
        json['doubtful'] is! bool ||
        json['base'] is! String ||
        answer is! Map ||
        answer.length > 100 ||
        answer.keys.any((k) => k is! String) ||
        answer.values.any((v) => v is! String || v.length > 100000)) {
      throw const FormatException(
        'Draft jawaban tidak valid. Hubungi pengawas.',
      );
    }
    return ExamAnswerDraft(
      id: json['id'] as String,
      questionId: json['question'] as int,
      type: json['type'] as String,
      answer: Map<String, String>.from(answer),
      doubtful: json['doubtful'] as bool,
      serverVersion: json['base'] as String,
    );
  }
  final String id;
  final int questionId;
  final String type;
  final Map<String, String> answer;
  final bool doubtful;
  final String serverVersion;
  Map<String, dynamic> toJson() => {
    'id': id,
    'question': questionId,
    'type': type,
    'answer': answer,
    'doubtful': doubtful,
    'base': serverVersion,
  };
  ExamAnswerDraft rebase(String version) => ExamAnswerDraft(
    id: id,
    questionId: questionId,
    type: type,
    answer: answer,
    doubtful: doubtful,
    serverVersion: version,
  );
  bool matches(StudentExamQuestion question) {
    final restored = question.copyWith(answer: answer, doubtful: doubtful);
    return doubtful == question.doubtful &&
        _canonical(restored.answerPayload) ==
            _canonical(question.answerPayload);
  }

  static String _canonical(Object? value) {
    if (value is Map) {
      final keys = value.keys.map((e) => e.toString()).toList()..sort();
      return jsonEncode({for (final key in keys) key: value[key]});
    }
    return jsonEncode(value);
  }
}

class ExamDraftSession {
  const ExamDraftSession(this.scope, this.key, this.examId, this.startedAt);
  final String scope;
  final String key;
  final int examId;
  final String startedAt;
}

/// Uses the same encrypted platform storage as credentials, never preferences,
/// logs, cache files or notification payloads. No file bytes/question bank stored.
class ExamAnswerDraftStore {
  ExamAnswerDraftStore(this.storage, this.scope);
  final ExamJournalStorage storage;
  final Future<String?> Function() scope;
  Future<void> _tail = Future.value();
  final Set<String> _retiredScopes = {};
  Future<T> _serial<T>(Future<T> Function() action) {
    final next = _tail.then((_) => action());
    _tail = next.then<void>((_) {}, onError: (Object _, StackTrace _) {});
    return next;
  }

  Future<String> _scope() async {
    final current = await scope();
    if (current == null || current.isEmpty) {
      throw StateError('Sesi draft tidak tersedia.');
    }
    return sha256.convert(utf8.encode(current)).toString();
  }

  String _key(String scope, int participant) =>
      'nusa.exam_drafts.$scope.$participant';
  Future<void> _check(ExamDraftSession session) async {
    if (_retiredScopes.contains(session.scope) ||
        await _scope() != session.scope) {
      throw StateError('Sesi draft sudah berubah.');
    }
  }

  Future<Map<String, dynamic>> _read(String key) async {
    final value = await storage.read(key);
    if (value == null || value == '{}') return {};
    if (value.length > 2 * 1024 * 1024) {
      throw const FormatException('Draft melebihi kapasitas.');
    }
    final data = jsonDecode(value);
    if (data is! Map || data['protocol'] != 1 || data['answers'] is! Map) {
      throw const FormatException('Penyimpanan draft tidak dapat dibaca.');
    }
    return Map<String, dynamic>.from(data);
  }

  bool _same(Map<String, dynamic> value, ExamDraftSession session) =>
      value['exam'] == session.examId && value['started'] == session.startedAt;
  Future<void> _write(
    ExamDraftSession session,
    Map<String, dynamic> data,
  ) async {
    await _check(session);
    final encoded = jsonEncode(data);
    if (encoded.length > 2 * 1024 * 1024) {
      throw StateError('Kapasitas draft penuh.');
    }
    await storage.write(session.key, encoded);
  }

  Future<ExamDraftSession> open(
    StudentExamSession session,
  ) => _serial(() async {
    if (!session.isRunning ||
        session.remainingSeconds <= 0 ||
        session.participant.startedAt == null ||
        session.questions.any((q) => q.serverVersion.isEmpty)) {
      throw StateError(
        'Server belum memberikan sesi dan versi jawaban yang dapat dipulihkan.',
      );
    }
    final current = await _scope();
    final bound = ExamDraftSession(
      current,
      _key(current, session.participant.id),
      session.exam.id,
      session.participant.startedAt!.toUtc().toIso8601String(),
    );
    var data = await _read(bound.key);
    if (!_same(data, bound)) {
      data = {
        'protocol': 1,
        'exam': bound.examId,
        'started': bound.startedAt,
        'answers': <String, dynamic>{},
      };
    }
    final indexKey = 'nusa.exam_drafts.index.$current';
    final index = jsonDecode(await storage.read(indexKey) ?? '[]') as List;
    if (!index.contains(bound.key)) {
      await storage.write(indexKey, jsonEncode([...index, bound.key]));
    }
    await _write(bound, data); // Verify storage before accepting answers.
    return bound;
  });
  Future<List<ExamAnswerDraft>> load(ExamDraftSession session) =>
      _serial(() async {
        await _check(session);
        final data = await _read(session.key);
        if (!_same(data, session)) return <ExamAnswerDraft>[];
        return (data['answers'] as Map).values
            .map(
              (v) =>
                  ExamAnswerDraft.fromJson(Map<String, dynamic>.from(v as Map)),
            )
            .toList();
      });
  Future<void> put(ExamDraftSession session, ExamAnswerDraft draft) =>
      _serial(() async {
        await _check(session);
        final data = await _read(session.key);
        if (!_same(data, session)) {
          throw StateError('Pengerjaan draft sudah berubah.');
        }
        (data['answers'] as Map)['${draft.questionId}'] = draft.toJson();
        await _write(session, data);
      });
  Future<void> acknowledge(
    ExamDraftSession session,
    ExamAnswerDraft sent,
    String serverVersion,
  ) => _serial(() async {
    await _check(session);
    final data = await _read(session.key);
    if (!_same(data, session)) return;
    final answers = data['answers'] as Map;
    final value = answers['${sent.questionId}'];
    if (value == null) return;
    final current = ExamAnswerDraft.fromJson(
      Map<String, dynamic>.from(value as Map),
    );
    if (current.id == sent.id) {
      answers.remove('${sent.questionId}');
    } else if (current.serverVersion == sent.serverVersion) {
      answers['${sent.questionId}'] = current.rebase(serverVersion).toJson();
    }
    await _write(session, data);
  });
  Future<void> clearParticipant(int participant) => _serial(() async {
    final current = await _scope();
    await storage.write(_key(current, participant), '{}');
  });
  Future<void> clearCurrentScope() => _serial(() async {
    final current = await _scope();
    _retiredScopes.add(current);
    final key = 'nusa.exam_drafts.index.$current';
    final index = jsonDecode(await storage.read(key) ?? '[]') as List;
    for (final entry in index.whereType<String>()) {
      if (entry.startsWith('nusa.exam_drafts.$current.')) {
        await storage.write(entry, '{}');
      }
    }
    await storage.write(key, '[]');
  });
}

final examAnswerDraftStoreProvider = Provider<ExamAnswerDraftStore>((ref) {
  final tokens = ref.watch(tokenStorageProvider);
  final server = ref.watch(appConfigProvider).apiBaseUri.toString();
  return ExamAnswerDraftStore(ref.watch(examJournalStorageProvider), () async {
    final token = await tokens.read();
    return token == null ? null : '$server|$token';
  });
});
