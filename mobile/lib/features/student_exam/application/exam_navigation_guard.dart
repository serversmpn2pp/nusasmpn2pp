import 'package:flutter_riverpod/flutter_riverpod.dart';

/// UI guard only. The server remains authoritative for holds and answer writes.
/// Ownership prevents an old/disposed exam view from releasing a newer guard.
class ExamNavigationGuard {
  Object? _owner;
  int? _participantId;

  bool get active => _participantId != null;

  void protect(int participantId, Object owner) {
    _participantId = participantId;
    _owner = owner;
  }

  void release(Object owner) {
    if (!identical(_owner, owner)) return;
    _owner = null;
    _participantId = null;
  }

  bool allows(String path) => !active || path == '/ujian-saya/$_participantId';
}

final examNavigationGuardProvider = Provider<ExamNavigationGuard>(
  (ref) => ExamNavigationGuard(),
);
