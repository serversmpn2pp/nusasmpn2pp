import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/features/student_exam/data/exam_security_journal.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';

void main() {
  test('kejadian offline bertahan setelah restart dan dikirim berurutan dengan ID tetap', () async {
    final storage = _MemoryStorage();
    final journal = ExamSecurityJournal(
      storage,
      () async => 'server-a|akun-a-token',
    );
    await journal.begin(31);
    await journal.record(31, 'keluar');
    String? firstId;
    await expectLater(
      journal.flush(31, (event, metadata) async {
        firstId = metadata['kejadian_id'] as String;
        throw Exception('offline');
      }),
      throwsException,
    );
    await journal.record(31, 'kembali');
    final restarted = ExamSecurityJournal(
      storage,
      () async => 'server-a|akun-a-token',
    );
    await restarted.begin(31);
    final events = <String>[];
    await restarted.flush(31, (event, metadata) async {
      events.add(event);
      if (event == 'keluar') {
        expect(metadata['kejadian_id'], firstId);
        expect(metadata['dikirim_ulang'], isTrue);
      }
      if (event == 'kembali') expect(metadata['dikirim_ulang'], isTrue);
      return _update();
    });
    expect(events, ['keluar', 'kembali', 'pemulihan']);
    expect(jsonDecode(storage.values.values.single)['events'], isEmpty);
  });

  test(
    'pencatatan baru tidak menunggu request jaringan yang masih tertahan',
    () async {
      final storage = _MemoryStorage();
      final journal = ExamSecurityJournal(storage, () async => 'session-a');
      await journal.record(31, 'keluar');
      final waiting = Completer<StudentExamSecurityUpdate>();
      final started = Completer<void>();
      final events = <String>[];
      final flushing = journal.flush(31, (event, metadata) async {
        events.add(event);
        if (event == 'keluar') {
          started.complete();
          return waiting.future;
        }
        return _update();
      });
      await started.future;
      await journal.record(31, 'kembali');
      expect(jsonDecode(storage.values.values.single)['events'], hasLength(2));
      waiting.complete(_update());
      await flushing;
      expect(events, ['keluar', 'kembali']);
      expect(jsonDecode(storage.values.values.single)['events'], isEmpty);
    },
  );

  test('akun, token login, server, dan peserta memiliki ruang penyimpanan terpisah', () async {
    final storage = _MemoryStorage();
    var scope = 'server-a|akun-a';
    final journal = ExamSecurityJournal(storage, () async => scope);
    await journal.record(31, 'keluar');
    for (final nextScope in [
      'server-a|akun-b',
      'server-b|akun-a',
      'server-a|token-baru-akun-a',
    ]) {
      scope = nextScope;
      var sent = false;
      await journal.flush(31, (event, metadata) async {
        sent = true;
        return _update();
      });
      expect(sent, isFalse);
    }
    scope = 'server-a|akun-a';
    await journal.flush(
      32,
      (event, metadata) async =>
          throw StateError('Peserta lain tidak boleh dikirim'),
    );
    var sent = false;
    await journal.flush(31, (event, metadata) async {
      sent = true;
      return _update();
    });
    expect(sent, isTrue);
    expect(storage.values.keys.every((key) => !key.contains('akun-a')), isTrue);
  });

  test('respons sesi lama tidak boleh diterapkan atau menghapus bukti saat akun berganti', () async {
    final storage = _MemoryStorage();
    var scope = 'akun-a';
    final journal = ExamSecurityJournal(storage, () async => scope);
    await journal.record(31, 'keluar');
    await expectLater(
      journal.flush(31, (event, metadata) async {
        scope = 'akun-b';
        return _update();
      }),
      throwsStateError,
    );
    expect(jsonDecode(storage.values.values.single)['events'], hasLength(1));
  });

  test('penyelesaian mempertahankan bukti yang belum diakui dan tidak memunculkan pemulihan palsu', () async {
    final storage = _MemoryStorage();
    final journal = ExamSecurityJournal(storage, () async => 'akun-a');
    await journal.begin(31);
    await journal.record(31, 'keluar');
    await journal.complete(31);
    final stored = jsonDecode(storage.values.values.single);
    expect(stored['active'], isFalse);
    expect(stored['events'], hasLength(1));
    await journal.begin(31);
    expect(jsonDecode(storage.values.values.single)['events'], hasLength(1));
  });
}

StudentExamSecurityUpdate _update() => StudentExamSecurityUpdate.fromJson({
  'mode': 'pengerjaan',
  'keamanan': <String, dynamic>{},
});

class _MemoryStorage implements ExamJournalStorage {
  final values = <String, String>{};
  @override
  Future<String?> read(String key) async => values[key];
  @override
  Future<void> write(String key, String value) async {
    values[key] = value;
  }
}
