import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/features/student_exam/data/exam_answer_draft_store.dart';
import 'package:nusa/features/student_exam/data/exam_security_journal.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';

class _Storage implements ExamJournalStorage {
  final values = <String, String>{};
  bool fail = false;
  @override
  Future<String?> read(String key) async {
    if (fail) throw StateError('Storage unavailable');
    return values[key];
  }

  @override
  Future<void> write(String key, String value) async {
    if (fail) throw StateError('Storage unavailable');
    values[key] = value;
  }
}

StudentExamSession _session({
  int participant = 31,
  int exam = 9,
  String start = '2026-10-24T08:00:00Z',
  String version = 'v0',
  String mode = 'pengerjaan',
  int seconds = 1800,
}) => StudentExamSession.fromJson({
  'mode': mode,
  'sisa_detik': seconds,
  'peserta': {'id': participant, 'waktu_mulai': start},
  'ujian': {'id': exam},
  'soal': [
    {
      'id': 101,
      'jenis': 'uraian',
      'jawaban': {},
      'ragu': false,
      'versi_jawaban': version,
    },
  ],
});

void main() {
  test('draft bertahan antar instance tanpa menyimpan soal, nama, atau token dalam key', () async {
    final storage = _Storage();
    final store = ExamAnswerDraftStore(storage, () async => 'server-a|token-a');
    final session = _session();
    final bound = await store.open(session);
    final draft = ExamAnswerDraft.fromQuestion(
      session.questions.first.copyWith(
        answer: {'1': 'Jawaban terakhir'},
        doubtful: true,
      ),
    );
    await store.put(bound, draft);
    final restarted = ExamAnswerDraftStore(
      storage,
      () async => 'server-a|token-a',
    );
    final restored = await restarted.load(await restarted.open(session));
    expect(restored.single.answer, {'1': 'Jawaban terakhir'});
    expect(restored.single.doubtful, isTrue);
    expect(restored.single.id, draft.id);
    expect(storage.values.keys.any((key) => key.contains('token-a')), isFalse);
    expect(
      jsonDecode(storage.values[bound.key]!)['answers']['101'].keys,
      unorderedEquals(['id', 'question', 'type', 'answer', 'doubtful', 'base']),
    );
  });
  test(
    'server, credential, peserta dan awal pengerjaan memisahkan draft',
    () async {
      final storage = _Storage();
      var scope = 'server-a|token-a';
      final store = ExamAnswerDraftStore(storage, () async => scope);
      final bound = await store.open(_session());
      await store.put(
        bound,
        ExamAnswerDraft.fromQuestion(
          _session().questions.first.copyWith(answer: {'1': 'Rahasia'}),
        ),
      );
      for (final other in ['server-b|token-a', 'server-a|token-b']) {
        scope = other;
        expect(await store.load(await store.open(_session())), isEmpty);
      }
      scope = 'server-a|token-a';
      expect(
        await store.load(await store.open(_session(participant: 32))),
        isEmpty,
      );
      expect(
        await store.load(
          await store.open(_session(start: '2026-10-24T09:00:00Z')),
        ),
        isEmpty,
      );
    },
  );
  test('ack lama tidak menghapus draft lebih baru dan memperbarui versi dasar secara kausal', () async {
    final store = ExamAnswerDraftStore(_Storage(), () async => 'a');
    final session = _session();
    final bound = await store.open(session);
    final first = ExamAnswerDraft.fromQuestion(
      session.questions.first.copyWith(answer: {'1': 'A'}),
    );
    final second = ExamAnswerDraft.fromQuestion(
      session.questions.first.copyWith(answer: {'1': 'B'}),
    );
    await store.put(bound, first);
    await store.put(bound, second);
    await store.acknowledge(bound, first, 'v1');
    final pending = (await store.load(bound)).single;
    expect(pending.id, second.id);
    expect(pending.answer['1'], 'B');
    expect(pending.serverVersion, 'v1');
    await store.acknowledge(bound, pending, 'v2');
    expect(await store.load(bound), isEmpty);
  });
  test('scope berubah menolak write dan ack dari respons lama', () async {
    var scope = 'a';
    final store = ExamAnswerDraftStore(_Storage(), () async => scope);
    final session = _session();
    final bound = await store.open(session);
    final draft = ExamAnswerDraft.fromQuestion(session.questions.first);
    scope = 'b';
    await expectLater(store.put(bound, draft), throwsStateError);
    await expectLater(store.acknowledge(bound, draft, 'v1'), throwsStateError);
  });
  test(
    'selesai dan logout membersihkan isi draft hanya pada namespace yang tepat',
    () async {
      final storage = _Storage();
      final store = ExamAnswerDraftStore(storage, () async => 'a');
      final other = ExamAnswerDraftStore(storage, () async => 'b');
      for (final current in [store, other]) {
        for (final id in [31, 32]) {
          final session = _session(participant: id);
          final bound = await current.open(session);
          await current.put(
            bound,
            ExamAnswerDraft.fromQuestion(
              session.questions.first.copyWith(answer: {'1': 'Teks siswa'}),
            ),
          );
        }
      }
      await store.clearParticipant(31);
      expect(await store.load(await store.open(_session())), isEmpty);
      await store.clearCurrentScope();
      await expectLater(
        store.open(_session(participant: 32)),
        throwsStateError,
      );
      expect(await other.load(await other.open(_session())), hasLength(1));
    },
  );
  test('sesi ditahan, deadline habis, dan backend tanpa versi tidak membuka draft untuk dikirim', () async {
    final store = ExamAnswerDraftStore(_Storage(), () async => 'a');
    for (final session in [
      _session(mode: 'ditahan'),
      _session(seconds: 0),
      _session(version: ''),
    ]) {
      await expectLater(store.open(session), throwsStateError);
    }
  });
  test(
    'storage gagal atau data rusak tidak diam-diam dianggap draft kosong',
    () async {
      final storage = _Storage();
      final store = ExamAnswerDraftStore(storage, () async => 'a');
      final bound = await store.open(_session());
      storage.values[bound.key] = '{invalid';
      await expectLater(store.load(bound), throwsFormatException);
      storage.fail = true;
      await expectLater(store.open(_session()), throwsStateError);
    },
  );
  test('pencocokan server menangani pemangkasan nilai dan respons autosave yang hilang', () {
    final q = _session().questions.first.copyWith(answer: {'1': 'A'});
    final draft = ExamAnswerDraft.fromQuestion(
      q.copyWith(answer: {'1': ' A '}),
    );
    expect(draft.matches(q), isTrue);
    expect(draft.matches(q.copyWith(answer: {'1': 'B'})), isFalse);
  });
}
