import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:nusa/core/storage/token_storage.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';

abstract interface class ExamJournalStorage {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
}

class SecureExamJournalStorage implements ExamJournalStorage {
  const SecureExamJournalStorage(this.storage);
  final FlutterSecureStorage storage;
  @override
  Future<String?> read(String key) => storage.read(key: key);
  @override
  Future<void> write(String key, String value) =>
      storage.write(key: key, value: value);
}

typedef ExamEventSender = Future<StudentExamSecurityUpdate> Function(
  String event,
  Map<String, dynamic> metadata,
);

/// Encrypted, ordered journal. Each server/account/login credential has its own
/// namespace. Never replay a previous account's events under the current token.
class ExamSecurityJournal {
  ExamSecurityJournal(this.storage, this.scope);
  final ExamJournalStorage storage;
  final Future<String?> Function() scope;
  Future<void> _tail = Future.value();
  final Map<int, Future<StudentExamSecurityUpdate?>> _flights = {};

  Future<T> _serial<T>(Future<T> Function() operation) {
    final future = _tail.then((_) => operation());
    _tail = future.then<void>(
      (_) {},
      onError: (Object error, StackTrace stack) {},
    );
    return future;
  }

  Future<String> _key(int participantId) async {
    final value = await scope();
    if (value == null || value.isEmpty) {
      throw StateError('Sesi ujian tidak tersedia.');
    }
    return 'nusa.exam_journal.${sha256.convert(utf8.encode(value))}.$participantId';
  }

  Future<Map<String, dynamic>> _read(String key) async {
    final value = await storage.read(key);
    return value == null
        ? {'active': false, 'events': <dynamic>[]}
        : Map<String, dynamic>.from(jsonDecode(value) as Map);
  }

  Map<String, dynamic> _event(String type, Map<String, dynamic> metadata) {
    final bytes = List<int>.generate(16, (_) => Random.secure().nextInt(256));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return {
      'type': type,
      'attempted': false,
      'metadata': {
        ...metadata,
        'kejadian_id':
            '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}',
        'waktu_klien': DateTime.now().toUtc().toIso8601String(),
      },
    };
  }

  Future<void> begin(int id) => _serial(() async {
    final key = await _key(id);
    final state = await _read(key);
    if (state['active'] == true) {
      (state['events'] as List).add(
        _event('pemulihan', {'pemicu': 'buka-ulang-sesi'}),
      );
    }
    state['active'] = true;
    await storage.write(key, jsonEncode(state));
  });

  Future<void> complete(int id) => _serial(() async {
    final key = await _key(id);
    final state = await _read(key);
    state['active'] = false;
    // Keep unacknowledged evidence; do not silently erase it on completion.
    await storage.write(key, jsonEncode(state));
  });

  Future<void> record(
    int id,
    String event, {
    Map<String, dynamic> metadata = const {},
  }) => _serial(() async {
    final key = await _key(id);
    final state = await _read(key);
    (state['events'] as List).add(_event(event, metadata));
    await storage.write(key, jsonEncode(state));
  });

  Future<StudentExamSecurityUpdate?> flush(int id, ExamEventSender send) {
    final active = _flights[id];
    if (active != null) return active;
    final flight = _flush(id, send);
    _flights[id] = flight;
    return flight.whenComplete(() => _flights.remove(id));
  }

  Future<StudentExamSecurityUpdate?> _flush(
    int id,
    ExamEventSender send,
  ) async {
    final key = await _key(id);
    StudentExamSecurityUpdate? latest;
    var recoveringBatch = false;
    while (true) {
      if (await _key(id) != key) throw StateError('Sesi pengguna berubah.');
      final pending = await _serial(() async {
        final state = await _read(key);
        final events = state['events'] as List;
        if (events.isEmpty) return null;
        final event = Map<String, dynamic>.from(events.first as Map);
        final retry = event['attempted'] == true;
        event['attempted'] = true;
        events[0] = event;
        await storage.write(key, jsonEncode(state));
        return {...event, 'retry': retry};
      });
      if (pending == null) return latest;
      if (await _key(id) != key) throw StateError('Sesi pengguna berubah.');
      final recordedAt = DateTime.tryParse(
        (pending['metadata'] as Map)['waktu_klien'] as String? ?? '',
      );
      recoveringBatch =
          recoveringBatch ||
          pending['retry'] == true ||
          (recordedAt != null &&
              DateTime.now().toUtc().difference(recordedAt).inSeconds > 30);
      latest = await send(pending['type'] as String, {
        ...Map<String, dynamic>.from(pending['metadata'] as Map),
        'dikirim_ulang': recoveringBatch,
      });
      if (await _key(id) != key) throw StateError('Sesi pengguna berubah.');
      await _serial(() async {
        final state = await _read(key);
        final events = state['events'] as List;
        events.removeWhere(
          (event) =>
              event['metadata']['kejadian_id'] ==
              (pending['metadata'] as Map)['kejadian_id'],
        );
        await storage.write(key, jsonEncode(state));
      });
    }
  }
}

final examJournalStorageProvider = Provider<ExamJournalStorage>(
  (ref) => SecureExamJournalStorage(ref.watch(secureStorageProvider)),
);

final examSecurityJournalProvider = Provider<ExamSecurityJournal>((ref) {
  final tokenStorage = ref.watch(tokenStorageProvider);
  final server = ref.watch(appConfigProvider).apiBaseUri.toString();
  return ExamSecurityJournal(ref.watch(examJournalStorageProvider), () async {
    final token = await tokenStorage.read();
    return token == null ? null : '$server|$token';
  });
});
